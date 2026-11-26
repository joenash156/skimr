/**
 * The Cloudflare Turnstile site key (public, not a secret).
 *
 * 1. At runtime from <meta name="turnstile-site-key">, which PHP can fill in
 *    from TURNSTILE_SITE_KEY the same way it fills the CSP nonce.
 * 2. Else from VITE_TURNSTILE_SITE_KEY at build time.
 * 3. Else Cloudflare's always-passing TEST key, the one in .env.example, so
 *    local development works with the local test secret.
 */
export const TURNSTILE_TEST_SITE_KEY = '1x00000000000000000000AA';
const PLACEHOLDER = '__TURNSTILE_SITE_KEY__';

export function getTurnstileSiteKey(): string {
  if (typeof document !== 'undefined') {
    const meta = document.querySelector<HTMLMetaElement>('meta[name="turnstile-site-key"]');
    const value = meta?.content.trim();
    if (value && value !== PLACEHOLDER) return value;
  }
  const built: unknown = import.meta.env.VITE_TURNSTILE_SITE_KEY;
  if (typeof built === 'string' && built.trim() !== '') return built.trim();
  return TURNSTILE_TEST_SITE_KEY;
}

/** The actions the API checks on each Turnstile token (brief 3a). */
export type TurnstileAction = 'register' | 'login' | 'forgot_password';
