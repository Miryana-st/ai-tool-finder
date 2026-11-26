"use client";

import {
  Box,
  BriefcaseBusiness,
  Crown,
  Heart,
  KeyRound,
  LayoutGrid,
  Pencil,
  Settings,
  ShieldCheck,
  UserRound,
  type LucideIcon,
} from "lucide-react";
import { useEffect, useId, useRef, useState, type ReactNode } from "react";
import UserAvatar from "@/components/layout/UserAvatar";
import { buttonVariants } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import { useAuth } from "@/context/AuthContext";
import { cn } from "@/lib/utils";
import ChangePasswordForm from "./ChangePasswordForm";
import ProfileInformationForm from "./ProfileInformationForm";
import ProfileToolList from "./ProfileToolList";
import TwoFactorSettings from "./TwoFactorSettings";
import { useProfileTools } from "./useProfileTools";

const numberFormatter = new Intl.NumberFormat("bg-BG");

const cardClassName = "rounded-2xl border bg-card text-card-foreground shadow-sm";

type Tab = "mine" | "favorites" | "settings";

// `shortLabel` fits the three tabs on a phone screen.
const TABS: { id: Tab; label: string; shortLabel: string; icon: LucideIcon }[] = [
  { id: "mine", label: "Моите инструменти", shortLabel: "Моите", icon: LayoutGrid },
  { id: "favorites", label: "Любими", shortLabel: "Любими", icon: Heart },
  { id: "settings", label: "Настройки", shortLabel: "Настройки", icon: Settings },
];

export default function ProfileOverview() {
  const { user } = useAuth();
  const [tab, setTab] = useState<Tab>("mine");
  const mine = useProfileTools("mine");
  const favorites = useProfileTools("favorites");
  const tabsId = useId();
  const tabsRef = useRef<HTMLDivElement>(null);
  const nameInputRef = useRef<HTMLInputElement>(null);
  // Counts the clicks on "Редактирай профила"; 0 until the first one.
  const [editRequests, setEditRequests] = useState(0);

  // "Редактирай профила" opens the settings tab and moves the focus to the name field.
  useEffect(() => {
    if (editRequests > 0) {
      tabsRef.current?.scrollIntoView({ behavior: "smooth", block: "start" });
      nameInputRef.current?.focus({ preventScroll: true });
    }
  }, [editRequests]);

  if (!user) {
    return null;
  }

  const handleRemoved = (toolId: number, reason: "deleted" | "unfavorited") => {
    favorites.remove(toolId);

    if (reason === "deleted") {
      mine.remove(toolId);
    }
  };

  return (
    <section className="flex flex-col gap-6">
      <h1 className="sr-only">Профил</h1>

      <div className={cn(cardClassName, "flex flex-col gap-6 p-5 md:p-7")}>
        <div className="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
          <div className="flex flex-col items-center gap-5 text-center sm:flex-row sm:text-left">
            <UserAvatar
              name={user.name}
              className="size-28 shadow-lg ring-4 ring-violet-100 sm:size-32 dark:ring-violet-500/20"
              initialsClassName="text-4xl font-bold tracking-wide"
            />
            <div className="flex min-w-0 flex-col items-center gap-1.5 sm:items-start">
              <p className="truncate text-2xl font-bold tracking-tight">{user.name}</p>
              <p className="truncate text-muted-foreground">{user.email}</p>
              <div className="mt-2 flex flex-wrap justify-center gap-2 sm:justify-start">
                <RoleBadge icon={BriefcaseBusiness} label={user.company_role_label} />
                {user.system_role === "admin" && <RoleBadge icon={Crown} label={user.system_role_label} />}
              </div>
            </div>
          </div>

          <button
            type="button"
            onClick={() => {
              setTab("settings");
              setEditRequests((count) => count + 1);
            }}
            className={cn(
              buttonVariants(),
              "h-11 self-center rounded-xl bg-violet-600 px-5 text-white shadow-md shadow-violet-600/20 hover:bg-violet-500 sm:self-start",
            )}
          >
            <Pencil />
            Редактирай профила
          </button>
        </div>

        <div className="grid grid-cols-2 gap-4">
          <StatCard icon={Box} label="Добавени инструменти" value={mine.total} isLoading={mine.status === "loading"} onClick={() => setTab("mine")} />
          <StatCard icon={Heart} label="Любими" value={favorites.total} isLoading={favorites.status === "loading"} onClick={() => setTab("favorites")} />
        </div>
      </div>

      <div ref={tabsRef} className="flex scroll-mt-20 flex-col gap-5">
        <div
          role="tablist"
          aria-label="Профил"
          className="flex gap-1 overflow-x-auto shadow-[inset_0_-1px_0_var(--color-border)] [scrollbar-width:none]"
        >
          {TABS.map(({ id, label, shortLabel, icon: Icon }) => (
            <button
              key={id}
              type="button"
              role="tab"
              id={`${tabsId}-${id}-tab`}
              aria-selected={tab === id}
              aria-controls={`${tabsId}-${id}-panel`}
              onClick={() => setTab(id)}
              className={cn(
                "inline-flex flex-1 shrink-0 items-center justify-center gap-2 border-b-2 px-3 py-3 text-sm font-semibold transition-colors sm:flex-none sm:px-4",
                tab === id
                  ? "border-violet-600 text-violet-700 dark:text-violet-300"
                  : "border-transparent text-muted-foreground hover:text-foreground",
              )}
            >
              <Icon className={cn("size-4", tab === id && id === "favorites" && "fill-current")} />
              <span className="sm:hidden">{shortLabel}</span>
              <span className="hidden sm:inline">{label}</span>
            </button>
          ))}
        </div>

        <div role="tabpanel" id={`${tabsId}-${tab}-panel`} aria-labelledby={`${tabsId}-${tab}-tab`}>
          {tab === "mine" && <ProfileToolList kind="mine" list={mine} onRemoved={handleRemoved} />}
          {tab === "favorites" && <ProfileToolList kind="favorites" list={favorites} onRemoved={handleRemoved} />}
          {tab === "settings" && (
            <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
              <SettingsCard icon={UserRound} title="Лични данни">
                <ProfileInformationForm user={user} nameInputRef={nameInputRef} />
              </SettingsCard>
              <SettingsCard icon={KeyRound} title="Смяна на паролата">
                <ChangePasswordForm />
              </SettingsCard>
              <SettingsCard icon={ShieldCheck} title="Двуфакторна защита">
                <TwoFactorSettings />
              </SettingsCard>
            </div>
          )}
        </div>
      </div>
    </section>
  );
}

function RoleBadge({ icon: Icon, label }: { icon: LucideIcon; label: string }) {
  return (
    <span className="inline-flex items-center gap-1.5 rounded-lg bg-violet-100 px-3 py-1.5 text-sm font-semibold text-violet-700 dark:bg-violet-500/15 dark:text-violet-300">
      <Icon className="size-4" aria-hidden="true" />
      {label}
    </span>
  );
}

function StatCard({
  icon: Icon,
  label,
  value,
  isLoading,
  onClick,
}: {
  icon: LucideIcon;
  label: string;
  // Null when it could not be loaded.
  value: number | null;
  isLoading: boolean;
  onClick: () => void;
}) {
  return (
    <button
      type="button"
      onClick={onClick}
      className="flex flex-col items-center gap-1.5 rounded-2xl border bg-background p-5 text-center shadow-xs transition-shadow hover:shadow-md"
    >
      <Icon className="size-6 text-violet-600 dark:text-violet-400" aria-hidden="true" />
      {isLoading ? (
        <Skeleton className="h-8 w-10" />
      ) : (
        <span className="text-2xl font-bold tabular-nums">{value === null ? "—" : numberFormatter.format(value)}</span>
      )}
      <span className="text-sm font-medium text-muted-foreground">{label}</span>
    </button>
  );
}

function SettingsCard({ icon: Icon, title, children }: { icon: LucideIcon; title: string; children: ReactNode }) {
  return (
    <div className={cn(cardClassName, "flex flex-col gap-5 p-5 md:p-6")}>
      <h2 className="flex items-center gap-2.5 text-xl font-bold">
        <Icon className="size-5 text-violet-600" />
        {title}
      </h2>
      {children}
    </div>
  );
}
