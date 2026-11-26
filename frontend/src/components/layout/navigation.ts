import { Heart, House, LayoutGrid, SquarePlus, UserRound, type LucideIcon } from "lucide-react";

// `shortLabel` is used in the phone menu bar, where space is tight.
export type NavItem = { label: string; shortLabel: string; href: string | null; icon: LucideIcon };

// The main menu of the sidebar; its labels also name the breadcrumbs.
// A null href is a page that does not exist yet.
export const MAIN_NAV: NavItem[] = [
  { label: "Табло", shortLabel: "Табло", href: "/dashboard", icon: House },
  { label: "Списък с инструменти", shortLabel: "Инструменти", href: "/tools", icon: LayoutGrid },
  { label: "Добави инструмент", shortLabel: "Добави", href: "/tools/new", icon: SquarePlus },
  { label: "Любими", shortLabel: "Любими", href: "/favorites", icon: Heart },
  { label: "Профил", shortLabel: "Профил", href: "/profile", icon: UserRound },
];

export type AdminNavItem = { label: string; href: string };

// The admin pages, shown under "Админ" in the sidebar to admins only. The backend
// guards the pages themselves; this list only decides what the sidebar shows.
export const ADMIN_NAV: AdminNavItem[] = [
  { label: "Потребители", href: "/admin/users" },
  { label: "Чакащи инструменти", href: "/admin/pending-tools" },
  { label: "Длъжности", href: "/admin/company-roles" },
  { label: "Категории", href: "/admin/categories" },
  { label: "Тагове", href: "/admin/tags" },
];

export const HOME_HREF = "/dashboard";

// The most specific href that contains the current page, so /tools/new does not also mark /tools.
export function activeHref(pathname: string, hrefs: string[]): string | null {
  return (
    hrefs
      .filter((href) => pathname === href || pathname.startsWith(`${href}/`))
      .sort((a, b) => b.length - a.length)[0] ?? null
  );
}
