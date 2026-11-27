import AuthButton from "./AuthButton";
import Clock from "./Clock";
import Logo from "./Logo";

// The simple header for guests and the session check (see AppShell).
export default function Header() {
  return (
    <header className="border-b border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-950">
      <div className="mx-auto flex max-w-5xl items-center justify-between gap-4 px-4 py-3">
        <Logo />

        <div className="flex items-center gap-4">
          <Clock />
          <AuthButton />
        </div>
      </div>
    </header>
  );
}
