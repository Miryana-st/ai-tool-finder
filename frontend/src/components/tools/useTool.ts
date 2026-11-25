import { useEffect, useState } from "react";
import * as api from "@/lib/api";
import { ApiError, type Tool } from "@/lib/api";

export type ToolState =
  | { status: "loading" }
  | { status: "not-found" }
  | { status: "error" }
  | { status: "ready"; tool: Tool };

// Loads a tool by its slug for the details and edit pages.
export function useTool(slug: string): ToolState {
  const [state, setState] = useState<{ slug: string; value: ToolState } | null>(null);

  useEffect(() => {
    const controller = new AbortController();

    api
      .getTool(slug, controller.signal)
      .then((tool) => setState({ slug, value: { status: "ready", tool } }))
      .catch((error: unknown) => {
        if (api.isAbortError(error)) {
          return;
        }
        if (error instanceof ApiError && error.status === 404) {
          setState({ slug, value: { status: "not-found" } });
          return;
        }
        console.error("Could not load the tool.", error);
        setState({ slug, value: { status: "error" } });
      });

    return () => controller.abort();
  }, [slug]);

  return state?.slug === slug ? state.value : { status: "loading" };
}
