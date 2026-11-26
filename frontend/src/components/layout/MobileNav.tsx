"use client";

import { ShieldCheck } from "lucide-react";
import Link from "next/link";
import { usePathname } from "next/navigation";
import type { ReactNode } from "react";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { useAuth } from "@/context/AuthContext";
import { cn } from "@/lib/utils";
import { activeHref, ADMIN_NAV, MAIN_NAV } from "./navigation";

const itemClassName =
  "flex min-w-0 flex-1 flex-col items-center gap-0.5 rounded-lg px-0.5 py-1.5 text-[10px] leading-tight font-medium tracking-tight transition-colors sm:text-[11px] [&_svg]:size-5";

const activeClassName = "bg-violet-600 text-white shadow-sm shadow-violet-600/30";
const idleClassName = "text-muted-foreground hover:bg-violet-50 hover:text-violet-700 dark:hover:bg-violet-500/10";

// On phones the sidebar becomes this bar under the top bar: an icon and a short
// label per page, and for admins an "Админ" item that opens the admin pages.
export default function MobileNav() {
  const pathname = usePathname();
  const { user } = useAuth();
  const isAdmin = user?.system_role === "admin";

  const hrefs = [...MAIN_NAV, ...(isAdmin ? ADMIN_NAV : [])]
    .map((item) => item.href)
    .filter((href): href is string => href !== null);
  const current = activeHref(pathname, hrefs);
  const isAdminPage = ADMIN_NAV.some((item) => item.href === current);

  return (
    <nav
      aria-label="Основно меню"
      className="sticky top-14 z-10 border-b bg-background/95 px-2 py-1.5 backdrop-blur md:hidden"
    >
      <ul className="flex gap-0.5">
        {MAIN_NAV.filter((item) => item.href !== null).map((item) => {
          const Icon = item.icon;
          const isActive = item.href === current;

          return (
            <li key={item.label} className="flex min-w-0 flex-auto">
              <Link
                href={item.href!}
                aria-current={isActive ? "page" : undefined}
                className={cn(itemClassName, isActive ? activeClassName : idleClassName)}
              >
                <Icon aria-hidden="true" />
                <NavLabel>{item.shortLabel}</NavLabel>
              </Link>
            </li>
          );
        })}

        {isAdmin && (
          <li className="flex min-w-0 flex-auto">
            <DropdownMenu>
              <DropdownMenuTrigger
                className={cn(itemClassName, "outline-none", isAdminPage ? activeClassName : idleClassName)}
              >
                <ShieldCheck aria-hidden="true" />
                <NavLabel>Админ</NavLabel>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="end" className="w-48">
                {ADMIN_NAV.map((item) => (
                  <DropdownMenuItem
                    key={item.href}
                    render={<Link href={item.href} />}
                    className={cn(item.href === current && "font-semibold text-violet-700 dark:text-violet-300")}
                  >
                    {item.label}
                  </DropdownMenuItem>
                ))}
              </DropdownMenuContent>
            </DropdownMenu>
          </li>
        )}
      </ul>
    </nav>
  );
}

function NavLabel({ children }: { children: ReactNode }) {
  return <span className="w-full truncate text-center">{children}</span>;
}
