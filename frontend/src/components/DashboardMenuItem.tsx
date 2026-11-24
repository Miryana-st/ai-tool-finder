import Link from "next/link";
import type { MenuItem } from "@/lib/api";

const baseClassName =
  "flex items-center justify-between rounded-lg border px-4 py-3 text-sm font-medium";

// A single menu entry: a link when it has an href, otherwise a "coming soon" placeholder.
export default function DashboardMenuItem({ item }: { item: MenuItem }) {
  if (item.href) {
    return (
      <Link
        href={item.href}
        className={`${baseClassName} border-zinc-200 bg-white hover:border-indigo-500 hover:text-indigo-600 dark:border-zinc-800 dark:bg-zinc-950`}
      >
        {item.label}
      </Link>
    );
  }

  return (
    <span
      aria-disabled="true"
      className={`${baseClassName} cursor-not-allowed border-dashed border-zinc-300 text-zinc-500 dark:border-zinc-700`}
    >
      {item.label}
      <span className="text-xs font-normal">Скоро</span>
    </span>
  );
}
