import type { Metadata } from "next";
import { Suspense } from "react";
import RequireAuth from "@/components/RequireAuth";
import ToolList from "@/components/tools/ToolList";

export const metadata: Metadata = {
  title: "Любими инструменти | AI-Finder",
};

// ToolList reads the filters from the URL (useSearchParams), so it needs a Suspense boundary.
export default function FavoritesPage() {
  return (
    <RequireAuth>
      <Suspense fallback={<p className="text-zinc-600 dark:text-zinc-400">Зареждане…</p>}>
        <ToolList favoritesOnly />
      </Suspense>
    </RequireAuth>
  );
}
