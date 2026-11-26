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

// Approved categories with the most tools (deleted tools are not counted).
export type PopularCategory = {
  id: number;
  name: string;
  slug: string;
  tools_count: number;
};

export type DashboardStats = {
  tools_total: number;
  // Tools marked as useful for the user's company role.
  tools_for_company_role: number;
  // Tools the user marked as favorite.
  favorite_tools: number;
  popular_categories: PopularCategory[];
};

export type Dashboard = {
  user: User;
  menu: MenuItem[];
  stats: DashboardStats;
};

export type ValidationErrors = Record<string, string[]>;

export type CompanyRole = {
  id: number;
  slug: string;
  name: string;
};

// Users suggest categories; only approved ones are offered in the tool form.
export type Category = {
  id: number;
  name: string;
  slug: string;
  description: string | null;
  is_approved: boolean;
  // Only in the admin list of pending suggestions; null if the user was deleted.
  suggested_by?: { id: number; name: string } | null;
  created_at: string;
};

// Tags work like categories: users suggest them, only approved ones can be picked.
export type Tag = {
  id: number;
  name: string;
  slug: string;
  is_approved: boolean;
  // Only in the admin list of pending suggestions; null if the user was deleted.
  suggested_by?: { id: number; name: string } | null;
  created_at: string;
};

// What users suggest and an admin approves; also the API path segment.
export type SuggestionKind = "categories" | "tags";

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
  // How many users marked the tool as favorite, and whether the signed-in user did.
  favorites_count: number;
  is_favorite: boolean;
  can: { update: boolean; delete: boolean };
  created_at: string;
  updated_at: string;
};

// Data sent when creating or replacing a tool. Relations, tags included, are sent as ids.
export type ToolInput = {
  name: string;
  url: string;
  documentation_url: string;
  description: string;
  how_to_use: string;
  examples: string | null;
  company_roles: number[];
  categories: number[];
  tags: number[];
};

// `company_role`, `category` and `tag` are slugs.
export type ToolFilters = {
  search?: string;
  company_role?: string;
  category?: string;
  tag?: string;
  // 1: only the signed-in user's favorite tools.
  favorites?: 1;
  page?: number;
};

export type FavoriteInfo = Pick<Tool, "is_favorite" | "favorites_count">;

// Admin user list filters; `search` matches name or email, `company_role` is a slug.
export type UserFilters = {
  search?: string;
  system_role?: User["system_role"] | "";
  company_role?: string;
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

// Admin only. The slug cannot change later: dashboard menus are keyed by it.
export async function createCompanyRole(input: { slug: string; name: string }): Promise<CompanyRole> {
  const response = await apiFetch("/api/company-roles", {
    method: "POST",
    body: JSON.stringify(input),
  });
  const body: { data: CompanyRole } = await response.json();
  return body.data;
}

// Admin only. Only the name can change.
export async function updateCompanyRole(id: number, input: { name: string }): Promise<CompanyRole> {
  const response = await apiFetch(`/api/company-roles/${id}`, {
    method: "PUT",
    body: JSON.stringify(input),
  });
  const body: { data: CompanyRole } = await response.json();
  return body.data;
}

// Admin only. A role that users have cannot be deleted (409).
export async function deleteCompanyRole(id: number): Promise<void> {
  await apiFetch(`/api/company-roles/${id}`, { method: "DELETE" });
}

// Admin only. 20 users per page, ordered by name.
export async function getUsers(
  filters: UserFilters,
  signal?: AbortSignal,
): Promise<Paginated<User>> {
  const response = await apiFetch(`/api/users${toQuery(filters)}`, { signal });
  return response.json();
}

// Admin only, not oneself. A user who has added tools cannot be deleted (409).
export async function deleteUser(id: number): Promise<void> {
  await apiFetch(`/api/users/${id}`, { method: "DELETE" });
}

// Admin only. Taking the role from the last admin is refused (422 on `system_role`).
// Data an admin sends to create a user; `company_role` is a slug.
export type NewUserInput = {
  name: string;
  email: string;
  password: string;
  password_confirmation: string;
  company_role: string;
  system_role: User["system_role"];
};

export async function createUser(input: NewUserInput): Promise<User> {
  const response = await apiFetch("/api/users", {
    method: "POST",
    body: JSON.stringify(input),
  });
  const body: { data: User } = await response.json();
  return body.data;
}

// The signed-in user's own password (Fortify).
export async function updatePassword(input: {
  current_password: string;
  password: string;
  password_confirmation: string;
}): Promise<void> {
  await apiFetch("/api/user/password", {
    method: "PUT",
    body: JSON.stringify(input),
  });
}

export async function updateUserSystemRole(
  id: number,
  systemRole: User["system_role"],
): Promise<User> {
  const response = await apiFetch(`/api/users/${id}/system-role`, {
    method: "PATCH",
    body: JSON.stringify({ system_role: systemRole }),
  });
  const body: { data: User } = await response.json();
  return body.data;
}

export async function getCategories(signal?: AbortSignal): Promise<Category[]> {
  const response = await apiFetch("/api/categories", { signal });
  const body: { data: Category[] } = await response.json();
  return body.data;
}

// An admin's category is approved right away; anybody else's waits for an admin.
export async function suggestCategory(input: {
  name: string;
  slug: string;
  description: string | null;
}): Promise<Category> {
  const response = await apiFetch("/api/categories", {
    method: "POST",
    body: JSON.stringify(input),
  });
  const body: { data: Category } = await response.json();
  return body.data;
}

// An admin's tag is approved right away; anybody else's waits for an admin.
export async function suggestTag(input: { name: string; slug: string }): Promise<Tag> {
  const response = await apiFetch("/api/tags", {
    method: "POST",
    body: JSON.stringify(input),
  });
  const body: { data: Tag } = await response.json();
  return body.data;
}

// Admin only: the category or tag suggestions waiting for a decision.
export async function getPendingSuggestions(
  kind: SuggestionKind,
  signal?: AbortSignal,
): Promise<(Category | Tag)[]> {
  const response = await apiFetch(`/api/${kind}/pending`, { signal });
  const body: { data: (Category | Tag)[] } = await response.json();
  return body.data;
}

// Admin only.
export async function approveSuggestion(kind: SuggestionKind, id: number): Promise<void> {
  await apiFetch(`/api/${kind}/${id}/approve`, { method: "POST" });
}

// Admin only. The suggestion is deleted.
export async function rejectSuggestion(kind: SuggestionKind, id: number): Promise<void> {
  await apiFetch(`/api/${kind}/${id}`, { method: "DELETE" });
}

// Approved tags only.
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
  const response = await apiFetch(`/api/tools${toQuery(filters)}`, { signal });
  return response.json();
}

// "?key=value&…" for the filled-in filters, or "" when there are none.
function toQuery(filters: Record<string, string | number | undefined>): string {
  const params = new URLSearchParams();

  for (const [key, value] of Object.entries(filters)) {
    if (value !== undefined && value !== "") {
      params.set(key, String(value));
    }
  }

  return params.size > 0 ? `?${params}` : "";
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

// Adding and removing can both be repeated safely; the answer is the current state.
export async function setToolFavorite(toolId: number, isFavorite: boolean): Promise<FavoriteInfo> {
  const response = await apiFetch(`/api/tools/${toolId}/favorite`, {
    method: isFavorite ? "PUT" : "DELETE",
  });
  const body: { data: FavoriteInfo } = await response.json();
  return body.data;
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
