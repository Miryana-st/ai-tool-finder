"use client";

import { useState, type KeyboardEvent } from "react";
import * as api from "@/lib/api";
import { ApiError, type Category } from "@/lib/api";
import { fieldErrorClassName, inputClassName, secondaryButtonClassName } from "./styles";

type Props = {
  categories: Category[];
  selected: number[];
  onToggle: (id: number) => void;
  onCreated: (category: Category) => void;
  error: string | null;
};

// Categories as checkboxes, plus an inline "+ New category" field.
export default function CategoryPicker({ categories, selected, onToggle, onCreated, error }: Props) {
  const [isAdding, setIsAdding] = useState(false);
  const [name, setName] = useState("");
  const [createError, setCreateError] = useState<string | null>(null);
  const [isSaving, setIsSaving] = useState(false);

  const create = async () => {
    setIsSaving(true);
    setCreateError(null);

    try {
      const category = await api.createCategory(name.trim());
      onCreated(category);
      setName("");
      setIsAdding(false);
    } catch (caught) {
      if (caught instanceof ApiError && caught.status === 422) {
        setCreateError(api.fieldError(caught.errors, "name") ?? caught.message);
      } else {
        console.error("Could not create the category.", caught);
        setCreateError("Категорията не можа да се добави. Опитай отново.");
      }
    } finally {
      setIsSaving(false);
    }
  };

  // Enter must add the category, not submit the whole tool form.
  const handleKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
    if (event.key === "Enter") {
      event.preventDefault();
      void create();
    }
  };

  return (
    <fieldset className="flex flex-col gap-2">
      <legend className="mb-1 text-sm font-medium">Категории</legend>

      {categories.length === 0 && (
        <p className="text-sm text-zinc-600 dark:text-zinc-400">Все още няма категории.</p>
      )}

      <div className="grid grid-cols-1 gap-2 sm:grid-cols-3">
        {categories.map((category) => (
          <label key={category.id} className="flex items-center gap-2 text-sm">
            <input
              type="checkbox"
              checked={selected.includes(category.id)}
              onChange={() => onToggle(category.id)}
            />
            {category.name}
          </label>
        ))}
      </div>

      {isAdding ? (
        <div className="flex flex-col gap-1">
          <div className="flex gap-2">
            <input
              type="text"
              value={name}
              onChange={(event) => setName(event.target.value)}
              onKeyDown={handleKeyDown}
              maxLength={50}
              autoFocus
              aria-label="Име на нова категория"
              aria-invalid={Boolean(createError)}
              className={inputClassName}
            />
            <button
              type="button"
              onClick={() => void create()}
              disabled={isSaving || name.trim() === ""}
              className={secondaryButtonClassName}
            >
              Добави
            </button>
            <button
              type="button"
              onClick={() => {
                setIsAdding(false);
                setName("");
                setCreateError(null);
              }}
              className="text-sm text-zinc-600 hover:underline dark:text-zinc-400"
            >
              Отказ
            </button>
          </div>
          {createError && <span className={fieldErrorClassName}>{createError}</span>}
        </div>
      ) : (
        <button
          type="button"
          onClick={() => setIsAdding(true)}
          className="self-start text-sm text-indigo-600 hover:underline"
        >
          + Нова категория
        </button>
      )}

      {error && <span className={fieldErrorClassName}>{error}</span>}
    </fieldset>
  );
}
