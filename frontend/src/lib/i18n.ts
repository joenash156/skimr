import i18n, { type BackendModule, type ReadCallback, type ResourceKey } from 'i18next';
import { initReactI18next } from 'react-i18next';
import { setRequestLanguage } from './api/client';

/** Spec 13: English is the source language; French and Spanish are translated. */
export const SUPPORTED_LANGUAGES = ['en', 'fr', 'es'] as const;
export type Language = (typeof SUPPORTED_LANGUAGES)[number];
export const DEFAULT_LANGUAGE: Language = 'en';

export function isSupportedLanguage(value: unknown): value is Language {
  return typeof value === 'string' && (SUPPORTED_LANGUAGES as readonly string[]).includes(value);
}

/**
 * One namespace per feature, in src/locales/{lang}/{namespace}.json, loaded
 * only when a screen needs it (spec 13). Vite turns each file into its own chunk.
 */
const loaders = import.meta.glob<{ default: ResourceKey }>('../locales/*/*.json');

export const lazyLocaleBackend: BackendModule = {
  type: 'backend',
  init() {},
  read(language: string, namespace: string, callback: ReadCallback) {
    const load = loaders[`../locales/${language}/${namespace}.json`];
    if (!load) {
      callback(new Error(`No translations for ${language}/${namespace}`), false);
      return;
    }
    load().then(
      (module) => callback(null, module.default),
      (error: unknown) =>
        callback(error instanceof Error ? error : new Error(String(error)), false),
    );
  },
};

/**
 * Language before we know the user (brief B0 task 7): the browser's languages,
 * then English. Once GET /me answers, AuthProvider switches to `user.locale`.
 */
export function detectBrowserLanguage(
  languages: readonly string[] = typeof navigator === 'undefined' ? [] : navigator.languages,
): Language {
  for (const tag of languages) {
    const base = tag.toLowerCase().split('-')[0];
    if (isSupportedLanguage(base)) return base;
  }
  return DEFAULT_LANGUAGE;
}

function onLanguageChanged(language: string): void {
  setRequestLanguage(language);
  if (typeof document !== 'undefined') document.documentElement.lang = language;
}

i18n.on('languageChanged', onLanguageChanged);

export const i18nReady = i18n
  .use(lazyLocaleBackend)
  .use(initReactI18next)
  .init({
    lng: detectBrowserLanguage(),
    fallbackLng: DEFAULT_LANGUAGE,
    supportedLngs: SUPPORTED_LANGUAGES,
    ns: ['common'],
    defaultNS: 'common',
    // React already escapes output; dangerouslySetInnerHTML is banned anyway.
    interpolation: { escapeValue: false },
    returnNull: false,
    react: { useSuspense: true },
  });

export default i18n;
