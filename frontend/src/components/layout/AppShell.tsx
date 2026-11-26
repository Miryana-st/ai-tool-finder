"use client";

import type { ReactNode } from "react";
import Header from "@/components/Header";
import { SidebarInset, SidebarProvider } from "@/components/ui/sidebar";
import { useAuth } from "@/context/AuthContext";
import { BreadcrumbProvider } from "@/context/BreadcrumbContext";
import { DashboardProvider } from "@/context/DashboardContext";
import AppBreadcrumbs from "./AppBreadcrumbs";
import AppSidebar from "./AppSidebar";
import AppTopbar from "./AppTopbar";

// Logged-in users get the sidebar layout; guests (and the session check) the simple header.
export default function AppShell({ children }: { children: ReactNode }) {
  const { status } = useAuth();

  if (status !== "authenticated") {
    return (
      <>
        <Header />
        <main className="mx-auto w-full max-w-5xl flex-1 px-4 py-10">{children}</main>
      </>
    );
  }

  return (
    <DashboardProvider>
      <BreadcrumbProvider>
        <SidebarProvider>
          <AppSidebar />
          <SidebarInset>
            <AppTopbar />
            <div className="w-full flex-1 px-4 py-8 md:px-8">
              <AppBreadcrumbs />
              {children}
            </div>
          </SidebarInset>
        </SidebarProvider>
      </BreadcrumbProvider>
    </DashboardProvider>
  );
}
