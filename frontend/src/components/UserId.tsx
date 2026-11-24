"use client";

import { useEffect, useState } from "react";

type User = {
  id: number;
};

const apiUrl = process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000";

// Shows the logged-in user's ID. The full auth context (useAuth) comes with the login flow (#6).
export default function UserId() {
  const [user, setUser] = useState<User | null>(null);

  useEffect(() => {
    const controller = new AbortController();

    fetch(`${apiUrl}/api/user`, {
      credentials: "include",
      headers: { Accept: "application/json" },
      signal: controller.signal,
    })
      .then(async (response) => {
        // 401 simply means "not logged in" - nothing to show.
        if (!response.ok) {
          return;
        }

        const body: { data: User } = await response.json();
        setUser(body.data);
      })
      .catch((error: unknown) => {
        if (error instanceof DOMException && error.name === "AbortError") {
          return;
        }
        console.error("Could not load the current user.", error);
      });

    return () => controller.abort();
  }, []);

  if (!user) {
    return null;
  }

  return (
    <span className="text-sm text-zinc-600 dark:text-zinc-400">
      ID: {user.id}
    </span>
  );
}
