"use client";

import { useEffect, useState } from "react";

const formatter = new Intl.DateTimeFormat("bg-BG", {
  hour: "2-digit",
  minute: "2-digit",
  second: "2-digit",
});

export default function Clock() {
  // Starts empty so the server and the first client render match (no hydration mismatch).
  const [now, setNow] = useState<Date | null>(null);

  useEffect(() => {
    const tick = () => setNow(new Date());
    tick();
    const interval = setInterval(tick, 1000);

    return () => clearInterval(interval);
  }, []);

  return (
    <time
      dateTime={now?.toISOString()}
      className="font-mono text-sm tabular-nums text-zinc-600 dark:text-zinc-400"
      suppressHydrationWarning
    >
      {now ? formatter.format(now) : "--:--:--"}
    </time>
  );
}
