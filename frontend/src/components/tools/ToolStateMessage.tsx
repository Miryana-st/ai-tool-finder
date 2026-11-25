import Link from "next/link";
import type { ToolState } from "./useTool";
import { mutedTextClassName } from "@/styles/classNames";

// What to show while a tool is not ready: loading, not found or an error.
export default function ToolStateMessage({ state }: { state: Exclude<ToolState, { status: "ready" }> }) {
  if (state.status === "loading") {
    return <p className={mutedTextClassName}>Зареждане…</p>;
  }

  return (
    <div className="flex flex-col gap-3">
      <p role="alert" className={state.status === "error" ? "text-red-600 dark:text-red-400" : mutedTextClassName}>
        {state.status === "not-found"
          ? "Инструментът не е намерен."
          : "Инструментът не можа да се зареди. Опитай отново по-късно."}
      </p>
      <Link href="/tools" className="text-sm text-indigo-600 hover:underline">
        ← Към всички инструменти
      </Link>
    </div>
  );
}
