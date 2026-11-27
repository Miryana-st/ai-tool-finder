"use client";

import { ArrowRight, Heart } from "lucide-react";
import Link from "next/link";
import { useState } from "react";
import { buttonVariants } from "@/components/ui/button";
import * as api from "@/lib/api";
import type { FavoriteInfo, Tool } from "@/lib/api";
import { cn } from "@/lib/utils";
import { markdownToPlainText } from "./Markdown";
import PendingBadge from "./PendingBadge";
import RatingStars, { formatRating } from "./RatingStars";

const MAX_CHIPS = 3;

const chipBaseClassName = "inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium";

export default function ToolCard({ tool }: { tool: Tool }) {
  const logo = tool.images[0];
  const [favorite, setFavorite] = useState<FavoriteInfo>({
    is_favorite: tool.is_favorite,
    favorites_count: tool.favorites_count,
  });

  const chips = [
    ...tool.categories.map((category) => ({ key: `category-${category.id}`, label: category.name, accent: true })),
    ...tool.company_roles.map((role) => ({ key: `role-${role.id}`, label: role.name, accent: false })),
  ];
  const hiddenChips = chips.length - MAX_CHIPS;

  return (
    <article className="group relative flex h-full flex-col gap-4 rounded-2xl border bg-card p-5 text-card-foreground shadow-sm transition-shadow hover:shadow-md">
      <div className="flex items-start justify-between gap-3">
        <Link href={`/tools/${tool.slug}`} tabIndex={-1} aria-hidden="true">
          {logo ? (
            // Uploaded files come from /storage, outside the Next.js image pipeline.
            // eslint-disable-next-line @next/next/no-img-element
            <img src={logo.url} alt="" className="size-16 rounded-2xl border bg-muted object-cover shadow-sm" />
          ) : (
            <span className="flex size-16 items-center justify-center rounded-2xl bg-linear-to-br from-violet-500 to-indigo-600 text-3xl font-bold text-white shadow-sm">
              {tool.name.charAt(0).toUpperCase()}
            </span>
          )}
        </Link>

        <FavoriteToggle toolId={tool.id} toolName={tool.name} favorite={favorite} onChange={setFavorite} />
      </div>

      <div className="flex flex-col gap-1.5">
        <h2 className="text-lg font-bold tracking-tight">
          <Link href={`/tools/${tool.slug}`} className="hover:text-violet-700 dark:hover:text-violet-300">
            {tool.name}
          </Link>
        </h2>
        {!tool.is_approved && <PendingBadge className="self-start" />}
        {tool.is_approved && (
          <span className="inline-flex items-center gap-1.5 text-sm text-muted-foreground">
            <RatingStars value={tool.rating_average} className="text-sm" />
            {tool.rating_average === null ? (
              "Без оценки"
            ) : (
              <span>
                <span className="font-semibold text-foreground">{formatRating(tool.rating_average)}</span>
                {` (${tool.ratings_count})`}
              </span>
            )}
          </span>
        )}
        <p className="line-clamp-2 text-sm text-muted-foreground">{markdownToPlainText(tool.description)}</p>
      </div>

      {chips.length > 0 && (
        <ul aria-label="Категории и роли" className="flex flex-wrap gap-1.5">
          {chips.slice(0, MAX_CHIPS).map((chip) => (
            <li
              key={chip.key}
              className={cn(
                chipBaseClassName,
                chip.accent
                  ? "bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300"
                  : "border bg-muted/50 text-muted-foreground",
              )}
            >
              {chip.label}
            </li>
          ))}
          {hiddenChips > 0 && (
            <li className={cn(chipBaseClassName, "border bg-muted/50 text-muted-foreground")}>+{hiddenChips}</li>
          )}
        </ul>
      )}

      <div className="mt-auto flex items-center justify-between gap-3 pt-1">
        <span className="inline-flex items-center gap-1.5 text-sm text-muted-foreground">
          <Heart className="size-4 fill-rose-500 text-rose-500" aria-hidden="true" />
          <span>
            <span>Любим на </span><span className="font-semibold text-foreground">{favorite.favorites_count}</span> потребител/я
          </span>
        </span>

        <Link
          href={`/tools/${tool.slug}`}
          className={cn(
            buttonVariants({ size: "sm" }),
            "h-8 rounded-lg bg-violet-600 px-3 text-white hover:bg-violet-700",
          )}
        >
          Детайли
          <ArrowRight className="size-3.5" aria-hidden="true" />
        </Link>
      </div>
    </article>
  );
}

function FavoriteToggle({
  toolId,
  toolName,
  favorite,
  onChange,
}: {
  toolId: number;
  toolName: string;
  favorite: FavoriteInfo;
  onChange: (favorite: FavoriteInfo) => void;
}) {
  const [isSaving, setIsSaving] = useState(false);
  const [hasError, setHasError] = useState(false);

  const handleClick = async () => {
    setIsSaving(true);
    setHasError(false);

    try {
      onChange(await api.setToolFavorite(toolId, !favorite.is_favorite));
    } catch (caught) {
      console.error("Could not change the favorite tool.", caught);
      setHasError(true);
    } finally {
      setIsSaving(false);
    }
  };

  const label = favorite.is_favorite ? `Премахни ${toolName} от любими` : `Добави ${toolName} в любими`;

  return (
    <div className="flex flex-col items-end gap-1">
      <button
        type="button"
        onClick={() => void handleClick()}
        disabled={isSaving}
        aria-pressed={favorite.is_favorite}
        aria-label={label}
        title={label}
        className="flex size-9 items-center justify-center rounded-full text-violet-500 transition-colors hover:bg-violet-50 disabled:opacity-60 dark:hover:bg-violet-500/10"
      >
        <Heart className={cn("size-5", favorite.is_favorite && "fill-rose-500 text-rose-500")} />
      </button>
      {hasError && (
        <p role="alert" className="text-xs text-red-600">
          Неуспешно. Опитай пак.
        </p>
      )}
    </div>
  );
}
