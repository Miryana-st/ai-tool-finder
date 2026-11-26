"use client";

import { useRouter } from "next/navigation";
import { useEffect, useState, type FormEvent } from "react";
import { useAuth } from "@/context/AuthContext";
import { ApiError, type ValidationErrors } from "@/lib/api";

const inputClassName =
  "rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900";

const submitClassName =
  "rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500 disabled:opacity-60";

const linkButtonClassName = "self-start text-sm text-indigo-600 hover:underline disabled:opacity-60";

export default function LoginForm() {
  const { status } = useAuth();
  const router = useRouter();
  // After a right password, users with two-factor authentication enter a code.
  const [step, setStep] = useState<"password" | "two-factor">("password");

  // Already logged in (or just logged in) - go to the dashboard.
  useEffect(() => {
    if (status === "authenticated") {
      router.replace("/dashboard");
    }
  }, [status, router]);

  return step === "password" ? (
    <PasswordStep onTwoFactor={() => setStep("two-factor")} />
  ) : (
    <TwoFactorStep onBack={() => setStep("password")} />
  );
}

function PasswordStep({ onTwoFactor }: { onTwoFactor: () => void }) {
  const { login } = useAuth();
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [fieldErrors, setFieldErrors] = useState<ValidationErrors>({});
  const [formError, setFormError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  const handleSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setIsSubmitting(true);
    setFieldErrors({});
    setFormError(null);

    try {
      const { twoFactor } = await login(email, password);

      if (twoFactor) {
        onTwoFactor();
      }
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

      <button type="submit" disabled={isSubmitting} className={submitClassName}>
        {isSubmitting ? "Влизане…" : "Вход"}
      </button>
    </form>
  );
}

function TwoFactorStep({ onBack }: { onBack: () => void }) {
  const { completeTwoFactor } = useAuth();
  // A recovery code replaces the app code when the phone is not at hand.
  const [useRecoveryCode, setUseRecoveryCode] = useState(false);
  const [value, setValue] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const field = useRecoveryCode ? "recovery_code" : "code";

  const handleSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setIsSubmitting(true);
    setError(null);

    try {
      const code = value.trim();
      await completeTwoFactor(useRecoveryCode ? { recovery_code: code } : { code });
    } catch (caught) {
      if (caught instanceof ApiError && caught.status === 422) {
        // Also a 422 when the session of the login expired; then "Назад" helps.
        setError(
          useRecoveryCode
            ? "Резервният код е грешен или вече е използван."
            : "Кодът е грешен или е изтекъл. Въведи текущия код от приложението.",
        );
      } else if (caught instanceof ApiError && caught.status === 429) {
        setError("Твърде много опити. Опитай отново след малко.");
      } else {
        console.error("Two-factor login failed.", caught);
        setError("Входът не беше успешен. Опитай отново по-късно.");
      }
      setIsSubmitting(false);
    }
  };

  const toggleRecoveryCode = () => {
    setUseRecoveryCode((current) => !current);
    setValue("");
    setError(null);
  };

  return (
    <form onSubmit={handleSubmit} noValidate className="flex flex-col gap-4">
      <p className="text-sm text-zinc-600 dark:text-zinc-400">
        {useRecoveryCode
          ? "Въведи един от резервните кодове, които запази при включването на двуфакторната защита."
          : "Отвори приложението за автентикация и въведи 6-цифрения код за AI-Finder."}
      </p>

      {error && (
        <p role="alert" className="text-sm text-red-600">
          {error}
        </p>
      )}

      <label className="flex flex-col gap-1 text-sm font-medium">
        {useRecoveryCode ? "Резервен код" : "Код от приложението"}
        <input
          key={field}
          type="text"
          name={field}
          autoComplete="one-time-code"
          inputMode={useRecoveryCode ? "text" : "numeric"}
          autoFocus
          value={value}
          onChange={(event) => setValue(event.target.value)}
          aria-invalid={Boolean(error)}
          className={inputClassName}
        />
      </label>

      <button type="submit" disabled={isSubmitting || value.trim() === ""} className={submitClassName}>
        {isSubmitting ? "Проверка…" : "Потвърди"}
      </button>

      <button type="button" onClick={toggleRecoveryCode} disabled={isSubmitting} className={linkButtonClassName}>
        {useRecoveryCode ? "Използвай код от приложението" : "Използвай резервен код"}
      </button>
      <button type="button" onClick={onBack} disabled={isSubmitting} className={linkButtonClassName}>
        ← Назад
      </button>
    </form>
  );
}
