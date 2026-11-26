/**
 * Where to go after signing in. Only same-app paths are allowed, so a crafted
 * ?next=https://evil.example (or //evil.example) can't send users elsewhere.
 */
const AUTH_PATHS = ['/login', '/register', '/forgot-password', '/reset-password', '/verify-email'];

export function safeNext(next: string | null | undefined, fallback = '/'): string {
  if (!next || !next.startsWith('/') || next.startsWith('//') || next.includes('\\')) {
    return fallback;
  }
  const path = next.split(/[?#]/)[0] ?? '';
  if (AUTH_PATHS.some((auth) => path === auth || path.startsWith(`${auth}/`))) return fallback;
  return next;
}
