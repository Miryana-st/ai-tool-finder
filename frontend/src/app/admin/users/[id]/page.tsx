import type { Metadata } from "next";
import RequireAuth from "@/components/RequireAuth";
import AdminOnly from "@/components/admin/AdminOnly";
import UserEditForm from "@/components/admin/UserEditForm";

export const metadata: Metadata = {
  title: "Редактирай потребител | AI-Finder",
};

export default function AdminEditUserPage() {
  return (
    <RequireAuth>
      <AdminOnly>
        <UserEditForm />
      </AdminOnly>
    </RequireAuth>
  );
}
