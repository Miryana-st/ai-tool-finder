"use client";

import Link from "next/link";
import { useState } from "react";
import { useAuth } from "@/context/AuthContext";

const buttonClassName =
  "rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500 disabled:opacity-60";

// "Вход" for guests, "Изход" for logged-in users, nothing while the session is loading.
export default function AuthButton() {
  const { status, logout } = useAuth();
  const [isLoggingOut, setIsLoggingOut] = useState(false);

  if (status === "loading") {
    return null;
  }

  if (status === "guest") {
    return (
      <Link href="/login" className={buttonClassName}>
        Вход
      </Link>
    );
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
    <button
      type="button"
      onClick={handleLogout}
      disabled={isLoggingOut}
      className={buttonClassName}
    >
      Изход
    </button>
  );
}
