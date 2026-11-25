"use client";

import { useEffect, useId, useState, type KeyboardEvent } from "react";
import * as api from "@/lib/api";
import { ApiError, type Tag, type ValidationErrors } from "@/lib/api";
import { slugify } from "@/lib/slug";
import { useDebouncedValue } from "@/lib/useDebouncedValue";
import NameSlugFields, { canSendNameSlug } from "./NameSlugFields";
import {
  chipClassName,
  fieldErrorClassName,
  inputClassName,
  mutedTextClassName,
  secondaryButtonClassName,
} from "@/styles/classNames";

const MAX_TAG_LENGTH = 30;

type Props = {
  tags: Tag[];
  onChange: (tags: Tag[]) => void;
  error: string | null;
};

// Chip input over the approved tags: typing shows matching tags to pick.
// A new tag is suggested with "Suggest a tag" and can be picked once an admin
// approves it (an admin's own tag is approved, and picked, right away).
export default function TagInput({ tags, onChange, error }: Props) {
  const inputId = useId();
  const [text, setText] = useState("");
  const [suggestions, setSuggestions] = useState<{ search: string; tags: Tag[] }>({
    search: "",
    tags: [],
  });
  const search = useDebouncedValue(text.trim());

  useEffect(() => {
    if (search === "") {
      return;
    }

    const controller = new AbortController();

    api
      .getTags(search, controller.signal)
      .then((found) => setSuggestions({ search, tags: found }))
      .catch((caught: unknown) => {
        if (!api.isAbortError(caught)) {
          console.error("Could not load tag suggestions.", caught);
        }
      });

    return () => controller.abort();
  }, [search]);

  const isPicked = (tag: Tag) => tags.some((picked) => picked.id === tag.id);

  const pick = (tag: Tag) => {
    if (!isPicked(tag)) {
      onChange([...tags, tag]);
    }
    setText("");
  };

  const remove = (tag: Tag) => onChange(tags.filter((picked) => picked.id !== tag.id));

  const isSettled = text.trim() !== "" && suggestions.search === search && search === text.trim();
  const matches = text.trim() !== "" && suggestions.search === search
    ? suggestions.tags.filter((tag) => !isPicked(tag))
    : [];

  const handleKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
    if (event.key === "Enter") {
      // Enter picks the first match; it never submits the tool form.
      event.preventDefault();
      if (matches.length > 0) {
        pick(matches[0]);
      }
    } else if (event.key === "Backspace" && text === "" && tags.length > 0) {
      remove(tags[tags.length - 1]);
    }
  };

  return (
    <div className="flex flex-col gap-2">
      <label htmlFor={inputId} className="text-sm font-medium">
        Тагове
      </label>

      {tags.length > 0 && (
        <div className="flex flex-wrap items-center gap-2">
          {tags.map((tag) => (
            <span key={tag.id} className={chipClassName}>
              #{tag.name}
              <button
                type="button"
                onClick={() => remove(tag)}
                aria-label={`Премахни таг ${tag.name}`}
                className="text-zinc-500 hover:text-red-600"
              >
                ×
              </button>
            </span>
          ))}
        </div>
      )}

      <div className="relative">
        <input
          id={inputId}
          type="text"
          value={text}
          onChange={(event) => setText(event.target.value)}
          onKeyDown={handleKeyDown}
          maxLength={MAX_TAG_LENGTH}
          placeholder="Започни да пишеш, напр. Python"
          autoComplete="off"
          aria-invalid={Boolean(error)}
          className={inputClassName}
        />

        {matches.length > 0 && (
          <ul className="absolute z-10 mt-1 w-full overflow-hidden rounded-md border border-zinc-200 bg-white shadow-md dark:border-zinc-700 dark:bg-zinc-900">
            {matches.map((tag) => (
              <li key={tag.id}>
                <button
                  type="button"
                  onClick={() => pick(tag)}
                  className="block w-full px-3 py-2 text-left text-sm hover:bg-zinc-100 dark:hover:bg-zinc-800"
                >
                  #{tag.name}
                </button>
              </li>
            ))}
          </ul>
        )}
      </div>

      {isSettled && matches.length === 0 && suggestions.tags.length === 0 && (
        <p className={`text-xs ${mutedTextClassName}`}>
          Няма такъв таг. Можеш да го предложиш по-долу.
        </p>
      )}

      {error && <span className={fieldErrorClassName}>{error}</span>}

      <SuggestTag initialName={text.trim()} onApproved={pick} />
    </div>
  );
}

// "+ Suggest a tag": name and slug, sent to an admin for approval.
function SuggestTag({ initialName, onApproved }: { initialName: string; onApproved: (tag: Tag) => void }) {
  const [isOpen, setIsOpen] = useState(false);
  const [name, setName] = useState("");
  const [slug, setSlug] = useState("");
  const [errors, setErrors] = useState<ValidationErrors>({});
  const [formError, setFormError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const [isSaving, setIsSaving] = useState(false);

  const close = () => {
    setIsOpen(false);
    setName("");
    setSlug("");
    setErrors({});
    setFormError(null);
  };

  const suggest = async () => {
    setIsSaving(true);
    setErrors({});
    setFormError(null);
    setNotice(null);

    try {
      const tag = await api.suggestTag({ name: name.trim(), slug });

      if (tag.is_approved) {
        onApproved(tag);
        setNotice(`Тагът „${tag.name}“ е добавен.`);
      } else {
        setNotice(
          `Предложението „${tag.name}“ е изпратено за одобрение. Ще можеш да го избереш, след като админ го одобри.`,
        );
      }
      close();
    } catch (caught) {
      if (caught instanceof ApiError && caught.status === 422) {
        setErrors(caught.errors);
      } else {
        console.error("Could not suggest the tag.", caught);
        setFormError("Предложението не можа да се изпрати. Опитай отново.");
      }
    } finally {
      setIsSaving(false);
    }
  };

  const send = () => {
    if (canSendNameSlug(name, slug) && !isSaving) {
      void suggest();
    }
  };

  return (
    <>
      {notice && (
        <p role="status" className="text-sm text-green-700 dark:text-green-400">
          {notice}
        </p>
      )}

      {isOpen ? (
        <div className="flex flex-col gap-3 rounded-lg border border-zinc-200 p-4 dark:border-zinc-800">
          <p className="text-sm font-medium">Предложи таг</p>

          <NameSlugFields
            name={name}
            slug={slug}
            onNameChange={setName}
            onSlugChange={setSlug}
            nameError={api.fieldError(errors, "name")}
            slugError={api.fieldError(errors, "slug")}
            maxLength={MAX_TAG_LENGTH}
            onEnter={send}
          />

          {formError && (
            <p role="alert" className={fieldErrorClassName}>
              {formError}
            </p>
          )}

          <div className="flex gap-2">
            <button
              type="button"
              onClick={send}
              disabled={isSaving || !canSendNameSlug(name, slug)}
              className={secondaryButtonClassName}
            >
              {isSaving ? "Изпращане…" : "Изпрати"}
            </button>
            <button type="button" onClick={close} className={`text-sm hover:underline ${mutedTextClassName}`}>
              Отказ
            </button>
          </div>
        </div>
      ) : (
        <button
          type="button"
          onClick={() => {
            // Start from what the user was typing in the tag field.
            setName(initialName);
            setSlug(slugifyForTag(initialName));
            setNotice(null);
            setIsOpen(true);
          }}
          className="self-start text-sm text-indigo-600 hover:underline"
        >
          + Предложи таг
        </button>
      )}
    </>
  );
}

function slugifyForTag(name: string): string {
  return slugify(name).slice(0, MAX_TAG_LENGTH);
}
