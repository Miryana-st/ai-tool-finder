import type { Metadata } from "next";
import Header from "@/components/Header";
import "./globals.css";

export const metadata: Metadata = {
  title: "AI-Finder",
  description: "Открий и управлявай AI инструменти.",
};

export default function RootLayout({ children }: LayoutProps<"/">) {
  return (
    <html lang="bg" className="h-full antialiased">
      <body className="flex min-h-full flex-col">
        <Header />
        <main className="mx-auto w-full max-w-5xl flex-1 px-4 py-10">
          {children}
        </main>
      </body>
    </html>
  );
}
