"use client";

import Link from "next/link";
import { useParams, useSearchParams } from "next/navigation";
import ToolForm from "./ToolForm";
import ToolStateMessage from "./ToolStateMessage";
import { useTool } from "./useTool";
import { mutedTextClassName } from "./styles";

// Edit page body: loads the tool and shows the form if the user may edit it.
// The backend checks the same rights again (ToolPolicy) when saving.
export default function ToolEditor() {
  const { slug } = useParams<{ slug: string }>();
  const searchParams = useSearchParams();
  const state = useTool(slug);

  if (state.status !== "ready") {
    return <ToolStateMessage state={state} />;
  }

  const { tool } = state;

  if (!tool.can.update) {
    return (
      <div className="flex flex-col gap-3">
        <p role="alert" className={mutedTextClassName}>
          Нямаш права да редактираш този инструмент.
        </p>
        <Link href={`/tools/${tool.slug}`} className="text-sm text-indigo-600 hover:underline">
          ← Към инструмента
        </Link>
      </div>
    );
  }

  return (
    <section className="flex flex-col gap-6">
      <h1 className="text-3xl font-semibold tracking-tight">Редакция: {tool.name}</h1>
      {searchParams.get("images") === "failed" && (
        <p role="alert" className="text-sm text-red-600">
          Инструментът е добавен, но някои скрийншоти не се качиха. Добави ги отново тук.
        </p>
      )}
      <ToolForm key={tool.id} tool={tool} />
    </section>
  );
}
