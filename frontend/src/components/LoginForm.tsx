"use client";

import { useRouter } from "next/navigation";
import { useEffect, useState, type FormEvent } from "react";
import { useAuth } from "@/context/AuthContext";
import { ApiError, type ValidationErrors } from "@/lib/api";

const inputClassName =
  "rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900";

export default function LoginForm() {
  const { status, login } = useAuth();
  const router = useRouter();
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [fieldErrors, setFieldErrors] = useState<ValidationErrors>({});
  const [formError, setFormError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  // Already logged in (or just logged in) - go to the dashboard.
  useEffect(() => {
    if (status === "authenticated") {
      router.replace("/dashboard");
    }
  }, [status, router]);

  const handleSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setIsSubmitting(true);
    setFieldErrors({});
    setFormError(null);

    try {
      await login(email, password);
    } catch (error) {
      if (error instanceof ApiError && error.status === 422) {
        setFieldErrors(error.errors);
      } else if (error instanceof ApiError && error.status === 429) {
        setFormError("Твърде много опити за вход. Опитай отново след малко.");
      } else {
        console.error("Login failed.", error);
        setFormError("Входът не беше успешен. Опитай отново по-късно.");
      }
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <form onSubmit={handleSubmit} noValidate className="flex flex-col gap-4">
      {formError && (
        <p role="alert" className="text-sm text-red-600">
          {formError}
        </p>
      )}

      <label className="flex flex-col gap-1 text-sm font-medium">
        Имейл
        <input
          type="email"
          name="email"
          autoComplete="email"
          value={email}
          onChange={(event) => setEmail(event.target.value)}
          aria-invalid={Boolean(fieldErrors.email)}
          className={inputClassName}
        />
        {fieldErrors.email && (
          <span className="text-sm font-normal text-red-600">
            {fieldErrors.email[0]}
          </span>
        )}
      </label>

      <label className="flex flex-col gap-1 text-sm font-medium">
        Парола
        <input
          type="password"
          name="password"
          autoComplete="current-password"
          value={password}
          onChange={(event) => setPassword(event.target.value)}
          aria-invalid={Boolean(fieldErrors.password)}
          className={inputClassName}
        />
        {fieldErrors.password && (
          <span className="text-sm font-normal text-red-600">
            {fieldErrors.password[0]}
          </span>
        )}
      </label>

      <button
        type="submit"
        disabled={isSubmitting}
        className="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500 disabled:opacity-60"
      >
        {isSubmitting ? "Влизане…" : "Вход"}
      </button>
    </form>
  );
}
