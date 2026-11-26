import * as React from "react";
import { ChevronRight } from "lucide-react";
import { cn } from "@/lib/utils";

// Breadcrumb building blocks in the style of shadcn/ui.

function Breadcrumb(props: React.ComponentProps<"nav">) {
  return <nav aria-label="Път до страницата" data-slot="breadcrumb" {...props} />;
}

function BreadcrumbList({ className, ...props }: React.ComponentProps<"ol">) {
  return (
    <ol
      data-slot="breadcrumb-list"
      className={cn("flex flex-wrap items-center gap-1.5 text-sm break-words text-muted-foreground sm:gap-2", className)}
      {...props}
    />
  );
}

function BreadcrumbItem({ className, ...props }: React.ComponentProps<"li">) {
  return <li data-slot="breadcrumb-item" className={cn("inline-flex items-center gap-1.5", className)} {...props} />;
}

function BreadcrumbPage({ className, ...props }: React.ComponentProps<"span">) {
  return (
    <span
      data-slot="breadcrumb-page"
      aria-current="page"
      className={cn("font-medium text-foreground", className)}
      {...props}
    />
  );
}

function BreadcrumbSeparator({ className, ...props }: React.ComponentProps<"li">) {
  return (
    <li
      data-slot="breadcrumb-separator"
      role="presentation"
      aria-hidden="true"
      className={cn("[&>svg]:size-3.5", className)}
      {...props}
    >
      <ChevronRight />
    </li>
  );
}

// Use with `className={breadcrumbLinkClassName}` on a Next.js Link.
const breadcrumbLinkClassName = "transition-colors hover:text-foreground";

export { Breadcrumb, BreadcrumbItem, BreadcrumbList, BreadcrumbPage, BreadcrumbSeparator, breadcrumbLinkClassName };
