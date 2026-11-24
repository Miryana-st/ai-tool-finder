"use client";

import { useRouter } from "next/navigation";
import { useEffect, type ReactNode } from "react";
import { useAuth } from "@/context/AuthContext";

// Client-side guard: sends guests to /login. Data itself is protected by the backend (auth:sanctum).
export default function RequireAuth({ children }: { children: ReactNode }) {
  const { status } = useAuth();
  const router = useRouter();

  useEffect(() => {
    if (status === "guest") {
      router.replace("/login");
    }
  }, [status, router]);

  if (status !== "authenticated") {
    return <p className="text-zinc-600 dark:text-zinc-400">Зареждане…</p>;
  }

  return children;
}
