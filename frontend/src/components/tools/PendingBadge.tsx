import { Clock } from "lucide-react";
import { cn } from "@/lib/utils";

// Marks a tool that waits for an admin; only its creator and admins see such tools.
export default function PendingBadge({ className }: { className?: string }) {
  return (
    <span
      title="Инструментът ще се вижда от всички след одобрение от администратор."
      className={cn(
        "inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium whitespace-nowrap text-amber-800 dark:bg-amber-500/15 dark:text-amber-300",
        className,
      )}
    >
      <Clock className="size-3.5" aria-hidden="true" />
      Чака одобрение
    </span>
  );
}
