"use client";

import { KeyRound, UserRound } from "lucide-react";
import UserAvatar from "@/components/layout/UserAvatar";
import { useAuth } from "@/context/AuthContext";
import { mutedTextClassName } from "@/styles/classNames";
import ChangePasswordForm from "./ChangePasswordForm";

const cardClassName = "flex flex-col gap-5 rounded-2xl border bg-card p-5 text-card-foreground shadow-sm md:p-6";

export default function ProfileOverview() {
  const { user } = useAuth();

  if (!user) {
    return null;
  }

  return (
    <section className="flex flex-col gap-6">
      <h1 className="text-3xl font-semibold tracking-tight">Профил</h1>

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <div className={cardClassName}>
          <h2 className="flex items-center gap-2.5 text-xl font-bold">
            <UserRound className="size-5 text-violet-600" />
            Моите данни
          </h2>
          <div className="flex items-center gap-4">
            <UserAvatar name={user.name} className="size-14" />
            <div className="flex min-w-0 flex-col">
              <span className="truncate text-lg font-semibold">{user.name}</span>
              <span className={`truncate text-sm ${mutedTextClassName}`}>{user.email}</span>
            </div>
          </div>
          <dl className="grid grid-cols-[max-content_1fr] gap-x-4 gap-y-2 text-sm">
            <dt className="font-medium">Роля във фирмата</dt>
            <dd className={mutedTextClassName}>{user.company_role_label}</dd>
            <dt className="font-medium">Системна роля</dt>
            <dd className={mutedTextClassName}>{user.system_role_label}</dd>
          </dl>
        </div>

        <div className={cardClassName}>
          <h2 className="flex items-center gap-2.5 text-xl font-bold">
            <KeyRound className="size-5 text-violet-600" />
            Смяна на паролата
          </h2>
          <ChangePasswordForm />
        </div>
      </div>
    </section>
  );
}
