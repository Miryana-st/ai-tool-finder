"use client";

import { Star } from "lucide-react";
import { useState } from "react";
import * as api from "@/lib/api";
import type { RatingInfo } from "@/lib/api";
import { cn } from "@/lib/utils";
import RatingStars, { formatRating, ratingsCountLabel } from "./RatingStars";

const STAR_LABELS = ["Слаб", "Среден", "Добър", "Много добър", "Отличен"];

// The tool's average rating and the signed-in user's own 1–5 star rating,
// which they can give, change or remove.
export default function ToolRating({
  toolId,
  rating,
  onChange,
}: {
  toolId: number;
  rating: RatingInfo;
  onChange: (rating: RatingInfo) => void;
}) {
  const [hovered, setHovered] = useState<number | null>(null);
  const [isSaving, setIsSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const save = async (value: number | null) => {
    setIsSaving(true);
    setError(null);

    try {
      onChange(await api.setToolRating(toolId, value));
    } catch (caught) {
      console.error("Could not save the rating.", caught);
      setError("Оценката не можа да се запази. Опитай отново.");
    } finally {
      setIsSaving(false);
    }
  };

  const shown = hovered ?? rating.my_rating ?? 0;

  return (
    <div className="flex flex-col gap-5">
      <div className="flex items-center gap-4">
        <span className="text-4xl font-bold tabular-nums">
          {rating.rating_average === null ? "–" : formatRating(rating.rating_average)}
        </span>
        <div className="flex flex-col gap-1">
          <RatingStars value={rating.rating_average} className="text-lg" />
          <span className="text-sm text-muted-foreground">
            {rating.ratings_count === 0 ? "Още няма оценки" : ratingsCountLabel(rating.ratings_count)}
          </span>
        </div>
      </div>

      <div className="flex flex-col gap-2 border-t pt-4">
        <span id="my-rating-label" className="text-sm font-medium">
          {rating.my_rating === null ? "Оцени инструмента" : "Твоята оценка"}
        </span>
        <div className="flex flex-wrap items-center gap-3">
          <div
            role="radiogroup"
            aria-labelledby="my-rating-label"
            className="flex"
            onMouseLeave={() => setHovered(null)}
          >
            {STAR_LABELS.map((label, index) => {
              const value = index + 1;

              return (
                <button
                  key={value}
                  type="button"
                  role="radio"
                  aria-checked={rating.my_rating === value}
                  aria-label={`${value} от 5: ${label}`}
                  title={label}
                  disabled={isSaving}
                  onMouseEnter={() => setHovered(value)}
                  onFocus={() => setHovered(value)}
                  onBlur={() => setHovered(null)}
                  onClick={() => void save(value)}
                  className="rounded-md p-1 transition-transform hover:scale-110 focus-visible:ring-3 focus-visible:ring-violet-500/30 focus-visible:outline-none disabled:opacity-60"
                >
                  <Star
                    className={cn(
                      "size-7",
                      value <= shown ? "fill-amber-400 text-amber-400" : "text-zinc-300 dark:text-zinc-600",
                    )}
                  />
                </button>
              );
            })}
          </div>
          {hovered !== null && <span className="text-sm text-muted-foreground">{STAR_LABELS[hovered - 1]}</span>}
        </div>
        {rating.my_rating !== null && (
          <button
            type="button"
            onClick={() => void save(null)}
            disabled={isSaving}
            className="self-start text-sm text-muted-foreground underline-offset-4 hover:text-foreground hover:underline disabled:opacity-60"
          >
            Премахни оценката си
          </button>
        )}
        {error && (
          <p role="alert" className="text-sm text-red-600">
            {error}
          </p>
        )}
      </div>
    </div>
  );
}
