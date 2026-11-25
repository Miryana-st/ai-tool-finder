import type { Metadata } from "next";
import RequireAuth from "@/components/RequireAuth";
import PendingCategories from "@/components/admin/PendingCategories";

export const metadata: Metadata = {
  title: "Предложени категории | AI-Finder",
};

export default function AdminCategoriesPage() {
  return (
    <RequireAuth>
      <PendingCategories />
    </RequireAuth>
  );
}
