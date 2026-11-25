import DashboardOverview from "@/components/dashboard/DashboardOverview";
import RequireAuth from "@/components/RequireAuth";

export default function DashboardPage() {
  return (
    <RequireAuth>
      <DashboardOverview />
    </RequireAuth>
  );
}
