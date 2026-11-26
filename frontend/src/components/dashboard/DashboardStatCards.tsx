import { Box, Heart, UsersRound, type LucideIcon } from "lucide-react";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent } from "@/components/ui/card";
import type { DashboardStats } from "@/lib/api";
import { cn } from "@/lib/utils";

const numberFormatter = new Intl.NumberFormat("bg-BG");

type Stat = {
  label: string;
  // Null for a feature that is not built yet.
  value: number | null;
  icon: LucideIcon;
  iconClassName: string;
};

export default function DashboardStatCards({ stats }: { stats: DashboardStats }) {
  const items: Stat[] = [
    {
      label: "Общ брой инструменти",
      value: stats.tools_total,
      icon: Box,
      iconClassName: "bg-violet-100 text-violet-600",
    },
    {
      label: "Инструменти, съвпадащи с твоята професия",
      value: stats.tools_for_company_role,
      icon: UsersRound,
      iconClassName: "bg-emerald-100 text-emerald-600",
    },
    {
      label: "Любими",
      value: stats.favorite_tools,
      icon: Heart,
      iconClassName: "bg-rose-100 text-rose-500",
    },
  ];

  return (
    <section aria-label="Статистика" className="grid grid-cols-1 gap-4 sm:grid-cols-3">
      {items.map((item) => (
        <StatCard key={item.label} {...item} />
      ))}
    </section>
  );
}

function StatCard({ label, value, icon: Icon, iconClassName }: Stat) {
  return (
    <Card className="shadow-sm">
      <CardContent className="flex flex-col gap-3">
        <div className="flex items-start justify-between">
          <span className={cn("flex size-11 items-center justify-center rounded-xl", iconClassName)}>
            <Icon className="size-6" />
          </span>
          {value === null && <Badge variant="secondary">Скоро</Badge>}
        </div>
        <p className="text-3xl font-bold tabular-nums text-slate-900">
          {value === null ? "—" : numberFormatter.format(value)}
        </p>
        <p className="text-sm font-medium text-slate-700">{label}</p>
      </CardContent>
    </Card>
  );
}
