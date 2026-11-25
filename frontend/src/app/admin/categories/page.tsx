import type { Metadata } from "next";
import RequireAuth from "@/components/RequireAuth";
import PendingSuggestions from "@/components/admin/PendingSuggestions";

export const metadata: Metadata = {
  title: "Предложени категории | AI-Finder",
};

export default function AdminCategoriesPage() {
  return (
    <RequireAuth>
      <PendingSuggestions kind="categories" />
    </RequireAuth>
  );
}
