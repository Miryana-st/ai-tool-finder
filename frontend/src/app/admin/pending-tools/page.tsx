import type { Metadata } from "next";
import RequireAuth from "@/components/RequireAuth";
import AdminOnly from "@/components/admin/AdminOnly";
import PendingTools from "@/components/admin/PendingTools";

export const metadata: Metadata = {
  title: "Чакащи инструменти | AI-Tool-Finder",
};

export default function AdminPendingToolsPage() {
  return (
    <RequireAuth>
      <AdminOnly>
        <PendingTools />
      </AdminOnly>
    </RequireAuth>
  );
}
