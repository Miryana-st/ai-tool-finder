import Link from "next/link";
import { ArrowRight } from "lucide-react";
import { cn } from "@/lib/utils";

// The "Вход" button of the home page, which only guests see (see GuestOnly).
export default function HomeCallToAction({ className }: { className?: string }) {
  return (
    <Link
      href="/login"
      className={cn(
        "inline-flex h-11 items-center justify-center gap-2 rounded-lg bg-indigo-600 px-6 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-indigo-500 focus-visible:ring-3 focus-visible:ring-indigo-500/40 focus-visible:outline-none",
        className,
      )}
    >
      Вход
      <ArrowRight className="size-4" aria-hidden="true" />
    </Link>
  );
}
