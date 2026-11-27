"use client";

import Link from "next/link";
import { ArrowRight } from "lucide-react";
import { useAuth } from "@/context/AuthContext";
import { cn } from "@/lib/utils";

// "Вход" for guests, "Към таблото" for signed-in users; nothing while the session loads.
export default function HomeCallToAction({ className }: { className?: string }) {
  const { status } = useAuth();

  if (status === "loading") {
    return <div className={cn("h-11", className)} aria-hidden="true" />;
  }

  const signedIn = status === "authenticated";

  return (
    <Link
      href={signedIn ? "/dashboard" : "/login"}
      className={cn(
        "inline-flex h-11 items-center justify-center gap-2 rounded-lg bg-indigo-600 px-6 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-indigo-500 focus-visible:ring-3 focus-visible:ring-indigo-500/40 focus-visible:outline-none",
        className,
      )}
    >
      {signedIn ? "Към таблото" : "Вход"}
      <ArrowRight className="size-4" aria-hidden="true" />
    </Link>
  );
}
