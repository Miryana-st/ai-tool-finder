import type { Metadata } from "next";
import { Suspense } from "react";
import RequireAuth from "@/components/RequireAuth";
import AdminOnly from "@/components/admin/AdminOnly";
import UserList from "@/components/admin/UserList";

export const metadata: Metadata = {
  title: "Потребители | AI-Tool-Finder",
};

// UserList reads the filters from the URL (useSearchParams), so it needs a Suspense boundary.
export default function AdminUsersPage() {
  return (
    <RequireAuth>
      <AdminOnly>
        <Suspense fallback={<p className="text-zinc-600 dark:text-zinc-400">Зареждане…</p>}>
          <UserList />
        </Suspense>
      </AdminOnly>
    </RequireAuth>
  );
}
