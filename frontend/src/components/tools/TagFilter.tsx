"use client";

import { Tag as TagIcon } from "lucide-react";
import { useEffect, useId, useState, type KeyboardEvent } from "react";
import * as api from "@/lib/api";
import type { Tag } from "@/lib/api";
import { cn } from "@/lib/utils";
import { useDebouncedValue } from "@/lib/useDebouncedValue";

type Props = {
  // Slugs of the tags already picked; they are left out of the suggestions.
  picked: string[];
  // Suggestions shown before anything is typed.
  initialTags: Tag[];
  onPick: (tag: Tag) => void;
  disabled?: boolean;
  className?: string;
};

// A field for adding tags to the tool filters: typing shows the matching approved tags.
export default function TagFilter({ picked, initialTags, onPick, disabled = false, className }: Props) {
  const listId = useId();
  const [text, setText] = useState("");
  const [isOpen, setIsOpen] = useState(false);
  const [found, setFound] = useState<{ search: string; tags: Tag[] }>({ search: "", tags: [] });
  const search = useDebouncedValue(text.trim());

  useEffect(() => {
    if (search === "") {
      return;
    }

    const controller = new AbortController();

    api
      .getTags(search, controller.signal)
      .then((tags) => setFound({ search, tags }))
      .catch((error: unknown) => {
        if (!api.isAbortError(error)) {
          console.error("Could not load tag suggestions.", error);
        }
      });

    return () => controller.abort();
  }, [search]);

  const source = text.trim() === "" ? initialTags : found.search === search ? found.tags : [];
  const matches = source.filter((tag) => !picked.includes(tag.slug));

  const pick = (tag: Tag) => {
    onPick(tag);
    setText("");
  };

  const handleKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
    if (event.key === "Enter") {
      event.preventDefault();
      if (matches.length > 0) {
        pick(matches[0]);
      }
    } else if (event.key === "Escape") {
      setIsOpen(false);
    }
  };

  const showList = isOpen && !disabled && matches.length > 0;

  return (
    <div className={cn("relative", className)}>
      <label>
        <span className="sr-only">Добави таг към филтрите</span>
        <TagIcon
          className="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-muted-foreground"
          aria-hidden="true"
        />
        <input
          type="text"
          role="combobox"
          aria-expanded={showList}
          aria-controls={listId}
          aria-autocomplete="list"
          value={text}
          onChange={(event) => {
            setText(event.target.value);
            setIsOpen(true);
          }}
          onFocus={() => setIsOpen(true)}
          onBlur={() => setIsOpen(false)}
          onKeyDown={handleKeyDown}
          disabled={disabled}
          placeholder={disabled ? "Максимум тагове" : "Добави таг…"}
          autoComplete="off"
          className="h-11 w-full rounded-xl border border-input bg-card pr-3.5 pl-10 text-sm shadow-xs outline-none transition-colors placeholder:text-muted-foreground focus-visible:border-violet-500 focus-visible:ring-3 focus-visible:ring-violet-500/20 disabled:opacity-60"
        />
      </label>

      {showList && (
        <ul
          id={listId}
          role="listbox"
          className="absolute z-20 mt-1 max-h-64 w-full overflow-y-auto rounded-xl border bg-popover p-1 text-popover-foreground shadow-lg"
        >
          {matches.map((tag) => (
            <li key={tag.id} role="option" aria-selected={false}>
              <button
                type="button"
                // Keep the focus in the field, so picking does not close the list first.
                onMouseDown={(event) => event.preventDefault()}
                onClick={() => pick(tag)}
                className="block w-full rounded-lg px-3 py-2 text-left text-sm hover:bg-violet-50 hover:text-violet-700 dark:hover:bg-violet-500/10 dark:hover:text-violet-300"
              >
                #{tag.name}
              </button>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
