import { useId } from "react";

// Magnifying glass with an AI spark: the AI Tool Finder mark.
export default function LogoMark({ className }: { className?: string }) {
  const gradientId = useId();

  return (
    <svg viewBox="0 0 48 48" className={className} aria-hidden="true" focusable="false">
      <defs>
        <linearGradient id={gradientId} x1="0" y1="0" x2="1" y2="1">
          <stop offset="0%" stopColor="#c4b5fd" />
          <stop offset="100%" stopColor="#7c3aed" />
        </linearGradient>
      </defs>
      <circle cx="21" cy="21" r="14" fill="none" stroke={`url(#${gradientId})`} strokeWidth="4.5" />
      <path d="M31.5 31.5 42 42" stroke={`url(#${gradientId})`} strokeWidth="5" strokeLinecap="round" />
      <path
        d="M21 12.5c.9 4.2 3.3 6.6 7.5 7.5-4.2.9-6.6 3.3-7.5 7.5-.9-4.2-3.3-6.6-7.5-7.5 4.2-.9 6.6-3.3 7.5-7.5Z"
        fill="#ede9fe"
      />
    </svg>
  );
}
