import type { Metadata } from "next";
import RequireAuth from "@/components/RequireAuth";
import TaxonomyManager from "@/components/admin/TaxonomyManager";

export const metadata: Metadata = {
  title: "Категории | AI-Finder",
};

export default function AdminCategoriesPage() {
  return (
    <RequireAuth>
      <TaxonomyManager kind="categories" />
    </RequireAuth>
  );
}
