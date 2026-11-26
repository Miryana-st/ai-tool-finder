"use client";

import { Save } from "lucide-react";
import { useState, type FormEvent, type Ref } from "react";
import { buttonVariants } from "@/components/ui/button";
import { useAuth } from "@/context/AuthContext";
import * as api from "@/lib/api";
import { ApiError, type User, type ValidationErrors } from "@/lib/api";
import { cn } from "@/lib/utils";
import { fieldErrorClassName, formFieldClassName } from "@/styles/classNames";

type Field = "name" | "email";

const FIELDS: { name: Field; label: string; type: string; autoComplete: string }[] = [
  { name: "name", label: "Име", type: "text", autoComplete: "name" },
  { name: "email", label: "Имейл", type: "email", autoComplete: "email" },
];

// The signed-in user changes their own name and email.
export default function ProfileInformationForm({ user, nameInputRef }: { user: User; nameInputRef?: Ref<HTMLInputElement> }) {
  const { refreshUser } = useAuth();
  const [input, setInput] = useState({ name: user.name, email: user.email });
  const [errors, setErrors] = useState<ValidationErrors>({});
  const [message, setMessage] = useState<{ type: "success" | "error"; text: string } | null>(null);
  const [isSaving, setIsSaving] = useState(false);

  const handleSubmit = async (event: FormEvent) => {
    event.preventDefault();
    setIsSaving(true);
    setErrors({});
    setMessage(null);

    try {
      await api.updateProfileInformation(input);
      await refreshUser();
      setMessage({ type: "success", text: "Данните са запазени." });
    } catch (caught) {
      if (caught instanceof ApiError && caught.status === 422) {
        setErrors(caught.errors);
      } else {
        console.error("Could not update the profile information.", caught);
        setMessage({ type: "error", text: "Данните не можаха да се запазят. Опитай отново по-късно." });
      }
    } finally {
      setIsSaving(false);
    }
  };

  return (
    <form onSubmit={(event) => void handleSubmit(event)} noValidate className="flex flex-col gap-5">
      {FIELDS.map((field) => {
        const error = api.fieldError(errors, field.name);

        return (
          <label key={field.name} className="flex flex-col gap-2 text-sm font-semibold">
            {field.label}
            <input
              ref={field.name === "name" ? nameInputRef : undefined}
              type={field.type}
              value={input[field.name]}
              onChange={(event) => setInput((current) => ({ ...current, [field.name]: event.target.value }))}
              autoComplete={field.autoComplete}
              required
              aria-invalid={error ? true : undefined}
              className={cn(formFieldClassName, "h-11")}
            />
            {error && <span className={fieldErrorClassName}>{error}</span>}
          </label>
        );
      })}

      {message && (
        <p
          role={message.type === "error" ? "alert" : "status"}
          className={cn("text-sm", message.type === "error" ? "text-red-600" : "text-emerald-600")}
        >
          {message.text}
        </p>
      )}

      <button
        type="submit"
        disabled={isSaving}
        className={cn(
          buttonVariants(),
          "h-11 self-start rounded-xl bg-violet-600 px-6 text-white hover:bg-violet-500",
        )}
      >
        <Save />
        {isSaving ? "Запазване…" : "Запази данните"}
      </button>
    </form>
  );
}
