// Thin client for the Laravel backend (Sanctum cookie-based SPA auth).

export type User = {
  id: number;
  name: string;
  email: string;
  role: string;
  role_label: string;
};

// A null href marks a placeholder that is not wired up yet.
export type MenuItem = {
  key: string;
  label: string;
  href: string | null;
};

export type Dashboard = {
  user: User;
  menu: MenuItem[];
};

export type ValidationErrors = Record<string, string[]>;

const apiUrl = process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000";

export class ApiError extends Error {
  constructor(
    public readonly status: number,
    message: string,
    public readonly errors: ValidationErrors = {},
  ) {
    super(message);
    this.name = "ApiError";
  }
}

function readXsrfToken(): string | null {
  const match = document.cookie
    .split("; ")
    .find((cookie) => cookie.startsWith("XSRF-TOKEN="));

  return match ? decodeURIComponent(match.split("=")[1]) : null;
}

export async function apiFetch(
  path: string,
  init: RequestInit = {},
): Promise<Response> {
  const method = (init.method ?? "GET").toUpperCase();
  const headers = new Headers(init.headers);
  headers.set("Accept", "application/json");

  if (init.body !== undefined) {
    headers.set("Content-Type", "application/json");
  }

  if (method !== "GET" && method !== "HEAD") {
    const token = readXsrfToken();
    if (token) {
      headers.set("X-XSRF-TOKEN", token);
    }
  }

  const response = await fetch(`${apiUrl}${path}`, {
    ...init,
    method,
    headers,
    credentials: "include",
  });

  if (!response.ok) {
    const body: { message?: string; errors?: ValidationErrors } = await response
      .json()
      .catch(() => ({}));

    throw new ApiError(
      response.status,
      body.message ?? response.statusText,
      body.errors ?? {},
    );
  }

  return response;
}

export async function getCsrfCookie(): Promise<void> {
  await apiFetch("/sanctum/csrf-cookie");
}

export async function login(email: string, password: string): Promise<void> {
  await apiFetch("/api/login", {
    method: "POST",
    body: JSON.stringify({ email, password }),
  });
}

export async function logout(): Promise<void> {
  await apiFetch("/api/logout", { method: "POST" });
}

// Returns null when there is no active session (401).
export async function getUser(signal?: AbortSignal): Promise<User | null> {
  try {
    const response = await apiFetch("/api/user", { signal });
    const body: { data: User } = await response.json();
    return body.data;
  } catch (error) {
    if (error instanceof ApiError && error.status === 401) {
      return null;
    }
    throw error;
  }
}

export async function getDashboard(signal?: AbortSignal): Promise<Dashboard> {
  const response = await apiFetch("/api/dashboard", { signal });
  const body: { data: Dashboard } = await response.json();
  return body.data;
}
