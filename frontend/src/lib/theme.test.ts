import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { applyTheme, getStoredTheme, resolveTheme, setTheme } from './theme';

function mockSystemDark(dark: boolean) {
  vi.stubGlobal(
    'matchMedia',
    vi.fn((query: string) => ({
      matches: dark && query === '(prefers-color-scheme: dark)',
      media: query,
      addEventListener: vi.fn(),
      removeEventListener: vi.fn(),
    })),
  );
}

beforeEach(() => {
  localStorage.clear();
  document.documentElement.classList.remove('dark');
});

afterEach(() => {
  vi.unstubAllGlobals();
});

describe('theme', () => {
  it('defaults to system and ignores junk in storage', () => {
    expect(getStoredTheme()).toBe('system');
    localStorage.setItem('theme', 'purple');
    expect(getStoredTheme()).toBe('system');
  });

  it('stores the choice under localStorage.theme and sets the dark class', () => {
    mockSystemDark(false);
    setTheme('dark');
    expect(localStorage.getItem('theme')).toBe('dark');
    expect(document.documentElement.classList.contains('dark')).toBe(true);
    setTheme('light');
    expect(document.documentElement.classList.contains('dark')).toBe(false);
  });

  it('"system" follows prefers-color-scheme', () => {
    mockSystemDark(true);
    expect(resolveTheme('system')).toBe('dark');
    applyTheme('system');
    expect(document.documentElement.classList.contains('dark')).toBe(true);
    mockSystemDark(false);
    expect(resolveTheme('system')).toBe('light');
  });
});
