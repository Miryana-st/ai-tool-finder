"use client";

import { useState, type KeyboardEvent } from "react";
import { SLUG_PATTERN, slugify } from "@/lib/slug";
import { fieldErrorClassName, inputClassName, mutedTextClassName } from "./styles";

type Props = {
  name: string;
  slug: string;
  onNameChange: (name: string) => void;
  onSlugChange: (slug: string) => void;
  nameError: string | null;
  slugError: string | null;
  maxLength: number;
  // Enter in a field must send the suggestion, not submit the whole tool form.
  onEnter: () => void;
};

// Name and slug of a suggested category or tag. The slug is required. It is
// filled in from a Latin name until the user edits it; a Cyrillic name gives
// no slug, so the user types it.
export default function NameSlugFields({
  name,
  slug,
  onNameChange,
  onSlugChange,
  nameError,
  slugError,
  maxLength,
  onEnter,
}: Props) {
  const [isSlugEdited, setIsSlugEdited] = useState(false);

  const handleKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
    if (event.key === "Enter") {
      event.preventDefault();
      onEnter();
    }
  };

  const formatError = slug !== "" && !SLUG_PATTERN.test(slug)
    ? "Само малки латински букви, цифри и тирета между думите."
    : null;

  return (
    <>
      <label className="flex flex-col gap-1 text-sm font-medium">
        Име
        <input
          type="text"
          value={name}
          onChange={(event) => {
            onNameChange(event.target.value);
            if (!isSlugEdited) {
              onSlugChange(slugify(event.target.value).slice(0, maxLength));
            }
          }}
          onKeyDown={handleKeyDown}
          maxLength={maxLength}
          autoFocus
          aria-invalid={Boolean(nameError)}
          className={inputClassName}
        />
        {nameError && <span className={fieldErrorClassName}>{nameError}</span>}
      </label>

      <label className="flex flex-col gap-1 text-sm font-medium">
        Slug
        <span className={`text-xs font-normal ${mutedTextClassName}`}>
          Задължителен. Използва се в линковете и филтрите, напр. „voice-video“.
        </span>
        <input
          type="text"
          value={slug}
          onChange={(event) => {
            setIsSlugEdited(true);
            onSlugChange(event.target.value.toLowerCase());
          }}
          onKeyDown={handleKeyDown}
          maxLength={maxLength}
          spellCheck={false}
          autoCapitalize="none"
          aria-invalid={Boolean(slugError ?? formatError)}
          className={`${inputClassName} font-mono`}
        />
        {(slugError ?? formatError) && (
          <span className={fieldErrorClassName}>{slugError ?? formatError}</span>
        )}
      </label>
    </>
  );
}

// Whether the name and slug can be sent: both filled in, the slug well formed.
export function canSendNameSlug(name: string, slug: string): boolean {
  return name.trim() !== "" && SLUG_PATTERN.test(slug);
}
