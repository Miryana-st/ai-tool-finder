import { Heart, House, LayoutGrid, SquarePlus, UserRound, type LucideIcon } from "lucide-react";

export type NavItem = { label: string; href: string | null; icon: LucideIcon };

// The main menu of the sidebar; its labels also name the breadcrumbs.
// A null href is a page that does not exist yet.
export const MAIN_NAV: NavItem[] = [
  { label: "Табло", href: "/dashboard", icon: House },
  { label: "Списък с инструменти", href: "/tools", icon: LayoutGrid },
  { label: "Добави инструмент", href: "/tools/new", icon: SquarePlus },
  { label: "Любими", href: "/favorites", icon: Heart },
  { label: "Профил", href: "/profile", icon: UserRound },
];

export type AdminNavItem = { label: string; href: string };

// The admin pages, shown under "Админ" in the sidebar to admins only. The backend
// guards the pages themselves; this list only decides what the sidebar shows.
export const ADMIN_NAV: AdminNavItem[] = [
  { label: "Потребители", href: "/admin/users" },
  { label: "Длъжности", href: "/admin/company-roles" },
  { label: "Категории", href: "/admin/categories" },
  { label: "Тагове", href: "/admin/tags" },
];

export const HOME_HREF = "/dashboard";
