import type { Metadata } from "next";
import { Geist } from "next/font/google";
import AppShell from "@/components/layout/AppShell";
import { TooltipProvider } from "@/components/ui/tooltip";
import { AuthProvider } from "@/context/AuthContext";
import { cn } from "@/lib/utils";
import "@/styles/globals.css";

const geist = Geist({ subsets: ["latin", "cyrillic"], variable: "--font-sans" });

export const metadata: Metadata = {
  title: "AI-Tool-Finder",
  description: "Открий и управлявай AI инструменти.",
};

export default function RootLayout({ children }: LayoutProps<"/">) {
  return (
    <html lang="bg" className={cn("h-full font-sans antialiased", geist.variable)}>
      <body className="flex min-h-full flex-col">
        <AuthProvider>
          <TooltipProvider>
            <AppShell>{children}</AppShell>
          </TooltipProvider>
        </AuthProvider>
      </body>
    </html>
  );
}
