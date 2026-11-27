import { SidebarBody } from './SidebarBody';

/**
 * Desktop (1024 px and up): 256 px with labels, or a 64 px icon rail with
 * tooltips (spec 14.3). The width follows <html data-sidebar>.
 */
export function DesktopSidebar() {
  return (
    <aside
      id="app-sidebar"
      className="sticky top-0 hidden h-dvh w-64 shrink-0 overflow-hidden border-e border-border bg-surface transition-[width] duration-300 ease-out lg:block in-data-[sidebar=collapsed]:w-16"
    >
      <SidebarBody rail />
    </aside>
  );
}
