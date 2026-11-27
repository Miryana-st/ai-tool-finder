"use client";

import { useRouter } from "next/navigation";
import { useEffect, type ReactNode } from "react";
import { useAuth } from "@/context/AuthContext";

// The opposite of RequireAuth: sends signed-in users to the dashboard and shows
// the children to guests only. Nothing is shown while the session is checked,
// so signed-in users do not see the guest page flash before the redirect.
export default function GuestOnly({ children }: { children: ReactNode }) {
  const { status } = useAuth();
  const router = useRouter();

  useEffect(() => {
    if (status === "authenticated") {
      router.replace("/dashboard");
    }
  }, [status, router]);

  if (status !== "guest") {
    return null;
  }

  return children;
}
