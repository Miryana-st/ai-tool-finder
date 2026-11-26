"use client";

import { useEffect, useState, type FormEvent } from "react";
import * as api from "@/lib/api";
import { ApiError, type CompanyRole, type ValidationErrors } from "@/lib/api";
import AdminPagination, { usePagedItems } from "@/components/admin/AdminPagination";
import NameSlugFields, { canSendNameSlug } from "@/components/tools/NameSlugFields";
import {
  chipClassName,
  dangerButtonClassName,
  fieldErrorClassName,
  inputClassName,
  mutedTextClassName,
  primaryButtonClassName,
  secondaryButtonClassName,
} from "@/styles/classNames";

// Kept in line with StoreCompanyRoleRequest (slug max:50).
const SLUG_MAX_LENGTH = 50;

type ListState =
  | { status: "loading" }
  | { status: "error" }
  | { status: "ready"; roles: CompanyRole[] };

type Message = { type: "success" | "error"; text: string };

const byName = (a: CompanyRole, b: CompanyRole) => a.name.localeCompare(b.name, "bg");

// Admin page for the company roles: add, rename and delete.
export default function CompanyRoleManager() {
  const [state, setState] = useState<ListState>({ status: "loading" });
  const [message, setMessage] = useState<Message | null>(null);

  useEffect(() => {
    const controller = new AbortController();

    api
      .getCompanyRoles(controller.signal)
      .then((roles) => setState({ status: "ready", roles }))
      .catch((error: unknown) => {
        if (api.isAbortError(error)) {
          return;
        }
        console.error("Could not load the company roles.", error);
        setState({ status: "error" });
      });

    return () => controller.abort();
  }, []);

  const updateRoles = (change: (roles: CompanyRole[]) => CompanyRole[]) =>
    setState((current) =>
      current.status === "ready" ? { status: "ready", roles: change(current.roles).sort(byName) } : current,
    );

  return (
    <section className="flex flex-col gap-6">
      <div className="flex flex-col gap-1">
        <h1 className="text-3xl font-semibold tracking-tight">Длъжности</h1>
        <p className={mutedTextClassName}>
          Slug-ът не може да се променя след създаване. Длъжност, която е зададена на потребители, не може да се изтрие.
        </p>
      </div>

      <AddRoleForm
        onCreated={(role) => {
          updateRoles((roles) => [...roles, role]);
          setMessage({ type: "success", text: `Длъжността „${role.name}“ е добавена.` });
        }}
      />

      {message && (
        <p
          role={message.type === "error" ? "alert" : "status"}
          className={message.type === "error" ? "text-sm text-red-600" : "text-sm text-green-700 dark:text-green-400"}
        >
          {message.text}
        </p>
      )}

      <h2 className="text-lg font-semibold">Съществуващи длъжности</h2>

      {state.status === "loading" && <p className={mutedTextClassName}>Зареждане…</p>}

      {state.status === "error" && (
        <p role="alert" className="text-red-600 dark:text-red-400">
          Длъжностите не можаха да се заредят. Опитай отново по-късно.
        </p>
      )}

      {state.status === "ready" && state.roles.length === 0 && (
        <p className={mutedTextClassName}>Все още няма длъжности.</p>
      )}

      {state.status === "ready" && state.roles.length > 0 && (
        <RoleList
          roles={state.roles}
          onMessage={setMessage}
          onRenamed={(renamed) => updateRoles((roles) => roles.map((item) => (item.id === renamed.id ? renamed : item)))}
          onDeleted={(id) => updateRoles((roles) => roles.filter((item) => item.id !== id))}
        />
      )}
    </section>
  );
}

// Ten roles per page.
function RoleList({
  roles,
  onMessage,
  onRenamed,
  onDeleted,
}: {
  roles: CompanyRole[];
  onMessage: (message: Message | null) => void;
  onRenamed: (role: CompanyRole) => void;
  onDeleted: (id: number) => void;
}) {
  const { pageItems, page, lastPage, setPage } = usePagedItems(roles);

  return (
    <>
      <ul className="flex flex-col gap-3">
        {pageItems.map((role) => (
          <RoleItem key={role.id} role={role} onMessage={onMessage} onRenamed={onRenamed} onDeleted={onDeleted} />
        ))}
      </ul>
      <AdminPagination
        page={page}
        lastPage={lastPage}
        total={roles.length}
        itemsLabel={roles.length === 1 ? "длъжност" : "длъжности"}
        onChange={setPage}
      />
    </>
  );
}

function AddRoleForm({ onCreated }: { onCreated: (role: CompanyRole) => void }) {
  const [name, setName] = useState("");
  const [slug, setSlug] = useState("");
  const [errors, setErrors] = useState<ValidationErrors>({});
  const [formError, setFormError] = useState<string | null>(null);
  const [isSaving, setIsSaving] = useState(false);
  // Remounts the fields after a save, so the slug follows the name again.
  const [formKey, setFormKey] = useState(0);

  const submit = async () => {
    if (isSaving || !canSendNameSlug(name, slug)) {
      return;
    }

    setIsSaving(true);
    setErrors({});
    setFormError(null);

    try {
      const role = await api.createCompanyRole({ slug, name: name.trim() });
      onCreated(role);
      setName("");
      setSlug("");
      setFormKey((key) => key + 1);
    } catch (error) {
      if (error instanceof ApiError && error.status === 422) {
        setErrors(error.errors);
      } else {
        console.error("Could not create the company role.", error);
        setFormError("Длъжността не можа да се добави. Опитай отново.");
      }
    } finally {
      setIsSaving(false);
    }
  };

  const handleSubmit = (event: FormEvent) => {
    event.preventDefault();
    void submit();
  };

  return (
    <form
      onSubmit={handleSubmit}
      className="flex flex-col gap-4 rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-950"
    >
      <h2 className="text-lg font-semibold">Нова длъжност</h2>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <NameSlugFields
          key={formKey}
          name={name}
          slug={slug}
          onNameChange={setName}
          onSlugChange={setSlug}
          nameError={api.fieldError(errors, "name")}
          slugError={api.fieldError(errors, "slug")}
          maxLength={SLUG_MAX_LENGTH}
          onEnter={() => void submit()}
        />
      </div>

      {formError && (
        <p role="alert" className={fieldErrorClassName}>
          {formError}
        </p>
      )}

      <button
        type="submit"
        disabled={isSaving || !canSendNameSlug(name, slug)}
        className={`${primaryButtonClassName} self-start`}
      >
        {isSaving ? "Добавяне…" : "Добави длъжност"}
      </button>
    </form>
  );
}

function RoleItem({
  role,
  onMessage,
  onRenamed,
  onDeleted,
}: {
  role: CompanyRole;
  onMessage: (message: Message | null) => void;
  onRenamed: (role: CompanyRole) => void;
  onDeleted: (id: number) => void;
}) {
  const [isEditing, setIsEditing] = useState(false);
  const [name, setName] = useState(role.name);
  const [nameError, setNameError] = useState<string | null>(null);
  const [isBusy, setIsBusy] = useState(false);

  const cancel = () => {
    setIsEditing(false);
    setName(role.name);
    setNameError(null);
  };

  const rename = async (event: FormEvent) => {
    event.preventDefault();

    if (name.trim() === "" || isBusy) {
      return;
    }

    setIsBusy(true);
    setNameError(null);
    onMessage(null);

    try {
      const renamed = await api.updateCompanyRole(role.id, { name: name.trim() });
      setIsEditing(false);
      onRenamed(renamed);
      onMessage({ type: "success", text: `Длъжността е преименувана на „${renamed.name}“.` });
    } catch (error) {
      if (error instanceof ApiError && error.status === 422) {
        setNameError(api.fieldError(error.errors, "name") ?? error.message);
      } else {
        console.error("Could not rename the company role.", error);
        onMessage({ type: "error", text: "Длъжността не можа да се преименува. Опитай отново." });
      }
    } finally {
      setIsBusy(false);
    }
  };

  const remove = async () => {
    if (!window.confirm(`Да изтрия ли длъжността „${role.name}“?`)) {
      return;
    }

    setIsBusy(true);
    onMessage(null);

    try {
      await api.deleteCompanyRole(role.id);
      onDeleted(role.id);
      onMessage({ type: "success", text: `Длъжността „${role.name}“ е изтрита.` });
    } catch (error) {
      // 409: the role is assigned to users; the server says so.
      if (error instanceof ApiError && error.status === 409) {
        onMessage({ type: "error", text: error.message });
      } else if (error instanceof ApiError && error.status === 404) {
        onDeleted(role.id);
        onMessage({ type: "error", text: `Длъжността „${role.name}“ вече е изтрита.` });
      } else {
        console.error("Could not delete the company role.", error);
        onMessage({ type: "error", text: "Длъжността не можа да се изтрие. Опитай отново." });
      }
    } finally {
      setIsBusy(false);
    }
  };

  return (
    <li className="flex flex-wrap items-start justify-between gap-4 rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-950">
      {isEditing ? (
        <form onSubmit={(event) => void rename(event)} className="flex flex-1 flex-wrap items-start gap-2">
          <label className="flex min-w-48 flex-1 flex-col gap-1 text-sm font-medium">
            <span className="sr-only">Ново име на „{role.name}“</span>
            <input
              type="text"
              value={name}
              onChange={(event) => setName(event.target.value)}
              onKeyDown={(event) => {
                if (event.key === "Escape") {
                  cancel();
                }
              }}
              maxLength={255}
              autoFocus
              aria-invalid={Boolean(nameError)}
              className={inputClassName}
            />
            {nameError && <span className={fieldErrorClassName}>{nameError}</span>}
          </label>
          <button type="submit" disabled={isBusy || name.trim() === ""} className={primaryButtonClassName}>
            Запази
          </button>
          <button type="button" onClick={cancel} disabled={isBusy} className={secondaryButtonClassName}>
            Откажи
          </button>
        </form>
      ) : (
        <>
          <p className="flex flex-wrap items-center gap-2 font-medium">
            {role.name}
            <span className={`${chipClassName} font-mono`}>{role.slug}</span>
          </p>
          <div className="flex gap-2">
            <button
              type="button"
              onClick={() => setIsEditing(true)}
              disabled={isBusy}
              className={secondaryButtonClassName}
            >
              Преименувай
            </button>
            <button
              type="button"
              onClick={() => void remove()}
              disabled={isBusy}
              className={dangerButtonClassName}
            >
              Изтрий
            </button>
          </div>
        </>
      )}
    </li>
  );
}
