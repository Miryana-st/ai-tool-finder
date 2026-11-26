"use client";

import { ArrowRight, Heart, Pencil, Trash2 } from "lucide-react";
import Link from "next/link";
import { useState, type ReactNode } from "react";
import { markdownToPlainText } from "@/components/tools/Markdown";
import { Skeleton } from "@/components/ui/skeleton";
import * as api from "@/lib/api";
import type { Tool } from "@/lib/api";
import { cn } from "@/lib/utils";
import type { ProfileTools, ProfileToolsKind } from "./useProfileTools";

const MAX_CHIPS = 2;

const chipBaseClassName = "inline-flex items-center rounded-full px-3 py-1 text-xs font-medium whitespace-nowrap";

const iconButtonClassName =
  "flex size-10 items-center justify-center rounded-xl border bg-background shadow-xs transition-colors disabled:opacity-60";

type Action = { href: string; label: string };

const TEXTS: Record<ProfileToolsKind, { title: string; headerAction: Action; empty: string; emptyAction: Action }> = {
  mine: {
    title: "Твоите добавени инструменти",
    headerAction: { href: "/tools/new", label: "Добави инструмент" },
    empty: "Още не си добавил инструмент.",
    emptyAction: { href: "/tools/new", label: "Добави инструмент" },
  },
  favorites: {
    title: "Твоите любими инструменти",
    headerAction: { href: "/favorites", label: "Виж всички" },
    empty: "Още нямаш любими инструменти.",
    emptyAction: { href: "/tools", label: "Разгледай инструментите" },
  },
};

export default function ProfileToolList({
  kind,
  list,
  onRemoved,
}: {
  kind: ProfileToolsKind;
  list: ProfileTools;
  // Called after a tool was deleted (mine) or removed from favorites.
  onRemoved: (toolId: number, reason: "deleted" | "unfavorited") => void;
}) {
  const texts = TEXTS[kind];

  return (
    <div className="flex flex-col gap-3">
      <div className="flex items-center justify-between gap-3">
        <h2 className="text-lg font-bold tracking-tight">{texts.title}</h2>
        <Link
          href={texts.headerAction.href}
          className="inline-flex shrink-0 items-center gap-1 text-sm font-semibold whitespace-nowrap text-violet-600 hover:text-violet-700 dark:text-violet-400"
        >
          {texts.headerAction.label}
          <ArrowRight className="size-4" aria-hidden="true" />
        </Link>
      </div>

      {list.status === "loading" && <LoadingRows />}

      {list.status === "error" && (
        <p role="alert" className="rounded-2xl border bg-card p-5 text-sm text-red-600">
          Инструментите не можаха да се заредят. Опитай отново по-късно.
        </p>
      )}

      {list.status === "ready" && list.tools.length === 0 && (
        <div className="flex flex-col items-center gap-3 rounded-2xl border bg-card p-8 text-center text-sm text-muted-foreground">
          <p>{texts.empty}</p>
          <Link
            href={texts.emptyAction.href}
            className="font-semibold text-violet-600 hover:text-violet-700 dark:text-violet-400"
          >
            {texts.emptyAction.label}
          </Link>
        </div>
      )}

      {list.status === "ready" && list.tools.length > 0 && (
        <ul className="divide-y overflow-hidden rounded-2xl border bg-card text-card-foreground shadow-sm">
          {list.tools.map((tool) => (
            <li key={tool.id}>
              <ToolRow tool={tool} kind={kind} onRemoved={onRemoved} />
            </li>
          ))}
        </ul>
      )}

      {list.hasMore && (
        <div className="flex flex-col items-center gap-2">
          <button
            type="button"
            onClick={() => void list.loadMore()}
            disabled={list.isLoadingMore}
            className="rounded-xl border bg-background px-5 py-2 text-sm font-semibold shadow-xs hover:bg-muted disabled:opacity-60"
          >
            {list.isLoadingMore ? "Зареждане…" : "Покажи още"}
          </button>
          {list.loadMoreError && (
            <p role="alert" className="text-sm text-red-600">
              Не успяхме да заредим още. Опитай пак.
            </p>
          )}
        </div>
      )}
    </div>
  );
}

function ToolRow({
  tool,
  kind,
  onRemoved,
}: {
  tool: Tool;
  kind: ProfileToolsKind;
  onRemoved: (toolId: number, reason: "deleted" | "unfavorited") => void;
}) {
  const [isSaving, setIsSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const logo = tool.images[0];

  const chips = [
    ...tool.company_roles.map((role) => ({ key: `role-${role.id}`, label: role.name, accent: false })),
    ...tool.categories.map((category) => ({ key: `category-${category.id}`, label: category.name, accent: true })),
  ];
  const shownChips = [
    ...chips.filter((chip) => !chip.accent).slice(0, 1),
    ...chips.filter((chip) => chip.accent).slice(0, MAX_CHIPS - 1),
  ];

  const run = async (action: () => Promise<void>, reason: "deleted" | "unfavorited", failure: string) => {
    setIsSaving(true);
    setError(null);

    try {
      await action();
      onRemoved(tool.id, reason);
    } catch (caught) {
      console.error(`Could not change the tool (${reason}).`, caught);
      setError(failure);
      setIsSaving(false);
    }
  };

  const handleDelete = () => {
    if (!window.confirm(`Сигурен ли си, че искаш да изтриеш „${tool.name}“?`)) {
      return;
    }

    void run(() => api.deleteTool(tool.id), "deleted", "Инструментът не можа да се изтрие.");
  };

  const handleUnfavorite = () => {
    void run(
      async () => {
        await api.setToolFavorite(tool.id, false);
      },
      "unfavorited",
      "Не успяхме да го премахнем от любими.",
    );
  };

  return (
    <div className="flex flex-col gap-2 p-4 sm:px-5">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:gap-5">
        <div className="flex min-w-0 flex-1 items-center gap-4">
          <Link href={`/tools/${tool.slug}`} tabIndex={-1} aria-hidden="true" className="shrink-0">
            {logo ? (
              // Uploaded files come from /storage, outside the Next.js image pipeline.
              // eslint-disable-next-line @next/next/no-img-element
              <img src={logo.url} alt="" className="size-12 rounded-xl border bg-muted object-cover" />
            ) : (
              <span className="flex size-12 items-center justify-center rounded-xl bg-linear-to-br from-violet-500 to-indigo-600 text-xl font-bold text-white">
                {tool.name.charAt(0).toUpperCase()}
              </span>
            )}
          </Link>
  
          <div className="flex min-w-0 flex-col gap-0.5">
            <Link
              href={`/tools/${tool.slug}`}
              className="truncate font-bold hover:text-violet-700 dark:hover:text-violet-300"
            >
              {tool.name}
            </Link>
            <p className="truncate text-sm text-muted-foreground">{markdownToPlainText(tool.description)}</p>
          </div>
        </div>
  
        <div className="flex items-center justify-between gap-3 sm:justify-end">
          {shownChips.length > 0 && (
            <ul aria-label="Роли и категории" className="flex flex-wrap gap-2">
              {shownChips.map((chip) => (
                <li
                  key={chip.key}
                  className={cn(
                    chipBaseClassName,
                    chip.accent
                      ? "bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300"
                      : "bg-indigo-50 text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300",
                  )}
                >
                  {chip.label}
                </li>
              ))}
            </ul>
          )}
  
          <div className="flex shrink-0 items-center gap-2 sm:ml-4">
            {kind === "mine" ? (
              <>
                {tool.can.update && (
                  <ActionLink href={`/tools/${tool.slug}/edit`} label={`Редактирай ${tool.name}`}>
                    <Pencil className="size-4" />
                  </ActionLink>
                )}
                {tool.can.delete && (
                  <button
                    type="button"
                    onClick={handleDelete}
                    disabled={isSaving}
                    aria-label={`Изтрий ${tool.name}`}
                    title={`Изтрий ${tool.name}`}
                    className={cn(iconButtonClassName, "text-red-600 hover:bg-red-50 dark:hover:bg-red-500/10")}
                  >
                    <Trash2 className="size-4" />
                  </button>
                )}
              </>
            ) : (
              <button
                type="button"
                onClick={handleUnfavorite}
                disabled={isSaving}
                aria-label={`Премахни ${tool.name} от любими`}
                title={`Премахни ${tool.name} от любими`}
                className={cn(iconButtonClassName, "text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-500/10")}
              >
                <Heart className="size-4 fill-rose-500" />
              </button>
            )}
          </div>
        </div>
      </div>

      {error && (
        <p role="alert" className="text-right text-xs text-red-600">
          {error} Опитай пак.
        </p>
      )}
    </div>
  );
}

function ActionLink({ href, label, children }: { href: string; label: string; children: ReactNode }) {
  return (
    <Link
      href={href}
      aria-label={label}
      title={label}
      className={cn(iconButtonClassName, "text-violet-700 hover:bg-violet-50 dark:text-violet-300 dark:hover:bg-violet-500/10")}
    >
      {children}
    </Link>
  );
}

function LoadingRows() {
  return (
    <div className="divide-y rounded-2xl border bg-card" aria-busy="true" aria-label="Зареждане">
      {[0, 1, 2].map((row) => (
        <div key={row} className="flex items-center gap-4 p-4 sm:px-5">
          <Skeleton className="size-12 rounded-xl" />
          <div className="flex flex-1 flex-col gap-2">
            <Skeleton className="h-4 w-40" />
            <Skeleton className="h-3 w-64 max-w-full" />
          </div>
        </div>
      ))}
    </div>
  );
}
