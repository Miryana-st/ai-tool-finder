import {
  ArrowRight,
  Bot,
  ChartColumn,
  CodeXml,
  FlaskConical,
  Folder,
  Palette,
  PenLine,
  Shapes,
  type LucideIcon,
} from "lucide-react";
import Link from "next/link";
import { Card } from "@/components/ui/card";
import type { PopularCategory } from "@/lib/api";
import { cn } from "@/lib/utils";

// Icons for the base categories (see the backend's base category migrations).
const CATEGORY_ICONS: Record<string, LucideIcon> = {
  coding: CodeXml,
  design: Palette,
  data: ChartColumn,
  automation: Bot,
  testing: FlaskConical,
  content: PenLine,
  other: Shapes,
};

const ICON_COLORS = [
  "text-teal-500",
  "text-fuchsia-500",
  "text-blue-500",
  "text-amber-500",
  "text-indigo-500",
];

export default function PopularCategories({ categories }: { categories: PopularCategory[] }) {
  return (
    <section aria-labelledby="popular-categories" className="flex flex-col gap-4">
      <div className="flex items-center justify-between gap-4">
        <h2 id="popular-categories" className="text-xl font-bold text-slate-900">
          Популярни категории
        </h2>
        <Link
          href="/tools"
          className="inline-flex items-center gap-1 text-sm font-medium text-primary hover:underline"
        >
          Виж всички
          <ArrowRight className="size-4" />
        </Link>
      </div>

      {categories.length === 0 ? (
        <p className="text-sm text-muted-foreground">Все още няма категории с инструменти.</p>
      ) : (
        <ul className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
          {categories.map((category, index) => {
            const Icon = CATEGORY_ICONS[category.slug] ?? Folder;

            return (
              <li key={category.id}>
                <Link
                  href={`/tools?${new URLSearchParams({ category: category.slug })}`}
                  className="group block h-full rounded-xl outline-none focus-visible:ring-2 focus-visible:ring-ring"
                >
                  <Card className="h-full items-center gap-2 bg-indigo-50/60 px-3 text-center shadow-sm transition group-hover:-translate-y-0.5 group-hover:shadow-md">
                    <Icon className={cn("size-8", ICON_COLORS[index % ICON_COLORS.length])} />
                    <span className="text-sm font-semibold text-slate-800">{category.name}</span>
                    <span className="text-sm text-muted-foreground tabular-nums">
                      ({category.tools_count})
                    </span>
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
