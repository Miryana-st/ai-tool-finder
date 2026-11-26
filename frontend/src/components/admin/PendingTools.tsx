"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import AdminPagination, { usePagedItems } from "@/components/admin/AdminPagination";
import { markdownToPlainText } from "@/components/tools/Markdown";
import * as api from "@/lib/api";
import { ApiError, type Tool } from "@/lib/api";
import {
  chipClassName,
  dangerButtonClassName,
  mutedTextClassName,
  primaryButtonClassName,
  secondaryButtonClassName,
} from "@/styles/classNames";

type ListState =
  | { status: "loading" }
  | { status: "error" }
  | { status: "ready"; tools: Tool[] };

const dateFormat = new Intl.DateTimeFormat("bg-BG", { dateStyle: "medium", timeStyle: "short" });

// Tools added by users and waiting for an admin. Use it inside AdminOnly.
export default function PendingTools() {
  const [state, setState] = useState<ListState>({ status: "loading" });
  const [busyId, setBusyId] = useState<number | null>(null);
  const [message, setMessage] = useState<{ type: "success" | "error"; text: string } | null>(null);
  const tools = state.status === "ready" ? state.tools : [];
  const { pageItems, page, lastPage, setPage } = usePagedItems(tools);

  useEffect(() => {
    const controller = new AbortController();

    api
      .getPendingTools(controller.signal)
      .then((tools) => setState({ status: "ready", tools }))
      .catch((error: unknown) => {
        if (api.isAbortError(error)) {
          return;
        }
        console.error("Could not load the pending tools.", error);
        setState({ status: "error" });
      });

    return () => controller.abort();
  }, []);

  const remove = (id: number) =>
    setState((current) =>
      current.status === "ready" ? { status: "ready", tools: current.tools.filter((tool) => tool.id !== id) } : current,
    );

  const decide = async (tool: Tool, decision: "approve" | "reject") => {
    if (decision === "reject" && !window.confirm(`Да отхвърля ли „${tool.name}“? Инструментът ще бъде изтрит.`)) {
      return;
    }

    setBusyId(tool.id);
    setMessage(null);

    try {
      if (decision === "approve") {
        await api.approveTool(tool.id);
        setMessage({ type: "success", text: `„${tool.name}“ е одобрен и вече се вижда от всички.` });
      } else {
        await api.rejectTool(tool.id);
        setMessage({ type: "success", text: `„${tool.name}“ е отхвърлен.` });
      }
      remove(tool.id);
    } catch (error) {
      // 409: already approved; 404: already rejected or deleted (e.g. by another admin or its creator).
      if (error instanceof ApiError && error.status === 409) {
        setMessage({ type: "error", text: `„${tool.name}“ вече е одобрен.` });
        remove(tool.id);
      } else if (error instanceof ApiError && error.status === 404) {
        setMessage({ type: "error", text: `„${tool.name}“ вече е отхвърлен или изтрит.` });
        remove(tool.id);
      } else {
        console.error("Could not decide on the tool.", error);
        setMessage({ type: "error", text: "Действието не беше успешно. Опитай отново." });
      }
    } finally {
      setBusyId(null);
    }
  };

  return (
    <section className="flex flex-col gap-6">
      <div className="flex flex-col gap-1">
        <h1 className="text-3xl font-semibold tracking-tight">Чакащи инструменти</h1>
        <p className={mutedTextClassName}>
          Инструментите, добавени от потребители, се виждат от всички едва след одобрение. Отхвърлените се изтриват.
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
          Инструментите не можаха да се заредят. Опитай отново по-късно.
        </p>
      )}

      {state.status === "ready" && tools.length === 0 && (
        <p className={mutedTextClassName}>Няма чакащи инструменти.</p>
      )}

      {tools.length > 0 && (
        <ul className="flex flex-col gap-3">
          {pageItems.map((tool) => (
            <li
              key={tool.id}
              className="flex flex-wrap items-start justify-between gap-4 rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-950"
            >
              <div className="flex min-w-0 flex-1 flex-col gap-1">
                <p className="font-medium">{tool.name}</p>
                <p className="line-clamp-2 text-sm">{markdownToPlainText(tool.description)}</p>
                {tool.categories.length > 0 && (
                  <ul aria-label="Категории" className="flex flex-wrap gap-1.5 pt-1">
                    {tool.categories.map((category) => (
                      <li key={category.id} className={chipClassName}>
                        {category.name}
                      </li>
                    ))}
                  </ul>
                )}
                <p className={`text-xs ${mutedTextClassName}`}>
                  Добавен от {tool.creator.name} · {dateFormat.format(new Date(tool.created_at))}
                </p>
              </div>

              <div className="flex flex-wrap gap-2">
                <Link href={`/tools/${tool.slug}`} className={secondaryButtonClassName}>
                  Преглед
                </Link>
                <button
                  type="button"
                  onClick={() => void decide(tool, "approve")}
                  disabled={busyId !== null}
                  className={primaryButtonClassName}
                >
                  Одобри
                </button>
                <button
                  type="button"
                  onClick={() => void decide(tool, "reject")}
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

      {tools.length > 0 && (
        <AdminPagination
          page={page}
          lastPage={lastPage}
          total={tools.length}
          itemsLabel={tools.length === 1 ? "инструмент" : "инструмента"}
          onChange={setPage}
        />
      )}
    </section>
  );
}
