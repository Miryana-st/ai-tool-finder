"use client";

import { Send } from "lucide-react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useEffect, useState, type FormEvent, type ReactNode } from "react";
import { buttonVariants } from "@/components/ui/button";
import * as api from "@/lib/api";
import {
  ApiError,
  type Category,
  type CompanyRole,
  type Tag,
  type Tool,
  type ToolImage,
  type ToolInput,
  type ValidationErrors,
} from "@/lib/api";
import { cn } from "@/lib/utils";
import CategoryPicker from "./CategoryPicker";
import RequiredMark from "./RequiredMark";
import TagInput from "./TagInput";
import ToolImagesInput from "./ToolImagesInput";
import { fieldErrorClassName, formFieldClassName, mutedTextClassName } from "@/styles/classNames";

type TextFields = Pick<ToolInput, "name" | "url" | "documentation_url" | "description" | "how_to_use"> & {
  examples: string;
};

// Shared by /tools/new (no `tool`) and /tools/[slug]/edit.
export default function ToolForm({ tool }: { tool?: Tool }) {
  const router = useRouter();
  const [fields, setFields] = useState<TextFields>({
    name: tool?.name ?? "",
    url: tool?.url ?? "",
    documentation_url: tool?.documentation_url ?? "",
    description: tool?.description ?? "",
    how_to_use: tool?.how_to_use ?? "",
    examples: tool?.examples ?? "",
  });
  const [companyRoleIds, setCompanyRoleIds] = useState<number[]>(
    tool?.company_roles.map((role) => role.id) ?? [],
  );
  const [categoryIds, setCategoryIds] = useState<number[]>(
    tool?.categories.map((category) => category.id) ?? [],
  );
  const [tags, setTags] = useState<Tag[]>(tool?.tags ?? []);
  const [images, setImages] = useState<ToolImage[]>(tool?.images ?? []);
  const [files, setFiles] = useState<File[]>([]);

  const [companyRoles, setCompanyRoles] = useState<CompanyRole[]>([]);
  const [categories, setCategories] = useState<Category[]>([]);
  const [optionsError, setOptionsError] = useState(false);

  const [errors, setErrors] = useState<ValidationErrors>({});
  const [formError, setFormError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  useEffect(() => {
    const controller = new AbortController();

    Promise.all([api.getCompanyRoles(controller.signal), api.getCategories(controller.signal)])
      .then(([loadedRoles, loadedCategories]) => {
        setCompanyRoles(loadedRoles);
        setCategories(loadedCategories);
      })
      .catch((error: unknown) => {
        if (api.isAbortError(error)) {
          return;
        }
        console.error("Could not load the form options.", error);
        setOptionsError(true);
      });

    return () => controller.abort();
  }, []);

  const setField = (name: keyof TextFields, value: string) =>
    setFields((current) => ({ ...current, [name]: value }));

  const toggle = (ids: number[], id: number) =>
    ids.includes(id) ? ids.filter((current) => current !== id) : [...ids, id];

  const error = (field: string) => api.fieldError(errors, field);

  const handleSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setIsSubmitting(true);
    setErrors({});
    setFormError(null);

    const input: ToolInput = {
      ...fields,
      examples: fields.examples.trim() === "" ? null : fields.examples,
      company_roles: companyRoleIds,
      categories: categoryIds,
      tags: tags.map((tag) => tag.id),
    };

    let saved: Tool;

    try {
      saved = tool ? await api.updateTool(tool.id, input) : await api.createTool(input);
    } catch (caught) {
      setIsSubmitting(false);

      if (caught instanceof ApiError && caught.status === 422) {
        setErrors(caught.errors);
        setFormError("Провери маркираните полета.");
      } else if (caught instanceof ApiError && caught.status === 403) {
        setFormError("Нямаш права да редактираш този инструмент.");
      } else {
        console.error("Could not save the tool.", caught);
        setFormError("Инструментът не можа да се запази. Опитай отново по-късно.");
      }
      return;
    }

    // A new tool exists only now, so its waiting screenshots are uploaded after it.
    for (const file of files) {
      try {
        await api.uploadToolImage(saved.id, file);
      } catch (caught) {
        console.error("Could not upload a screenshot of the new tool.", caught);
        router.push(`/tools/${saved.slug}/edit?images=failed`);
        return;
      }
    }

    router.push(`/tools/${saved.slug}`);
  };

  return (
    <form
      onSubmit={handleSubmit}
      noValidate
      className="flex flex-col gap-6 rounded-2xl border bg-card p-5 text-card-foreground shadow-sm md:p-6"
    >
      {formError && (
        <p role="alert" className="text-sm text-red-600">
          {formError}
        </p>
      )}
      {optionsError && (
        <p role="alert" className="text-sm text-red-600">
          Длъжностите и категориите не можаха да се заредят. Презареди страницата.
        </p>
      )}

      <div className="grid grid-cols-1 gap-8 lg:grid-cols-[minmax(0,0.9fr)_minmax(0,1.2fr)_minmax(0,1fr)]">
        <div className="flex flex-col gap-5">
          <div className="flex flex-col gap-2">
            <ToolImagesInput
              toolId={tool?.id ?? null}
              images={images}
              onImagesChange={setImages}
              files={files}
              onFilesChange={setFiles}
            />
            {tool && (
              <p className={`text-xs ${mutedTextClassName}`}>Снимките се качват и изтриват веднага.</p>
            )}
          </div>

          <CategoryPicker
            categories={categories}
            selected={categoryIds}
            onToggle={(id) => setCategoryIds((current) => toggle(current, id))}
            onCreated={(category) => {
              setCategories((current) =>
                [...current, category].sort((a, b) => a.name.localeCompare(b.name)),
              );
              setCategoryIds((current) => [...current, category.id]);
            }}
            error={error("categories")}
          />

          <TagInput tags={tags} onChange={setTags} error={error("tags")} />
        </div>

        <div className="flex flex-col gap-5">
          <TextField label="Име" required error={error("name")}>
            <input
              type="text"
              value={fields.name}
              onChange={(event) => setField("name", event.target.value)}
              maxLength={255}
              placeholder="напр. ChatGPT"
              aria-invalid={Boolean(error("name"))}
              className={cn(formFieldClassName, "h-11")}
            />
          </TextField>

          <TextField label="URL на инструмента" required error={error("url")}>
            <input
              type="url"
              value={fields.url}
              onChange={(event) => setField("url", event.target.value)}
              placeholder="https://…"
              aria-invalid={Boolean(error("url"))}
              className={cn(formFieldClassName, "h-11")}
            />
          </TextField>

          <TextField label="Официална документация" required error={error("documentation_url")}>
            <input
              type="url"
              value={fields.documentation_url}
              onChange={(event) => setField("documentation_url", event.target.value)}
              placeholder="https://…"
              aria-invalid={Boolean(error("documentation_url"))}
              className={cn(formFieldClassName, "h-11")}
            />
          </TextField>

          <TextField label="Описание" required error={error("description")}>
            <textarea
              value={fields.description}
              onChange={(event) => setField("description", event.target.value)}
              rows={4}
              placeholder="Какво представлява инструментът и с какво е специален? Поддържа Markdown."
              aria-invalid={Boolean(error("description"))}
              className={formFieldClassName}
            />
          </TextField>

          <TextField label="Как се използва" required error={error("how_to_use")}>
            <textarea
              value={fields.how_to_use}
              onChange={(event) => setField("how_to_use", event.target.value)}
              rows={4}
              placeholder="Стъпка по стъпка… Поддържа Markdown."
              aria-invalid={Boolean(error("how_to_use"))}
              className={formFieldClassName}
            />
          </TextField>
        </div>

        <div className="flex flex-col gap-5">
          <fieldset className="flex flex-col gap-2">
            <legend className="mb-2 text-sm font-semibold">
              Длъжност <RequiredMark />
            </legend>
            <div className="flex flex-col gap-2.5">
              {companyRoles.map((role) => (
                <label key={role.id} className="flex cursor-pointer items-center gap-2.5 text-sm">
                  <input
                    type="checkbox"
                    checked={companyRoleIds.includes(role.id)}
                    onChange={() => setCompanyRoleIds((current) => toggle(current, role.id))}
                    className="size-4 accent-violet-600"
                  />
                  {role.name}
                </label>
              ))}
            </div>
            {error("company_roles") && <span className={fieldErrorClassName}>{error("company_roles")}</span>}
          </fieldset>

          <TextField label="Примери (не е задължително)" error={error("examples")}>
            <textarea
              value={fields.examples}
              onChange={(event) => setField("examples", event.target.value)}
              rows={3}
              placeholder="Markdown, напр. - [Заглавие](https://пример)"
              aria-invalid={Boolean(error("examples"))}
              className={formFieldClassName}
            />
          </TextField>
        </div>
      </div>

      <div className="flex justify-end gap-3 border-t pt-5">
        <Link
          href={tool ? `/tools/${tool.slug}` : "/tools"}
          className={cn(buttonVariants({ variant: "outline" }), "h-11 rounded-xl px-6")}
        >
          Откажи
        </Link>
        <button
          type="submit"
          disabled={isSubmitting}
          className={cn(
            buttonVariants(),
            "h-11 rounded-xl bg-violet-600 px-6 text-white shadow-md shadow-violet-600/30 hover:bg-violet-500",
          )}
        >
          <Send aria-hidden="true" />
          {isSubmitting ? "Запазване…" : "Запази"}
        </button>
      </div>
    </form>
  );
}

function TextField({
  label,
  required = false,
  error,
  children,
}: {
  label: string;
  required?: boolean;
  error: string | null;
  children: ReactNode;
}) {
  return (
    <label className="flex flex-col gap-2 text-sm font-semibold">
      <span>
        {label}
        {required && (
          <>
            {" "}
            <RequiredMark />
          </>
        )}
      </span>
      {children}
      {error && <span className={fieldErrorClassName}>{error}</span>}
    </label>
  );
}
