"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { useAuth } from "@/context/AuthContext";
import * as api from "@/lib/api";
import { ApiError, type Category } from "@/lib/api";
import {
  dangerButtonClassName,
  mutedTextClassName,
  primaryButtonClassName,
} from "@/components/tools/styles";

type ListState =
  | { status: "loading" }
  | { status: "error" }
  | { status: "ready"; categories: Category[] };

const dateFormat = new Intl.DateTimeFormat("bg-BG", { dateStyle: "medium", timeStyle: "short" });

// Category suggestions waiting for an admin. The backend allows this to admins only;
// the check here just shows a clear message instead of an error.
export default function PendingCategories() {
  const { user } = useAuth();
  const isAdmin = user?.system_role === "admin";

  if (!isAdmin) {
    return (
      <div className="flex flex-col gap-3">
        <p role="alert" className={mutedTextClassName}>
          Тази страница е само за администратори.
        </p>
        <Link href="/dashboard" className="text-sm text-indigo-600 hover:underline">
          ← Към таблото
        </Link>
      </div>
    );
  }

  return <PendingCategoryList />;
}

function PendingCategoryList() {
  const [state, setState] = useState<ListState>({ status: "loading" });
  const [busyId, setBusyId] = useState<number | null>(null);
  const [message, setMessage] = useState<{ type: "success" | "error"; text: string } | null>(null);

  useEffect(() => {
    const controller = new AbortController();

    api
      .getPendingCategories(controller.signal)
      .then((categories) => setState({ status: "ready", categories }))
      .catch((error: unknown) => {
        if (api.isAbortError(error)) {
          return;
        }
        console.error("Could not load the category suggestions.", error);
        setState({ status: "error" });
      });

    return () => controller.abort();
  }, []);

  const remove = (id: number) =>
    setState((current) =>
      current.status === "ready"
        ? { status: "ready", categories: current.categories.filter((category) => category.id !== id) }
        : current,
    );

  const decide = async (category: Category, decision: "approve" | "reject") => {
    if (decision === "reject" && !window.confirm(`Да отхвърля ли „${category.name}“? Предложението ще бъде изтрито.`)) {
      return;
    }

    setBusyId(category.id);
    setMessage(null);

    try {
      if (decision === "approve") {
        await api.approveCategory(category.id);
        setMessage({ type: "success", text: `„${category.name}“ е одобрена и вече може да се избира.` });
      } else {
        await api.rejectCategory(category.id);
        setMessage({ type: "success", text: `„${category.name}“ е отхвърлена.` });
      }
      remove(category.id);
    } catch (error) {
      // 409: somebody else has already approved it.
      if (error instanceof ApiError && error.status === 409) {
        setMessage({ type: "error", text: `„${category.name}“ вече е одобрена.` });
        remove(category.id);
      } else if (error instanceof ApiError && error.status === 404) {
        setMessage({ type: "error", text: `„${category.name}“ вече е отхвърлена.` });
        remove(category.id);
      } else {
        console.error("Could not decide on the category suggestion.", error);
        setMessage({ type: "error", text: "Действието не беше успешно. Опитай отново." });
      }
    } finally {
      setBusyId(null);
    }
  };

  return (
    <section className="flex flex-col gap-6">
      <div className="flex flex-col gap-1">
        <h1 className="text-3xl font-semibold tracking-tight">Предложени категории</h1>
        <p className={mutedTextClassName}>
          Одобрените категории се появяват във формата за AI инструмент. Отхвърлените се изтриват.
        </p>
      </div>

      {message && (
        <p
          role={message.type === "error" ? "alert" : "status"}
          className={message.type === "error" ? "text-sm text-red-600" : "text-sm text-green-700 dark:text-green-400"}
        >
          {message.text}
        </p>
      )}

      {state.status === "loading" && <p className={mutedTextClassName}>Зареждане…</p>}

      {state.status === "error" && (
        <p role="alert" className="text-red-600 dark:text-red-400">
          Предложенията не можаха да се заредят. Опитай отново по-късно.
        </p>
      )}

      {state.status === "ready" && state.categories.length === 0 && (
        <p className={mutedTextClassName}>Няма чакащи предложения.</p>
      )}

      {state.status === "ready" && state.categories.length > 0 && (
        <ul className="flex flex-col gap-3">
          {state.categories.map((category) => (
            <li
              key={category.id}
              className="flex flex-wrap items-start justify-between gap-4 rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-950"
            >
              <div className="flex flex-col gap-1">
                <p className="font-medium">{category.name}</p>
                {category.description && <p className="text-sm">{category.description}</p>}
                <p className={`text-xs ${mutedTextClassName}`}>
                  Предложена от {category.suggested_by?.name ?? "изтрит потребител"} ·{" "}
                  {dateFormat.format(new Date(category.created_at))}
                </p>
              </div>

              <div className="flex gap-2">
                <button
                  type="button"
                  onClick={() => void decide(category, "approve")}
                  disabled={busyId !== null}
                  className={primaryButtonClassName}
                >
                  Одобри
                </button>
                <button
                  type="button"
                  onClick={() => void decide(category, "reject")}
                  disabled={busyId !== null}
                  className={dangerButtonClassName}
                >
                  Отхвърли
                </button>
              </div>
            </li>
          ))}
        </ul>
      )}
    </section>
  );
}
