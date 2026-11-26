"use client";

import { ChevronLeft, ChevronRight } from "lucide-react";
import { cn } from "@/lib/utils";
import { mutedTextClassName } from "@/styles/classNames";

// The page numbers to show: the first, the last and the ones next to the current page.
function visiblePages(page: number, lastPage: number): (number | "gap")[] {
  const pages: (number | "gap")[] = [];

  for (let number = 1; number <= lastPage; number++) {
    if (number === 1 || number === lastPage || Math.abs(number - page) <= 1) {
      pages.push(number);
    } else if (pages[pages.length - 1] !== "gap") {
      pages.push("gap");
    }
  }

  return pages;
}

// ‹ 1 2 3 … › with a short summary under it, shown even when there is only one page.
export default function Pagination({
  page,
  lastPage,
  summary,
  onChange,
}: {
  page: number;
  lastPage: number;
  // Shown under the page buttons, e.g. "43 инструмента".
  summary: string;
  onChange: (page: number) => void;
}) {
  const arrowClassName =
    "flex size-9 items-center justify-center rounded-lg border bg-card text-muted-foreground shadow-xs transition-colors hover:border-violet-300 hover:text-violet-700 disabled:pointer-events-none disabled:opacity-40";

  return (
    <nav aria-label="Страници" className="flex flex-col items-center gap-2">
      <ul className="flex flex-wrap items-center justify-center gap-1.5 sm:gap-2">
        <li>
          <button
            type="button"
            onClick={() => onChange(page - 1)}
            disabled={page <= 1}
            aria-label="Предишна страница"
            className={arrowClassName}
          >
            <ChevronLeft className="size-4" />
          </button>
        </li>
        {visiblePages(page, lastPage).map((item, index) =>
          item === "gap" ? (
            <li key={`gap-${index}`} aria-hidden="true" className="px-1 text-muted-foreground">
              …
            </li>
          ) : (
            <li key={item}>
              <button
                type="button"
                onClick={() => onChange(item)}
                aria-current={item === page ? "page" : undefined}
                className={cn(
                  "flex size-9 items-center justify-center rounded-lg text-sm font-semibold transition-colors",
                  item === page
                    ? "bg-violet-600 text-white shadow-md shadow-violet-600/30"
                    : "border bg-card shadow-xs hover:border-violet-300 hover:text-violet-700",
                )}
              >
                {item}
              </button>
            </li>
          ),
        )}
        <li>
          <button
            type="button"
            onClick={() => onChange(page + 1)}
            disabled={page >= lastPage}
            aria-label="Следваща страница"
            className={arrowClassName}
          >
            <ChevronRight className="size-4" />
          </button>
        </li>
      </ul>
      <p className={cn("text-sm", mutedTextClassName)}>{summary}</p>
    </nav>
  );
}
