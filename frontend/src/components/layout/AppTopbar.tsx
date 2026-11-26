import Link from "next/link";
import Clock from "@/components/Clock";
import { Separator } from "@/components/ui/separator";
import { SidebarTrigger } from "@/components/ui/sidebar";
import LogoMark from "./LogoMark";
import UserMenu from "./UserMenu";

// On phones the sidebar is replaced by MobileNav under this bar, so the bar shows
// the logo instead of the button that opens the sidebar.
export default function AppTopbar() {
  return (
    <header className="sticky top-0 z-10 flex h-14 shrink-0 items-center gap-3 border-b bg-background/80 px-4 backdrop-blur md:h-16 md:px-8">
      <SidebarTrigger className="-ml-1 max-md:hidden" />
      <Separator orientation="vertical" className="h-5 max-md:hidden" />
      <Link href="/dashboard" className="flex items-center gap-2 md:hidden">
        <LogoMark className="size-8" />
        <span className="text-sm leading-tight font-bold">AI Tool Finder</span>
      </Link>
      <Clock />
      <div className="ml-auto">
        <UserMenu />
      </div>
    </header>
  );
}
