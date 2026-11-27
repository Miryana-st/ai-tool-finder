"use client";

import { Pencil, Trash2 } from "lucide-react";
import { useEffect, useState, type FormEvent } from "react";
import UserAvatar from "@/components/layout/UserAvatar";
import { Button } from "@/components/ui/button";
import * as api from "@/lib/api";
import { ApiError, MAX_COMMENT_LENGTH, fieldError, isAbortError, type ToolComment } from "@/lib/api";
import { cn } from "@/lib/utils";
import { formFieldClassName } from "@/styles/classNames";

type ListState =
  | { status: "loading" }
  | { status: "error" }
  | { status: "ready"; comments: ToolComment[]; page: number; lastPage: number; total: number };

// The comments on an approved tool: newest first, 10 at a time with "show more",
// a form for a new comment, and editing or deleting where the user may.
export default function ToolComments({ toolId }: { toolId: number }) {
  const [state, setState] = useState<ListState>({ status: "loading" });
  const [isLoadingMore, setIsLoadingMore] = useState(false);
  const [loadMoreError, setLoadMoreError] = useState(false);

  useEffect(() => {
    const controller = new AbortController();

    api
      .getToolComments(toolId, 1, controller.signal)
      .then((result) =>
        setState({
          status: "ready",
          comments: result.data,
          page: result.meta.current_page,
          lastPage: result.meta.last_page,
          total: result.meta.total,
        }),
      )
      .catch((error) => {
        if (!isAbortError(error)) {
          console.error("Could not load the comments.", error);
          setState({ status: "error" });
        }
      });

    return () => controller.abort();
  }, [toolId]);

  if (state.status === "loading") {
    return <p className="text-sm text-muted-foreground">Зареждане на коментарите…</p>;
  }

  if (state.status === "error") {
    return (
      <p role="alert" className="text-sm text-red-600">
        Коментарите не можаха да се заредят. Опитай да презаредиш страницата.
      </p>
    );
  }

  const loadMore = async () => {
    setIsLoadingMore(true);
    setLoadMoreError(false);

    try {
      const result = await api.getToolComments(toolId, state.page + 1);
      setState((current) => {
        if (current.status !== "ready") {
          return current;
        }
        // New comments shift the pages, so a comment can come again: keep it once.
        const known = new Set(current.comments.map((comment) => comment.id));

        return {
          ...current,
          comments: [...current.comments, ...result.data.filter((comment) => !known.has(comment.id))],
          page: result.meta.current_page,
          lastPage: result.meta.last_page,
        };
      });
    } catch (error) {
      console.error("Could not load more comments.", error);
      setLoadMoreError(true);
    } finally {
      setIsLoadingMore(false);
    }
  };

  const update = (change: (comments: ToolComment[]) => ToolComment[], totalChange = 0) =>
    setState((current) =>
      current.status === "ready"
        ? { ...current, comments: change(current.comments), total: current.total + totalChange }
        : current,
    );

  return (
    <div className="flex flex-col gap-6">
      <CommentForm
        toolId={toolId}
        onCreated={(comment) => update((comments) => [comment, ...comments], 1)}
      />

      {state.comments.length === 0 ? (
        <p className="text-sm text-muted-foreground">Още няма коментари. Сподели как използваш инструмента.</p>
      ) : (
        <>
        <p className="text-sm font-medium text-muted-foreground">{commentsCountLabel(state.total)}</p>
        <ul className="flex flex-col divide-y">
          {state.comments.map((comment) => (
            <CommentItem
              key={comment.id}
              toolId={toolId}
              comment={comment}
              onUpdated={(updated) =>
                update((comments) => comments.map((item) => (item.id === updated.id ? updated : item)))
              }
              onDeleted={() => update((comments) => comments.filter((item) => item.id !== comment.id), -1)}
            />
          ))}
        </ul>
        </>
      )}

      {state.page < state.lastPage && (
        <div className="flex flex-col items-center gap-2">
          <Button variant="outline" onClick={() => void loadMore()} disabled={isLoadingMore} className="rounded-xl">
            {isLoadingMore ? "Зареждане…" : "Покажи още коментари"}
          </Button>
          {loadMoreError && (
            <p role="alert" className="text-sm text-red-600">
              Не можаха да се заредят още коментари. Опитай отново.
            </p>
          )}
        </div>
      )}
    </div>
  );
}

// "1 коментар", "3 коментара".
function commentsCountLabel(count: number): string {
  return `${count} ${count === 1 ? "коментар" : "коментара"}`;
}

function CommentForm({ toolId, onCreated }: { toolId: number; onCreated: (comment: ToolComment) => void }) {
  const [body, setBody] = useState("");
  const [isSaving, setIsSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const handleSubmit = async (event: FormEvent) => {
    event.preventDefault();
    setIsSaving(true);
    setError(null);

    try {
      onCreated(await api.createToolComment(toolId, body));
      setBody("");
    } catch (caught) {
      console.error("Could not add the comment.", caught);
      setError(errorMessage(caught, "Коментарът не можа да се добави. Опитай отново."));
    } finally {
      setIsSaving(false);
    }
  };

  return (
    <form onSubmit={(event) => void handleSubmit(event)} className="flex flex-col gap-2">
      <label htmlFor="new-comment" className="sr-only">
        Нов коментар
      </label>
      <CommentTextarea id="new-comment" value={body} onChange={setBody} placeholder="Напиши коментар…" invalid={error !== null} />
      <div className="flex items-start justify-between gap-3">
        <div className="min-h-5 text-sm">
          {error && (
            <p role="alert" className="text-red-600">
              {error}
            </p>
          )}
        </div>
        <Button
          type="submit"
          disabled={isSaving || body.trim() === ""}
          className="shrink-0 rounded-xl bg-violet-600 text-white hover:bg-violet-700"
        >
          {isSaving ? "Публикуване…" : "Публикувай"}
        </Button>
      </div>
    </form>
  );
}

function CommentItem({
  toolId,
  comment,
  onUpdated,
  onDeleted,
}: {
  toolId: number;
  comment: ToolComment;
  onUpdated: (comment: ToolComment) => void;
  onDeleted: () => void;
}) {
  const [isEditing, setIsEditing] = useState(false);
  const [draft, setDraft] = useState(comment.body);
  const [isSaving, setIsSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const authorName = comment.author?.name ?? "Изтрит потребител";

  const handleSave = async (event: FormEvent) => {
    event.preventDefault();
    setIsSaving(true);
    setError(null);

    try {
      onUpdated(await api.updateToolComment(toolId, comment.id, draft));
      setIsEditing(false);
    } catch (caught) {
      console.error("Could not save the comment.", caught);
      setError(errorMessage(caught, "Коментарът не можа да се запази. Опитай отново."));
    } finally {
      setIsSaving(false);
    }
  };

  const handleDelete = async () => {
    if (!window.confirm("Сигурен ли си, че искаш да изтриеш коментара?")) {
      return;
    }

    setIsSaving(true);
    setError(null);

    try {
      await api.deleteToolComment(toolId, comment.id);
      onDeleted();
    } catch (caught) {
      console.error("Could not delete the comment.", caught);
      setError("Коментарът не можа да се изтрие. Опитай отново.");
      setIsSaving(false);
    }
  };

  return (
    <li className="flex gap-3 py-4 first:pt-0 last:pb-0">
      <UserAvatar name={authorName} className="mt-0.5 shrink-0" />
      <div className="flex min-w-0 flex-1 flex-col gap-1.5">
        <div className="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1">
          <p className="text-sm">
            <span className={cn("font-semibold", !comment.author && "text-muted-foreground italic")}>{authorName}</span>
            <span className="text-muted-foreground">
              {" · "}
              <time dateTime={comment.created_at}>{formatDateTime(comment.created_at)}</time>
              {comment.is_edited && " · редактиран"}
            </span>
          </p>
          {!isEditing && (comment.can.update || comment.can.delete) && (
            <div className="flex items-center gap-1">
              {comment.can.update && (
                <Button
                  variant="ghost"
                  size="icon-sm"
                  onClick={() => {
                    setDraft(comment.body);
                    setError(null);
                    setIsEditing(true);
                  }}
                  aria-label="Редактирай коментара"
                  title="Редактирай"
                >
                  <Pencil />
                </Button>
              )}
              {comment.can.delete && (
                <Button
                  variant="ghost"
                  size="icon-sm"
                  onClick={() => void handleDelete()}
                  disabled={isSaving}
                  aria-label="Изтрий коментара"
                  title="Изтрий"
                  className="text-red-600 hover:text-red-600"
                >
                  <Trash2 />
                </Button>
              )}
            </div>
          )}
        </div>

        {isEditing ? (
          <form onSubmit={(event) => void handleSave(event)} className="flex flex-col gap-2">
            <label htmlFor={`comment-${comment.id}`} className="sr-only">
              Текст на коментара
            </label>
            <CommentTextarea id={`comment-${comment.id}`} value={draft} onChange={setDraft} invalid={error !== null} autoFocus />
            <div className="flex justify-end gap-2">
              <Button type="button" variant="outline" onClick={() => setIsEditing(false)} disabled={isSaving} className="rounded-xl">
                Отказ
              </Button>
              <Button
                type="submit"
                disabled={isSaving || draft.trim() === ""}
                className="rounded-xl bg-violet-600 text-white hover:bg-violet-700"
              >
                {isSaving ? "Запазване…" : "Запази"}
              </Button>
            </div>
          </form>
        ) : (
          <p className="text-sm break-words whitespace-pre-line">{comment.body}</p>
        )}

        {error && (
          <p role="alert" className="text-sm text-red-600">
            {error}
          </p>
        )}
      </div>
    </li>
  );
}

function CommentTextarea({
  id,
  value,
  onChange,
  placeholder,
  invalid,
  autoFocus,
}: {
  id: string;
  value: string;
  onChange: (value: string) => void;
  placeholder?: string;
  invalid: boolean;
  autoFocus?: boolean;
}) {
  return (
    <div className="flex flex-col gap-1">
      <textarea
        id={id}
        value={value}
        onChange={(event) => onChange(event.target.value)}
        placeholder={placeholder}
        maxLength={MAX_COMMENT_LENGTH}
        rows={3}
        aria-invalid={invalid}
        autoFocus={autoFocus}
        className={cn(formFieldClassName, "resize-y")}
      />
      <span className="self-end text-xs text-muted-foreground tabular-nums">
        {value.length}/{MAX_COMMENT_LENGTH}
      </span>
    </div>
  );
}

// The validation message for the text, or the given fallback.
function errorMessage(error: unknown, fallback: string): string {
  return (error instanceof ApiError && fieldError(error.errors, "body")) || fallback;
}

function formatDateTime(value: string): string {
  return new Date(value).toLocaleString("bg-BG", { dateStyle: "medium", timeStyle: "short" });
}
