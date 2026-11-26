"use client";

import { useState } from "react";
import Pagination from "@/components/Pagination";

// Items per page in the admin lists.
export const ADMIN_PAGE_SIZE = 10;

// The shared page buttons with "43 потребителя" under them.
export default function AdminPagination({
  page,
  lastPage,
  total,
  itemsLabel,
  onChange,
}: {
  page: number;
  lastPage: number;
  total: number;
  // The counted items, e.g. "потребителя".
  itemsLabel: string;
  onChange: (page: number) => void;
}) {
  return <Pagination page={page} lastPage={lastPage} summary={`${total} ${itemsLabel}`} onChange={onChange} />;
}

// Pages through a list that is loaded whole (company roles, categories, tags).
// When items are removed, the page moves back so it never points past the end.
export function usePagedItems<T>(items: T[]) {
  const [requestedPage, setPage] = useState(1);
  const lastPage = Math.max(1, Math.ceil(items.length / ADMIN_PAGE_SIZE));
  const page = Math.min(requestedPage, lastPage);
  const start = (page - 1) * ADMIN_PAGE_SIZE;

  return {
    pageItems: items.slice(start, start + ADMIN_PAGE_SIZE),
    page,
    lastPage,
    setPage,
  };
}
