"use client";

import { useEffect, useId, useState, type KeyboardEvent } from "react";
import * as api from "@/lib/api";
import type { Tag } from "@/lib/api";
import { useDebouncedValue } from "@/lib/useDebouncedValue";
import { chipClassName, fieldErrorClassName, inputClassName } from "./styles";

const MAX_TAG_LENGTH = 30;

type Props = {
  tags: string[];
  onChange: (tags: string[]) => void;
  error: string | null;
};

// Chip input: Enter or comma adds a tag, existing tags are suggested while typing.
// New tags are allowed; the backend creates them.
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

  const hasTag = (name: string) =>
    tags.some((tag) => tag.toLowerCase() === name.toLowerCase());

  const addTag = (name: string) => {
    const tag = name.trim().slice(0, MAX_TAG_LENGTH);

    if (tag !== "" && !hasTag(tag)) {
      onChange([...tags, tag]);
    }
    setText("");
  };

  const removeTag = (name: string) => onChange(tags.filter((tag) => tag !== name));

  const handleKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
    if (event.key === "Enter" || event.key === ",") {
      event.preventDefault();
      addTag(text);
    } else if (event.key === "Backspace" && text === "" && tags.length > 0) {
      removeTag(tags[tags.length - 1]);
    }
  };

  const visibleSuggestions =
    text.trim() !== "" && suggestions.search === search
      ? suggestions.tags.filter((tag) => !hasTag(tag.name))
      : [];

  return (
    <div className="flex flex-col gap-1">
      <label htmlFor={inputId} className="text-sm font-medium">
        Тагове
      </label>

      <div className="flex flex-wrap items-center gap-2">
        {tags.map((tag) => (
          <span key={tag} className={chipClassName}>
            #{tag}
            <button
              type="button"
              onClick={() => removeTag(tag)}
              aria-label={`Премахни таг ${tag}`}
              className="text-zinc-500 hover:text-red-600"
            >
              ×
            </button>
          </span>
        ))}
      </div>

      <div className="relative">
        <input
          id={inputId}
          type="text"
          value={text}
          onChange={(event) => setText(event.target.value)}
          onKeyDown={handleKeyDown}
          onBlur={() => addTag(text)}
          maxLength={MAX_TAG_LENGTH}
          placeholder="Напиши таг и натисни Enter"
          aria-invalid={Boolean(error)}
          className={inputClassName}
        />

        {visibleSuggestions.length > 0 && (
          <ul className="absolute z-10 mt-1 w-full overflow-hidden rounded-md border border-zinc-200 bg-white shadow-md dark:border-zinc-700 dark:bg-zinc-900">
            {visibleSuggestions.map((tag) => (
              <li key={tag.id}>
                <button
                  type="button"
                  // mousedown runs before the input's blur, so the click is not lost.
                  onMouseDown={(event) => {
                    event.preventDefault();
                    addTag(tag.name);
                  }}
                  className="block w-full px-3 py-2 text-left text-sm hover:bg-zinc-100 dark:hover:bg-zinc-800"
                >
                  #{tag.name}
                </button>
              </li>
            ))}
          </ul>
        )}
      </div>

      {error && <span className={fieldErrorClassName}>{error}</span>}
    </div>
  );
}
