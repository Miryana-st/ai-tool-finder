"use client";

import { KeyRound } from "lucide-react";
import { useState, type FormEvent } from "react";
import { buttonVariants } from "@/components/ui/button";
import * as api from "@/lib/api";
import { ApiError, type ValidationErrors } from "@/lib/api";
import { cn } from "@/lib/utils";
import { fieldErrorClassName, formFieldClassName } from "@/styles/classNames";

const EMPTY_INPUT = { current_password: "", password: "", password_confirmation: "" };

type Field = keyof typeof EMPTY_INPUT;

const FIELDS: { name: Field; label: string; autoComplete: string }[] = [
  { name: "current_password", label: "Текуща парола", autoComplete: "current-password" },
  { name: "password", label: "Нова парола", autoComplete: "new-password" },
  { name: "password_confirmation", label: "Потвърди новата парола", autoComplete: "new-password" },
];

// The signed-in user changes their own password, e.g. the one an admin set for them.
export default function ChangePasswordForm() {
  const [input, setInput] = useState(EMPTY_INPUT);
  const [errors, setErrors] = useState<ValidationErrors>({});
  const [message, setMessage] = useState<{ type: "success" | "error"; text: string } | null>(null);
  const [isSaving, setIsSaving] = useState(false);

  const handleSubmit = async (event: FormEvent) => {
    event.preventDefault();
    setIsSaving(true);
    setErrors({});
    setMessage(null);

    try {
      await api.updatePassword(input);
      setInput(EMPTY_INPUT);
      setMessage({ type: "success", text: "Паролата е сменена." });
    } catch (caught) {
      if (caught instanceof ApiError && caught.status === 422) {
        setErrors(caught.errors);
      } else {
        console.error("Could not change the password.", caught);
        setMessage({ type: "error", text: "Паролата не можа да се смени. Опитай отново по-късно." });
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
              type="password"
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
        <KeyRound />
        {isSaving ? "Запазване…" : "Смени паролата"}
      </button>
    </form>
  );
}
