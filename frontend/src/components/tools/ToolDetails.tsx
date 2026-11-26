"use client";

import {
  BookOpen,
  CalendarDays,
  ClipboardList,
  Code,
  Crown,
  ExternalLink,
  Folder,
  Globe,
  Heart,
  Images,
  Info,
  Lightbulb,
  Palette,
  Pencil,
  Rocket,
  Server,
  ShieldCheck,
  Sparkles,
  Tags,
  Trash2,
  UserRound,
  Users,
  type LucideIcon,
} from "lucide-react";
import Link from "next/link";
import { useParams, useRouter } from "next/navigation";
import { useState, type ReactNode } from "react";
import { buttonVariants } from "@/components/ui/button";
import RobotIllustration from "@/components/dashboard/RobotIllustration";
import { useBreadcrumbLabel } from "@/context/BreadcrumbContext";
import * as api from "@/lib/api";
import type { FavoriteInfo, Tool } from "@/lib/api";
import { cn } from "@/lib/utils";
import FavoriteButton from "./FavoriteButton";
import Markdown from "./Markdown";
import PendingBadge from "./PendingBadge";
import ToolStateMessage from "./ToolStateMessage";
import { useTool } from "./useTool";
import { mutedTextClassName } from "@/styles/classNames";

const cardClassName = "rounded-2xl border bg-card p-5 text-card-foreground shadow-sm md:p-6";

const violetChipClassName =
  "inline-flex items-center rounded-full bg-violet-100 px-3 py-1 text-xs font-medium text-violet-700 dark:bg-violet-500/15 dark:text-violet-300";

// Icons for the base company roles; roles added later by an admin get the default icon.
const ROLE_ICONS: Record<string, LucideIcon> = {
  owner: Crown,
  backend: Server,
  frontend: Code,
  pm: ClipboardList,
  qa: ShieldCheck,
  designer: Palette,
};

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
  const [favorite, setFavorite] = useState<FavoriteInfo>({
    is_favorite: tool.is_favorite,
    favorites_count: tool.favorites_count,
  });
  const logo = tool.images[0];

  useBreadcrumbLabel(`/tools/${tool.slug}`, tool.name);

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

  const sections = [
    { id: "overview", label: "Общ преглед", icon: Info },
    { id: "how-to-use", label: "Как се използва", icon: Rocket },
    ...(tool.examples ? [{ id: "examples", label: "Примери", icon: Lightbulb }] : []),
    ...(tool.images.length > 0 ? [{ id: "screenshots", label: "Скрийншоти", icon: Images }] : []),
  ];

  return (
    <article className="flex flex-col gap-6">
      <header className="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
        <div className="flex flex-col gap-5 sm:flex-row sm:items-start">
          {logo ? (
            // Uploaded files come from /storage, outside the Next.js image pipeline.
            // eslint-disable-next-line @next/next/no-img-element
            <img
              src={logo.url}
              alt=""
              className="size-24 shrink-0 rounded-3xl border bg-muted object-cover shadow-md md:size-32"
            />
          ) : (
            <div
              aria-hidden="true"
              className="flex size-24 shrink-0 items-center justify-center rounded-3xl bg-linear-to-br from-violet-500 to-indigo-600 text-5xl font-bold text-white shadow-md md:size-32"
            >
              {tool.name.charAt(0).toUpperCase()}
            </div>
          )}

          <div className="flex flex-col gap-3">
            <h1 className="text-3xl font-bold tracking-tight md:text-4xl">{tool.name}</h1>

            {!tool.is_approved && (
              <p className="flex flex-wrap items-center gap-2 text-sm text-amber-800 dark:text-amber-300">
                <PendingBadge />
                Ще се вижда от всички след одобрение от администратор.
              </p>
            )}

            {tool.categories.length > 0 && (
              <ul aria-label="Категории" className="flex flex-wrap gap-2">
                {tool.categories.map((category) => (
                  <li key={category.id} className={cn(violetChipClassName, "text-sm")}>
                    {category.name}
                  </li>
                ))}
              </ul>
            )}

            <div className={`flex flex-wrap items-center gap-x-5 gap-y-2 text-sm ${mutedTextClassName}`}>
              <span className="inline-flex items-center gap-1.5">
                <UserRound className="size-4 text-violet-500" />
                Добавен от {tool.creator.name}
              </span>
              <span className="inline-flex items-center gap-1.5">
                <CalendarDays className="size-4 text-violet-500" />
                {new Date(tool.created_at).toLocaleDateString("bg-BG")}
              </span>
              <span className="inline-flex items-center gap-1.5">
                <Heart className="size-4 fill-rose-500 text-rose-500" />
                В любими на {favorite.favorites_count}{" "}
                {favorite.favorites_count === 1 ? "потребител" : "потребители"}
              </span>
            </div>
          </div>
        </div>

        <div className="flex flex-col gap-2 lg:items-end">
          <div className="flex flex-wrap items-start gap-3">
            <FavoriteButton toolId={tool.id} favorite={favorite} onChange={setFavorite} />
            {tool.can.update && (
              <Link
                href={`/tools/${tool.slug}/edit`}
                className={cn(buttonVariants({ variant: "outline" }), "h-11 rounded-xl px-5")}
              >
                <Pencil />
                Редактирай
              </Link>
            )}
            {tool.can.delete && (
              <button
                type="button"
                onClick={() => void handleDelete()}
                disabled={isDeleting}
                className={cn(
                  buttonVariants({ variant: "outline" }),
                  "h-11 rounded-xl px-5 text-red-600 hover:text-red-600",
                )}
              >
                <Trash2 />
                {isDeleting ? "Изтриване…" : "Изтрий"}
              </button>
            )}
          </div>
          {deleteError && (
            <p role="alert" className="text-sm text-red-600">
              {deleteError}
            </p>
          )}
        </div>
      </header>

      <div className="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]">
        <div className="flex flex-col gap-6">
          <nav
            aria-label="Секции"
            className="flex overflow-x-auto rounded-2xl border bg-card text-sm font-medium shadow-sm [scrollbar-width:none]"
          >
            {sections.map(({ id, label, icon: Icon }) => (
              <a
                key={id}
                href={`#${id}`}
                className="flex flex-1 items-center justify-center gap-2 whitespace-nowrap border-r px-4 py-3.5 last:border-r-0 hover:bg-violet-50 hover:text-violet-700 dark:hover:bg-violet-500/10"
              >
                <Icon className="size-4 text-violet-600" />
                {label}
              </a>
            ))}
          </nav>

          <section
            id="overview"
            className={cn(
              cardClassName,
              "relative scroll-mt-6 overflow-hidden bg-linear-to-r from-card via-card to-violet-50 lg:min-h-72 dark:to-violet-500/10",
            )}
          >
            <div className="relative z-10 flex flex-col gap-3 lg:pr-80">
              <SectionTitle icon={Sparkles}>За инструмента</SectionTitle>
              <Markdown>{tool.description}</Markdown>
            </div>
            <RobotIllustration className="pointer-events-none absolute right-2 bottom-0 hidden h-64 lg:block" />
          </section>

          <section id="how-to-use" className={cn(cardClassName, "flex scroll-mt-6 flex-col gap-3")}>
            <SectionTitle icon={Rocket}>Как се използва</SectionTitle>
            <Markdown>{tool.how_to_use}</Markdown>
          </section>

          {tool.examples && (
            <section id="examples" className={cn(cardClassName, "flex scroll-mt-6 flex-col gap-3")}>
              <SectionTitle icon={Lightbulb}>Реални примери</SectionTitle>
              <Markdown>{tool.examples}</Markdown>
            </section>
          )}
        </div>

        <aside className="flex flex-col gap-6">
          <section className={cn(cardClassName, "flex flex-col gap-4")}>
            <SectionTitle icon={Info}>Детайли</SectionTitle>
            <dl className="grid grid-cols-[max-content_minmax(0,1fr)] items-start gap-x-4 gap-y-3.5 text-sm">
              <DetailRow icon={Globe} label="Уебсайт">
                <ExternalTextLink href={tool.url} />
              </DetailRow>
              <DetailRow icon={BookOpen} label="Документация">
                <ExternalTextLink href={tool.documentation_url} />
              </DetailRow>
              <DetailRow icon={Folder} label="Категории">
                <ChipList items={tool.categories.map((category) => ({ key: category.id, label: category.name }))} />
              </DetailRow>
              {tool.tags.length > 0 && (
                <DetailRow icon={Tags} label="Тагове">
                  <ul className="flex flex-wrap gap-1.5">
                    {tool.tags.map((tag) => (
                      <li key={tag.id}>
                        <Link
                          href={`/tools?tag=${encodeURIComponent(tag.slug)}`}
                          className={cn(violetChipClassName, "hover:bg-violet-200 dark:hover:bg-violet-500/25")}
                        >
                          #{tag.name}
                        </Link>
                      </li>
                    ))}
                  </ul>
                </DetailRow>
              )}
            </dl>
          </section>

          {tool.company_roles.length > 0 && (
            <section className={cn(cardClassName, "flex flex-col gap-4")}>
              <SectionTitle icon={Users}>Препоръчан за роли</SectionTitle>
              <ul className="grid grid-cols-[repeat(auto-fill,minmax(6rem,1fr))] gap-3">
                {tool.company_roles.map((role) => {
                  const Icon = ROLE_ICONS[role.slug] ?? UserRound;

                  return (
                    <li key={role.id}>
                      <Link
                        href={`/tools?company_role=${encodeURIComponent(role.slug)}`}
                        className="flex flex-col items-center gap-2 rounded-xl border px-2 py-3 text-center text-xs font-medium transition-colors hover:border-violet-300 hover:bg-violet-50 dark:hover:bg-violet-500/10"
                      >
                        <Icon className="size-6 text-violet-600" />
                        {role.name}
                      </Link>
                    </li>
                  );
                })}
              </ul>
            </section>
          )}

          {tool.images.length > 0 && (
            <section id="screenshots" className={cn(cardClassName, "flex scroll-mt-6 flex-col gap-4")}>
              <SectionTitle icon={Images}>Скрийншоти</SectionTitle>
              <ul className="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-2 2xl:grid-cols-3">
                {tool.images.map((image) => (
                  <li key={image.id}>
                    <a
                      href={image.url}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="block aspect-4/3 overflow-hidden rounded-xl border bg-muted transition-shadow hover:shadow-md"
                    >
                      {/* Uploaded files come from /storage, outside the Next.js image pipeline. */}
                      {/* eslint-disable-next-line @next/next/no-img-element */}
                      <img src={image.url} alt={image.original_name} className="size-full object-cover" />
                    </a>
                  </li>
                ))}
              </ul>
            </section>
          )}
        </aside>
      </div>
    </article>
  );
}

function SectionTitle({ icon: Icon, children }: { icon: LucideIcon; children: ReactNode }) {
  return (
    <h2 className="flex items-center gap-2.5 text-xl font-bold">
      <Icon className="size-5 text-violet-600" />
      {children}
    </h2>
  );
}

function DetailRow({ icon: Icon, label, children }: { icon: LucideIcon; label: string; children: ReactNode }) {
  return (
    <>
      <dt className="flex items-center gap-2.5 py-0.5 font-medium">
        <Icon className={`size-4 ${mutedTextClassName}`} />
        {label}
      </dt>
      <dd className="min-w-0 py-0.5">{children}</dd>
    </>
  );
}

function ExternalTextLink({ href }: { href: string }) {
  return (
    <a
      href={href}
      target="_blank"
      rel="noopener noreferrer"
      className="inline-flex max-w-full items-center gap-1.5 text-violet-600 hover:underline"
    >
      <span className="truncate">{href}</span>
      <ExternalLink className="size-3.5 shrink-0" />
    </a>
  );
}

function ChipList({ items }: { items: { key: number; label: string }[] }) {
  return (
    <ul className="flex flex-wrap gap-1.5">
      {items.map((item) => (
        <li key={item.key} className={violetChipClassName}>
          {item.label}
        </li>
      ))}
    </ul>
  );
}
