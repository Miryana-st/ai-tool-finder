import type { Metadata } from "next";
import RequireAuth from "@/components/RequireAuth";
import PendingSuggestions from "@/components/admin/PendingSuggestions";

export const metadata: Metadata = {
  title: "Предложени тагове | AI-Finder",
};

export default function AdminTagsPage() {
  return (
    <RequireAuth>
      <PendingSuggestions kind="tags" />
    </RequireAuth>
  );
}
