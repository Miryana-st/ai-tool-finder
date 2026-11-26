"use client";

import { ChevronRight, Circle, ShieldCheck } from "lucide-react";
import Link from "next/link";
import { usePathname } from "next/navigation";
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from "@/components/ui/collapsible";
import {
  Sidebar,
  SidebarContent,
  SidebarFooter,
  SidebarGroup,
  SidebarGroupContent,
  SidebarGroupLabel,
  SidebarHeader,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
  SidebarMenuSkeleton,
  SidebarMenuSub,
  SidebarMenuSubButton,
  SidebarMenuSubItem,
  SidebarSeparator,
} from "@/components/ui/sidebar";
import { useAuth } from "@/context/AuthContext";
import { useDashboard } from "@/context/DashboardContext";
import type { MenuItem } from "@/lib/api";
import UserAvatar from "./UserAvatar";
import LogoMark from "./LogoMark";
import { MAIN_NAV, type NavItem } from "./navigation";

// The tools list is in the main menu, so it is left out of the role menu from the backend.
const MAIN_NAV_MENU_KEYS = ["tools"];

const menuButtonClassName =
  "h-11 gap-3 px-3 text-[15px] data-active:bg-sidebar-primary data-active:text-sidebar-primary-foreground data-active:shadow-md [&_svg]:size-5";

// The most specific href that contains the current page, so /tools/new does not also mark /tools.
function activeHref(pathname: string, hrefs: string[]): string | null {
  return (
    hrefs
      .filter((href) => pathname === href || pathname.startsWith(`${href}/`))
      .sort((a, b) => b.length - a.length)[0] ?? null
  );
}

export default function AppSidebar() {
  const pathname = usePathname();
  const { user } = useAuth();
  const dashboard = useDashboard();

  const roleItems =
    dashboard.status === "ready"
      ? dashboard.dashboard.menu.filter((item) => !MAIN_NAV_MENU_KEYS.includes(item.key))
      : [];

  const hrefs = [...MAIN_NAV, ...roleItems]
    .map((item) => item.href)
    .filter((href): href is string => href !== null);
  const current = activeHref(pathname, hrefs);
  const isAdmin = user?.system_role === "admin";

  return (
    <Sidebar>
      <SidebarHeader className="px-4 pt-6 pb-4">
        <Link href="/dashboard" className="flex items-center gap-3">
          <LogoMark className="size-11 shrink-0" />
          <span className="text-xl leading-tight font-bold">
            AI Tool
            <br />
            Finder
          </span>
        </Link>
      </SidebarHeader>

      <SidebarContent className="px-2">
        <SidebarGroup>
          <SidebarGroupContent>
            <SidebarMenu className="gap-1.5">
              {MAIN_NAV.map((item) => (
                <SidebarMenuItem key={item.label}>
                  <NavButton item={item} isActive={item.href === current} />
                </SidebarMenuItem>
              ))}
            </SidebarMenu>
          </SidebarGroupContent>
        </SidebarGroup>

        <SidebarSeparator className="mx-4" />

        {dashboard.status === "loading" && (
          <SidebarGroup>
            <SidebarMenuSkeleton showIcon />
            <SidebarMenuSkeleton showIcon />
          </SidebarGroup>
        )}

        {dashboard.status === "error" && (
          <p role="alert" className="px-4 text-sm text-sidebar-foreground/70">
            Менюто не можа да се зареди.
          </p>
        )}

        {roleItems.length > 0 &&
          (isAdmin ? (
            <AdminMenu items={roleItems} current={current} />
          ) : (
            <SidebarGroup>
              <SidebarGroupLabel className="text-sidebar-foreground/60">
                За твоята роля
              </SidebarGroupLabel>
              <SidebarGroupContent>
                <SidebarMenu className="gap-1.5">
                  {roleItems.map((item) => (
                    <SidebarMenuItem key={item.key}>
                      <NavButton
                        item={{ label: item.label, href: item.href, icon: Circle }}
                        isActive={item.href !== null && item.href === current}
                      />
                    </SidebarMenuItem>
                  ))}
                </SidebarMenu>
              </SidebarGroupContent>
            </SidebarGroup>
          ))}
      </SidebarContent>

      {user && (
        <SidebarFooter className="border-t border-sidebar-border px-4 py-4">
          <div className="flex items-center gap-3">
            <UserAvatar name={user.name} className="size-10" />
            <div className="flex min-w-0 flex-col">
              <span className="truncate text-sm font-medium">{user.name}</span>
              <span className="truncate text-xs text-sidebar-foreground/60">
                {user.company_role_label}
                {isAdmin && ` · ${user.system_role_label}`}
              </span>
            </div>
          </div>
        </SidebarFooter>
      )}
    </Sidebar>
  );
}

function NavButton({ item, isActive }: { item: NavItem; isActive: boolean }) {
  const Icon = item.icon;

  if (item.href === null) {
    return (
      <SidebarMenuButton aria-disabled="true" className={menuButtonClassName}>
        <Icon />
        <span>{item.label}</span>
        <span className="ml-auto text-xs">Скоро</span>
      </SidebarMenuButton>
    );
  }

  return (
    <SidebarMenuButton
      isActive={isActive}
      className={menuButtonClassName}
      render={<Link href={item.href} />}
    >
      <Icon />
      <span>{item.label}</span>
    </SidebarMenuButton>
  );
}

// Admins get the admin menu from the backend, folded under "Админ".
function AdminMenu({ items, current }: { items: MenuItem[]; current: string | null }) {
  const hasActiveItem = items.some((item) => item.href !== null && item.href === current);

  return (
    <SidebarGroup>
      <SidebarMenu>
        <Collapsible defaultOpen={hasActiveItem} render={<SidebarMenuItem />}>
          <CollapsibleTrigger
            render={<SidebarMenuButton className={menuButtonClassName} />}
          >
            <ShieldCheck />
            <span>Админ</span>
            <ChevronRight className="ml-auto size-4! transition-transform group-data-panel-open/menu-button:rotate-90" />
          </CollapsibleTrigger>
          <CollapsibleContent>
            <SidebarMenuSub className="mt-1">
              {items.map((item) => (
                <SidebarMenuSubItem key={item.key}>
                  {item.href ? (
                    <SidebarMenuSubButton
                      isActive={item.href === current}
                      className="h-9"
                      render={<Link href={item.href} />}
                    >
                      <span>{item.label}</span>
                    </SidebarMenuSubButton>
                  ) : (
                    <SidebarMenuSubButton aria-disabled="true" className="h-9">
                      <span>{item.label}</span>
                      <span className="ml-auto text-xs">Скоро</span>
                    </SidebarMenuSubButton>
                  )}
                </SidebarMenuSubItem>
              ))}
            </SidebarMenuSub>
          </CollapsibleContent>
        </Collapsible>
      </SidebarMenu>
    </SidebarGroup>
  );
}
