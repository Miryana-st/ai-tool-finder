"use client";

import { Heart } from "lucide-react";
import { useState } from "react";
import { buttonVariants } from "@/components/ui/button";
import * as api from "@/lib/api";
import type { FavoriteInfo } from "@/lib/api";
import { cn } from "@/lib/utils";

// Adds the tool to the signed-in user's favorites or removes it from them.
export default function FavoriteButton({
  toolId,
  favorite,
  onChange,
}: {
  toolId: number;
  favorite: FavoriteInfo;
  onChange: (favorite: FavoriteInfo) => void;
}) {
  const [isSaving, setIsSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const handleClick = async () => {
    setIsSaving(true);
    setError(null);

    try {
      onChange(await api.setToolFavorite(toolId, !favorite.is_favorite));
    } catch (caught) {
      console.error("Could not change the favorite tool.", caught);
      setError("Любимите не можаха да се променят. Опитай отново.");
    } finally {
      setIsSaving(false);
    }
  };

  return (
    <div className="flex flex-col gap-1">
      <button
        type="button"
        onClick={() => void handleClick()}
        disabled={isSaving}
        aria-pressed={favorite.is_favorite}
        className={cn(
          buttonVariants({ variant: "outline" }),
          "h-11 rounded-xl px-5 text-violet-700 dark:text-violet-300",
          favorite.is_favorite && "border-violet-300 bg-violet-50 dark:bg-violet-500/10",
        )}
      >
        <Heart className={cn("size-5", favorite.is_favorite && "fill-rose-500 text-rose-500")} />
        {favorite.is_favorite ? "В любими" : "Добави в любими"}
      </button>
      {error && (
        <p role="alert" className="text-sm text-red-600">
          {error}
        </p>
      )}
    </div>
  );
}
