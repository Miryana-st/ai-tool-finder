"use client";

import { useCallback, useEffect, useState } from "react";
import * as api from "@/lib/api";
import type { PaginationMeta, Tool, ToolFilters } from "@/lib/api";

export type ProfileToolsKind = "mine" | "favorites";

const FILTERS: Record<ProfileToolsKind, ToolFilters> = {
  mine: { mine: 1 },
  favorites: { favorites: 1 },
};

export type ProfileTools = {
  tools: Tool[];
  // Null until the first page has loaded.
  total: number | null;
  status: "loading" | "ready" | "error";
  hasMore: boolean;
  isLoadingMore: boolean;
  loadMoreError: boolean;
  loadMore: () => Promise<void>;
  // Drops a tool from the list, e.g. after it was deleted or removed from favorites.
  remove: (toolId: number) => void;
};

// The signed-in user's added or favorite tools, page by page.
export function useProfileTools(kind: ProfileToolsKind): ProfileTools {
  const [tools, setTools] = useState<Tool[]>([]);
  const [meta, setMeta] = useState<PaginationMeta | null>(null);
  const [status, setStatus] = useState<ProfileTools["status"]>("loading");
  const [isLoadingMore, setIsLoadingMore] = useState(false);
  const [loadMoreError, setLoadMoreError] = useState(false);

  useEffect(() => {
    const controller = new AbortController();

    api
      .getTools(FILTERS[kind], controller.signal)
      .then((page) => {
        setTools(page.data);
        setMeta(page.meta);
        setStatus("ready");
      })
      .catch((error: unknown) => {
        if (api.isAbortError(error)) {
          return;
        }
        console.error("Could not load the profile tools.", error);
        setStatus("error");
      });

    return () => controller.abort();
  }, [kind]);

  const loadMore = useCallback(async () => {
    if (!meta) {
      return;
    }

    setIsLoadingMore(true);
    setLoadMoreError(false);

    // Removed tools shift the pages, so the next page is worked out from what is
    // shown, and tools that are already shown are skipped.
    const page = Math.floor(tools.length / meta.per_page) + 1;

    try {
      const next = await api.getTools({ ...FILTERS[kind], page });
      setTools((current) => [
        ...current,
        ...next.data.filter((tool) => !current.some((shown) => shown.id === tool.id)),
      ]);
      setMeta(next.meta);
    } catch (error) {
      console.error("Could not load more profile tools.", error);
      setLoadMoreError(true);
    } finally {
      setIsLoadingMore(false);
    }
  }, [kind, meta, tools.length]);

  const remove = useCallback(
    (toolId: number) => {
      if (!tools.some((tool) => tool.id === toolId)) {
        return;
      }

      setTools((current) => current.filter((tool) => tool.id !== toolId));
      setMeta((current) => current && { ...current, total: Math.max(current.total - 1, 0) });
    },
    [tools],
  );

  return {
    tools,
    total: meta?.total ?? null,
    status,
    hasMore: meta !== null && tools.length < meta.total,
    isLoadingMore,
    loadMoreError,
    loadMore,
    remove,
  };
}
