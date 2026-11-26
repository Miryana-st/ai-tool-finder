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

export default function UserAvatar({
  name,
  className,
  initialsClassName,
}: {
  name: string;
  className?: string;
  // E.g. a bigger text size for a big avatar.
  initialsClassName?: string;
}) {
  return (
    <Avatar className={cn("size-9", className)}>
      <AvatarFallback className={cn("bg-linear-to-br from-violet-400 to-indigo-600 font-semibold text-white", initialsClassName)}>
        {initials(name)}
      </AvatarFallback>
    </Avatar>
  );
}
