"use client";

import { UserPlus } from "lucide-react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useEffect, useState, type FormEvent, type ReactNode } from "react";
import RequiredMark from "@/components/tools/RequiredMark";
import { buttonVariants } from "@/components/ui/button";
import * as api from "@/lib/api";
import { ApiError, type CompanyRole, type NewUserInput, type ValidationErrors } from "@/lib/api";
import { cn } from "@/lib/utils";
import { fieldErrorClassName, formFieldClassName, mutedTextClassName } from "@/styles/classNames";

const EMPTY_INPUT: NewUserInput = {
  name: "",
  email: "",
  password: "",
  password_confirmation: "",
  company_role: "",
  system_role: "user",
};

// Admins create an account with a password they choose; the user can change it later in their profile.
export default function UserCreateForm() {
  const router = useRouter();
  const [input, setInput] = useState<NewUserInput>(EMPTY_INPUT);
  const [companyRoles, setCompanyRoles] = useState<CompanyRole[]>([]);
  const [errors, setErrors] = useState<ValidationErrors>({});
  const [formError, setFormError] = useState<string | null>(null);
  const [isSaving, setIsSaving] = useState(false);

  useEffect(() => {
    const controller = new AbortController();

    api
      .getCompanyRoles(controller.signal)
      .then(setCompanyRoles)
      .catch((error: unknown) => {
        if (!api.isAbortError(error)) {
          console.error("Could not load the company roles.", error);
          setFormError("Ролите във фирмата не можаха да се заредят. Опитай отново по-късно.");
        }
      });

    return () => controller.abort();
  }, []);

  const change = (field: keyof NewUserInput) => (value: string) =>
    setInput((current) => ({ ...current, [field]: value }));

  const error = (field: string) => api.fieldError(errors, field);

  const handleSubmit = async (event: FormEvent) => {
    event.preventDefault();
    setIsSaving(true);
    setErrors({});
    setFormError(null);

    try {
      const user = await api.createUser(input);
      router.push(`/admin/users?search=${encodeURIComponent(user.email)}`);
    } catch (caught) {
      if (caught instanceof ApiError && caught.status === 422) {
        setErrors(caught.errors);
      } else if (caught instanceof ApiError && caught.status === 403) {
        setFormError("Само администратори могат да добавят потребители.");
      } else {
        console.error("Could not create the user.", caught);
        setFormError("Потребителят не можа да се добави. Опитай отново по-късно.");
      }
      setIsSaving(false);
    }
  };

  return (
    <section className="flex flex-col gap-6">
      <div className="flex flex-col gap-1">
        <h1 className="text-3xl font-semibold tracking-tight">Добави потребител</h1>
        <p className={mutedTextClassName}>
          Задай начална парола и я съобщи на потребителя. Той може да я смени от своя профил.
        </p>
      </div>

      <form
        onSubmit={(event) => void handleSubmit(event)}
        noValidate
        className="flex max-w-3xl flex-col gap-6 rounded-2xl border bg-card p-5 text-card-foreground shadow-sm md:p-6"
      >
        {formError && (
          <p role="alert" className="text-sm text-red-600">
            {formError}
          </p>
        )}

        <div className="grid grid-cols-1 gap-5 md:grid-cols-2">
          <Field label="Име" required error={error("name")}>
            <input
              value={input.name}
              onChange={(event) => change("name")(event.target.value)}
              autoComplete="off"
              required
              className={cn(formFieldClassName, "h-11")}
            />
          </Field>
          <Field label="Имейл" required error={error("email")}>
            <input
              type="email"
              value={input.email}
              onChange={(event) => change("email")(event.target.value)}
              autoComplete="off"
              required
              className={cn(formFieldClassName, "h-11")}
            />
          </Field>
          <Field label="Парола" required error={error("password")}>
            <input
              type="password"
              value={input.password}
              onChange={(event) => change("password")(event.target.value)}
              autoComplete="new-password"
              required
              className={cn(formFieldClassName, "h-11")}
            />
          </Field>
          <Field label="Потвърди паролата" required error={error("password_confirmation")}>
            <input
              type="password"
              value={input.password_confirmation}
              onChange={(event) => change("password_confirmation")(event.target.value)}
              autoComplete="new-password"
              required
              className={cn(formFieldClassName, "h-11")}
            />
          </Field>
          <Field label="Роля във фирмата" required error={error("company_role")}>
            <select
              value={input.company_role}
              onChange={(event) => change("company_role")(event.target.value)}
              required
              className={cn(formFieldClassName, "h-11")}
            >
              <option value="" disabled>
                Избери роля
              </option>
              {companyRoles.map((role) => (
                <option key={role.id} value={role.slug}>
                  {role.name}
                </option>
              ))}
            </select>
          </Field>
          <Field label="Системна роля" required error={error("system_role")}>
            <select
              value={input.system_role}
              onChange={(event) => change("system_role")(event.target.value)}
              required
              className={cn(formFieldClassName, "h-11")}
            >
              <option value="user">Потребител</option>
              <option value="admin">Администратор</option>
            </select>
          </Field>
        </div>

        <div className="flex justify-end gap-3 border-t pt-5">
          <Link href="/admin/users" className={cn(buttonVariants({ variant: "outline" }), "h-11 rounded-xl px-6")}>
            Отказ
          </Link>
          <button
            type="submit"
            disabled={isSaving}
            className={cn(buttonVariants(), "h-11 rounded-xl bg-violet-600 px-6 text-white hover:bg-violet-500")}
          >
            <UserPlus />
            {isSaving ? "Добавяне…" : "Добави потребител"}
          </button>
        </div>
      </form>
    </section>
  );
}

function Field({
  label,
  required = false,
  error,
  children,
}: {
  label: string;
  required?: boolean;
  error: string | null;
  children: ReactNode;
}) {
  return (
    <label className="flex flex-col gap-2 text-sm font-semibold">
      <span>
        {label}
        {required && (
          <>
            {" "}
            <RequiredMark />
          </>
        )}
      </span>
      {children}
      {error && <span className={fieldErrorClassName}>{error}</span>}
    </label>
  );
}
