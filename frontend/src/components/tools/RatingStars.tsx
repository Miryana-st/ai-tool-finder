import { Star } from "lucide-react";
import { cn } from "@/lib/utils";

// Five stars filled up to the average (partly filled for decimals, e.g. 4.3).
// Read-only; the rating itself is given in ToolRating.
export default function RatingStars({ value, className }: { value: number | null; className?: string }) {
  const percent = value === null ? 0 : Math.min(Math.max(value / 5, 0), 1) * 100;

  return (
    <span
      role="img"
      aria-label={value === null ? "Без оценка" : `Оценка ${formatRating(value)} от 5`}
      className={cn("relative inline-flex shrink-0", className)}
    >
      <StarRow className="text-zinc-300 dark:text-zinc-600" />
      <span className="absolute inset-y-0 left-0 overflow-hidden" style={{ width: `${percent}%` }}>
        <StarRow className="fill-amber-400 text-amber-400" />
      </span>
    </span>
  );
}

function StarRow({ className }: { className: string }) {
  return (
    <span className="inline-flex gap-0.5" aria-hidden="true">
      {[1, 2, 3, 4, 5].map((star) => (
        <Star key={star} className={cn("size-[1em] shrink-0", className)} />
      ))}
    </span>
  );
}

// 4.3 → "4,3" (Bulgarian decimal comma).
export function formatRating(value: number): string {
  return value.toLocaleString("bg-BG", { minimumFractionDigits: 1, maximumFractionDigits: 1 });
}

// "1 оценка", "3 оценки".
export function ratingsCountLabel(count: number): string {
  return `${count} ${count === 1 ? "оценка" : "оценки"}`;
}
