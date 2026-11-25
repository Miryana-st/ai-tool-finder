"use client";

import { useState } from "react";
import * as api from "@/lib/api";
import { ApiError, type Category, type ValidationErrors } from "@/lib/api";
import NameSlugFields, { canSendNameSlug } from "./NameSlugFields";
import {
  fieldErrorClassName,
  inputClassName,
  mutedTextClassName,
  secondaryButtonClassName,
} from "@/styles/classNames";

type Props = {
  categories: Category[];
  selected: number[];
  onToggle: (id: number) => void;
  // Called for a category that is approved right away (added by an admin).
  onCreated: (category: Category) => void;
  error: string | null;
};

// The approved categories as checkboxes, plus "Suggest a category". A user's
// suggestion goes to an admin and appears here only after it is approved.
export default function CategoryPicker({ categories, selected, onToggle, onCreated, error }: Props) {
  const [isSuggesting, setIsSuggesting] = useState(false);
  const [name, setName] = useState("");
  const [slug, setSlug] = useState("");
  const [description, setDescription] = useState("");
  const [errors, setErrors] = useState<ValidationErrors>({});
  const [formError, setFormError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const [isSaving, setIsSaving] = useState(false);

  const close = () => {
    setIsSuggesting(false);
    setName("");
    setSlug("");
    setDescription("");
    setErrors({});
    setFormError(null);
  };

  const suggest = async () => {
    setIsSaving(true);
    setErrors({});
    setFormError(null);
    setNotice(null);

    try {
      const category = await api.suggestCategory({
        name: name.trim(),
        slug,
        description: description.trim() || null,
      });

      if (category.is_approved) {
        onCreated(category);
        setNotice(`Категорията „${category.name}“ е добавена.`);
      } else {
        setNotice(
          `Предложението „${category.name}“ е изпратено за одобрение. Ще можеш да го избереш, след като админ го одобри.`,
        );
      }
      close();
    } catch (caught) {
      if (caught instanceof ApiError && caught.status === 422) {
        setErrors(caught.errors);
      } else {
        console.error("Could not suggest the category.", caught);
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

  const nameError = api.fieldError(errors, "name");
  const descriptionError = api.fieldError(errors, "description");

  return (
    <fieldset className="flex flex-col gap-2">
      <legend className="mb-1 text-sm font-medium">Категории</legend>

      {categories.length === 0 && (
        <p className={`text-sm ${mutedTextClassName}`}>Все още няма категории.</p>
      )}

      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
        {categories.map((category) => (
          <label key={category.id} className="flex items-start gap-2 text-sm">
            <input
              type="checkbox"
              checked={selected.includes(category.id)}
              onChange={() => onToggle(category.id)}
              className="mt-1"
            />
            <span className="flex flex-col">
              {category.name}
              {category.description && (
                <span className={`text-xs ${mutedTextClassName}`}>{category.description}</span>
              )}
            </span>
          </label>
        ))}
      </div>

      {error && <span className={fieldErrorClassName}>{error}</span>}

      {notice && (
        <p role="status" className="text-sm text-green-700 dark:text-green-400">
          {notice}
        </p>
      )}

      {isSuggesting ? (
        <div className="flex flex-col gap-3 rounded-lg border border-zinc-200 p-4 dark:border-zinc-800">
          <p className="text-sm font-medium">Предложи категория</p>

          <NameSlugFields
            name={name}
            slug={slug}
            onNameChange={setName}
            onSlugChange={setSlug}
            nameError={nameError}
            slugError={api.fieldError(errors, "slug")}
            maxLength={50}
            onEnter={send}
          />

          <label className="flex flex-col gap-1 text-sm font-medium">
            Описание
            <span className={`text-xs font-normal ${mutedTextClassName}`}>
              По желание. Какво влиза в категорията, напр. „транскрипция, TTS, монтаж“.
            </span>
            <textarea
              value={description}
              onChange={(event) => setDescription(event.target.value)}
              maxLength={500}
              rows={2}
              aria-invalid={Boolean(descriptionError)}
              className={inputClassName}
            />
            {descriptionError && <span className={fieldErrorClassName}>{descriptionError}</span>}
          </label>

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
            <button
              type="button"
              onClick={close}
              className={`text-sm hover:underline ${mutedTextClassName}`}
            >
              Отказ
            </button>
          </div>
        </div>
      ) : (
        <button
          type="button"
          onClick={() => {
            setNotice(null);
            setIsSuggesting(true);
          }}
          className="self-start text-sm text-indigo-600 hover:underline"
        >
          + Предложи категория
        </button>
      )}
    </fieldset>
  );
}
