// Shared Tailwind classes for buttons, fields and text (tool and admin pages).

export const inputClassName =
  "w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm font-normal dark:border-zinc-700 dark:bg-zinc-900";

// Taller, rounder fields of the tool form (add and edit tool pages).
export const formFieldClassName =
  "w-full rounded-xl border border-input bg-background px-3.5 py-2.5 text-sm font-normal shadow-xs outline-none transition-colors placeholder:text-muted-foreground focus-visible:border-violet-500 focus-visible:ring-3 focus-visible:ring-violet-500/20 aria-invalid:border-destructive aria-invalid:ring-3 aria-invalid:ring-destructive/20 dark:bg-input/30";

export const primaryButtonClassName =
  "inline-flex items-center justify-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500 disabled:opacity-60";

export const secondaryButtonClassName =
  "inline-flex items-center justify-center rounded-md border border-zinc-300 px-4 py-2 text-sm font-medium hover:border-indigo-500 hover:text-indigo-600 disabled:opacity-60 dark:border-zinc-700";

export const dangerButtonClassName =
  "inline-flex items-center justify-center rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-500 disabled:opacity-60";

export const chipClassName =
  "inline-flex items-center gap-1 rounded-full bg-zinc-100 px-2.5 py-0.5 text-xs font-medium text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300";

export const fieldErrorClassName = "text-sm font-normal text-red-600";

export const mutedTextClassName = "text-zinc-600 dark:text-zinc-400";
