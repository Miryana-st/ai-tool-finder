"use client";

import { createContext, useCallback, useContext, useEffect, useMemo, useState, type ReactNode } from "react";

// Labels set by pages for path segments that only they can name, e.g. a tool's
// name for /tools/{slug}. Keyed by the href of the segment.
type BreadcrumbLabels = Record<string, string>;

type BreadcrumbContextValue = {
  labels: BreadcrumbLabels;
  setLabel: (href: string, label: string | null) => void;
};

const BreadcrumbContext = createContext<BreadcrumbContextValue | null>(null);

export function BreadcrumbProvider({ children }: { children: ReactNode }) {
  const [labels, setLabels] = useState<BreadcrumbLabels>({});

  const setLabel = useCallback((href: string, label: string | null) => {
    setLabels((current) => {
      if (label === null) {
        const rest = { ...current };
        delete rest[href];
        return rest;
      }
      return current[href] === label ? current : { ...current, [href]: label };
    });
  }, []);

  const value = useMemo(() => ({ labels, setLabel }), [labels, setLabel]);

  return <BreadcrumbContext.Provider value={value}>{children}</BreadcrumbContext.Provider>;
}

export function useBreadcrumbLabels(): BreadcrumbLabels {
  return useContext(BreadcrumbContext)?.labels ?? {};
}

// Name the breadcrumb of `href` while the calling page is shown. A null label
// (e.g. while the data is loading) leaves the default label from the URL.
export function useBreadcrumbLabel(href: string, label: string | null) {
  const setLabel = useContext(BreadcrumbContext)?.setLabel;

  useEffect(() => {
    if (label === null) {
      return;
    }
    setLabel?.(href, label);
    return () => setLabel?.(href, null);
  }, [setLabel, href, label]);
}
