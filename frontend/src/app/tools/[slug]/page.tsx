import type { Metadata } from "next";
import { Suspense } from "react";
import RequireAuth from "@/components/RequireAuth";
import ToolDetails from "@/components/tools/ToolDetails";

export const metadata: Metadata = {
  title: "AI инструмент | AI-Tool-Finder",
};

// ToolDetails reads the slug from the URL (useParams), so it needs a Suspense boundary.
export default function ToolPage() {
  return (
    <RequireAuth>
      <Suspense fallback={<p className="text-zinc-600 dark:text-zinc-400">Зареждане…</p>}>
        <ToolDetails />
      </Suspense>
    </RequireAuth>
  );
}
