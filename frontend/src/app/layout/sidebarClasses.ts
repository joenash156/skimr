/**
 * Shared sidebar classes (from Nana's reference sample, adapted to spec 14).
 *
 * FADE hides labels when the desktop rail is collapsed: <html data-sidebar>
 * is set before paint, so there's no flash. Labels stay in the accessibility
 * tree, so links keep their names; sighted users get tooltips instead.
 */
export const FADE =
  'transition-opacity in-data-[sidebar=collapsed]:pointer-events-none in-data-[sidebar=collapsed]:opacity-0';

/** One row: 40 px tall (44 px on touch), icon centred in the 64 px rail. */
export const ROW =
  'group relative flex h-10 w-full items-center gap-3 rounded-control px-3 text-sm font-medium whitespace-nowrap transition-colors pointer-coarse:h-11';
