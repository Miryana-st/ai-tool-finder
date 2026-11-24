import DashboardGreeting from "@/components/DashboardGreeting";
import RequireAuth from "@/components/RequireAuth";

// The role-based menu comes with #7.
export default function DashboardPage() {
  return (
    <RequireAuth>
      <section className="flex flex-col gap-4">
        <DashboardGreeting />
      </section>
    </RequireAuth>
  );
}
