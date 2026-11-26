/** Joins class names, skipping false, null and undefined (no extra library needed). */
export function cn(...classes: Array<string | false | null | undefined>): string {
  return classes.filter(Boolean).join(' ');
}
