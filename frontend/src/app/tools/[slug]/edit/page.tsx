import type { Metadata } from "next";
import { Suspense } from "react";
import RequireAuth from "@/components/RequireAuth";
import ToolEditor from "@/components/tools/ToolEditor";

export const metadata: Metadata = {
  title: "Редакция на AI инструмент | AI-Tool-Finder",
};

// ToolEditor reads the slug and query from the URL, so it needs a Suspense boundary.
export default function EditToolPage() {
  return (
    <RequireAuth>
      <Suspense fallback={<p className="text-zinc-600 dark:text-zinc-400">Зареждане…</p>}>
        <ToolEditor />
      </Suspense>
    </RequireAuth>
  );
}
