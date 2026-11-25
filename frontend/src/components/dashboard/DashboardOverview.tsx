"use client";

import { Skeleton } from "@/components/ui/skeleton";
import { useAuth } from "@/context/AuthContext";
import { useDashboard } from "@/context/DashboardContext";
import DashboardHero from "./DashboardHero";
import DashboardStatCards from "./DashboardStatCards";
import PopularCategories from "./PopularCategories";

export default function DashboardOverview() {
  const { user } = useAuth();
  const dashboard = useDashboard();

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
