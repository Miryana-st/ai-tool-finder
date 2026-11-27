import type { Metadata } from "next";
import RequireAuth from "@/components/RequireAuth";
import CategoryList from "@/components/categories/CategoryList";

export const metadata: Metadata = {
  title: "Категории | AI-Tool-Finder",
};

export default function CategoriesPage() {
  return (
    <RequireAuth>
      <CategoryList />
    </RequireAuth>
  );
}
