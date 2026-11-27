import type { Metadata } from "next";
import RequireAuth from "@/components/RequireAuth";
import ProfileOverview from "@/components/profile/ProfileOverview";

export const metadata: Metadata = {
  title: "Профил | AI-Tool-Finder",
};

export default function ProfilePage() {
  return (
    <RequireAuth>
      <ProfileOverview />
    </RequireAuth>
  );
}
