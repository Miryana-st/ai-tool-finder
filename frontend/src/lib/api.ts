// Thin client for the Laravel backend (Sanctum cookie-based SPA auth).

export type User = {
  id: number;
  name: string;
  email: string;
  // Rights in the application: "user" or "admin".
  system_role: "user" | "admin";
  system_role_label: string;
  // Position in the company (backend, qa, ...); admins can add new ones.
  company_role: string;
  company_role_label: string;
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

export type CompanyRole = {
  id: number;
  slug: string;
  name: string;
};

export type Category = {
  id: number;
  name: string;
  slug: string;
};

export type Tag = {
  id: number;
  name: string;
  slug: string;
};

export type ToolImage = {
  id: number;
  url: string;
  original_name: string;
  sort_order: number;
};

// `description`, `how_to_use` and `examples` are Markdown.
export type Tool = {
  id: number;
  name: string;
  slug: string;
  url: string;
  documentation_url: string;
  description: string;
  how_to_use: string;
  examples: string | null;
  creator: { id: number; name: string };
  categories: Category[];
  tags: Tag[];
  company_roles: CompanyRole[];
  images: ToolImage[];
  can: { update: boolean; delete: boolean };
  created_at: string;
  updated_at: string;
};

// Data sent when creating or replacing a tool. Relations are sent as ids, tags as names.
export type ToolInput = {
  name: string;
  url: string;
  documentation_url: string;
  description: string;
  how_to_use: string;
  examples: string | null;
  company_roles: number[];
  categories: number[];
  tags: string[];
};

// `company_role`, `category` and `tag` are slugs.
export type ToolFilters = {
  search?: string;
  company_role?: string;
  category?: string;
  tag?: string;
  page?: number;
};

export type PaginationMeta = {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
};

export type Paginated<T> = {
  data: T[];
  meta: PaginationMeta;
};

// Screenshot limits, kept in line with StoreToolImageRequest and Tool::MAX_IMAGES.
export const MAX_TOOL_IMAGES = 6;
export const MAX_TOOL_IMAGE_BYTES = 4096 * 1024;
export const TOOL_IMAGE_TYPES = ["image/png", "image/jpeg", "image/webp"];

// Empty by default: nginx serves the API from the same origin as the frontend.
const apiUrl = process.env.NEXT_PUBLIC_API_URL ?? "";

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

export function isAbortError(error: unknown): boolean {
  return error instanceof DOMException && error.name === "AbortError";
}

// The first message for a field, including its array items ("tags.2" counts for "tags").
export function fieldError(errors: ValidationErrors, field: string): string | null {
  const key = Object.keys(errors).find(
    (name) => name === field || name.startsWith(`${field}.`),
  );

  return key ? errors[key][0] : null;
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

  // The browser sets multipart/form-data with its boundary for FormData itself.
  if (init.body !== undefined && !(init.body instanceof FormData)) {
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

export async function getCompanyRoles(signal?: AbortSignal): Promise<CompanyRole[]> {
  const response = await apiFetch("/api/company-roles", { signal });
  const body: { data: CompanyRole[] } = await response.json();
  return body.data;
}

export async function getCategories(signal?: AbortSignal): Promise<Category[]> {
  const response = await apiFetch("/api/categories", { signal });
  const body: { data: Category[] } = await response.json();
  return body.data;
}

export async function createCategory(name: string): Promise<Category> {
  const response = await apiFetch("/api/categories", {
    method: "POST",
    body: JSON.stringify({ name }),
  });
  const body: { data: Category } = await response.json();
  return body.data;
}

export async function getTags(search = "", signal?: AbortSignal): Promise<Tag[]> {
  const query = search ? `?${new URLSearchParams({ search })}` : "";
  const response = await apiFetch(`/api/tags${query}`, { signal });
  const body: { data: Tag[] } = await response.json();
  return body.data;
}

export async function getTools(
  filters: ToolFilters,
  signal?: AbortSignal,
): Promise<Paginated<Tool>> {
  const params = new URLSearchParams();

  for (const [key, value] of Object.entries(filters)) {
    if (value !== undefined && value !== "") {
      params.set(key, String(value));
    }
  }

  const query = params.size > 0 ? `?${params}` : "";
  const response = await apiFetch(`/api/tools${query}`, { signal });
  return response.json();
}

// Tools are shown by slug but changed by id (see routes/api.php).
export async function getTool(slug: string, signal?: AbortSignal): Promise<Tool> {
  const response = await apiFetch(`/api/tools/${encodeURIComponent(slug)}`, { signal });
  const body: { data: Tool } = await response.json();
  return body.data;
}

export async function createTool(input: ToolInput): Promise<Tool> {
  const response = await apiFetch("/api/tools", {
    method: "POST",
    body: JSON.stringify(input),
  });
  const body: { data: Tool } = await response.json();
  return body.data;
}

export async function updateTool(id: number, input: ToolInput): Promise<Tool> {
  const response = await apiFetch(`/api/tools/${id}`, {
    method: "PUT",
    body: JSON.stringify(input),
  });
  const body: { data: Tool } = await response.json();
  return body.data;
}

export async function deleteTool(id: number): Promise<void> {
  await apiFetch(`/api/tools/${id}`, { method: "DELETE" });
}

export async function uploadToolImage(toolId: number, file: File): Promise<ToolImage> {
  const formData = new FormData();
  formData.append("image", file);

  const response = await apiFetch(`/api/tools/${toolId}/images`, {
    method: "POST",
    body: formData,
  });
  const body: { data: ToolImage } = await response.json();
  return body.data;
}

export async function deleteToolImage(toolId: number, imageId: number): Promise<void> {
  await apiFetch(`/api/tools/${toolId}/images/${imageId}`, { method: "DELETE" });
}
