import type { Metadata } from "next";
import RequireAuth from "@/components/RequireAuth";
import AdminOnly from "@/components/admin/AdminOnly";
import CompanyRoleManager from "@/components/admin/CompanyRoleManager";

export const metadata: Metadata = {
  title: "Длъжности | AI-Finder",
};

export default function AdminCompanyRolesPage() {
  return (
    <RequireAuth>
      <AdminOnly>
        <CompanyRoleManager />
      </AdminOnly>
    </RequireAuth>
  );
}
