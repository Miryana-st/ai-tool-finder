"use client";

import { useEffect, useState } from "react";
import { Skeleton } from "@/components/ui/skeleton";
import { useAuth } from "@/context/AuthContext";
import * as api from "@/lib/api";
import type { Dashboard } from "@/lib/api";
import DashboardHero from "./DashboardHero";
import DashboardStatCards from "./DashboardStatCards";
import PopularCategories from "./PopularCategories";

type DashboardState =
  | { status: "loading" }
  | { status: "error" }
  | { status: "ready"; dashboard: Dashboard };

// The stats are loaded on every visit to the dashboard, so the numbers stay fresh.
export default function DashboardOverview() {
  const { user } = useAuth();
  const [dashboard, setDashboard] = useState<DashboardState>({ status: "loading" });

  useEffect(() => {
    const controller = new AbortController();

    api
      .getDashboard(controller.signal)
      .then((data) => setDashboard({ status: "ready", dashboard: data }))
      .catch((error: unknown) => {
        if (api.isAbortError(error)) {
          return;
        }
        console.error("Could not load the dashboard.", error);
        setDashboard({ status: "error" });
      });

    return () => controller.abort();
  }, []);

  if (!user) {
    return null;
  }

  return (
    <div className="mx-auto flex w-full max-w-6xl flex-col gap-8">
      <DashboardHero user={user} />

      {dashboard.status === "loading" && (
        <div className="flex flex-col gap-8" aria-busy="true" aria-label="Зареждане…">
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
            {[0, 1, 2].map((key) => (
              <Skeleton key={key} className="h-40 rounded-xl" />
            ))}
          </div>
          <Skeleton className="h-36 rounded-xl" />
        </div>
      )}

      {dashboard.status === "error" && (
        <p role="alert" className="text-destructive">
          Данните не можаха да се заредят. Опитай отново по-късно.
        </p>
      )}

      {dashboard.status === "ready" && (
        <>
          <DashboardStatCards stats={dashboard.dashboard.stats} />
          <PopularCategories categories={dashboard.dashboard.stats.popular_categories} />
        </>
      )}
    </div>
  );
}
