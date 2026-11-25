"use client";

import Link from "next/link";
import { usePathname, useRouter, useSearchParams } from "next/navigation";
import { useCallback, useEffect, useRef, useState } from "react";
import * as api from "@/lib/api";
import type { Category, CompanyRole, Paginated, Tag, Tool } from "@/lib/api";
import ToolCard from "./ToolCard";
import {
  inputClassName,
  mutedTextClassName,
  primaryButtonClassName,
  secondaryButtonClassName,
} from "./styles";

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
export default function ToolList() {
  const searchParams = useSearchParams();
  const router = useRouter();
  const pathname = usePathname();
  const query = searchParams.toString();

  const urlSearch = searchParams.get("search") ?? "";
  const companyRole = searchParams.get("company_role") ?? "";
  const category = searchParams.get("category") ?? "";
  const tag = searchParams.get("tag") ?? "";
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

  const updateQuery = useCallback(
    (changes: Record<string, string | null>) => {
      const params = new URLSearchParams(window.location.search);

      for (const [key, value] of Object.entries(changes)) {
        if (value) {
          params.set(key, value);
        } else {
          params.delete(key);
        }
      }

      const next = params.toString();
      router.replace(next ? `${pathname}?${next}` : pathname, { scroll: false });
    },
    [router, pathname],
  );

  // A filter change always starts again from the first page.
  const setFilter = (key: string, value: string) => updateQuery({ [key]: value || null, page: null });

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
        { search: urlSearch, company_role: companyRole, category, tag, page },
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
  }, [query, urlSearch, companyRole, category, tag, page]);

  const isLoading = tools === null || tools.query !== query;
  const hasFilters = Boolean(urlSearch || companyRole || category || tag);

  // A tag reached through a link may not be among the suggested tags.
  const tagOptions = tag && !options.tags.some((option) => option.slug === tag)
    ? [...options.tags, { id: 0, name: tag, slug: tag }]
    : options.tags;

  return (
    <section className="flex flex-col gap-6">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <h1 className="text-3xl font-semibold tracking-tight">AI инструменти</h1>
        <Link href="/tools/new" className={primaryButtonClassName}>
          + Добави инструмент
        </Link>
      </div>

      <form
        role="search"
        onSubmit={(event) => event.preventDefault()}
        className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4"
      >
        <label className="flex flex-col gap-1 text-sm font-medium">
          Търсене по име
          <input
            type="search"
            value={search}
            onChange={(event) => handleSearchChange(event.target.value)}
            placeholder="напр. ChatGPT"
            className={inputClassName}
          />
        </label>

        <FilterSelect
          label="Роля във фирмата"
          value={companyRole}
          onChange={(value) => setFilter("company_role", value)}
          options={options.companyRoles.map((role) => ({ value: role.slug, label: role.name }))}
        />
        <FilterSelect
          label="Категория"
          value={category}
          onChange={(value) => setFilter("category", value)}
          options={options.categories.map((option) => ({ value: option.slug, label: option.name }))}
        />
        <FilterSelect
          label="Таг"
          value={tag}
          onChange={(value) => setFilter("tag", value)}
          options={tagOptions.map((option) => ({ value: option.slug, label: option.name }))}
        />
      </form>

      {hasFilters && (
        <button
          type="button"
          onClick={() => {
            window.clearTimeout(searchTimeout.current);
            router.replace(pathname, { scroll: false });
          }}
          className="self-start text-sm text-indigo-600 hover:underline"
        >
          Изчисти филтрите
        </button>
      )}

      {isLoading ? (
        <p className={mutedTextClassName}>Зареждане…</p>
      ) : tools.status === "error" ? (
        <p role="alert" className="text-red-600 dark:text-red-400">
          Инструментите не можаха да се заредят. Опитай отново по-късно.
        </p>
      ) : tools.result.data.length === 0 ? (
        <p className={mutedTextClassName}>
          {hasFilters
            ? "Няма инструменти, които отговарят на филтрите."
            : "Все още няма добавени инструменти."}
        </p>
      ) : (
        <>
          <ul className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {tools.result.data.map((tool) => (
              <li key={tool.id}>
                <ToolCard tool={tool} />
              </li>
            ))}
          </ul>

          <Pagination
            page={tools.result.meta.current_page}
            lastPage={tools.result.meta.last_page}
            total={tools.result.meta.total}
            onChange={(next) => updateQuery({ page: next > 1 ? String(next) : null })}
          />
        </>
      )}
    </section>
  );
}

function FilterSelect({
  label,
  value,
  onChange,
  options,
}: {
  label: string;
  value: string;
  onChange: (value: string) => void;
  options: { value: string; label: string }[];
}) {
  return (
    <label className="flex flex-col gap-1 text-sm font-medium">
      {label}
      <select value={value} onChange={(event) => onChange(event.target.value)} className={inputClassName}>
        <option value="">Всички</option>
        {options.map((option) => (
          <option key={option.value} value={option.value}>
            {option.label}
          </option>
        ))}
      </select>
    </label>
  );
}

function Pagination({
  page,
  lastPage,
  total,
  onChange,
}: {
  page: number;
  lastPage: number;
  total: number;
  onChange: (page: number) => void;
}) {
  return (
    <nav aria-label="Страници" className="flex items-center justify-between gap-4 text-sm">
      <button
        type="button"
        onClick={() => onChange(page - 1)}
        disabled={page <= 1}
        className={secondaryButtonClassName}
      >
        ← Предишна
      </button>
      <span className={mutedTextClassName}>
        Страница {page} от {lastPage} · {total} инструмента
      </span>
      <button
        type="button"
        onClick={() => onChange(page + 1)}
        disabled={page >= lastPage}
        className={secondaryButtonClassName}
      >
        Следваща →
      </button>
    </nav>
  );
}
