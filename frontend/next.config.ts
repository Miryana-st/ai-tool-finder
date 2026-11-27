import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  /* config options here */
  cacheComponents: true,
  partialPrefetching: true,
  // Stops `next dev` from (re)creating frontend/AGENTS.md; agent rules live in the root CLAUDE.md.
  agentRules: false,
  // Turbopack misses file changes on the Windows bind mount in Docker, so it polls there.
  // WATCHPACK_POLLING is set only in docker-compose; elsewhere native watching is used.
  ...(process.env.WATCHPACK_POLLING === "true" && {
    watchOptions: { pollIntervalMs: 1000 },
  }),
  turbopack: {
    rules: {
      "*.css": {
        loaders: ["@tailwindcss/turbopack"],
        as: "*.css",
      },
    },
  },
};

export default nextConfig;
