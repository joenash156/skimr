import { describe, expect, it } from 'vitest';
import { countryOptions, fromE164, toE164 } from '@/lib/phone';
import { safeNext } from './safeNext';

describe('safeNext', () => {
  it('keeps same-app paths', () => {
    expect(safeNext('/projects/abc/sources?x=1')).toBe('/projects/abc/sources?x=1');
  });

  it('rejects other sites and auth pages', () => {
    for (const bad of [
      'https://evil.example',
      '//evil.example',
      '/\\evil.example',
      'projects',
      '/login',
      '/login?next=/x',
      '/register',
    ]) {
      expect(safeNext(bad)).toBe('/');
    }
    expect(safeNext(null)).toBe('/');
  });
});

describe('phone (spec 5.2: E.164, default Ghana +233)', () => {
  it('builds E.164 and drops the trunk 0', () => {
    expect(toE164({ country: 'GH', national: '024 123 4567' })).toBe('+233241234567');
    expect(toE164({ country: 'FR', national: '06 12 34 56 78' })).toBe('+33612345678');
    expect(toE164({ country: 'GH', national: '' })).toBe('');
  });

  it('splits a stored number back into country and number', () => {
    expect(fromE164('+233241234567')).toEqual({ country: 'GH', national: '241234567' });
    expect(fromE164('+33612345678')).toEqual({ country: 'FR', national: '612345678' });
    expect(fromE164(null)).toEqual({ country: 'GH', national: '' });
  });

  it('names countries in the UI language', () => {
    const ghana = (lang: string) => countryOptions(lang).find((o) => o.value === 'GH')?.label;
    expect(ghana('en')).toBe('Ghana (+233)');
    expect(countryOptions('fr').find((o) => o.value === 'DE')?.label).toBe('Allemagne (+49)');
  });
});
