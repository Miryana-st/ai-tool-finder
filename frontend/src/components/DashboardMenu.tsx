"use client";

import { useEffect, useState } from "react";
import * as api from "@/lib/api";
import type { MenuItem } from "@/lib/api";
import DashboardMenuItem from "./DashboardMenuItem";

type MenuState =
  | { status: "loading" }
  | { status: "error" }
  | { status: "ready"; items: MenuItem[] };

// Role-based menu. The items come from the backend (GET /api/dashboard),
// so new role features only need a backend change plus a page for their href.
export default function DashboardMenu() {
  const [state, setState] = useState<MenuState>({ status: "loading" });

  useEffect(() => {
    const controller = new AbortController();

    api
      .getDashboard(controller.signal)
      .then((dashboard) => setState({ status: "ready", items: dashboard.menu }))
      .catch((error: unknown) => {
        if (error instanceof DOMException && error.name === "AbortError") {
          return;
        }
        console.error("Could not load the dashboard menu.", error);
        setState({ status: "error" });
      });

    return () => controller.abort();
  }, []);

  if (state.status === "loading") {
    return <p className="text-zinc-600 dark:text-zinc-400">Зареждане на менюто…</p>;
  }

  if (state.status === "error") {
    return (
      <p role="alert" className="text-red-600 dark:text-red-400">
        Менюто не можа да се зареди. Опитай отново по-късно.
      </p>
    );
  }

  if (state.items.length === 0) {
    return (
      <p className="text-zinc-600 dark:text-zinc-400">
        Няма налични функции за твоята роля.
      </p>
    );
  }

  return (
    <nav aria-label="Меню по роля">
      <ul className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
        {state.items.map((item) => (
          <li key={item.key}>
            <DashboardMenuItem item={item} />
          </li>
        ))}
      </ul>
    </nav>
  );
}
