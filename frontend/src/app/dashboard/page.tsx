import RequireAuth from "@/components/RequireAuth";

// The greeting and role-based menu come with #7.
export default function DashboardPage() {
  return (
    <RequireAuth>
      <section className="flex flex-col gap-4">
        <h1 className="text-3xl font-semibold tracking-tight">Табло</h1>
      </section>
    </RequireAuth>
  );
}
