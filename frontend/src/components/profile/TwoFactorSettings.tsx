"use client";

import { ShieldCheck, ShieldOff } from "lucide-react";
import { useState, type FormEvent } from "react";
import { buttonVariants } from "@/components/ui/button";
import { useAuth } from "@/context/AuthContext";
import * as api from "@/lib/api";
import { ApiError } from "@/lib/api";
import { cn } from "@/lib/utils";
import { fieldErrorClassName, formFieldClassName, mutedTextClassName } from "@/styles/classNames";

const primaryClassName = cn(buttonVariants(), "h-11 rounded-xl bg-violet-600 px-6 text-white hover:bg-violet-500");
const outlineClassName = cn(buttonVariants({ variant: "outline" }), "h-11 rounded-xl px-5");

type Message = { type: "success" | "error"; text: string };

// Two-factor authentication with an authenticator app. The backend asks for a
// recently confirmed password (423) before any of these actions; then the
// password form is shown and the action runs again after it.
export default function TwoFactorSettings() {
  const { user, refreshUser } = useAuth();
  const [setup, setSetup] = useState<{ svg: string; secretKey: string } | null>(null);
  const [recoveryCodes, setRecoveryCodes] = useState<string[] | null>(null);
  // The action waiting for the password to be confirmed.
  const [pendingAction, setPendingAction] = useState<(() => Promise<void>) | null>(null);
  const [isBusy, setIsBusy] = useState(false);
  const [message, setMessage] = useState<Message | null>(null);

  if (!user) {
    return null;
  }

  const run = async (action: () => Promise<void>, failure: string) => {
    setIsBusy(true);
    setMessage(null);

    try {
      await action();
      setPendingAction(null);
    } catch (caught) {
      if (caught instanceof ApiError && caught.status === 423) {
        setPendingAction(() => action);
      } else {
        console.error("Two-factor settings action failed.", caught);
        setMessage({ type: "error", text: failure });
      }
    } finally {
      setIsBusy(false);
    }
  };

  const enable = () =>
    run(async () => {
      await api.enableTwoFactor();
      setSetup(await api.getTwoFactorSetup());
    }, "Двуфакторната защита не можа да се включи. Опитай отново по-късно.");

  const cancelSetup = () =>
    run(async () => {
      await api.disableTwoFactor();
      setSetup(null);
    }, "Настройката не можа да се откаже. Опитай отново по-късно.");

  const showRecoveryCodes = () =>
    run(async () => {
      setRecoveryCodes(await api.getRecoveryCodes());
    }, "Резервните кодове не можаха да се заредят.");

  const regenerateRecoveryCodes = () => {
    if (!window.confirm("Старите резервни кодове ще спрат да работят. Да генерирам ли нови?")) {
      return;
    }

    void run(async () => {
      setRecoveryCodes(await api.regenerateRecoveryCodes());
      setMessage({ type: "success", text: "Генерирани са нови резервни кодове. Запази ги." });
    }, "Не успяхме да генерираме нови кодове.");
  };

  const disable = () => {
    if (!window.confirm("Да изключа ли двуфакторната защита? Входът ще иска само парола.")) {
      return;
    }

    void run(async () => {
      await api.disableTwoFactor();
      setRecoveryCodes(null);
      await refreshUser();
      setMessage({ type: "success", text: "Двуфакторната защита е изключена." });
    }, "Двуфакторната защита не можа да се изключи. Опитай отново по-късно.");
  };

  const handleConfirmed = async () => {
    setSetup(null);
    setRecoveryCodes(await api.getRecoveryCodes());
    await refreshUser();
    setMessage({ type: "success", text: "Двуфакторната защита е включена. Запази резервните кодове." });
  };

  return (
    <div className="flex flex-col gap-5">
      <p className={cn("flex items-center gap-2 text-sm font-semibold", user.two_factor_enabled ? "text-emerald-600" : mutedTextClassName)}>
        {user.two_factor_enabled ? <ShieldCheck className="size-5" /> : <ShieldOff className="size-5" />}
        {user.two_factor_enabled ? "Включена" : "Изключена"}
      </p>

      {pendingAction ? (
        <ConfirmPasswordForm
          onConfirmed={() => void run(pendingAction, "Действието не беше успешно. Опитай отново.")}
          onCancel={() => setPendingAction(null)}
        />
      ) : setup ? (
        <SetupStep setup={setup} isBusy={isBusy} onConfirmed={handleConfirmed} onCancel={() => void cancelSetup()} />
      ) : user.two_factor_enabled ? (
        <div className="flex flex-col gap-4">
          <p className={cn("text-sm", mutedTextClassName)}>
            При вход след паролата се иска код от приложението за автентикация.
          </p>

          {recoveryCodes && <RecoveryCodes codes={recoveryCodes} />}

          <div className="flex flex-wrap gap-3">
            {recoveryCodes ? (
              <button type="button" onClick={regenerateRecoveryCodes} disabled={isBusy} className={outlineClassName}>
                Генерирай нови кодове
              </button>
            ) : (
              <button type="button" onClick={() => void showRecoveryCodes()} disabled={isBusy} className={outlineClassName}>
                Покажи резервните кодове
              </button>
            )}
            <button
              type="button"
              onClick={disable}
              disabled={isBusy}
              className={cn(outlineClassName, "text-red-600 hover:text-red-600")}
            >
              Изключи
            </button>
          </div>
        </div>
      ) : (
        <div className="flex flex-col gap-4">
          <p className={cn("text-sm", mutedTextClassName)}>
            Освен паролата при вход ще се иска и код от приложение за автентикация (Google Authenticator,
            Microsoft Authenticator, 1Password и др.).
          </p>
          <button type="button" onClick={() => void enable()} disabled={isBusy} className={cn(primaryClassName, "self-start")}>
            <ShieldCheck />
            {isBusy ? "Включване…" : "Включи"}
          </button>
        </div>
      )}

      {message && (
        <p
          role={message.type === "error" ? "alert" : "status"}
          className={cn("text-sm", message.type === "error" ? "text-red-600" : "text-emerald-600")}
        >
          {message.text}
        </p>
      )}
    </div>
  );
}

function ConfirmPasswordForm({ onConfirmed, onCancel }: { onConfirmed: () => void; onCancel: () => void }) {
  const [password, setPassword] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [isSaving, setIsSaving] = useState(false);

  const handleSubmit = async (event: FormEvent) => {
    event.preventDefault();
    setIsSaving(true);
    setError(null);

    try {
      await api.confirmPassword(password);
      onConfirmed();
    } catch (caught) {
      if (caught instanceof ApiError && caught.status === 422) {
        setError("Паролата е грешна.");
      } else {
        console.error("Could not confirm the password.", caught);
        setError("Паролата не можа да се потвърди. Опитай отново по-късно.");
      }
      setIsSaving(false);
    }
  };

  return (
    <form onSubmit={(event) => void handleSubmit(event)} noValidate className="flex flex-col gap-4">
      <label className="flex flex-col gap-2 text-sm font-semibold">
        Потвърди паролата си, за да продължиш
        <input
          type="password"
          value={password}
          onChange={(event) => setPassword(event.target.value)}
          autoComplete="current-password"
          autoFocus
          required
          aria-invalid={error ? true : undefined}
          className={cn(formFieldClassName, "h-11")}
        />
        {error && <span className={fieldErrorClassName}>{error}</span>}
      </label>
      <div className="flex flex-wrap gap-3">
        <button type="submit" disabled={isSaving || password === ""} className={primaryClassName}>
          {isSaving ? "Проверка…" : "Потвърди"}
        </button>
        <button type="button" onClick={onCancel} disabled={isSaving} className={outlineClassName}>
          Откажи
        </button>
      </div>
    </form>
  );
}

function SetupStep({
  setup,
  isBusy,
  onConfirmed,
  onCancel,
}: {
  setup: { svg: string; secretKey: string };
  isBusy: boolean;
  onConfirmed: () => Promise<void>;
  onCancel: () => void;
}) {
  const [code, setCode] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [isSaving, setIsSaving] = useState(false);

  const handleSubmit = async (event: FormEvent) => {
    event.preventDefault();
    setIsSaving(true);
    setError(null);

    try {
      await api.confirmTwoFactor(code.trim());
      await onConfirmed();
    } catch (caught) {
      if (caught instanceof ApiError && caught.status === 422) {
        setError("Кодът е грешен или е изтекъл. Въведи текущия код от приложението.");
      } else {
        console.error("Could not confirm two-factor authentication.", caught);
        setError("Кодът не можа да се провери. Опитай отново по-късно.");
      }
      setIsSaving(false);
    }
  };

  return (
    <form onSubmit={(event) => void handleSubmit(event)} noValidate className="flex flex-col gap-4">
      <p className="text-sm">
        1. Сканирай QR кода с приложението за автентикация. Ако не можеш, въведи ключа ръчно.
      </p>
      <div className="flex flex-col items-start gap-3 sm:flex-row sm:items-center">
        {/* SVG markup generated by our backend (Fortify), not user input. */}
        <div
          aria-label="QR код за приложението за автентикация"
          role="img"
          className="rounded-xl border bg-white p-3"
          dangerouslySetInnerHTML={{ __html: setup.svg }}
        />
        <code className="rounded-lg bg-muted px-3 py-2 font-mono text-sm break-all">{setup.secretKey}</code>
      </div>

      <label className="flex flex-col gap-2 text-sm font-semibold">
        2. Въведи 6-цифрения код от приложението
        <input
          type="text"
          inputMode="numeric"
          autoComplete="one-time-code"
          value={code}
          onChange={(event) => setCode(event.target.value)}
          required
          aria-invalid={error ? true : undefined}
          className={cn(formFieldClassName, "h-11 max-w-48")}
        />
        {error && <span className={fieldErrorClassName}>{error}</span>}
      </label>

      <div className="flex flex-wrap gap-3">
        <button type="submit" disabled={isSaving || isBusy || code.trim() === ""} className={primaryClassName}>
          {isSaving ? "Проверка…" : "Потвърди и включи"}
        </button>
        <button type="button" onClick={onCancel} disabled={isSaving || isBusy} className={outlineClassName}>
          Откажи
        </button>
      </div>
    </form>
  );
}

function RecoveryCodes({ codes }: { codes: string[] }) {
  return (
    <div className="flex flex-col gap-2 rounded-xl border bg-muted/50 p-4">
      <p className="text-sm font-semibold">Резервни кодове</p>
      <p className={cn("text-sm", mutedTextClassName)}>
        Запази ги на сигурно място. Всеки код влиза веднъж, ако нямаш достъп до приложението.
      </p>
      <ul className="grid grid-cols-1 gap-1 font-mono text-sm sm:grid-cols-2">
        {codes.map((code) => (
          <li key={code}>{code}</li>
        ))}
      </ul>
    </div>
  );
}
