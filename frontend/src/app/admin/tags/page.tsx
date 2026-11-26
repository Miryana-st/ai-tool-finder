import type { Metadata } from "next";
import RequireAuth from "@/components/RequireAuth";
import TaxonomyManager from "@/components/admin/TaxonomyManager";

export const metadata: Metadata = {
  title: "Тагове | AI-Finder",
};

export default function AdminTagsPage() {
  return (
    <RequireAuth>
      <TaxonomyManager kind="tags" />
    </RequireAuth>
  );
}
