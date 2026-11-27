import type { Metadata } from "next";
import LoginForm from "@/components/LoginForm";

export const metadata: Metadata = {
  title: "Вход | AI-Tool-Finder",
};

export default function LoginPage() {
  return (
    <section className="mx-auto flex w-full max-w-sm flex-col gap-6">
      <h1 className="text-3xl font-semibold tracking-tight">Вход</h1>
      <LoginForm />
    </section>
  );
}
