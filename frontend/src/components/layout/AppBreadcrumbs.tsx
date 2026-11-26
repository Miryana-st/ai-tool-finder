"use client";

import { House } from "lucide-react";
import Link from "next/link";
import { usePathname } from "next/navigation";
import { Fragment } from "react";
import {
  Breadcrumb,
  BreadcrumbItem,
  BreadcrumbList,
  BreadcrumbPage,
  BreadcrumbSeparator,
  breadcrumbLinkClassName,
} from "@/components/ui/breadcrumb";
import { useBreadcrumbLabels } from "@/context/BreadcrumbContext";
import { useDashboard } from "@/context/DashboardContext";
import { HOME_HREF, MAIN_NAV } from "./navigation";

// Path segments that are not pages of their own: shown, but not as links.
const GROUP_LABELS: Record<string, string> = {
  "/admin": "Админ",
};

// Pages that are not in a menu.
const PAGE_LABELS: Record<string, string> = {
  "/admin/users/new": "Добави потребител",
};

// The last segment of these paths, whatever comes before it.
const SEGMENT_LABELS: Record<string, string> = {
  edit: "Редакция",
};

type Crumb = { href: string; label: string; isLink: boolean };

// The path from the dashboard to the current page, built from the URL. Pages name
// segments the URL cannot (a tool's name for its slug) with useBreadcrumbLabel.
export default function AppBreadcrumbs() {
  const pathname = usePathname();
  const pageLabels = useBreadcrumbLabels();
  const dashboard = useDashboard();

  if (pathname === HOME_HREF) {
    return null;
  }

  const knownLabels: Record<string, string> = {};
  if (dashboard.status === "ready") {
    for (const item of dashboard.dashboard.menu) {
      if (item.href) {
        knownLabels[item.href] = item.label;
      }
    }
  }
  for (const item of MAIN_NAV) {
    if (item.href) {
      knownLabels[item.href] = item.label;
    }
  }

  const segments = pathname.split("/").filter(Boolean);
  const crumbs: Crumb[] = segments.map((segment, index) => {
    const href = `/${segments.slice(0, index + 1).join("/")}`;
    const label =
      pageLabels[href] ??
      knownLabels[href] ??
      PAGE_LABELS[href] ??
      GROUP_LABELS[href] ??
      SEGMENT_LABELS[segment] ??
      decodeURIComponent(segment);

    return { href, label, isLink: !(href in GROUP_LABELS) };
  });

  return (
    <Breadcrumb className="mb-6">
      <BreadcrumbList>
        <BreadcrumbItem>
          <Link href={HOME_HREF} aria-label="Табло" className={breadcrumbLinkClassName}>
            <House className="size-4" />
          </Link>
        </BreadcrumbItem>
        {crumbs.map((crumb, index) => (
          <Fragment key={crumb.href}>
            <BreadcrumbSeparator />
            <BreadcrumbItem>
              {index === crumbs.length - 1 ? (
                <BreadcrumbPage>{crumb.label}</BreadcrumbPage>
              ) : crumb.isLink ? (
                <Link href={crumb.href} className={breadcrumbLinkClassName}>
                  {crumb.label}
                </Link>
              ) : (
                <span>{crumb.label}</span>
              )}
            </BreadcrumbItem>
          </Fragment>
        ))}
      </BreadcrumbList>
    </Breadcrumb>
  );
}
