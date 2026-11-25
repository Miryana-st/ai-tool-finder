"use client";

import { CloudUpload, Plus, X } from "lucide-react";
import { useEffect, useRef, useState, type DragEvent } from "react";
import * as api from "@/lib/api";
import {
  ApiError,
  MAX_TOOL_IMAGE_BYTES,
  MAX_TOOL_IMAGES,
  TOOL_IMAGE_TYPES,
  type ToolImage,
} from "@/lib/api";
import { fieldErrorClassName, mutedTextClassName } from "@/styles/classNames";

type Props = {
  // Set for an existing tool: files are uploaded and deleted right away.
  // Null for a new tool: files wait in `files` until the tool is created.
  toolId: number | null;
  images: ToolImage[];
  onImagesChange: (images: ToolImage[]) => void;
  files: File[];
  onFilesChange: (files: File[]) => void;
};

// Screenshots: drag & drop or file picker, with previews and delete.
export default function ToolImagesInput({ toolId, images, onImagesChange, files, onFilesChange }: Props) {
  const [error, setError] = useState<string | null>(null);
  const [isDragging, setIsDragging] = useState(false);
  const [isBusy, setIsBusy] = useState(false);
  const fileInput = useRef<HTMLInputElement>(null);

  const count = images.length + files.length;

  const addFiles = async (selected: File[]) => {
    setError(null);

    const valid = selected.filter((file) => TOOL_IMAGE_TYPES.includes(file.type) && file.size <= MAX_TOOL_IMAGE_BYTES);
    const room = MAX_TOOL_IMAGES - count;
    const accepted = valid.slice(0, Math.max(0, room));

    if (valid.length < selected.length) {
      setError("Някои файлове бяха пропуснати: позволени са PNG, JPG и WEBP до 4 MB.");
    } else if (accepted.length < valid.length) {
      setError(`Може да има най-много ${MAX_TOOL_IMAGES} скрийншота.`);
    }

    if (accepted.length === 0) {
      return;
    }

    if (toolId === null) {
      onFilesChange([...files, ...accepted]);
      return;
    }

    setIsBusy(true);
    const uploaded: ToolImage[] = [];

    try {
      for (const file of accepted) {
        uploaded.push(await api.uploadToolImage(toolId, file));
      }
    } catch (caught) {
      if (caught instanceof ApiError && caught.status === 422) {
        setError(api.fieldError(caught.errors, "image") ?? caught.message);
      } else {
        console.error("Could not upload the screenshot.", caught);
        setError("Скрийншотът не можа да се качи. Опитай отново.");
      }
    } finally {
      onImagesChange([...images, ...uploaded]);
      setIsBusy(false);
    }
  };

  const removeImage = async (image: ToolImage) => {
    if (toolId === null) {
      return;
    }

    setError(null);
    setIsBusy(true);

    try {
      await api.deleteToolImage(toolId, image.id);
      onImagesChange(images.filter((current) => current.id !== image.id));
    } catch (caught) {
      console.error("Could not delete the screenshot.", caught);
      setError("Скрийншотът не можа да се изтрие. Опитай отново.");
    } finally {
      setIsBusy(false);
    }
  };

  const handleDrop = (event: DragEvent<HTMLButtonElement>) => {
    event.preventDefault();
    setIsDragging(false);

    if (!isBusy) {
      void addFiles(Array.from(event.dataTransfer.files));
    }
  };

  const openPicker = () => fileInput.current?.click();
  const canAdd = count < MAX_TOOL_IMAGES;

  return (
    <fieldset className="flex flex-col gap-3">
      <legend className="sr-only">
        Снимки ({count}/{MAX_TOOL_IMAGES})
      </legend>

      <input
        ref={fileInput}
        type="file"
        accept={TOOL_IMAGE_TYPES.join(",")}
        multiple
        hidden
        onChange={(event) => {
          void addFiles(Array.from(event.target.files ?? []));
          event.target.value = "";
        }}
      />

      {canAdd && (
        <button
          type="button"
          onClick={openPicker}
          disabled={isBusy}
          onDragOver={(event) => {
            event.preventDefault();
            setIsDragging(true);
          }}
          onDragLeave={() => setIsDragging(false)}
          onDrop={handleDrop}
          className={`flex min-h-52 flex-col items-center justify-center gap-2 rounded-2xl border-2 border-dashed px-4 py-8 text-center transition-colors disabled:opacity-60 ${
            isDragging
              ? "border-violet-500 bg-violet-50 dark:bg-violet-950/40"
              : "border-violet-200 bg-violet-50/40 hover:border-violet-400 dark:border-violet-900 dark:bg-transparent"
          }`}
        >
          <CloudUpload className="size-14 text-violet-600" strokeWidth={1.75} aria-hidden="true" />
          <span className="text-base font-semibold">Качи картинка</span>
          <span className={`text-sm ${mutedTextClassName}`}>Пусни файл тук или кликни за качване</span>
          <span className={`text-xs ${mutedTextClassName}`}>
            PNG, JPG или WEBP, до 4 MB · {count}/{MAX_TOOL_IMAGES}
          </span>
        </button>
      )}

      {isBusy && <p className={`text-sm ${mutedTextClassName}`}>Обработване…</p>}
      {error && (
        <p role="alert" className={fieldErrorClassName}>
          {error}
        </p>
      )}

      {count > 0 && (
        <ul className="grid grid-cols-3 gap-2">
          {images.map((image) => (
            <li key={`image-${image.id}`} className="relative">
              {/* eslint-disable-next-line @next/next/no-img-element */}
              <img src={image.url} alt={image.original_name} className={thumbnailClassName} />
              <RemoveButton label={image.original_name} disabled={isBusy} onClick={() => void removeImage(image)} />
            </li>
          ))}
          {files.map((file, index) => (
            <li key={`file-${index}-${file.name}`} className="relative">
              <FilePreview file={file} />
              <RemoveButton
                label={file.name}
                disabled={isBusy}
                onClick={() => onFilesChange(files.filter((current) => current !== file))}
              />
            </li>
          ))}
        </ul>
      )}

      {count > 0 && canAdd && (
        <button
          type="button"
          onClick={openPicker}
          disabled={isBusy}
          className="inline-flex h-11 items-center justify-center gap-2 rounded-xl border bg-background text-sm font-semibold text-violet-600 shadow-xs hover:bg-violet-50 disabled:opacity-60 dark:hover:bg-violet-950/40"
        >
          <Plus className="size-4" aria-hidden="true" />
          Добави още
        </button>
      )}
    </fieldset>
  );
}

const thumbnailClassName = "aspect-square w-full rounded-lg border object-cover";

function RemoveButton({ label, disabled, onClick }: { label: string; disabled: boolean; onClick: () => void }) {
  return (
    <button
      type="button"
      onClick={onClick}
      disabled={disabled}
      aria-label={`Изтрий ${label}`}
      className="absolute right-1 top-1 flex size-5 items-center justify-center rounded-full bg-black/70 text-white hover:bg-red-600 disabled:opacity-60"
    >
      <X className="size-3" aria-hidden="true" />
    </button>
  );
}

// Preview of a file that is not uploaded yet. The object URL lives as long as the effect.
function FilePreview({ file }: { file: File }) {
  const image = useRef<HTMLImageElement>(null);

  useEffect(() => {
    const url = URL.createObjectURL(file);

    if (image.current) {
      image.current.src = url;
    }

    return () => URL.revokeObjectURL(url);
  }, [file]);

  // eslint-disable-next-line @next/next/no-img-element
  return <img ref={image} alt={file.name} className={thumbnailClassName} />;
}
