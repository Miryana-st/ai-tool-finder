"use client";

import Link from "next/link";
import { useParams, useRouter } from "next/navigation";
import { useState } from "react";
import * as api from "@/lib/api";
import type { Tool } from "@/lib/api";
import Markdown from "./Markdown";
import ToolStateMessage from "./ToolStateMessage";
import { useTool } from "./useTool";
import {
  chipClassName,
  dangerButtonClassName,
  mutedTextClassName,
  secondaryButtonClassName,
} from "./styles";

export default function ToolDetails() {
  const { slug } = useParams<{ slug: string }>();
  const state = useTool(slug);

  if (state.status !== "ready") {
    return <ToolStateMessage state={state} />;
  }

  return <ToolView tool={state.tool} />;
}

function ToolView({ tool }: { tool: Tool }) {
  const router = useRouter();
  const [isDeleting, setIsDeleting] = useState(false);
  const [deleteError, setDeleteError] = useState<string | null>(null);

  const handleDelete = async () => {
    if (!window.confirm(`Сигурен ли си, че искаш да изтриеш „${tool.name}“?`)) {
      return;
    }

    setIsDeleting(true);
    setDeleteError(null);

    try {
      await api.deleteTool(tool.id);
      router.push("/tools");
    } catch (error) {
      console.error("Could not delete the tool.", error);
      setDeleteError("Инструментът не можа да се изтрие. Опитай отново по-късно.");
      setIsDeleting(false);
    }
  };

  return (
    <article className="flex flex-col gap-8">
      <Link href="/tools" className="text-sm text-indigo-600 hover:underline">
        ← Към всички инструменти
      </Link>

      <header className="flex flex-col gap-4">
        <div className="flex flex-wrap items-start justify-between gap-4">
          <div className="flex flex-col gap-1">
            <h1 className="text-3xl font-semibold tracking-tight">{tool.name}</h1>
            <p className={`text-sm ${mutedTextClassName}`}>Добавен от {tool.creator.name}</p>
          </div>

          {(tool.can.update || tool.can.delete) && (
            <div className="flex gap-2">
              {tool.can.update && (
                <Link href={`/tools/${tool.slug}/edit`} className={secondaryButtonClassName}>
                  Редактирай
                </Link>
              )}
              {tool.can.delete && (
                <button
                  type="button"
                  onClick={() => void handleDelete()}
                  disabled={isDeleting}
                  className={dangerButtonClassName}
                >
                  {isDeleting ? "Изтриване…" : "Изтрий"}
                </button>
              )}
            </div>
          )}
        </div>

        {deleteError && (
          <p role="alert" className="text-sm text-red-600">
            {deleteError}
          </p>
        )}

        <div className="flex flex-wrap gap-4 text-sm">
          <a href={tool.url} target="_blank" rel="noopener noreferrer" className="text-indigo-600 hover:underline">
            Към инструмента ↗
          </a>
          <a
            href={tool.documentation_url}
            target="_blank"
            rel="noopener noreferrer"
            className="text-indigo-600 hover:underline"
          >
            Документация ↗
          </a>
        </div>

        <dl className="grid grid-cols-1 gap-2 text-sm sm:grid-cols-[max-content_1fr] sm:gap-x-4">
          <dt className="font-medium">Роли във фирмата</dt>
          <dd className={mutedTextClassName}>{tool.company_roles.map((role) => role.name).join(", ")}</dd>
          <dt className="font-medium">Категории</dt>
          <dd className={mutedTextClassName}>{tool.categories.map((category) => category.name).join(", ")}</dd>
          {tool.tags.length > 0 && (
            <>
              <dt className="font-medium">Тагове</dt>
              <dd className="flex flex-wrap gap-1">
                {tool.tags.map((tag) => (
                  <Link
                    key={tag.id}
                    href={`/tools?tag=${encodeURIComponent(tag.slug)}`}
                    className={`${chipClassName} hover:text-indigo-600`}
                  >
                    #{tag.name}
                  </Link>
                ))}
              </dd>
            </>
          )}
        </dl>
      </header>

      <Section title="Описание">
        <Markdown>{tool.description}</Markdown>
      </Section>

      <Section title="Как се използва">
        <Markdown>{tool.how_to_use}</Markdown>
      </Section>

      {tool.examples && (
        <Section title="Реални примери">
          <Markdown>{tool.examples}</Markdown>
        </Section>
      )}

      {tool.images.length > 0 && (
        <Section title="Скрийншоти">
          <ul className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            {tool.images.map((image) => (
              <li key={image.id}>
                <a href={image.url} target="_blank" rel="noopener noreferrer">
                  {/* eslint-disable-next-line @next/next/no-img-element */}
                  <img
                    src={image.url}
                    alt={image.original_name}
                    className="w-full rounded-md border border-zinc-200 dark:border-zinc-800"
                  />
                </a>
              </li>
            ))}
          </ul>
        </Section>
      )}
    </article>
  );
}

function Section({ title, children }: { title: string; children: React.ReactNode }) {
  return (
    <section className="flex flex-col gap-3">
      <h2 className="text-xl font-semibold">{title}</h2>
      {children}
    </section>
  );
}
