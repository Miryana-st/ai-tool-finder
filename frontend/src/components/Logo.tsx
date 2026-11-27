import Link from "next/link";

// Shown only in the guest header, so it always links to the home page.
export default function Logo() {
  return (
    <Link href="/" className="flex items-center gap-2 font-semibold">
      <svg
        viewBox="0 0 32 32"
        className="h-8 w-8"
        aria-hidden="true"
        focusable="false"
      >
        <rect width="32" height="32" rx="8" className="fill-indigo-600" />
        <circle cx="14" cy="14" r="6" fill="none" stroke="white" strokeWidth="2.5" />
        <path d="M18.5 18.5 24 24" stroke="white" strokeWidth="2.5" strokeLinecap="round" />
      </svg>
      <span className="text-lg">AI-Tool-Finder</span>
    </Link>
  );
}
