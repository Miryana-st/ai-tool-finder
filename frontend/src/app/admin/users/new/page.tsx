import type { Metadata } from "next";
import RequireAuth from "@/components/RequireAuth";
import AdminOnly from "@/components/admin/AdminOnly";
import UserCreateForm from "@/components/admin/UserCreateForm";

export const metadata: Metadata = {
  title: "Добави потребител | AI-Tool-Finder",
};

export default function AdminNewUserPage() {
  return (
    <RequireAuth>
      <AdminOnly>
        <UserCreateForm />
      </AdminOnly>
    </RequireAuth>
  );
}
