/**
 * Theme (spec 14.4): light, dark or system, stored in localStorage under `theme`
 * and, for signed-in users, in users.theme (PATCH /me, see useThemePreference).
 * index.html applies it before paint; this module keeps it in sync afterwards,
 * using exactly the same rule as that inline script.
 */
import { useSyncExternalStore } from 'react';

export const THEMES = ['light', 'dark', 'system'] as const;
export type Theme = (typeof THEMES)[number];

const STORAGE_KEY = 'theme';
const DARK_QUERY = '(prefers-color-scheme: dark)';

type Listener = () => void;
const listeners = new Set<Listener>();

export function isTheme(value: unknown): value is Theme {
  return typeof value === 'string' && (THEMES as readonly string[]).includes(value);
}

export function getStoredTheme(): Theme {
  try {
    const value = localStorage.getItem(STORAGE_KEY);
    return isTheme(value) ? value : 'system';
  } catch {
    return 'system';
  }
}

function systemPrefersDark(): boolean {
  return typeof matchMedia === 'function' && matchMedia(DARK_QUERY).matches;
}

export function resolveTheme(theme: Theme): 'light' | 'dark' {
  if (theme === 'system') return systemPrefersDark() ? 'dark' : 'light';
  return theme;
}

export function applyTheme(theme: Theme): void {
  document.documentElement.classList.toggle('dark', resolveTheme(theme) === 'dark');
}

export function setTheme(theme: Theme): void {
  try {
    localStorage.setItem(STORAGE_KEY, theme);
  } catch {
    // Storage blocked (private mode): the choice still applies to this page.
  }
  applyTheme(theme);
  for (const listener of listeners) listener();
}

function subscribe(listener: Listener): () => void {
  listeners.add(listener);
  return () => {
    listeners.delete(listener);
  };
}

/** The current choice; every switcher on the page re-renders when it changes. */
export function useStoredTheme(): Theme {
  return useSyncExternalStore(subscribe, getStoredTheme, getStoredTheme);
}

/** "System" follows the OS setting live (spec 14.4). Returns an unsubscribe function. */
export function watchSystemTheme(): () => void {
  if (typeof matchMedia !== 'function') return () => {};
  const query = matchMedia(DARK_QUERY);
  const onChange = () => {
    if (getStoredTheme() === 'system') applyTheme('system');
  };
  query.addEventListener('change', onChange);
  return () => query.removeEventListener('change', onChange);
}
