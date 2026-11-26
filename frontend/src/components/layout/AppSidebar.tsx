"use client";

import { ChevronRight, ShieldCheck } from "lucide-react";
import Link from "next/link";
import { usePathname } from "next/navigation";
import { useState } from "react";
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from "@/components/ui/collapsible";
import {
  Sidebar,
  SidebarContent,
  SidebarFooter,
  SidebarGroup,
  SidebarGroupContent,
  SidebarHeader,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
  SidebarMenuSub,
  SidebarMenuSubButton,
  SidebarMenuSubItem,
  SidebarSeparator,
  useSidebar,
} from "@/components/ui/sidebar";
import { useAuth } from "@/context/AuthContext";
import UserAvatar from "./UserAvatar";
import LogoMark from "./LogoMark";
import { activeHref, ADMIN_NAV, MAIN_NAV, type NavItem } from "./navigation";

const menuButtonClassName =
  "h-11 gap-3 px-3 text-[15px] data-active:bg-sidebar-primary data-active:text-sidebar-primary-foreground data-active:shadow-md [&_svg]:size-5";

export default function AppSidebar() {
  const pathname = usePathname();
  const { user } = useAuth();
  const isAdmin = user?.system_role === "admin";
  const closeMobileMenu = useCloseMobileMenu();

  const hrefs = [...MAIN_NAV, ...(isAdmin ? ADMIN_NAV : [])]
    .map((item) => item.href)
    .filter((href): href is string => href !== null);
  const current = activeHref(pathname, hrefs);

  return (
    <Sidebar>
      <SidebarHeader className="px-4 pt-6 pb-4">
        <Link href="/dashboard" onClick={closeMobileMenu} className="flex items-center gap-3">
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

        {isAdmin && (
          <>
            <SidebarSeparator className="mx-4" />
            <AdminMenu current={current} />
          </>
        )}
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

// On a phone the menu is a sheet over the page; picking a link closes it.
function useCloseMobileMenu(): () => void {
  const { isMobile, setOpenMobile } = useSidebar();

  return () => {
    if (isMobile) {
      setOpenMobile(false);
    }
  };
}

function NavButton({ item, isActive }: { item: NavItem; isActive: boolean }) {
  const Icon = item.icon;
  const closeMobileMenu = useCloseMobileMenu();

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
      render={<Link href={item.href} onClick={closeMobileMenu} />}
    >
      <Icon />
      <span>{item.label}</span>
    </SidebarMenuButton>
  );
}

// The admin pages, folded under "Админ". The menu opens by itself when an admin
// page is opened (also by a link from elsewhere) and can be folded by hand.
function AdminMenu({ current }: { current: string | null }) {
  const closeMobileMenu = useCloseMobileMenu();
  const hasActiveItem = ADMIN_NAV.some((item) => item.href === current);
  const [open, setOpen] = useState(hasActiveItem);
  const [wasActive, setWasActive] = useState(hasActiveItem);

  // Controlled, because Base UI reads `defaultOpen` only once.
  if (hasActiveItem !== wasActive) {
    setWasActive(hasActiveItem);
    if (hasActiveItem) {
      setOpen(true);
    }
  }

  return (
    <SidebarGroup>
      <SidebarMenu>
        <Collapsible open={open} onOpenChange={setOpen} render={<SidebarMenuItem />}>
          <CollapsibleTrigger
            render={<SidebarMenuButton className={menuButtonClassName} />}
          >
            <ShieldCheck />
            <span>Админ</span>
            <ChevronRight className="ml-auto size-4! transition-transform group-data-panel-open/menu-button:rotate-90" />
          </CollapsibleTrigger>
          <CollapsibleContent>
            <SidebarMenuSub className="mt-1">
              {ADMIN_NAV.map((item) => (
                <SidebarMenuSubItem key={item.href}>
                  <SidebarMenuSubButton
                    isActive={item.href === current}
                    className="h-9"
                    render={<Link href={item.href} onClick={closeMobileMenu} />}
                  >
                    <span>{item.label}</span>
                  </SidebarMenuSubButton>
                </SidebarMenuSubItem>
              ))}
            </SidebarMenuSub>
          </CollapsibleContent>
        </Collapsible>
      </SidebarMenu>
    </SidebarGroup>
  );
}
