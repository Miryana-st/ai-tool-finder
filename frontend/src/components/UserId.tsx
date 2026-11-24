"use client";

import { useAuth } from "@/context/AuthContext";

// Shows the logged-in user's ID.
export default function UserId() {
  const { user } = useAuth();

  if (!user) {
    return null;
  }

  return (
    <span className="text-sm text-zinc-600 dark:text-zinc-400">
      ID: {user.id}
    </span>
  );
}
