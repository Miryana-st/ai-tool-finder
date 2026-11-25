"use client";

import { usePathname } from "next/navigation";
import { createContext, useContext, useEffect, useState, type ReactNode } from "react";
import * as api from "@/lib/api";
import type { Dashboard } from "@/lib/api";

export type DashboardState =
  | { status: "loading" }
  | { status: "error" }
  | { status: "ready"; dashboard: Dashboard };

const DashboardContext = createContext<DashboardState | null>(null);

// GET /api/dashboard for the logged-in user: the role menu (sidebar) and the stats (dashboard page).
// Loaded once for the sidebar and again on every visit to /dashboard, so the numbers stay fresh.
export function DashboardProvider({ children }: { children: ReactNode }) {
  const pathname = usePathname();
  const [state, setState] = useState<DashboardState>({ status: "loading" });
  const reloadKey = pathname === "/dashboard" ? pathname : "other";

  useEffect(() => {
    const controller = new AbortController();

    api
      .getDashboard(controller.signal)
      .then((dashboard) => setState({ status: "ready", dashboard }))
      .catch((error: unknown) => {
        if (api.isAbortError(error)) {
          return;
        }
        console.error("Could not load the dashboard.", error);
        setState((current) => (current.status === "ready" ? current : { status: "error" }));
      });

    return () => controller.abort();
  }, [reloadKey]);

  return <DashboardContext.Provider value={state}>{children}</DashboardContext.Provider>;
}

export function useDashboard(): DashboardState {
  const context = useContext(DashboardContext);

  if (!context) {
    throw new Error("useDashboard must be used within a DashboardProvider.");
  }

  return context;
}
