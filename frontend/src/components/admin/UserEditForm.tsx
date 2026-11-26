"use client";

import { Save, ShieldCheck, ShieldOff } from "lucide-react";
import Link from "next/link";
import { useParams, useRouter } from "next/navigation";
import { useEffect, useState, type FormEvent, type ReactNode } from "react";
import RequiredMark from "@/components/tools/RequiredMark";
import { buttonVariants } from "@/components/ui/button";
import { useAuth } from "@/context/AuthContext";
import { useBreadcrumbLabel } from "@/context/BreadcrumbContext";
import * as api from "@/lib/api";
import { ApiError, type CompanyRole, type User, type UserUpdateInput, type ValidationErrors } from "@/lib/api";
import { cn } from "@/lib/utils";
import { fieldErrorClassName, formFieldClassName, mutedTextClassName } from "@/styles/classNames";

type Message = { type: "success" | "error"; text: string };

type LoadState = { status: "loading" } | { status: "not-found" } | { status: "error" } | { status: "ready"; user: User };

const cardClassName = "flex max-w-3xl flex-col gap-6 rounded-2xl border bg-card p-5 text-card-foreground shadow-sm md:p-6";

const primaryClassName = cn(buttonVariants(), "h-11 rounded-xl bg-violet-600 px-6 text-white hover:bg-violet-500");

// Admin page for one user: details, a new password and the admin role. Use it inside AdminOnly.
export default function UserEditForm() {
  const { id } = useParams<{ id: string }>();
  const [state, setState] = useState<LoadState>({ status: "loading" });

  useEffect(() => {
    const controller = new AbortController();

    api
      .getUserById(Number(id), controller.signal)
      .then((user) => setState({ status: "ready", user }))
      .catch((error: unknown) => {
        if (api.isAbortError(error)) {
          return;
        }
        if (error instanceof ApiError && error.status === 404) {
          setState({ status: "not-found" });
          return;
        }
        console.error("Could not load the user.", error);
        setState({ status: "error" });
      });

    return () => controller.abort();
  }, [id]);

  if (state.status === "loading") {
    return <p className={mutedTextClassName}>Зареждане…</p>;
  }

  if (state.status !== "ready") {
    return (
      <div className="flex flex-col gap-3">
        <p role="alert" className={state.status === "error" ? "text-red-600" : mutedTextClassName}>
          {state.status === "not-found"
            ? "Потребителят не съществува или е изтрит."
            : "Потребителят не можа да се зареди. Опитай отново по-късно."}
        </p>
        <Link href="/admin/users" className="text-sm text-indigo-600 hover:underline">
          ← Към потребителите
        </Link>
      </div>
    );
  }

  return <UserEditor user={state.user} onChange={(user) => setState({ status: "ready", user })} />;
}

function UserEditor({ user, onChange }: { user: User; onChange: (user: User) => void }) {
  useBreadcrumbLabel(`/admin/users/${user.id}`, user.name);

  return (
    <section className="flex flex-col gap-6">
      <div className="flex flex-col gap-1">
        <h1 className="text-3xl font-semibold tracking-tight">Редактирай {user.name}</h1>
        <p className={mutedTextClassName}>
          Промени данните или задай нова парола. Потребителят може сам да смени паролата си от профила.
        </p>
      </div>

      <DetailsForm user={user} onSaved={onChange} />
      <SystemRoleCard user={user} onChange={onChange} />
      <TwoFactorCard user={user} onChange={onChange} />
    </section>
  );
}

// Turns off another user's two-factor authentication, e.g. after they lost their
// phone and recovery codes. Admins turn off their own from the profile.
function TwoFactorCard({ user, onChange }: { user: User; onChange: (user: User) => void }) {
  const { user: currentUser } = useAuth();
  const [message, setMessage] = useState<Message | null>(null);
  const [isSaving, setIsSaving] = useState(false);
  const isSelf = user.id === currentUser?.id;

  const reset = async () => {
    if (
      !window.confirm(
        `Да изключа ли двуфакторната защита на ${user.name}? Ще може да влезе само с парола и да я включи отново от профила си.`,
      )
    ) {
      return;
    }

    setIsSaving(true);
    setMessage(null);

    try {
      onChange(await api.resetUserTwoFactor(user.id));
      setMessage({ type: "success", text: `Двуфакторната защита на ${user.name} е изключена.` });
    } catch (caught) {
      if (caught instanceof ApiError && caught.status === 404) {
        setMessage({ type: "error", text: "Потребителят вече е изтрит." });
      } else {
        console.error("Could not turn off two-factor authentication.", caught);
        setMessage({ type: "error", text: "Действието не беше успешно. Опитай отново." });
      }
    } finally {
      setIsSaving(false);
    }
  };

  return (
    <div className={cardClassName}>
      <div className="flex flex-col gap-1">
        <h2 className="text-xl font-bold">Двуфакторна защита</h2>
        <p className={cn("text-sm", mutedTextClassName)}>
          Сега: <strong>{user.two_factor_enabled ? "включена" : "изключена"}</strong>.{" "}
          {isSelf
            ? "Своята двуфакторна защита управляваш от профила си."
            : user.two_factor_enabled
              ? "Изключи я, ако потребителят е загубил телефона и резервните си кодове."
              : "Потребителят може да я включи от профила си."}
        </p>
      </div>

      {message && <MessageText message={message} />}

      {!isSelf && (
        <button
          type="button"
          onClick={() => void reset()}
          disabled={isSaving || !user.two_factor_enabled}
          className={cn(buttonVariants({ variant: "outline" }), "h-11 self-start rounded-xl px-5 text-red-600 hover:text-red-600")}
        >
          <ShieldOff />
          {isSaving ? "Изключване…" : "Изключи 2FA"}
        </button>
      )}
    </div>
  );
}

function DetailsForm({ user, onSaved }: { user: User; onSaved: (user: User) => void }) {
  const { user: currentUser, refreshUser } = useAuth();
  const [input, setInput] = useState<UserUpdateInput>({
    name: user.name,
    email: user.email,
    company_role: user.company_role,
    password: "",
    password_confirmation: "",
  });
  const [companyRoles, setCompanyRoles] = useState<CompanyRole[]>([]);
  const [errors, setErrors] = useState<ValidationErrors>({});
  const [message, setMessage] = useState<Message | null>(null);
  const [isSaving, setIsSaving] = useState(false);

  useEffect(() => {
    const controller = new AbortController();

    api
      .getCompanyRoles(controller.signal)
      .then(setCompanyRoles)
      .catch((error: unknown) => {
        if (!api.isAbortError(error)) {
          console.error("Could not load the company roles.", error);
          setMessage({ type: "error", text: "Ролите във фирмата не можаха да се заредят. Опитай отново по-късно." });
        }
      });

    return () => controller.abort();
  }, []);

  const change = (field: keyof UserUpdateInput) => (value: string) =>
    setInput((current) => ({ ...current, [field]: value }));

  const error = (field: string) => api.fieldError(errors, field);

  const handleSubmit = async (event: FormEvent) => {
    event.preventDefault();
    setIsSaving(true);
    setErrors({});
    setMessage(null);

    try {
      const saved = await api.updateUser(user.id, input);
      const passwordChanged = input.password !== "";
      setInput((current) => ({ ...current, password: "", password_confirmation: "" }));
      onSaved(saved);

      // Admins editing themselves: the menu and profile show the new name and email.
      if (saved.id === currentUser?.id) {
        await refreshUser();
      }

      setMessage({
        type: "success",
        text: passwordChanged
          ? "Данните са запазени. Паролата е сменена и потребителят е изписан от другите си сесии."
          : "Данните са запазени.",
      });
    } catch (caught) {
      if (caught instanceof ApiError && caught.status === 422) {
        setErrors(caught.errors);
      } else if (caught instanceof ApiError && caught.status === 404) {
        setMessage({ type: "error", text: "Потребителят вече е изтрит." });
      } else {
        console.error("Could not update the user.", caught);
        setMessage({ type: "error", text: "Данните не можаха да се запазят. Опитай отново по-късно." });
      }
    } finally {
      setIsSaving(false);
    }
  };

  return (
    <form onSubmit={(event) => void handleSubmit(event)} noValidate className={cardClassName}>
      <h2 className="text-xl font-bold">Данни</h2>

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
        <Field label="Роля във фирмата" required error={error("company_role")}>
          <select
            value={input.company_role}
            onChange={(event) => change("company_role")(event.target.value)}
            required
            className={cn(formFieldClassName, "h-11")}
          >
            {companyRoles.length === 0 && <option value={user.company_role}>{user.company_role_label}</option>}
            {companyRoles.map((role) => (
              <option key={role.id} value={role.slug}>
                {role.name}
              </option>
            ))}
          </select>
        </Field>
      </div>

      <div className="flex flex-col gap-3 border-t pt-5">
        <div className="flex flex-col gap-1">
          <h3 className="font-semibold">Нова парола</h3>
          <p className={cn("text-sm", mutedTextClassName)}>
            Остави празно, за да не се сменя. Нова парола изписва потребителя от всичките му сесии.
          </p>
        </div>
        <div className="grid grid-cols-1 gap-5 md:grid-cols-2">
          <Field label="Нова парола" error={error("password")}>
            <input
              type="password"
              value={input.password}
              onChange={(event) => change("password")(event.target.value)}
              autoComplete="new-password"
              className={cn(formFieldClassName, "h-11")}
            />
          </Field>
          <Field label="Потвърди новата парола" error={error("password_confirmation")}>
            <input
              type="password"
              value={input.password_confirmation}
              onChange={(event) => change("password_confirmation")(event.target.value)}
              autoComplete="new-password"
              className={cn(formFieldClassName, "h-11")}
            />
          </Field>
        </div>
      </div>

      {message && <MessageText message={message} />}

      <div className="flex justify-end gap-3 border-t pt-5">
        <Link href="/admin/users" className={cn(buttonVariants({ variant: "outline" }), "h-11 rounded-xl px-6")}>
          Назад
        </Link>
        <button type="submit" disabled={isSaving} className={primaryClassName}>
          <Save />
          {isSaving ? "Запазване…" : "Запази"}
        </button>
      </div>
    </form>
  );
}

// "Направи admin" / "Махни admin", moved here from the users list. At least one admin always remains (backend).
function SystemRoleCard({ user, onChange }: { user: User; onChange: (user: User) => void }) {
  const router = useRouter();
  const { user: currentUser, refreshUser } = useAuth();
  const [message, setMessage] = useState<Message | null>(null);
  const [isSaving, setIsSaving] = useState(false);
  const isAdmin = user.system_role === "admin";
  const isSelf = user.id === currentUser?.id;

  const toggleAdmin = async () => {
    const question = !isAdmin
      ? `Да направя ли ${user.name} администратор?`
      : isSelf
        ? "Да махна ли твоята admin роля? Ще загубиш достъп до админ страниците."
        : `Да махна ли admin ролята на ${user.name}?`;

    if (!window.confirm(question)) {
      return;
    }

    setIsSaving(true);
    setMessage(null);

    try {
      const updated = await api.updateUserSystemRole(user.id, isAdmin ? "user" : "admin");

      // This page is for admins only. Loading the user again also hides the admin menu.
      if (isSelf && isAdmin) {
        await refreshUser();
        router.push("/dashboard");
        return;
      }

      onChange(updated);
      setMessage({
        type: "success",
        text: isAdmin ? `${updated.name} вече не е администратор.` : `${updated.name} вече е администратор.`,
      });
    } catch (caught) {
      if (caught instanceof ApiError && caught.status === 422) {
        setMessage({ type: "error", text: api.fieldError(caught.errors, "system_role") ?? caught.message });
      } else {
        console.error("Could not change the system role.", caught);
        setMessage({ type: "error", text: "Действието не беше успешно. Опитай отново." });
      }
    } finally {
      setIsSaving(false);
    }
  };

  return (
    <div className={cardClassName}>
      <div className="flex flex-col gap-1">
        <h2 className="text-xl font-bold">Системна роля</h2>
        <p className={cn("text-sm", mutedTextClassName)}>
          Сега: <strong>{user.system_role_label}</strong>. Администраторите управляват потребителите, ролите,
          категориите и таговете. Поне един администратор винаги остава.
        </p>
      </div>

      {message && <MessageText message={message} />}

      <button
        type="button"
        onClick={() => void toggleAdmin()}
        disabled={isSaving}
        className={cn(buttonVariants({ variant: "outline" }), "h-11 self-start rounded-xl px-5")}
      >
        {isAdmin ? <ShieldOff /> : <ShieldCheck />}
        {isAdmin ? "Махни admin" : "Направи admin"}
      </button>
    </div>
  );
}

function MessageText({ message }: { message: Message }) {
  return (
    <p
      role={message.type === "error" ? "alert" : "status"}
      className={cn("text-sm", message.type === "error" ? "text-red-600" : "text-emerald-600")}
    >
      {message.text}
    </p>
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
