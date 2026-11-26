"use client";

import { useRouter } from "next/navigation";
import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
  type ReactNode,
} from "react";
import * as api from "@/lib/api";
import type { User } from "@/lib/api";

type AuthStatus = "loading" | "authenticated" | "guest";

type AuthContextValue = {
  user: User | null;
  status: AuthStatus;
  // Resolves with `twoFactor: true` when the user still has to enter a code
  // (completeTwoFactor); otherwise the user is signed in.
  login: (email: string, password: string) => Promise<{ twoFactor: boolean }>;
  completeTwoFactor: (input: { code: string } | { recovery_code: string }) => Promise<void>;
  logout: () => Promise<void>;
  // Loads the user again, e.g. after they changed their name or email.
  refreshUser: () => Promise<void>;
};

const AuthContext = createContext<AuthContextValue | null>(null);

export function AuthProvider({ children }: { children: ReactNode }) {
  const router = useRouter();
  const [user, setUser] = useState<User | null>(null);
  const [status, setStatus] = useState<AuthStatus>("loading");

  const applyUser = useCallback((current: User | null) => {
    setUser(current);
    setStatus(current ? "authenticated" : "guest");
  }, []);

  useEffect(() => {
    const controller = new AbortController();

    api
      .getUser(controller.signal)
      .then(applyUser)
      .catch((error: unknown) => {
        if (error instanceof DOMException && error.name === "AbortError") {
          return;
        }
        console.error("Could not load the current user.", error);
        applyUser(null);
      });

    return () => controller.abort();
  }, [applyUser]);

  const login = useCallback(
    async (email: string, password: string) => {
      await api.getCsrfCookie();
      const { two_factor } = await api.login(email, password);

      if (two_factor) {
        return { twoFactor: true };
      }

      applyUser(await api.getUser());
      return { twoFactor: false };
    },
    [applyUser],
  );

  const completeTwoFactor = useCallback(
    async (input: { code: string } | { recovery_code: string }) => {
      await api.sendTwoFactorChallenge(input);
      applyUser(await api.getUser());
    },
    [applyUser],
  );

  const logout = useCallback(async () => {
    await api.logout();
    applyUser(null);
    router.push("/login");
  }, [applyUser, router]);

  const refreshUser = useCallback(async () => {
    applyUser(await api.getUser());
  }, [applyUser]);

  const value = useMemo(
    () => ({ user, status, login, completeTwoFactor, logout, refreshUser }),
    [user, status, login, completeTwoFactor, logout, refreshUser],
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthContextValue {
  const context = useContext(AuthContext);

  if (!context) {
    throw new Error("useAuth must be used within an AuthProvider.");
  }

  return context;
}
