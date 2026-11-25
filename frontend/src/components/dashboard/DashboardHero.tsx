import type { User } from "@/lib/api";
import RobotIllustration from "./RobotIllustration";

export default function DashboardHero({ user }: { user: User }) {
  return (
    <section className="relative overflow-hidden rounded-2xl bg-linear-to-r from-violet-100 via-indigo-50 to-violet-200 px-6 py-10 md:px-10">
      <div className="relative z-10 flex max-w-xl flex-col gap-6">
        <h1 className="text-3xl font-bold tracking-tight text-slate-900 md:text-4xl">
          Добре дошъл, {user.name}!
        </h1>
        <p className="text-lg text-slate-700">
          Ти си с роля: {user.company_role_label}
          {user.system_role === "admin" && ` (${user.system_role_label})`}
        </p>
      </div>
      <RobotIllustration className="pointer-events-none absolute -right-4 bottom-0 hidden h-full max-h-60 lg:block" />
    </section>
  );
}
