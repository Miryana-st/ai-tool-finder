import { Avatar, AvatarFallback } from "@/components/ui/avatar";
import { cn } from "@/lib/utils";

// "Иван Иванов" → "ИИ". Users have no profile picture yet, so the avatar shows initials.
function initials(name: string): string {
  return name
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0].toUpperCase())
    .join("");
}

export default function UserAvatar({ name, className }: { name: string; className?: string }) {
  return (
    <Avatar className={cn("size-9", className)}>
      <AvatarFallback className="bg-linear-to-br from-violet-400 to-indigo-600 font-semibold text-white">
        {initials(name)}
      </AvatarFallback>
    </Avatar>
  );
}
