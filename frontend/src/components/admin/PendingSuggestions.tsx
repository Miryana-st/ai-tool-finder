"use client";

import { useEffect, useState } from "react";
import AdminPagination, { usePagedItems } from "@/components/admin/AdminPagination";
import * as api from "@/lib/api";
import { ApiError, type Category, type SuggestionKind, type Tag } from "@/lib/api";
import {
  chipClassName,
  dangerButtonClassName,
  mutedTextClassName,
  primaryButtonClassName,
} from "@/styles/classNames";

type Suggestion = Category | Tag;

type ListState =
  | { status: "loading" }
  | { status: "error" }
  | { status: "ready"; suggestions: Suggestion[] };

const TEXT: Record<SuggestionKind, { intro: string; approved: (name: string) => string }> = {
  categories: {
    intro: "Одобрените категории се появяват във формата за AI инструмент. Отхвърлените се изтриват.",
    approved: (name) => `Категорията „${name}“ е одобрена и вече може да се избира.`,
  },
  tags: {
    intro: "Одобрените тагове могат да се избират във формата за AI инструмент. Отхвърлените се изтриват.",
    approved: (name) => `Тагът „${name}“ е одобрен и вече може да се избира.`,
  },
};

const dateFormat = new Intl.DateTimeFormat("bg-BG", { dateStyle: "medium", timeStyle: "short" });

// The white card of the admin forms (as the "Нова категория" form).
const cardClassName =
  "rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-950";

// Category or tag suggestions waiting for an admin. Use it inside AdminOnly.
export default function PendingSuggestions({
  kind,
  onApproved,
}: {
  kind: SuggestionKind;
  // Called with the suggestion once it is approved, so the list of approved ones can show it.
  onApproved: (item: Suggestion) => void;
}) {
  const text = TEXT[kind];
  const [state, setState] = useState<ListState>({ status: "loading" });
  const [busyId, setBusyId] = useState<number | null>(null);
  const [message, setMessage] = useState<{ type: "success" | "error"; text: string } | null>(null);
  const suggestions = state.status === "ready" ? state.suggestions : [];
  const { pageItems, page, lastPage, setPage } = usePagedItems(suggestions);

  useEffect(() => {
    const controller = new AbortController();

    api
      .getPendingSuggestions(kind, controller.signal)
      .then((suggestions) => setState({ status: "ready", suggestions }))
      .catch((error: unknown) => {
        if (api.isAbortError(error)) {
          return;
        }
        console.error("Could not load the suggestions.", error);
        setState({ status: "error" });
      });

    return () => controller.abort();
  }, [kind]);

  const remove = (id: number) =>
    setState((current) =>
      current.status === "ready"
        ? { status: "ready", suggestions: current.suggestions.filter((suggestion) => suggestion.id !== id) }
        : current,
    );

  const decide = async (suggestion: Suggestion, decision: "approve" | "reject") => {
    if (decision === "reject" && !window.confirm(`Да отхвърля ли „${suggestion.name}“? Предложението ще бъде изтрито.`)) {
      return;
    }

    setBusyId(suggestion.id);
    setMessage(null);

    try {
      if (decision === "approve") {
        await api.approveSuggestion(kind, suggestion.id);
        onApproved({ ...suggestion, is_approved: true, tools_count: 0 });
        setMessage({ type: "success", text: text.approved(suggestion.name) });
      } else {
        await api.rejectSuggestion(kind, suggestion.id);
        setMessage({ type: "success", text: `„${suggestion.name}“ е отхвърлено.` });
      }
      remove(suggestion.id);
    } catch (error) {
      // 409: already approved; 404: already rejected (e.g. by another admin).
      if (error instanceof ApiError && error.status === 409) {
        setMessage({ type: "error", text: `„${suggestion.name}“ вече е одобрено.` });
        remove(suggestion.id);
      } else if (error instanceof ApiError && error.status === 404) {
        setMessage({ type: "error", text: `„${suggestion.name}“ вече е отхвърлено.` });
        remove(suggestion.id);
      } else {
        console.error("Could not decide on the suggestion.", error);
        setMessage({ type: "error", text: "Действието не беше успешно. Опитай отново." });
      }
    } finally {
      setBusyId(null);
    }
  };

  return (
    <section aria-labelledby={`pending-${kind}`} className={`flex flex-col gap-4 ${cardClassName}`}>
      <div className="flex flex-col gap-1">
        <h2 id={`pending-${kind}`} className="text-lg font-semibold">
          Чакащи предложения
        </h2>
        <p className={`text-sm ${mutedTextClassName}`}>{text.intro}</p>
      </div>

      {message && (
        <p
          role={message.type === "error" ? "alert" : "status"}
          className={message.type === "error" ? "text-sm text-red-600" : "text-sm text-green-700 dark:text-green-400"}
        >
          {message.text}
        </p>
      )}

      {state.status === "loading" && <p className={mutedTextClassName}>Зареждане…</p>}

      {state.status === "error" && (
        <p role="alert" className="text-red-600 dark:text-red-400">
          Предложенията не можаха да се заредят. Опитай отново по-късно.
        </p>
      )}

      {state.status === "ready" && state.suggestions.length === 0 && (
        <p className={mutedTextClassName}>Няма чакащи предложения.</p>
      )}

      {suggestions.length > 0 && (
        <ul className="flex flex-col gap-3">
          {pageItems.map((suggestion) => (
            <li
              key={suggestion.id}
              className="flex flex-wrap items-start justify-between gap-4 rounded-lg border border-zinc-200 p-4 dark:border-zinc-800"
            >
              <div className="flex flex-col gap-1">
                <p className="flex flex-wrap items-center gap-2 font-medium">
                  {suggestion.name}
                  <span className={`${chipClassName} font-mono`}>{suggestion.slug}</span>
                </p>
                {"description" in suggestion && suggestion.description && (
                  <p className="text-sm">{suggestion.description}</p>
                )}
                <p className={`text-xs ${mutedTextClassName}`}>
                  Предложено от {suggestion.suggested_by?.name ?? "изтрит потребител"} ·{" "}
                  {dateFormat.format(new Date(suggestion.created_at))}
                </p>
              </div>

              <div className="flex gap-2">
                <button
                  type="button"
                  onClick={() => void decide(suggestion, "approve")}
                  disabled={busyId !== null}
                  className={primaryButtonClassName}
                >
                  Одобри
                </button>
                <button
                  type="button"
                  onClick={() => void decide(suggestion, "reject")}
                  disabled={busyId !== null}
                  className={dangerButtonClassName}
                >
                  Отхвърли
                </button>
              </div>
            </li>
          ))}
        </ul>
      )}

      {suggestions.length > 0 && (
        <AdminPagination
          page={page}
          lastPage={lastPage}
          total={suggestions.length}
          itemsLabel={suggestions.length === 1 ? "предложение" : "предложения"}
          onChange={setPage}
        />
      )}
    </section>
  );
}
