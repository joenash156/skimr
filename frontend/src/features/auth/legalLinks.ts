import type { Language } from '@/lib/i18n';

/** Public legal pages and their localised slugs (backend Public/Seo/PublicPageMap.php, P5). */
const SLUGS = {
  terms: { en: 'terms', fr: 'conditions-utilisation', es: 'terminos-de-uso' },
  privacy: { en: 'privacy', fr: 'confidentialite', es: 'privacidad' },
} as const;

export function legalUrl(page: keyof typeof SLUGS, language: Language): string {
  return `/${language}/${SLUGS[page][language]}`;
}
