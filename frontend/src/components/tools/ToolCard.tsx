import Link from "next/link";
import type { Tool } from "@/lib/api";
import { markdownToPlainText } from "./Markdown";
import { chipClassName, mutedTextClassName } from "./styles";

export default function ToolCard({ tool }: { tool: Tool }) {
  const thumbnail = tool.images[0];

  return (
    <article className="flex h-full flex-col overflow-hidden rounded-lg border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-950">
      <Link href={`/tools/${tool.slug}`} className="block aspect-video bg-zinc-100 dark:bg-zinc-900">
        {thumbnail ? (
          // Uploaded files come from /storage, outside the Next.js image pipeline.
          // eslint-disable-next-line @next/next/no-img-element
          <img
            src={thumbnail.url}
            alt={`Скрийншот на ${tool.name}`}
            className="h-full w-full object-cover"
          />
        ) : (
          <span className={`flex h-full items-center justify-center text-sm ${mutedTextClassName}`}>
            Няма изображение
          </span>
        )}
      </Link>

      <div className="flex flex-1 flex-col gap-3 p-4">
        <h2 className="text-lg font-semibold">
          <Link href={`/tools/${tool.slug}`} className="hover:text-indigo-600">
            {tool.name}
          </Link>
        </h2>

        <p className={`line-clamp-3 text-sm ${mutedTextClassName}`}>
          {markdownToPlainText(tool.description)}
        </p>

        <TermList label="Категории" items={tool.categories.map((category) => category.name)} />
        <TermList label="Роли" items={tool.company_roles.map((role) => role.name)} />

        {tool.tags.length > 0 && (
          <ul aria-label="Тагове" className="mt-auto flex flex-wrap gap-1">
            {tool.tags.map((tag) => (
              <li key={tag.id}>
                <Link href={`/tools?tag=${encodeURIComponent(tag.slug)}`} className={`${chipClassName} hover:text-indigo-600`}>
                  #{tag.name}
                </Link>
              </li>
            ))}
          </ul>
        )}
      </div>
    </article>
  );
}

function TermList({ label, items }: { label: string; items: string[] }) {
  if (items.length === 0) {
    return null;
  }

  return (
    <p className="text-sm">
      <span className="font-medium">{label}:</span>{" "}
      <span className={mutedTextClassName}>{items.join(", ")}</span>
    </p>
  );
}
