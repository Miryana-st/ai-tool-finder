import type { Metadata } from "next";
import { Suspense } from "react";
import RequireAuth from "@/components/RequireAuth";
import AdminOnly from "@/components/admin/AdminOnly";
import ActivityLog from "@/components/admin/ActivityLog";

export const metadata: Metadata = {
  title: "История на активността | AI-Finder",
};

// ActivityLog reads the filters from the URL (useSearchParams), so it needs a Suspense boundary.
export default function AdminActivityPage() {
  return (
    <RequireAuth>
      <AdminOnly>
        <Suspense fallback={<p className="text-zinc-600 dark:text-zinc-400">Зареждане…</p>}>
          <ActivityLog />
        </Suspense>
      </AdminOnly>
    </RequireAuth>
  );
}
