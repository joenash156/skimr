/**
 * Sidebar collapsed/expanded (spec 14.3): saved in localStorage under
 * `sidebar` and mirrored as <html data-sidebar="...">, which index.html sets
 * before paint. CSS reads it with Tailwind's `in-data-[sidebar=collapsed]:`.
 */
import { useCallback, useSyncExternalStore } from 'react';

const STORAGE_KEY = 'sidebar';
type Listener = () => void;
const listeners = new Set<Listener>();

export function isSidebarCollapsed(): boolean {
  return document.documentElement.dataset.sidebar === 'collapsed';
}

export function setSidebarCollapsed(collapsed: boolean): void {
  const value = collapsed ? 'collapsed' : 'expanded';
  document.documentElement.dataset.sidebar = value;
  try {
    localStorage.setItem(STORAGE_KEY, value);
  } catch {
    // Storage blocked: the choice lasts for this page only.
  }
  for (const listener of listeners) listener();
}

function subscribe(listener: Listener): () => void {
  listeners.add(listener);
  return () => {
    listeners.delete(listener);
  };
}

export function useSidebarCollapsed(): [collapsed: boolean, toggle: () => void] {
  const collapsed = useSyncExternalStore(subscribe, isSidebarCollapsed, () => false);
  const toggle = useCallback(() => setSidebarCollapsed(!isSidebarCollapsed()), []);
  return [collapsed, toggle];
}
