import type { Metadata } from "next";
import RequireAuth from "@/components/RequireAuth";
import ToolForm from "@/components/tools/ToolForm";

export const metadata: Metadata = {
  title: "Нов AI инструмент | AI-Finder",
};

export default function NewToolPage() {
  return (
    <RequireAuth>
      <section className="flex flex-col gap-6">
        <h1 className="text-3xl font-semibold tracking-tight">Нов AI инструмент</h1>
        <ToolForm />
      </section>
    </RequireAuth>
  );
}
