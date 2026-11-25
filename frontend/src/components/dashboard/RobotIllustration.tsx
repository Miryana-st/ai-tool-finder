// Decorative robot at a laptop for the dashboard greeting.
export default function RobotIllustration({ className }: { className?: string }) {
  return (
    <svg viewBox="0 0 320 240" className={className} aria-hidden="true" focusable="false">
      {/* Background blob and sparkles */}
      <path
        d="M40 210c-20-50 10-110 70-130s120-30 160 10 40 100 10 125-220 45-240-5Z"
        fill="#ddd6fe"
        opacity="0.7"
      />
      <g fill="#8b5cf6">
        <path d="M36 60l3 9 9 3-9 3-3 9-3-9-9-3 9-3Z" />
        <path d="M60 100l2 6 6 2-6 2-2 6-2-6-6-2 6-2Z" opacity="0.7" />
        <path d="M282 40l3 9 9 3-9 3-3 9-3-9-9-3 9-3Z" />
        <path d="M300 150l2 6 6 2-6 2-2 6-2-6-6-2 6-2Z" opacity="0.7" />
      </g>

      {/* Antenna */}
      <line x1="190" y1="22" x2="190" y2="44" stroke="#6366f1" strokeWidth="4" />
      <circle cx="190" cy="18" r="7" fill="#6366f1" />

      {/* Head */}
      <circle cx="132" cy="92" r="12" fill="#6366f1" />
      <circle cx="248" cy="92" r="12" fill="#6366f1" />
      <rect x="134" y="42" width="112" height="96" rx="44" fill="#ffffff" stroke="#e0e7ff" strokeWidth="3" />
      <rect x="148" y="58" width="84" height="62" rx="28" fill="#1e1b4b" />
      <path d="M166 92q8-12 16 0" stroke="#818cf8" strokeWidth="5" fill="none" strokeLinecap="round" />
      <path d="M198 92q8-12 16 0" stroke="#818cf8" strokeWidth="5" fill="none" strokeLinecap="round" />
      <path d="M182 104q8 6 16 0" stroke="#818cf8" strokeWidth="4" fill="none" strokeLinecap="round" />

      {/* Body */}
      <rect x="156" y="140" width="68" height="70" rx="26" fill="#ffffff" stroke="#e0e7ff" strokeWidth="3" />
      <circle cx="190" cy="168" r="8" fill="#c7d2fe" />

      {/* Laptop */}
      <path d="M96 150h92l-14 66H82Z" fill="#4f46e5" />
      <path d="M96 150h92l-14 66H82Z" fill="#312e81" opacity="0.25" />
      <ellipse cx="134" cy="183" rx="7" ry="8" fill="#e0e7ff" />
      <rect x="70" y="214" width="150" height="10" rx="5" fill="#3730a3" />

      {/* Arms */}
      <path d="M160 170c-14 8-22 22-22 38" stroke="#ffffff" strokeWidth="16" fill="none" strokeLinecap="round" />
      <path d="M222 170c14 10 18 24 12 40" stroke="#ffffff" strokeWidth="16" fill="none" strokeLinecap="round" />
      <path d="M222 170c14 10 18 24 12 40" stroke="#6366f1" strokeWidth="4" fill="none" strokeLinecap="round" opacity="0.25" />
    </svg>
  );
}
