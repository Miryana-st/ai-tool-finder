import DashboardGreeting from "@/components/DashboardGreeting";
import DashboardMenu from "@/components/DashboardMenu";
import RequireAuth from "@/components/RequireAuth";

export default function DashboardPage() {
  return (
    <RequireAuth>
      <section className="flex flex-col gap-6">
        <DashboardGreeting />
        <DashboardMenu />
      </section>
    </RequireAuth>
  );
}
