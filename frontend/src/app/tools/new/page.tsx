import { Plus } from "lucide-react";
import type { Metadata } from "next";
import RequireAuth from "@/components/RequireAuth";
import ToolForm from "@/components/tools/ToolForm";

export const metadata: Metadata = {
  title: "Добави инструмент | AI-Tool-Finder",
};

export default function NewToolPage() {
  return (
    <RequireAuth>
      <section className="mx-auto flex w-full max-w-7xl flex-col gap-6">
        <header className="flex items-center gap-4">
          <span className="flex size-14 shrink-0 items-center justify-center rounded-2xl bg-violet-600 text-white shadow-md shadow-violet-600/30">
            <Plus className="size-7" strokeWidth={2.5} aria-hidden="true" />
          </span>
          <div className="flex flex-col gap-1">
            <h1 className="text-3xl font-bold tracking-tight">Добави инструмент</h1>
            <p className="text-muted-foreground">Сподели инструмент с колегите си</p>
          </div>
        </header>
        <ToolForm />
      </section>
    </RequireAuth>
  );
}
