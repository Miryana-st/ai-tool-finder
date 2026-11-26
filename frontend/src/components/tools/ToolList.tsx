"use client";

import { ChevronDown, Heart, LayoutGrid, Plus, RotateCcw, Search, X } from "lucide-react";
import Link from "next/link";
import { usePathname, useRouter, useSearchParams } from "next/navigation";
import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { buttonVariants } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import Pagination from "@/components/Pagination";
import * as api from "@/lib/api";
import type { Category, CompanyRole, Paginated, Tag, Tool } from "@/lib/api";
import { cn } from "@/lib/utils";
import TagFilter from "./TagFilter";
import ToolCard from "./ToolCard";
import { mutedTextClassName } from "@/styles/classNames";

// The backend accepts at most this many tags in one filter (IndexToolRequest::MAX_TAGS).
const MAX_TAGS = 10;

const filterFieldClassName =
  "h-11 w-full rounded-xl border border-input bg-card text-sm font-medium shadow-xs outline-none transition-colors focus-visible:border-violet-500 focus-visible:ring-3 focus-visible:ring-violet-500/20";

const SKELETON_CARDS = 6;

const SEARCH_DELAY = 300;

type ToolsState =
  | { query: string; status: "error" }
  | { query: string; status: "ready"; result: Paginated<Tool> };

type FilterOptions = {
  companyRoles: CompanyRole[];
  categories: Category[];
  tags: Tag[];
};

// The filters live in the URL query, so a filtered view can be shared as a link.
// With `favoritesOnly`, only the signed-in user's favorite tools are listed (the favorites page).
export default function ToolList({ favoritesOnly = false }: { favoritesOnly?: boolean }) {
  const searchParams = useSearchParams();
  const router = useRouter();
  const pathname = usePathname();
  const query = searchParams.toString();

  const urlSearch = searchParams.get("search") ?? "";
  const companyRole = searchParams.get("company_role") ?? "";
  const category = searchParams.get("category") ?? "";
  // Each picked tag is a separate `tag` parameter: ?tag=llm&tag=images.
  const tags = useMemo(() => [...new Set(searchParams.getAll("tag").filter(Boolean))], [searchParams]);
  const page = Math.max(1, Number(searchParams.get("page")) || 1);

  const [search, setSearch] = useState(urlSearch);
  const [syncedSearch, setSyncedSearch] = useState(urlSearch);
  const searchTimeout = useRef<number | undefined>(undefined);

  // Keep the input in step when the URL changes from outside (back button, tag link).
  if (urlSearch !== syncedSearch) {
    setSyncedSearch(urlSearch);
    setSearch(urlSearch);
  }

  const [tools, setTools] = useState<ToolsState | null>(null);
  const [options, setOptions] = useState<FilterOptions>({
    companyRoles: [],
    categories: [],
    tags: [],
  });
  // Names of the tags picked in this visit, so their chips do not show only the slug.
  const [pickedTagNames, setPickedTagNames] = useState<Record<string, string>>({});

  const updateQuery = useCallback(
    (changes: Record<string, string | string[] | null>) => {
      const params = new URLSearchParams(window.location.search);

      for (const [key, value] of Object.entries(changes)) {
        params.delete(key);
        if (Array.isArray(value)) {
          value.forEach((item) => params.append(key, item));
        } else if (value) {
          params.set(key, value);
        }
      }

      const next = params.toString();
      router.replace(next ? `${pathname}?${next}` : pathname, { scroll: false });
    },
    [router, pathname],
  );

  // A filter change always starts again from the first page.
  const setFilter = (key: string, value: string) => updateQuery({ [key]: value || null, page: null });

  const addTag = (tag: Tag) => {
    if (tags.includes(tag.slug) || tags.length >= MAX_TAGS) {
      return;
    }
    setPickedTagNames((names) => ({ ...names, [tag.slug]: tag.name }));
    updateQuery({ tag: [...tags, tag.slug], page: null });
  };

  const removeTag = (slug: string) => updateQuery({ tag: tags.filter((picked) => picked !== slug), page: null });

  const tagName = (slug: string) =>
    pickedTagNames[slug] ?? options.tags.find((option) => option.slug === slug)?.name ?? slug;

  const handleSearchChange = (value: string) => {
    setSearch(value);
    window.clearTimeout(searchTimeout.current);
    searchTimeout.current = window.setTimeout(
      () => updateQuery({ search: value.trim() || null, page: null }),
      SEARCH_DELAY,
    );
  };

  useEffect(() => () => window.clearTimeout(searchTimeout.current), []);

  useEffect(() => {
    const controller = new AbortController();

    Promise.all([
      api.getCompanyRoles(controller.signal),
      api.getCategories(controller.signal),
      api.getTags("", controller.signal),
    ])
      .then(([companyRoles, categories, tags]) => setOptions({ companyRoles, categories, tags }))
      .catch((error: unknown) => {
        if (!api.isAbortError(error)) {
          console.error("Could not load the tool filters.", error);
        }
      });

    return () => controller.abort();
  }, []);

  useEffect(() => {
    const controller = new AbortController();

    api
      .getTools(
        {
          search: urlSearch,
          company_role: companyRole,
          category,
          tags,
          favorites: favoritesOnly ? 1 : undefined,
          page,
        },
        controller.signal,
      )
      .then((result) => setTools({ query, status: "ready", result }))
      .catch((error: unknown) => {
        if (api.isAbortError(error)) {
          return;
        }
        console.error("Could not load the tools.", error);
        setTools({ query, status: "error" });
      });

    return () => controller.abort();
  }, [query, urlSearch, companyRole, category, tags, favoritesOnly, page]);

  const isLoading = tools === null || tools.query !== query;
  const hasFilters = Boolean(urlSearch || companyRole || category || tags.length > 0);

  return (
    <section className="mx-auto flex w-full max-w-7xl flex-col gap-6">
      <header className="flex flex-wrap items-center justify-between gap-4">
        <div className="flex items-center gap-4">
          <span className="flex size-14 shrink-0 items-center justify-center rounded-2xl bg-violet-600 text-white shadow-md shadow-violet-600/30">
            {favoritesOnly ? (
              <Heart className="size-7" strokeWidth={2.25} aria-hidden="true" />
            ) : (
              <LayoutGrid className="size-7" strokeWidth={2.25} aria-hidden="true" />
            )}
          </span>
          <div className="flex flex-col gap-1">
            <h1 className="text-3xl font-bold tracking-tight">
              {favoritesOnly ? "Любими инструменти" : "AI инструменти"}
            </h1>
            <p className="text-muted-foreground">
              {favoritesOnly
                ? "Инструментите, които си отбелязал като любими."
                : "Разгледай подбраните AI инструменти на екипа."}
            </p>
          </div>
        </div>

        <Link
          href="/tools/new"
          className={cn(
            buttonVariants(),
            "h-11 rounded-xl bg-violet-600 px-5 text-white shadow-md shadow-violet-600/20 hover:bg-violet-700",
          )}
        >
          <Plus className="size-5" aria-hidden="true" />
          Добави инструмент
        </Link>
      </header>

      <form
        role="search"
        onSubmit={(event) => event.preventDefault()}
        className="flex flex-col gap-3"
      >
        <label className="relative">
          <span className="sr-only">Търсене по име</span>
          <Search
            className="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-muted-foreground"
            aria-hidden="true"
          />
          <input
            type="search"
            value={search}
            onChange={(event) => handleSearchChange(event.target.value)}
            placeholder="Търси по име…"
            className={cn(filterFieldClassName, "pr-3.5 pl-10 font-normal placeholder:text-muted-foreground")}
          />
        </label>

        <div className="flex flex-col gap-3 lg:flex-row lg:items-center">
          <div className="grid flex-1 grid-cols-1 gap-3 sm:grid-cols-3">
            <TagFilter
              picked={tags}
              initialTags={options.tags}
              onPick={addTag}
              disabled={tags.length >= MAX_TAGS}
            />
            <FilterSelect
              label="Роля във фирмата"
              allLabel="Всички роли"
              value={companyRole}
              onChange={(value) => setFilter("company_role", value)}
              options={options.companyRoles.map((role) => ({ value: role.slug, label: role.name }))}
            />
            <FilterSelect
              label="Категория"
              allLabel="Всички категории"
              value={category}
              onChange={(value) => setFilter("category", value)}
              options={options.categories.map((option) => ({ value: option.slug, label: option.name }))}
            />
          </div>

          {hasFilters && (
            <button
              type="button"
              onClick={() => {
                window.clearTimeout(searchTimeout.current);
                router.replace(pathname, { scroll: false });
              }}
              className="inline-flex shrink-0 items-center gap-1.5 self-start rounded-lg px-2 py-2 text-sm font-medium text-violet-700 hover:bg-violet-50 lg:self-auto dark:text-violet-300 dark:hover:bg-violet-500/10"
            >
              <RotateCcw className="size-4" aria-hidden="true" />
              Изчисти филтрите
            </button>
          )}
        </div>
      </form>

      {tags.length > 0 && (
        <ul aria-label="Избрани тагове" className="-mt-3 flex flex-wrap items-center gap-2">
          {tags.map((slug) => (
            <li
              key={slug}
              className="inline-flex items-center gap-1 rounded-full bg-violet-100 py-1 pr-1 pl-3 text-sm font-medium text-violet-700 dark:bg-violet-500/15 dark:text-violet-300"
            >
              #{tagName(slug)}
              <button
                type="button"
                onClick={() => removeTag(slug)}
                aria-label={`Премахни таг ${tagName(slug)}`}
                className="flex size-5 items-center justify-center rounded-full hover:bg-violet-200 dark:hover:bg-violet-500/25"
              >
                <X className="size-3.5" aria-hidden="true" />
              </button>
            </li>
          ))}
        </ul>
      )}

      {isLoading ? (
        <ul aria-label="Зареждане" className="grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-3">
          {Array.from({ length: SKELETON_CARDS }, (_, index) => (
            <li key={index} className="flex flex-col gap-4 rounded-2xl border bg-card p-5 shadow-sm">
              <Skeleton className="size-16 rounded-2xl" />
              <Skeleton className="h-5 w-1/2" />
              <Skeleton className="h-10 w-full" />
              <Skeleton className="h-8 w-full" />
            </li>
          ))}
        </ul>
      ) : tools.status === "error" ? (
        <p role="alert" className="text-red-600 dark:text-red-400">
          Инструментите не можаха да се заредят. Опитай отново по-късно.
        </p>
      ) : tools.result.data.length === 0 ? (
        <p className={cn("rounded-2xl border border-dashed bg-card p-10 text-center", mutedTextClassName)}>
          {hasFilters
            ? "Няма инструменти, които отговарят на филтрите."
            : favoritesOnly
              ? "Все още нямаш любими инструменти. Добави ги от страницата на инструмента."
              : "Все още няма добавени инструменти."}
        </p>
      ) : (
        <>
          <ul className="grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-3">
            {tools.result.data.map((tool) => (
              <li key={tool.id}>
                <ToolCard tool={tool} />
              </li>
            ))}
          </ul>

          <Pagination
            page={tools.result.meta.current_page}
            lastPage={tools.result.meta.last_page}
            summary={`${tools.result.meta.total} инструмента`}
            onChange={(next) => updateQuery({ page: next > 1 ? String(next) : null })}
          />
        </>
      )}
    </section>
  );
}

function FilterSelect({
  label,
  allLabel,
  value,
  onChange,
  options,
}: {
  label: string;
  allLabel: string;
  value: string;
  onChange: (value: string) => void;
  options: { value: string; label: string }[];
}) {
  return (
    <label className="relative">
      <span className="sr-only">{label}</span>
      <select
        value={value}
        onChange={(event) => onChange(event.target.value)}
        className={cn(filterFieldClassName, "cursor-pointer appearance-none truncate pr-9 pl-3.5")}
      >
        <option value="">{allLabel}</option>
        {options.map((option) => (
          <option key={option.value} value={option.value}>
            {option.label}
          </option>
        ))}
      </select>
      <ChevronDown
        className="pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 text-muted-foreground"
        aria-hidden="true"
      />
    </label>
  );
}
