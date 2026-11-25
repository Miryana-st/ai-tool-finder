import Clock from "@/components/Clock";
import { Separator } from "@/components/ui/separator";
import { SidebarTrigger } from "@/components/ui/sidebar";
import UserMenu from "./UserMenu";

export default function AppTopbar() {
  return (
    <header className="sticky top-0 z-10 flex h-16 shrink-0 items-center gap-3 border-b bg-background/80 px-4 backdrop-blur md:px-8">
      <SidebarTrigger className="-ml-1" />
      <Separator orientation="vertical" className="h-5" />
      <Clock />
      <div className="ml-auto">
        <UserMenu />
      </div>
    </header>
  );
}
