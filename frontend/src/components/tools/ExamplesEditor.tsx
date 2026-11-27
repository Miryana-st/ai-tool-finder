"use client";

import { ImagePlus, Link2 } from "lucide-react";
import { useEffect, useId, useRef, useState, type ChangeEvent } from "react";
import { Button } from "@/components/ui/button";
import * as api from "@/lib/api";
import { ApiError, MAX_TOOL_IMAGE_BYTES, TOOL_IMAGE_TYPES } from "@/lib/api";
import { cn } from "@/lib/utils";
import { formFieldClassName } from "@/styles/classNames";
import Markdown from "./Markdown";

// The Markdown "examples" of a tool, with buttons that insert a link or an uploaded
// screenshot where the cursor is, and a preview of how the text will look.
export default function ExamplesEditor({
  value,
  onChange,
  invalid,
}: {
  value: string;
  onChange: (value: string) => void;
  invalid: boolean;
}) {
  const textareaRef = useRef<HTMLTextAreaElement>(null);
  // The latest text. An upload finishes in the handler of an earlier render, whose
  // `value` misses what was typed meanwhile; inserting into that would drop it.
  const valueRef = useRef(value);
  useEffect(() => {
    valueRef.current = value;
  }, [value]);
  const fileInputRef = useRef<HTMLInputElement>(null);
  const id = useId();
  const [mode, setMode] = useState<"write" | "preview">("write");
  const [linkForm, setLinkForm] = useState<{ url: string; text: string } | null>(null);
  const [linkError, setLinkError] = useState<string | null>(null);
  const [isUploading, setIsUploading] = useState(false);
  const [uploadError, setUploadError] = useState<string | null>(null);

  // Puts the snippet in place of the selection (or at the cursor) and moves the cursor
  // after it. A block (a screenshot) gets blank lines around it: in Markdown a single
  // line break does not start a new paragraph.
  const insert = (snippet: string, block: boolean) => {
    const current = valueRef.current;
    const textarea = textareaRef.current;
    // The cursor is read now, so it matches the latest text.
    const start = Math.min(textarea?.selectionStart ?? current.length, current.length);
    const end = Math.min(textarea?.selectionEnd ?? current.length, current.length);
    const before = current.slice(0, start);
    const after = current.slice(end);
    const prefix = block && before.trim() !== "" ? "\n".repeat(2 - trailingNewlines(before)) : "";
    const suffix = block && after.trim() !== "" ? "\n".repeat(2 - leadingNewlines(after)) : "";
    const inserted = `${prefix}${snippet}${suffix}`;
    const next = before + inserted + after;

    valueRef.current = next;
    onChange(next);
    setMode("write");

    requestAnimationFrame(() => {
      const cursor = start + inserted.length;
      textareaRef.current?.focus();
      textareaRef.current?.setSelectionRange(cursor, cursor);
    });
  };

  const openLinkForm = () => {
    const textarea = textareaRef.current;
    const selected = textarea ? value.slice(textarea.selectionStart, textarea.selectionEnd) : "";
    setLinkForm({ url: "", text: selected });
    setLinkError(null);
  };

  const insertLink = () => {
    if (!linkForm) {
      return;
    }

    const url = linkForm.url.trim();

    if (!isHttpUrl(url)) {
      setLinkError("Въведи адрес, който започва с http:// или https://.");
      return;
    }

    insert(`[${escapeLinkText(linkForm.text.trim() || url)}](${escapeUrl(url)})`, false);
    setLinkForm(null);
  };

  const handleFile = async (event: ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0];
    // The same file can be picked again later.
    event.target.value = "";

    if (!file) {
      return;
    }

    setUploadError(null);

    if (!TOOL_IMAGE_TYPES.includes(file.type)) {
      setUploadError("Само PNG, JPG или WEBP.");
      return;
    }
    if (file.size > MAX_TOOL_IMAGE_BYTES) {
      setUploadError("Файлът е по-голям от 4 MB.");
      return;
    }

    setIsUploading(true);

    try {
      const image = await api.uploadExampleImage(file);
      insert(`![${escapeLinkText(withoutExtension(image.original_name))}](${escapeUrl(image.url)})`, true);
    } catch (caught) {
      console.error("Could not upload the example screenshot.", caught);
      setUploadError(
        caught instanceof ApiError && caught.status === 429
          ? "Качи твърде много снимки за кратко време. Опитай след минута."
          : (caught instanceof ApiError && api.fieldError(caught.errors, "image")) ||
              "Снимката не можа да се качи. Опитай отново.",
      );
    } finally {
      setIsUploading(false);
    }
  };

  return (
    <div className="flex flex-col gap-2 font-normal">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <div className="flex flex-wrap gap-2">
          <Button type="button" variant="outline" size="sm" onClick={openLinkForm} className="rounded-lg">
            <Link2 aria-hidden="true" />
            Добави линк
          </Button>
          <Button
            type="button"
            variant="outline"
            size="sm"
            onClick={() => fileInputRef.current?.click()}
            disabled={isUploading}
            className="rounded-lg"
          >
            <ImagePlus aria-hidden="true" />
            {isUploading ? "Качване…" : "Качи скрийншот"}
          </Button>
          <input
            ref={fileInputRef}
            type="file"
            accept={TOOL_IMAGE_TYPES.join(",")}
            onChange={(event) => void handleFile(event)}
            className="hidden"
            aria-hidden="true"
            tabIndex={-1}
          />
        </div>

        <div role="tablist" aria-label="Изглед на примерите" className="flex rounded-lg border p-0.5 text-xs">
          {(["write", "preview"] as const).map((tab) => (
            <button
              key={tab}
              type="button"
              role="tab"
              aria-selected={mode === tab}
              onClick={() => setMode(tab)}
              className={cn(
                "rounded-md px-2.5 py-1 font-medium",
                mode === tab ? "bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300" : "text-muted-foreground",
              )}
            >
              {tab === "write" ? "Писане" : "Преглед"}
            </button>
          ))}
        </div>
      </div>

      {linkForm && (
        <div className="flex flex-col gap-2 rounded-xl border bg-muted/40 p-3">
          <label htmlFor={`${id}-url`} className="text-xs font-semibold">
            Адрес
          </label>
          <input
            id={`${id}-url`}
            type="url"
            value={linkForm.url}
            onChange={(event) => setLinkForm({ ...linkForm, url: event.target.value })}
            onKeyDown={(event) => {
              // Enter would submit the whole tool form.
              if (event.key === "Enter") {
                event.preventDefault();
                insertLink();
              }
            }}
            placeholder="https://…"
            aria-invalid={linkError !== null}
            autoFocus
            className={formFieldClassName}
          />
          <label htmlFor={`${id}-text`} className="text-xs font-semibold">
            Текст на линка (не е задължително)
          </label>
          <input
            id={`${id}-text`}
            value={linkForm.text}
            onChange={(event) => setLinkForm({ ...linkForm, text: event.target.value })}
            onKeyDown={(event) => {
              if (event.key === "Enter") {
                event.preventDefault();
                insertLink();
              }
            }}
            placeholder="напр. Примерен PR"
            className={formFieldClassName}
          />
          {linkError && (
            <p role="alert" className="text-xs text-red-600">
              {linkError}
            </p>
          )}
          <div className="flex justify-end gap-2">
            <Button type="button" variant="outline" size="sm" onClick={() => setLinkForm(null)} className="rounded-lg">
              Отказ
            </Button>
            <Button type="button" size="sm" onClick={insertLink} className="rounded-lg bg-violet-600 text-white hover:bg-violet-700">
              Вмъкни линка
            </Button>
          </div>
        </div>
      )}

      {uploadError && (
        <p role="alert" className="text-xs text-red-600">
          {uploadError}
        </p>
      )}

      {mode === "write" ? (
        <textarea
          ref={textareaRef}
          value={value}
          onChange={(event) => onChange(event.target.value)}
          rows={6}
          placeholder="Опиши как използваш инструмента. С бутоните можеш да добавиш линк или скрийншот."
          aria-label="Примери"
          aria-invalid={invalid}
          className={cn(formFieldClassName, "resize-y")}
        />
      ) : (
        <div className="min-h-36 rounded-xl border bg-background px-3.5 py-2.5">
          {value.trim() === "" ? (
            <p className="text-sm text-muted-foreground">Няма какво да се покаже.</p>
          ) : (
            <Markdown>{value}</Markdown>
          )}
        </div>
      )}
    </div>
  );
}

function isHttpUrl(value: string): boolean {
  try {
    const url = new URL(value);
    return url.protocol === "http:" || url.protocol === "https:";
  } catch {
    return false;
  }
}

// Square brackets would end the link text early.
function escapeLinkText(text: string): string {
  return text.replace(/[[\]\\]/g, "\\$&");
}

// Spaces and parentheses would end the Markdown link target early.
function escapeUrl(url: string): string {
  return url.replace(/ /g, "%20").replace(/\(/g, "%28").replace(/\)/g, "%29");
}

// How many line breaks (at most 2) the text ends or starts with.
function trailingNewlines(text: string): number {
  return Math.min(text.length - text.replace(/\n+$/, "").length, 2);
}

function leadingNewlines(text: string): number {
  return Math.min(text.length - text.replace(/^\n+/, "").length, 2);
}

function withoutExtension(name: string): string {
  return name.replace(/\.[^.]+$/, "") || "скрийншот";
}
