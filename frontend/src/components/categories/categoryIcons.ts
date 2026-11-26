import {
  Bot,
  ChartColumn,
  CodeXml,
  FlaskConical,
  Folder,
  Palette,
  PenLine,
  Shapes,
  type LucideIcon,
} from "lucide-react";

// Icons for the base categories (see the backend's base category migrations).
const CATEGORY_ICONS: Record<string, LucideIcon> = {
  coding: CodeXml,
  design: Palette,
  data: ChartColumn,
  automation: Bot,
  testing: FlaskConical,
  content: PenLine,
  other: Shapes,
};

const ICON_COLORS = [
  "text-teal-500",
  "text-fuchsia-500",
  "text-blue-500",
  "text-amber-500",
  "text-indigo-500",
];

// A base category's own icon; any other category gets a folder.
export function categoryIcon(slug: string): LucideIcon {
  return CATEGORY_ICONS[slug] ?? Folder;
}

// Colors take turns by position in the list.
export function categoryIconColor(index: number): string {
  return ICON_COLORS[index % ICON_COLORS.length];
}
