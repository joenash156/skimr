import { describe, expect, it } from 'vitest';
import i18n, { detectBrowserLanguage, i18nReady, isSupportedLanguage } from './i18n';

describe('detectBrowserLanguage', () => {
  it('picks the first supported browser language, by base tag', () => {
    expect(detectBrowserLanguage(['de-DE', 'fr-CA', 'en'])).toBe('fr');
    expect(detectBrowserLanguage(['es-419'])).toBe('es');
  });

  it('falls back to English', () => {
    expect(detectBrowserLanguage(['de', 'pt-BR'])).toBe('en');
    expect(detectBrowserLanguage([])).toBe('en');
  });
});

describe('isSupportedLanguage', () => {
  it('accepts only en, fr and es', () => {
    expect(['en', 'fr', 'es'].every(isSupportedLanguage)).toBe(true);
    expect(isSupportedLanguage('de')).toBe(false);
    expect(isSupportedLanguage(undefined)).toBe(false);
  });
});

describe('i18n', () => {
  it('lazily loads the common namespace and switches language', async () => {
    await i18nReady;
    await i18n.changeLanguage('fr');
    expect(i18n.t('theme.dark')).toBe('Sombre');
    expect(document.documentElement.lang).toBe('fr');
    await i18n.changeLanguage('es');
    expect(i18n.t('theme.dark')).toBe('Oscuro');
    await i18n.changeLanguage('en');
    expect(i18n.t('theme.dark')).toBe('Dark');
  });
});
