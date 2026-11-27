"use client";

import Link from "next/link";
import { useAuth } from "@/context/AuthContext";

// "Вход" for guests, nothing while the session is loading. Logged-in users get
// the sidebar layout instead of this header, with "Изход" in the user menu.
export default function AuthButton() {
  const { status } = useAuth();

  if (status === "loading") {
    return null;
  }

  return (
    <Link
      href="/login"
      className="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500"
    >
      Вход
    </Link>
  );
}
