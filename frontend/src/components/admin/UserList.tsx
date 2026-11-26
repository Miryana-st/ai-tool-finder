"use client";

import Link from "next/link";
import { usePathname, useRouter, useSearchParams } from "next/navigation";
import { useCallback, useEffect, useRef, useState } from "react";
import { useAuth } from "@/context/AuthContext";
import AdminPagination from "@/components/admin/AdminPagination";
import * as api from "@/lib/api";
import { ApiError, type CompanyRole, type Paginated, type User } from "@/lib/api";
import {
  dangerButtonClassName,
  inputClassName,
  mutedTextClassName,
  primaryButtonClassName,
  secondaryButtonClassName,
} from "@/styles/classNames";

const SEARCH_DELAY = 300;

type UsersState =
  | { query: string; status: "error" }
  | { query: string; status: "ready"; result: Paginated<User> };

type Message = { type: "success" | "error"; text: string };

// Admin list of users. The filters live in the URL query, like the tool list.
export default function UserList() {
  const { user: currentUser, refreshUser } = useAuth();
  const searchParams = useSearchParams();
  const router = useRouter();
  const pathname = usePathname();
  const query = searchParams.toString();

  const urlSearch = searchParams.get("search") ?? "";
  const systemRole = searchParams.get("system_role") ?? "";
  const companyRole = searchParams.get("company_role") ?? "";
  const page = Math.max(1, Number(searchParams.get("page")) || 1);

  const [search, setSearch] = useState(urlSearch);
  const [syncedSearch, setSyncedSearch] = useState(urlSearch);
  const searchTimeout = useRef<number | undefined>(undefined);

  // Keep the input in step when the URL changes from outside (back button).
  if (urlSearch !== syncedSearch) {
    setSyncedSearch(urlSearch);
    setSearch(urlSearch);
  }

  const [users, setUsers] = useState<UsersState | null>(null);
  const [companyRoles, setCompanyRoles] = useState<CompanyRole[]>([]);
  const [busyId, setBusyId] = useState<number | null>(null);
  const [message, setMessage] = useState<Message | null>(null);
  // Bumped after a delete, so the page is loaded again (totals and the next user move up).
  const [reloadKey, setReloadKey] = useState(0);

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

    api
      .getCompanyRoles(controller.signal)
      .then(setCompanyRoles)
      .catch((error: unknown) => {
        if (!api.isAbortError(error)) {
          console.error("Could not load the company roles.", error);
        }
      });

    return () => controller.abort();
  }, []);

  useEffect(() => {
    const controller = new AbortController();

    api
      .getUsers(
        {
          search: urlSearch,
          system_role: systemRole === "user" || systemRole === "admin" ? systemRole : undefined,
          company_role: companyRole,
          page,
        },
        controller.signal,
      )
      .then((result) => setUsers({ query, status: "ready", result }))
      .catch((error: unknown) => {
        if (api.isAbortError(error)) {
          return;
        }
        console.error("Could not load the users.", error);
        setUsers({ query, status: "error" });
      });

    return () => controller.abort();
  }, [query, urlSearch, systemRole, companyRole, page, reloadKey]);

  const replaceUser = (updated: User) =>
    setUsers((current) =>
      current?.status === "ready"
        ? {
            ...current,
            result: {
              ...current.result,
              data: current.result.data.map((user) => (user.id === updated.id ? updated : user)),
            },
          }
        : current,
    );

  const toggleAdmin = async (user: User) => {
    const makeAdmin = user.system_role !== "admin";
    const isSelf = user.id === currentUser?.id;

    const question = makeAdmin
      ? `Да направя ли ${user.name} администратор?`
      : isSelf
        ? "Да махна ли твоята admin роля? Ще загубиш достъп до админ страниците."
        : `Да махна ли admin ролята на ${user.name}?`;

    if (!window.confirm(question)) {
      return;
    }

    setBusyId(user.id);
    setMessage(null);

    try {
      const updated = await api.updateUserSystemRole(user.id, makeAdmin ? "admin" : "user");

      // This page is for admins only. Loading the user again also hides the admin menu.
      if (isSelf && !makeAdmin) {
        await refreshUser();
        router.push("/dashboard");
        return;
      }

      replaceUser(updated);
      setMessage({
        type: "success",
        text: makeAdmin
          ? `${updated.name} вече е администратор.`
          : `${updated.name} вече не е администратор.`,
      });
    } catch (error) {
      if (error instanceof ApiError && error.status === 422) {
        setMessage({ type: "error", text: api.fieldError(error.errors, "system_role") ?? error.message });
      } else {
        console.error("Could not change the system role.", error);
        setMessage({ type: "error", text: "Действието не беше успешно. Опитай отново." });
      }
    } finally {
      setBusyId(null);
    }
  };

  const deleteUser = async (user: User) => {
    if (!window.confirm(`Да изтрия ли акаунта на ${user.name} (${user.email})? Това действие е необратимо.`)) {
      return;
    }

    setBusyId(user.id);
    setMessage(null);

    try {
      await api.deleteUser(user.id);
      setMessage({ type: "success", text: `Акаунтът на ${user.name} е изтрит.` });
      setReloadKey((key) => key + 1);
    } catch (error) {
      if (error instanceof ApiError && error.status === 409) {
        setMessage({
          type: "error",
          text: `${user.name} е добавил AI инструменти, затова акаунтът не може да се изтрие.`,
        });
      } else if (error instanceof ApiError && error.status === 404) {
        setMessage({ type: "error", text: `Акаунтът на ${user.name} вече е изтрит.` });
        setReloadKey((key) => key + 1);
      } else {
        console.error("Could not delete the user.", error);
        setMessage({ type: "error", text: "Действието не беше успешно. Опитай отново." });
      }
    } finally {
      setBusyId(null);
    }
  };

  const resetTwoFactor = async (user: User) => {
    if (
      !window.confirm(
        `Да изключа ли двуфакторната защита на ${user.name}? Ще може да влезе само с парола и да я включи отново от профила си.`,
      )
    ) {
      return;
    }

    setBusyId(user.id);
    setMessage(null);

    try {
      replaceUser(await api.resetUserTwoFactor(user.id));
      setMessage({ type: "success", text: `Двуфакторната защита на ${user.name} е изключена.` });
    } catch (error) {
      if (error instanceof ApiError && error.status === 404) {
        setMessage({ type: "error", text: `Акаунтът на ${user.name} вече е изтрит.` });
        setReloadKey((key) => key + 1);
      } else {
        console.error("Could not turn off two-factor authentication.", error);
        setMessage({ type: "error", text: "Действието не беше успешно. Опитай отново." });
      }
    } finally {
      setBusyId(null);
    }
  };

  const renderActions = (user: User) => (
    <>
      <button
        type="button"
        onClick={() => void toggleAdmin(user)}
        disabled={busyId !== null}
        className={secondaryButtonClassName}
      >
        {user.system_role === "admin" ? "Махни admin" : "Направи admin"}
      </button>
      {/* Admins turn off their own two-factor from the profile (the backend refuses here). */}
      {user.two_factor_enabled && user.id !== currentUser?.id && (
        <button
          type="button"
          onClick={() => void resetTwoFactor(user)}
          disabled={busyId !== null}
          className={secondaryButtonClassName}
        >
          Изключи 2FA
        </button>
      )}
      {/* Admins cannot delete their own account (the backend refuses too). */}
      {user.id !== currentUser?.id && (
        <button
          type="button"
          onClick={() => void deleteUser(user)}
          disabled={busyId !== null}
          className={dangerButtonClassName}
        >
          Изтрий
        </button>
      )}
    </>
  );

  const isLoading = users === null || users.query !== query;
  const hasFilters = Boolean(urlSearch || systemRole || companyRole);

  return (
    <section className="flex flex-col gap-6">
      <div className="flex flex-wrap items-start justify-between gap-4">
        <div className="flex flex-col gap-1">
          <h1 className="text-3xl font-semibold tracking-tight">Потребители</h1>
          <p className={mutedTextClassName}>
            Администраторите управляват ролите и потребителите. Поне един администратор винаги остава.
          </p>
        </div>
        <Link href="/admin/users/new" className={primaryButtonClassName}>
          + Добави потребител
        </Link>
      </div>

      <form
        role="search"
        onSubmit={(event) => event.preventDefault()}
        className="grid grid-cols-1 gap-3 sm:grid-cols-3"
      >
        <label className="flex flex-col gap-1 text-sm font-medium">
          Търсене по име или email
          <input
            type="search"
            value={search}
            onChange={(event) => handleSearchChange(event.target.value)}
            className={inputClassName}
          />
        </label>

        <FilterSelect
          label="Системна роля"
          value={systemRole}
          onChange={(value) => setFilter("system_role", value)}
          options={[
            { value: "user", label: "Потребител" },
            { value: "admin", label: "Администратор" },
          ]}
        />
        <FilterSelect
          label="Роля във фирмата"
          value={companyRole}
          onChange={(value) => setFilter("company_role", value)}
          options={companyRoles.map((role) => ({ value: role.slug, label: role.name }))}
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

      {message && (
        <p
          role={message.type === "error" ? "alert" : "status"}
          className={message.type === "error" ? "text-sm text-red-600" : "text-sm text-green-700 dark:text-green-400"}
        >
          {message.text}
        </p>
      )}

      {isLoading ? (
        <p className={mutedTextClassName}>Зареждане…</p>
      ) : users.status === "error" ? (
        <p role="alert" className="text-red-600 dark:text-red-400">
          Потребителите не можаха да се заредят. Опитай отново по-късно.
        </p>
      ) : users.result.data.length === 0 ? (
        <p className={mutedTextClassName}>
          {hasFilters ? "Няма потребители, които отговарят на филтрите." : "Все още няма потребители."}
        </p>
      ) : (
        <>
          {/* Phones get a card per user; the table needs more width. */}
          <ul className="flex flex-col gap-3 md:hidden">
            {users.result.data.map((user) => (
              <li
                key={user.id}
                className="flex flex-col gap-3 rounded-lg border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-950"
              >
                <div className="flex min-w-0 flex-col gap-0.5">
                  <p className="font-semibold">
                    {user.name}
                    {user.id === currentUser?.id && (
                      <span className={`ml-2 text-xs font-normal ${mutedTextClassName}`}>(ти)</span>
                    )}
                  </p>
                  <p className={`truncate text-sm ${mutedTextClassName}`}>{user.email}</p>
                </div>
                <dl className="grid grid-cols-[max-content_1fr] gap-x-3 gap-y-1 text-sm">
                  <dt className="font-medium text-violet-900 dark:text-violet-200">Роля във фирмата</dt>
                  <dd>{user.company_role_label}</dd>
                  <dt className="font-medium text-violet-900 dark:text-violet-200">Системна роля</dt>
                  <dd>{user.system_role_label}</dd>
                </dl>
                <div className="flex flex-wrap gap-2">{renderActions(user)}</div>
              </li>
            ))}
          </ul>

          <div className="hidden overflow-x-auto rounded-lg border border-zinc-200 shadow-sm md:block dark:border-zinc-800">
            <table className="w-full text-left text-sm">
              <thead className="border-b-2 border-violet-200 bg-violet-50 text-xs tracking-wide text-violet-900 uppercase dark:border-violet-500/30 dark:bg-violet-500/10 dark:text-violet-200">
                <tr>
                  <th scope="col" className="px-4 py-3 font-semibold">Име</th>
                  <th scope="col" className="px-4 py-3 font-semibold">Email</th>
                  <th scope="col" className="px-4 py-3 font-semibold">Роля във фирмата</th>
                  <th scope="col" className="px-4 py-3 font-semibold">Системна роля</th>
                  <th scope="col" className="px-4 py-3 font-semibold">
                    <span className="sr-only">Действие</span>
                  </th>
                </tr>
              </thead>
              <tbody className="divide-y divide-zinc-200 bg-white dark:divide-zinc-800 dark:bg-zinc-950">
                {users.result.data.map((user) => (
                  <tr key={user.id}>
                    <td className="px-4 py-3 font-medium">
                      {user.name}
                      {user.id === currentUser?.id && (
                        <span className={`ml-2 text-xs ${mutedTextClassName}`}>(ти)</span>
                      )}
                    </td>
                    <td className="px-4 py-3">{user.email}</td>
                    <td className="px-4 py-3">{user.company_role_label}</td>
                    <td className="px-4 py-3">{user.system_role_label}</td>
                    <td className="px-4 py-3">
                      <div className="flex justify-end gap-2">{renderActions(user)}</div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          <AdminPagination
            page={users.result.meta.current_page}
            lastPage={users.result.meta.last_page}
            total={users.result.meta.total}
            itemsLabel={users.result.meta.total === 1 ? "потребител" : "потребителя"}
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
