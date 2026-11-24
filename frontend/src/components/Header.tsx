import Link from "next/link";
import Clock from "./Clock";
import Logo from "./Logo";
import UserId from "./UserId";

export default function Header() {
  return (
    <header className="border-b border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-950">
      <div className="mx-auto flex max-w-5xl items-center justify-between gap-4 px-4 py-3">
        <Logo />

        <div className="flex items-center gap-4">
          <Clock />
          <UserId />
          <Link
            href="/login"
            className="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-500"
          >
            Вход
          </Link>
        </div>
      </div>
    </header>
  );
}
