// The slug format the backend accepts (see SlugFormat.php): lowercase Latin
// letters and digits, in words joined by single hyphens.
export const SLUG_PATTERN = /^[a-z0-9]+(?:-[a-z0-9]+)*$/;

// A slug suggestion from a name. Only Latin letters and digits are kept, so a
// Cyrillic name gives an empty slug that the user fills in by hand.
// "Next.js" → "nextjs", "VS Code" → "vs-code", "Café" → "cafe".
export function slugify(name: string): string {
  return name
    .normalize("NFKD")
    .replace(/[̀-ͯ]/g, "")
    .toLowerCase()
    .replace(/[.'’]/g, "")
    .replace(/[^a-z0-9]+/g, "-")
    .replace(/^-+|-+$/g, "");
}
