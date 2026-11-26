import { beforeEach, describe, expect, it, vi } from 'vitest';
import { tokenStore } from './tokenStore';

beforeEach(() => {
  tokenStore.clear();
  localStorage.clear();
  sessionStorage.clear();
});

describe('tokenStore', () => {
  it('keeps the token in memory and notifies subscribers', () => {
    const listener = vi.fn();
    const unsubscribe = tokenStore.subscribe(listener);

    tokenStore.set('abc');
    expect(tokenStore.get()).toBe('abc');
    tokenStore.set('abc'); // no change, no notification
    tokenStore.clear();
    expect(tokenStore.get()).toBeNull();

    expect(listener).toHaveBeenCalledTimes(2);
    unsubscribe();
    tokenStore.set('def');
    expect(listener).toHaveBeenCalledTimes(2);
  });

  it('never writes the token to browser storage (spec 11.1)', () => {
    tokenStore.set('secret-token');
    expect(localStorage.length).toBe(0);
    expect(sessionStorage.length).toBe(0);
    expect(document.cookie).not.toContain('secret-token');
  });
});
