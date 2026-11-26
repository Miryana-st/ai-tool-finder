"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { Card } from "@/components/ui/card";
import { Skeleton } from "@/components/ui/skeleton";
import * as api from "@/lib/api";
import type { Category } from "@/lib/api";
import { cn } from "@/lib/utils";
import { categoryIcon, categoryIconColor } from "./categoryIcons";

type ListState =
  | { status: "loading" }
  | { status: "error" }
  | { status: "ready"; categories: Category[] };

// Every approved category with its number of tools; each card opens the tools list filtered by it.
export default function CategoryList() {
  const [state, setState] = useState<ListState>({ status: "loading" });

  useEffect(() => {
    const controller = new AbortController();

    api
      .getCategories(controller.signal)
      .then((categories) => setState({ status: "ready", categories }))
      .catch((error: unknown) => {
        if (api.isAbortError(error)) {
          return;
        }
        console.error("Could not load the categories.", error);
        setState({ status: "error" });
      });

    return () => controller.abort();
  }, []);

  return (
    <section className="mx-auto flex w-full max-w-6xl flex-col gap-6">
      <div className="flex flex-col gap-1">
        <h1 className="text-3xl font-bold tracking-tight">Категории</h1>
        <p className="text-muted-foreground">Избери категория, за да видиш инструментите в нея.</p>
      </div>

      {state.status === "loading" && (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3" aria-busy="true" aria-label="Зареждане…">
          {[0, 1, 2, 3, 4, 5].map((key) => (
            <Skeleton key={key} className="h-32 rounded-xl" />
          ))}
        </div>
      )}

      {state.status === "error" && (
        <p role="alert" className="text-destructive">
          Категориите не можаха да се заредят. Опитай отново по-късно.
        </p>
      )}

      {state.status === "ready" && state.categories.length === 0 && (
        <p className="text-muted-foreground">Все още няма категории.</p>
      )}

      {state.status === "ready" && state.categories.length > 0 && (
        <ul className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {state.categories.map((category, index) => {
            const Icon = categoryIcon(category.slug);
            const toolsCount = category.tools_count ?? 0;

            return (
              <li key={category.id}>
                <Link
                  href={`/tools?${new URLSearchParams({ category: category.slug })}`}
                  className="group block h-full rounded-xl outline-none focus-visible:ring-2 focus-visible:ring-ring"
                >
                  <Card className="h-full flex-row items-start gap-4 px-5 shadow-sm transition group-hover:-translate-y-0.5 group-hover:shadow-md">
                    <span className="flex size-12 shrink-0 items-center justify-center rounded-xl bg-indigo-50 dark:bg-indigo-500/10">
                      <Icon className={cn("size-6", categoryIconColor(index))} aria-hidden="true" />
                    </span>
                    <div className="flex min-w-0 flex-col gap-1">
                      <span className="font-semibold text-foreground group-hover:text-violet-700 dark:group-hover:text-violet-300">
                        {category.name}
                      </span>
                      {category.description && (
                        <span className="line-clamp-2 text-sm text-muted-foreground">{category.description}</span>
                      )}
                      <span className="text-sm font-medium text-violet-700 tabular-nums dark:text-violet-300">
                        {toolsCount === 1 ? "1 инструмент" : `${toolsCount} инструмента`}
                      </span>
                    </div>
                  </Card>
                </Link>
              </li>
            );
          })}
        </ul>
      )}
    </section>
  );
}
