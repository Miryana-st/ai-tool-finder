"use client";

import Link from "next/link";
import type { ReactNode } from "react";
import { useAuth } from "@/context/AuthContext";
import { mutedTextClassName } from "@/styles/classNames";

// The backend allows admin pages to admins only; this check just shows a clear
// message instead of a failed request. Use it inside RequireAuth.
export default function AdminOnly({ children }: { children: ReactNode }) {
  const { user } = useAuth();

  if (user?.system_role !== "admin") {
    return (
      <div className="flex flex-col gap-3">
        <p role="alert" className={mutedTextClassName}>
          Тази страница е само за администратори.
        </p>
        <Link href="/dashboard" className="text-sm text-indigo-600 hover:underline">
          ← Към таблото
        </Link>
      </div>
    );
  }

  return children;
}
