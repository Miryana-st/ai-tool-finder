import { Heart, LogIn, Search, ShieldCheck, SquarePlus, UsersRound, type LucideIcon } from "lucide-react";
import { categoryIcon, categoryIconColor } from "@/components/categories/categoryIcons";
import RobotIllustration from "@/components/dashboard/RobotIllustration";
import HomeCallToAction from "@/components/home/HomeCallToAction";

type Highlight = { icon: LucideIcon; title: string; text: string };

const HIGHLIGHTS: Highlight[] = [
  {
    icon: UsersRound,
    title: "Според ролята ти",
    text: "Филтрирай по длъжност и виж кои инструменти ползват колегите ти от backend, дизайн, QA и PM.",
  },
  {
    icon: ShieldCheck,
    title: "Проверени от екипа",
    text: "Всеки нов инструмент минава през одобрение от администратор, преди да се появи в каталога.",
  },
  {
    icon: Heart,
    title: "Любими под ръка",
    text: "Отбелязвай инструментите, които ползваш често, и ги намирай с един клик.",
  },
];

// The base categories (see the backend's base category migrations). Guests
// cannot load the catalog, so the home page shows them as fixed text.
const CATEGORIES = [
  { slug: "coding", name: "Код и IDE", description: "Cursor, Copilot, ревю и генериране на код" },
  { slug: "design", name: "Дизайн и изображение", description: "UI генератори, Figma плъгини, икони" },
  { slug: "data", name: "Данни и анализ", description: "SQL асистенти, notebooks, BI" },
  { slug: "automation", name: "Автоматизация и агенти", description: "Агенти, workflow, n8n" },
  { slug: "testing", name: "Тестване и качество", description: "Тестове, бъгове, тестови данни" },
  { slug: "content", name: "Текст и съдържание", description: "Писане, превод, редактура" },
];

const STEPS: Highlight[] = [
  {
    icon: LogIn,
    title: "Влез в профила си",
    text: "Профилите се създават от администратор, заедно с длъжността ти в екипа.",
  },
  {
    icon: Search,
    title: "Намери инструмент",
    text: "Търси по име или филтрирай по длъжност, категория и тагове.",
  },
  {
    icon: SquarePlus,
    title: "Сподели находка",
    text: "Добави инструмент с описание, примери и снимки, за да го видят и останалите.",
  },
];

export default function Home() {
  return (
    <div className="flex flex-col gap-16 md:gap-20">
      <section className="relative overflow-hidden rounded-3xl bg-linear-to-br from-violet-100 via-indigo-50 to-violet-200 px-6 py-12 md:px-12 md:py-16">
        <div className="relative z-10 flex max-w-xl flex-col gap-6">
          <span className="w-fit rounded-full bg-white/70 px-3 py-1 text-xs font-semibold tracking-wide text-indigo-700 uppercase ring-1 ring-indigo-200">
            Каталог с AI инструменти за екипа
          </span>
          <h1 className="text-4xl font-bold tracking-tight text-balance text-slate-900 md:text-5xl">
            Открий правилния AI инструмент за своята работа
          </h1>
          <p className="text-lg text-pretty text-slate-700">
            AI-Tool-Finder събира на едно място инструментите, които екипът ползва и препоръчва,
            подредени по длъжност, категория и тагове.
          </p>
          <div className="flex flex-wrap items-center gap-4">
            <HomeCallToAction />
            <a href="#how-it-works" className="text-sm font-semibold text-indigo-700 hover:text-indigo-500">
              Как работи?
            </a>
          </div>
        </div>
        <RobotIllustration className="pointer-events-none absolute right-4 bottom-0 hidden h-full max-h-80 lg:block" />
      </section>

      <section aria-labelledby="highlights-heading" className="flex flex-col gap-8">
        <h2 id="highlights-heading" className="sr-only">
          Какво получаваш
        </h2>
        <ul className="grid gap-4 md:grid-cols-3">
          {HIGHLIGHTS.map(({ icon: Icon, title, text }) => (
            <li key={title} className="flex flex-col gap-3 rounded-2xl bg-card p-6 ring-1 ring-foreground/10">
              <span className="flex size-11 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600 dark:bg-indigo-950 dark:text-indigo-300">
                <Icon className="size-5" aria-hidden="true" />
              </span>
              <h3 className="text-base font-semibold">{title}</h3>
              <p className="text-sm text-muted-foreground">{text}</p>
            </li>
          ))}
        </ul>
      </section>

      <section aria-labelledby="categories-heading" className="flex flex-col gap-8">
        <div className="flex flex-col gap-2">
          <h2 id="categories-heading" className="text-2xl font-bold tracking-tight md:text-3xl">
            Инструменти за всяка задача
          </h2>
          <p className="text-muted-foreground">Каталогът е подреден в категории, а екипът може да предлага нови.</p>
        </div>
        <ul className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {CATEGORIES.map(({ slug, name, description }, index) => {
            const Icon = categoryIcon(slug);

            return (
              <li key={slug} className="flex items-start gap-4 rounded-2xl bg-card p-5 ring-1 ring-foreground/10">
                <Icon className={`mt-0.5 size-6 shrink-0 ${categoryIconColor(index)}`} aria-hidden="true" />
                <div className="flex flex-col gap-1">
                  <h3 className="font-semibold">{name}</h3>
                  <p className="text-sm text-muted-foreground">{description}</p>
                </div>
              </li>
            );
          })}
        </ul>
      </section>

      <section id="how-it-works" aria-labelledby="steps-heading" className="flex scroll-mt-8 flex-col gap-8">
        <h2 id="steps-heading" className="text-2xl font-bold tracking-tight md:text-3xl">
          Как работи
        </h2>
        <ol className="grid gap-6 md:grid-cols-3">
          {STEPS.map(({ icon: Icon, title, text }, index) => (
            <li key={title} className="flex flex-col gap-3">
              <div className="flex items-center gap-3">
                <span className="flex size-9 items-center justify-center rounded-full bg-indigo-600 text-sm font-bold text-white">
                  {index + 1}
                </span>
                <Icon className="size-5 text-indigo-600 dark:text-indigo-300" aria-hidden="true" />
              </div>
              <h3 className="text-base font-semibold">{title}</h3>
              <p className="text-sm text-muted-foreground">{text}</p>
            </li>
          ))}
        </ol>
      </section>

      <section className="flex flex-col items-center gap-5 rounded-3xl bg-indigo-950 px-6 py-12 text-center text-white">
        <h2 className="text-2xl font-bold tracking-tight text-balance md:text-3xl">
          Готов ли си да намериш следващия си любим инструмент?
        </h2>
        <p className="max-w-lg text-indigo-200">Каталогът расте с всяко предложение от екипа.</p>
        <HomeCallToAction className="bg-white text-indigo-950 hover:bg-indigo-100" />
      </section>
    </div>
  );
}
