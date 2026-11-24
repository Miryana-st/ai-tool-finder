"use client";

import { useAuth } from "@/context/AuthContext";

export default function DashboardGreeting() {
  const { user } = useAuth();

  if (!user) {
    return null;
  }

  return (
    <h1 className="text-3xl font-semibold tracking-tight">
      Добре дошъл, {user.name}! Ти си с роля: {user.role_label}.
    </h1>
  );
}
