"use client";

import { ChevronDown, LogOut } from "lucide-react";
import { useState } from "react";
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuGroup,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { useAuth } from "@/context/AuthContext";
import UserAvatar from "./UserAvatar";

// The avatar in the top bar: who is logged in (name, email, ID) and logout.
export default function UserMenu() {
  const { user, logout } = useAuth();
  const [isLoggingOut, setIsLoggingOut] = useState(false);

  if (!user) {
    return null;
  }

  const handleLogout = async () => {
    setIsLoggingOut(true);
    try {
      await logout();
    } catch (error) {
      console.error("Logout failed.", error);
    } finally {
      setIsLoggingOut(false);
    }
  };

  return (
    <DropdownMenu>
      <DropdownMenuTrigger
        aria-label="Профил"
        className="flex items-center gap-1.5 rounded-full p-0.5 outline-none hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring"
      >
        <UserAvatar name={user.name} />
        <ChevronDown className="size-4 text-muted-foreground" />
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end" className="w-60">
        <DropdownMenuGroup>
          <DropdownMenuLabel className="flex flex-col gap-0.5 py-1.5">
            <span className="text-sm text-foreground">{user.name}</span>
            <span className="truncate font-normal">{user.email}</span>
            <span className="font-normal">ID: {user.id}</span>
          </DropdownMenuLabel>
        </DropdownMenuGroup>
        <DropdownMenuSeparator />
        <DropdownMenuItem
          variant="destructive"
          disabled={isLoggingOut}
          onClick={handleLogout}
        >
          <LogOut />
          Изход
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  );
}
