"use client";

import { usePathname, useRouter, useSearchParams } from "next/navigation";
import { useCallback, useEffect, useRef, useState } from "react";
import AdminPagination from "@/components/admin/AdminPagination";
import * as api from "@/lib/api";
import type { ActivityEntry, ActivityEventGroup, Paginated } from "@/lib/api";
import { inputClassName, mutedTextClassName } from "@/styles/classNames";

const SEARCH_DELAY = 300;

const dateFormat = new Intl.DateTimeFormat("bg-BG", { day: "numeric", month: "short", year: "numeric" });
const timeFormat = new Intl.DateTimeFormat("bg-BG", { hour: "2-digit", minute: "2-digit" });
const fullDateFormat = new Intl.DateTimeFormat("bg-BG", { dateStyle: "full", timeStyle: "medium" });

// The backend sends field names; these are the names the admin knows from the forms.
const FIELD_LABELS: Record<string, string> = {
  name: "Име",
  slug: "Адрес (slug)",
  description: "Описание",
  url: "Сайт",
  documentation_url: "Документация",
  how_to_use: "Как се използва",
  examples: "Примери",
  categories: "Категории",
  company_roles: "Подходящ за",
  tags: "Тагове",
};

const SUBJECT_LABELS: Record<string, string> = {
  tool: "Инструмент",
  category: "Категория",
  tag: "Таг",
};

type EntriesState =
  | { query: string; status: "error" }
  | { query: string; status: "ready"; result: Paginated<ActivityEntry> };

// The admin's history of what users did. The filters live in the URL query, like the user list.
export default function ActivityLog() {
  const searchParams = useSearchParams();
  const router = useRouter();
  const pathname = usePathname();
  const query = searchParams.toString();

  const urlSearch = searchParams.get("search") ?? "";
  const group = searchParams.get("group") ?? "";
  const event = searchParams.get("event") ?? "";
  const from = searchParams.get("from") ?? "";
  const to = searchParams.get("to") ?? "";
  const page = positiveNumber(searchParams.get("page")) ?? 1;

  const [search, setSearch] = useState(urlSearch);
  const [syncedSearch, setSyncedSearch] = useState(urlSearch);
  const searchTimeout = useRef<number | undefined>(undefined);

  // Keep the input in step when the URL changes from outside (back button).
  if (urlSearch !== syncedSearch) {
    setSyncedSearch(urlSearch);
    setSearch(urlSearch);
  }

  const [entries, setEntries] = useState<EntriesState | null>(null);
  const [eventGroups, setEventGroups] = useState<ActivityEventGroup[]>([]);

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
  const setFilters = (changes: Record<string, string | null>) => updateQuery({ ...changes, page: null });

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

    api
      .getActivityEvents(controller.signal)
      .then(setEventGroups)
      .catch((error: unknown) => {
        if (!api.isAbortError(error)) {
          console.error("Could not load the activity events.", error);
        }
      });

    return () => controller.abort();
  }, []);

  useEffect(() => {
    const controller = new AbortController();

    api
      .getActivityLog(
        {
          search: urlSearch,
          group,
          event,
          created_from: startOfLocalDay(from),
          created_before: startOfLocalDay(to, 1),
          page,
        },
        controller.signal,
      )
      .then((result) => setEntries({ query, status: "ready", result }))
      .catch((error: unknown) => {
        if (api.isAbortError(error)) {
          return;
        }
        console.error("Could not load the activity history.", error);
        setEntries({ query, status: "error" });
      });

    return () => controller.abort();
  }, [query, urlSearch, group, event, from, to, page]);

  const isLoading = entries === null || entries.query !== query;
  const hasFilters = Boolean(urlSearch || group || event || from || to);
  const eventOptions = group ? eventGroups.filter((item) => item.value === group) : eventGroups;

  return (
    <section className="flex flex-col gap-6">
      <div className="flex flex-col gap-1">
        <h1 className="text-3xl font-semibold tracking-tight">История на активността</h1>
        <p className={mutedTextClassName}>Кой потребител какво е направил, кога и какво се е променило.</p>
      </div>

      <form
        role="search"
        onSubmit={(formEvent) => formEvent.preventDefault()}
        className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5"
      >
        <label className="flex flex-col gap-1 text-sm font-medium lg:col-span-1">
          Търси
          <input
            type="search"
            value={search}
            onChange={(changeEvent) => handleSearchChange(changeEvent.target.value)}
            placeholder="Име, инструмент, таг…"
            className={inputClassName}
          />
        </label>

        <label className="flex flex-col gap-1 text-sm font-medium">
          Област
          <select
            value={group}
            onChange={(changeEvent) => {
              const nextGroup = changeEvent.target.value;
              // An event of another group would leave the list empty.
              const keepEvent = !nextGroup || event.startsWith(`${nextGroup}.`);
              setFilters({ group: nextGroup || null, event: keepEvent ? event || null : null });
            }}
            className={inputClassName}
          >
            <option value="">Всички</option>
            {eventGroups.map((item) => (
              <option key={item.value} value={item.value}>
                {item.label}
              </option>
            ))}
          </select>
        </label>

        <label className="flex flex-col gap-1 text-sm font-medium">
          Действие
          <select
            value={event}
            onChange={(changeEvent) => setFilters({ event: changeEvent.target.value || null })}
            className={inputClassName}
          >
            <option value="">Всички</option>
            {eventOptions.map((item) => (
              <optgroup key={item.value} label={item.label}>
                {item.events.map((option) => (
                  <option key={option.value} value={option.value}>
                    {option.label}
                  </option>
                ))}
              </optgroup>
            ))}
          </select>
        </label>

        <label className="flex flex-col gap-1 text-sm font-medium">
          От дата
          <input
            type="date"
            value={from}
            max={to || undefined}
            onChange={(changeEvent) => setFilters({ from: changeEvent.target.value || null })}
            className={inputClassName}
          />
        </label>

        <label className="flex flex-col gap-1 text-sm font-medium">
          До дата
          <input
            type="date"
            value={to}
            min={from || undefined}
            onChange={(changeEvent) => setFilters({ to: changeEvent.target.value || null })}
            className={inputClassName}
          />
        </label>
      </form>

      {hasFilters && (
        <div className="flex flex-wrap items-center gap-2">
          <button
            type="button"
            onClick={() => {
              window.clearTimeout(searchTimeout.current);
              router.replace(pathname, { scroll: false });
            }}
            className="text-sm text-indigo-600 hover:underline"
          >
            Изчисти филтрите
          </button>
        </div>
      )}

      {isLoading ? (
        <p className={mutedTextClassName}>Зареждане…</p>
      ) : entries.status === "error" ? (
        <p role="alert" className="text-red-600 dark:text-red-400">
          Историята не можа да се зареди. Опитай отново по-късно.
        </p>
      ) : entries.result.data.length === 0 ? (
        <p className={mutedTextClassName}>
          {hasFilters ? "Няма действия, които отговарят на филтрите." : "Все още няма записани действия."}
        </p>
      ) : (
        <>
          {/* Phones get a card per entry; the table needs more width. */}
          <ul className="flex flex-col gap-3 md:hidden">
            {entries.result.data.map((entry) => (
              <li
                key={entry.id}
                className="flex flex-col gap-2 rounded-lg border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-950"
              >
                <div className="flex flex-wrap items-center justify-between gap-2">
                  <EventBadge entry={entry} />
                  <time dateTime={entry.created_at} className={`text-xs ${mutedTextClassName}`}>
                    {dateFormat.format(new Date(entry.created_at))}, {timeFormat.format(new Date(entry.created_at))}
                  </time>
                </div>
                <p className="text-sm">
                  <UserName entry={entry} />
                  {entry.subject && (
                    <>
                      {" → "}
                      <span className="font-medium">{entry.subject.label}</span>
                    </>
                  )}
                </p>
                {summary(entry) && <p className={`text-sm ${mutedTextClassName}`}>{summary(entry)}</p>}
              </li>
            ))}
          </ul>

          <div className="hidden overflow-x-auto rounded-lg border border-zinc-200 shadow-sm md:block dark:border-zinc-800">
            <table className="w-full text-left text-sm">
              <thead className="border-b-2 border-violet-200 bg-violet-50 text-xs tracking-wide text-violet-900 uppercase dark:border-violet-500/30 dark:bg-violet-500/10 dark:text-violet-200">
                <tr>
                  <th scope="col" className="px-4 py-3 font-semibold">Кога</th>
                  <th scope="col" className="px-4 py-3 font-semibold">Потребител</th>
                  <th scope="col" className="px-4 py-3 font-semibold">Действие</th>
                  <th scope="col" className="px-4 py-3 font-semibold">Обект</th>
                  <th scope="col" className="px-4 py-3 font-semibold">Промени</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-zinc-200 bg-white dark:divide-zinc-800 dark:bg-zinc-950">
                {entries.result.data.map((entry) => (
                  <tr key={entry.id} className="align-top">
                    <td className="px-4 py-3 whitespace-nowrap">
                      <time dateTime={entry.created_at} title={fullDateFormat.format(new Date(entry.created_at))}>
                        <span className="block">{dateFormat.format(new Date(entry.created_at))}</span>
                        <span className={`block text-xs ${mutedTextClassName}`}>
                          {timeFormat.format(new Date(entry.created_at))}
                        </span>
                      </time>
                    </td>
                    <td className="px-4 py-3">
                      <UserName entry={entry} />
                    </td>
                    <td className="px-4 py-3">
                      <EventBadge entry={entry} />
                    </td>
                    <td className="max-w-56 px-4 py-3">
                      {entry.subject ? (
                        <>
                          <span className={`block text-xs ${mutedTextClassName}`}>
                            {SUBJECT_LABELS[entry.subject.type] ?? entry.subject.type}
                          </span>
                          <span className="block truncate" title={entry.subject.label ?? undefined}>
                            {entry.subject.label}
                          </span>
                        </>
                      ) : (
                        <span className={mutedTextClassName}>—</span>
                      )}
                    </td>
                    <td className={`max-w-64 px-4 py-3 ${mutedTextClassName}`}>
                      <span className="line-clamp-2">{summary(entry) ?? "—"}</span>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          <AdminPagination
            page={entries.result.meta.current_page}
            lastPage={entries.result.meta.last_page}
            total={entries.result.meta.total}
            itemsLabel={entries.result.meta.total === 1 ? "действие" : "действия"}
            onChange={(next) => updateQuery({ page: next > 1 ? String(next) : null })}
          />
        </>
      )}
    </section>
  );
}

function UserName({ entry }: { entry: ActivityEntry }) {
  if (entry.user === null) {
    return <span className={mutedTextClassName}>Неизвестен</span>;
  }

  return (
    <span className="font-medium">
      {entry.user.name}
      {entry.user.id === null && <span className={`ml-1 text-xs font-normal ${mutedTextClassName}`}>(изтрит)</span>}
    </span>
  );
}

function EventBadge({ entry }: { entry: ActivityEntry }) {
  return (
    <span
      className={`inline-flex w-fit items-center rounded-full px-2.5 py-0.5 text-xs font-medium whitespace-nowrap ${eventTone(entry.event)}`}
    >
      {entry.event_label}
    </span>
  );
}

// The part after the group, e.g. "updated" of "tool.updated".
function actionOf(event: string): string {
  return event.split(".")[1] ?? "";
}

// Green for what was added, red for what was removed, blue for changes.
function eventTone(event: string): string {
  const action = actionOf(event);

  if (["deleted", "rejected", "image_deleted"].includes(action)) {
    return "bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300";
  }
  if (["created", "approved", "image_added"].includes(action)) {
    return "bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300";
  }
  return "bg-sky-100 text-sky-800 dark:bg-sky-500/15 dark:text-sky-300";
}

// A short line for the list: which fields changed, or the main detail of the action.
function summary(entry: ActivityEntry): string | null {
  const { image, approved } = entry.properties;
  const action = actionOf(entry.event);

  if (typeof image === "string") {
    return `Снимка: ${image}`;
  }
  if (action === "created") {
    return approved === false ? "Чака одобрение" : null;
  }
  if (action === "deleted") {
    return null;
  }

  const fields = Object.keys(entry.changes);
  return fields.length > 0 ? fields.map((field) => FIELD_LABELS[field] ?? field).join(", ") : null;
}

function positiveNumber(value: string | null): number | undefined {
  const number = Number(value);
  return Number.isInteger(number) && number > 0 ? number : undefined;
}

// The start of the chosen day (plus `addDays`) in the admin's time zone, as an ISO time.
function startOfLocalDay(date: string, addDays = 0): string | undefined {
  const [year, month, day] = date.split("-").map(Number);

  if (!year || !month || !day) {
    return undefined;
  }

  return new Date(year, month - 1, day + addDays).toISOString();
}
