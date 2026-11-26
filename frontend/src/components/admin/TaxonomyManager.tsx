"use client";

import { useEffect, useState, type FormEvent } from "react";
import AdminOnly from "@/components/admin/AdminOnly";
import AdminPagination, { usePagedItems } from "@/components/admin/AdminPagination";
import PendingSuggestions from "@/components/admin/PendingSuggestions";
import NameSlugFields, { canSendNameSlug } from "@/components/tools/NameSlugFields";
import * as api from "@/lib/api";
import { ApiError, type Category, type SuggestionKind, type Tag, type ValidationErrors } from "@/lib/api";
import {
  chipClassName,
  dangerButtonClassName,
  fieldErrorClassName,
  inputClassName,
  mutedTextClassName,
  primaryButtonClassName,
  secondaryButtonClassName,
} from "@/styles/classNames";

type Item = Category | Tag;

type ListState =
  | { status: "loading" }
  | { status: "error" }
  | { status: "ready"; items: Item[] };

type Message = { type: "success" | "error"; text: string };

// Kept in line with Store/Update{Category,Tag}Request.
const MAX_LENGTH: Record<SuggestionKind, number> = { categories: 50, tags: 30 };
const MAX_DESCRIPTION_LENGTH = 500;

const TEXT: Record<
  SuggestionKind,
  { title: string; listTitle: string; empty: string; addTitle: string; addButton: string; noun: string }
> = {
  categories: {
    title: "Категории",
    listTitle: "Съществуващи категории",
    empty: "Все още няма категории.",
    addTitle: "Нова категория",
    addButton: "Добави категория",
    noun: "Категорията",
  },
  tags: {
    title: "Тагове",
    listTitle: "Съществуващи тагове",
    empty: "Все още няма тагове.",
    addTitle: "Нов таг",
    addButton: "Добави таг",
    noun: "Тагът",
  },
};

const byName = (a: Item, b: Item) => a.name.localeCompare(b.name, "bg");

const cardClassName =
  "rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-950";

// Admin page for categories or tags: add, rename, delete, and decide on suggestions.
export default function TaxonomyManager({ kind }: { kind: SuggestionKind }) {
  return (
    <AdminOnly>
      <Manager key={kind} kind={kind} />
    </AdminOnly>
  );
}

function Manager({ kind }: { kind: SuggestionKind }) {
  const text = TEXT[kind];
  const [state, setState] = useState<ListState>({ status: "loading" });
  const [message, setMessage] = useState<Message | null>(null);

  useEffect(() => {
    const controller = new AbortController();

    api
      .getApprovedItems(kind, controller.signal)
      .then((items) => setState({ status: "ready", items }))
      .catch((error: unknown) => {
        if (api.isAbortError(error)) {
          return;
        }
        console.error(`Could not load the ${kind}.`, error);
        setState({ status: "error" });
      });

    return () => controller.abort();
  }, [kind]);

  const updateItems = (change: (items: Item[]) => Item[]) =>
    setState((current) =>
      current.status === "ready" ? { status: "ready", items: change(current.items).sort(byName) } : current,
    );

  const addItem = (item: Item) =>
    updateItems((items) => [...items.filter((existing) => existing.id !== item.id), item]);

  return (
    <section className="flex flex-col gap-6">
      <div className="flex flex-col gap-1">
        <h1 className="text-3xl font-semibold tracking-tight">{text.title}</h1>
        <p className={mutedTextClassName}>
          Slug-ът не се променя след създаване, за да не се чупят линковете и филтрите. Изтриването маха{" "}
          {kind === "categories" ? "категорията" : "тага"} от всички инструменти, но не изтрива самите инструменти.
        </p>
      </div>

      <AddItemForm
        kind={kind}
        onCreated={(item) => {
          addItem({ ...item, tools_count: 0 });
          setMessage({ type: "success", text: `${text.noun} „${item.name}“ е добавен${kind === "categories" ? "а" : ""}.` });
        }}
      />

      <PendingSuggestions kind={kind} onApproved={addItem} />

      {message && (
        <p
          role={message.type === "error" ? "alert" : "status"}
          className={message.type === "error" ? "text-sm text-red-600" : "text-sm text-green-700 dark:text-green-400"}
        >
          {message.text}
        </p>
      )}

      <div className="flex flex-col gap-4">
        <h2 className="text-lg font-semibold">
          {text.listTitle}
          {state.status === "ready" && (
            <span className={`ml-2 text-sm font-normal ${mutedTextClassName}`}>({state.items.length})</span>
          )}
        </h2>

        {state.status === "loading" && <p className={mutedTextClassName}>Зареждане…</p>}

        {state.status === "error" && (
          <p role="alert" className="text-red-600 dark:text-red-400">
            Списъкът не можа да се зареди. Опитай отново по-късно.
          </p>
        )}

        {state.status === "ready" && state.items.length === 0 && <p className={mutedTextClassName}>{text.empty}</p>}

        {state.status === "ready" && state.items.length > 0 && (
          <ItemList
            kind={kind}
            items={state.items}
            onMessage={setMessage}
            onUpdated={(updated) =>
              updateItems((items) => items.map((existing) => (existing.id === updated.id ? updated : existing)))
            }
            onDeleted={(id) => updateItems((items) => items.filter((existing) => existing.id !== id))}
          />
        )}
      </div>
    </section>
  );
}

// Ten items per page.
function ItemList({
  kind,
  items,
  onMessage,
  onUpdated,
  onDeleted,
}: {
  kind: SuggestionKind;
  items: Item[];
  onMessage: (message: Message | null) => void;
  onUpdated: (item: Item) => void;
  onDeleted: (id: number) => void;
}) {
  const { pageItems, page, lastPage, setPage } = usePagedItems(items);

  return (
    <>
      <ul className="flex flex-col gap-3">
        {pageItems.map((item) => (
          <ItemRow
            key={item.id}
            kind={kind}
            item={item}
            onMessage={onMessage}
            onUpdated={onUpdated}
            onDeleted={onDeleted}
          />
        ))}
      </ul>
      <AdminPagination
        page={page}
        lastPage={lastPage}
        total={items.length}
        itemsLabel={kind === "categories" ? (items.length === 1 ? "категория" : "категории") : items.length === 1 ? "таг" : "тага"}
        onChange={setPage}
      />
    </>
  );
}

// An admin's new category or tag is approved right away.
function AddItemForm({ kind, onCreated }: { kind: SuggestionKind; onCreated: (item: Item) => void }) {
  const text = TEXT[kind];
  const [name, setName] = useState("");
  const [slug, setSlug] = useState("");
  const [description, setDescription] = useState("");
  const [errors, setErrors] = useState<ValidationErrors>({});
  const [formError, setFormError] = useState<string | null>(null);
  const [isSaving, setIsSaving] = useState(false);
  // Remounts the fields after a save, so the slug follows the name again.
  const [formKey, setFormKey] = useState(0);

  const submit = async () => {
    if (isSaving || !canSendNameSlug(name, slug)) {
      return;
    }

    setIsSaving(true);
    setErrors({});
    setFormError(null);

    try {
      const item =
        kind === "categories"
          ? await api.suggestCategory({ name: name.trim(), slug, description: description.trim() || null })
          : await api.suggestTag({ name: name.trim(), slug });
      onCreated(item);
      setName("");
      setSlug("");
      setDescription("");
      setFormKey((key) => key + 1);
    } catch (error) {
      if (error instanceof ApiError && error.status === 422) {
        setErrors(error.errors);
      } else {
        console.error(`Could not add to the ${kind}.`, error);
        setFormError("Не можа да се добави. Опитай отново.");
      }
    } finally {
      setIsSaving(false);
    }
  };

  const handleSubmit = (event: FormEvent) => {
    event.preventDefault();
    void submit();
  };

  const descriptionError = api.fieldError(errors, "description");

  return (
    <form onSubmit={handleSubmit} className={`flex flex-col gap-4 ${cardClassName}`}>
      <h2 className="text-lg font-semibold">{text.addTitle}</h2>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <NameSlugFields
          key={formKey}
          name={name}
          slug={slug}
          onNameChange={setName}
          onSlugChange={setSlug}
          nameError={api.fieldError(errors, "name")}
          slugError={api.fieldError(errors, "slug")}
          maxLength={MAX_LENGTH[kind]}
          onEnter={() => void submit()}
        />
      </div>

      {kind === "categories" && (
        <label className="flex flex-col gap-1 text-sm font-medium">
          Описание <span className={`text-xs font-normal ${mutedTextClassName}`}>(по избор)</span>
          <textarea
            value={description}
            onChange={(event) => setDescription(event.target.value)}
            maxLength={MAX_DESCRIPTION_LENGTH}
            rows={2}
            aria-invalid={Boolean(descriptionError)}
            className={inputClassName}
          />
          {descriptionError && <span className={fieldErrorClassName}>{descriptionError}</span>}
        </label>
      )}

      {formError && (
        <p role="alert" className={fieldErrorClassName}>
          {formError}
        </p>
      )}

      <button
        type="submit"
        disabled={isSaving || !canSendNameSlug(name, slug)}
        className={`${primaryButtonClassName} self-start`}
      >
        {isSaving ? "Добавяне…" : text.addButton}
      </button>
    </form>
  );
}

function ItemRow({
  kind,
  item,
  onMessage,
  onUpdated,
  onDeleted,
}: {
  kind: SuggestionKind;
  item: Item;
  onMessage: (message: Message | null) => void;
  onUpdated: (item: Item) => void;
  onDeleted: (id: number) => void;
}) {
  const text = TEXT[kind];
  const initialDescription = "description" in item ? (item.description ?? "") : "";
  const [isEditing, setIsEditing] = useState(false);
  const [name, setName] = useState(item.name);
  const [description, setDescription] = useState(initialDescription);
  const [errors, setErrors] = useState<ValidationErrors>({});
  const [isBusy, setIsBusy] = useState(false);

  const cancel = () => {
    setIsEditing(false);
    setName(item.name);
    setDescription(initialDescription);
    setErrors({});
  };

  const save = async (event: FormEvent) => {
    event.preventDefault();

    if (name.trim() === "" || isBusy) {
      return;
    }

    setIsBusy(true);
    setErrors({});
    onMessage(null);

    try {
      const updated = await api.updateApprovedItem(
        kind,
        item.id,
        kind === "categories"
          ? { name: name.trim(), description: description.trim() || null }
          : { name: name.trim() },
      );
      setIsEditing(false);
      onUpdated(updated);
      onMessage({ type: "success", text: `Промените по „${updated.name}“ са запазени.` });
    } catch (error) {
      if (error instanceof ApiError && error.status === 422) {
        setErrors(error.errors);
      } else if (error instanceof ApiError && error.status === 404) {
        onDeleted(item.id);
        onMessage({ type: "error", text: `„${item.name}“ вече е изтрит(а).` });
      } else {
        console.error(`Could not update the ${kind} item.`, error);
        onMessage({ type: "error", text: "Промените не можаха да се запазят. Опитай отново." });
      }
    } finally {
      setIsBusy(false);
    }
  };

  const remove = async () => {
    const usage = item.tools_count ? ` Използва се от ${item.tools_count} инструмент(а) и ще бъде махнат(а) от тях.` : "";
    if (!window.confirm(`Да изтрия ли „${item.name}“?${usage}`)) {
      return;
    }

    setIsBusy(true);
    onMessage(null);

    try {
      await api.deleteApprovedItem(kind, item.id);
      onDeleted(item.id);
      onMessage({ type: "success", text: `${text.noun} „${item.name}“ е изтрит${kind === "categories" ? "а" : ""}.` });
    } catch (error) {
      if (error instanceof ApiError && error.status === 404) {
        onDeleted(item.id);
        onMessage({ type: "error", text: `„${item.name}“ вече е изтрит(а).` });
      } else {
        console.error(`Could not delete the ${kind} item.`, error);
        onMessage({ type: "error", text: "Не можа да се изтрие. Опитай отново." });
        setIsBusy(false);
      }
    }
  };

  const nameError = api.fieldError(errors, "name");
  const descriptionError = api.fieldError(errors, "description");

  if (isEditing) {
    return (
      <li className={cardClassName}>
        <form onSubmit={(event) => void save(event)} className="flex flex-col gap-3">
          <label className="flex flex-col gap-1 text-sm font-medium">
            Име
            <input
              type="text"
              value={name}
              onChange={(event) => setName(event.target.value)}
              onKeyDown={(event) => {
                if (event.key === "Escape") {
                  cancel();
                }
              }}
              maxLength={MAX_LENGTH[kind]}
              autoFocus
              aria-invalid={Boolean(nameError)}
              className={inputClassName}
            />
            {nameError && <span className={fieldErrorClassName}>{nameError}</span>}
          </label>

          {kind === "categories" && (
            <label className="flex flex-col gap-1 text-sm font-medium">
              Описание
              <textarea
                value={description}
                onChange={(event) => setDescription(event.target.value)}
                maxLength={MAX_DESCRIPTION_LENGTH}
                rows={2}
                aria-invalid={Boolean(descriptionError)}
                className={inputClassName}
              />
              {descriptionError && <span className={fieldErrorClassName}>{descriptionError}</span>}
            </label>
          )}

          <div className="flex gap-2">
            <button type="submit" disabled={isBusy || name.trim() === ""} className={primaryButtonClassName}>
              {isBusy ? "Запазване…" : "Запази"}
            </button>
            <button type="button" onClick={cancel} disabled={isBusy} className={secondaryButtonClassName}>
              Откажи
            </button>
          </div>
        </form>
      </li>
    );
  }

  return (
    <li className={`flex flex-wrap items-start justify-between gap-4 ${cardClassName}`}>
      <div className="flex min-w-0 flex-col gap-1">
        <p className="flex flex-wrap items-center gap-2 font-medium">
          {item.name}
          <span className={`${chipClassName} font-mono`}>{item.slug}</span>
          <span className={`text-xs font-normal ${mutedTextClassName}`}>
            {item.tools_count ?? 0} инструмент(а)
          </span>
        </p>
        {"description" in item && item.description && <p className="text-sm">{item.description}</p>}
      </div>

      <div className="flex gap-2">
        <button type="button" onClick={() => setIsEditing(true)} disabled={isBusy} className={secondaryButtonClassName}>
          Редактирай
        </button>
        <button type="button" onClick={() => void remove()} disabled={isBusy} className={dangerButtonClassName}>
          Изтрий
        </button>
      </div>
    </li>
  );
}
