/**
 * The public site's auth modal loader (spec 5.2 note). PHP adds this script to
 * every public page (backend/templates/public/layout.html.twig). It is tiny:
 * React, the forms and their CSS load only when a visitor reaches for
 * "Log in" or "Get started" (hover, focus or touch), and open on click.
 *
 * Progressive enhancement: the links are ordinary <a href="/app/login"> and
 * <a href="/app/register" data-auth="register">. Without JavaScript, with a
 * modifier key, or if the modal fails to load, they open the real page, which
 * shows the same modal.
 *
 * ?auth=login or ?auth=register on a public URL opens the modal on load.
 */
import type { AuthView } from '@/features/auth/flow/authFlow';
import type * as MountModule from './mountAuthModal';

const TRIGGER = 'a[data-auth]';

type Mount = typeof MountModule;
let loading: Promise<Mount> | null = null;

function load(): Promise<Mount> {
  loading ??= import('./mountAuthModal').catch((error: unknown) => {
    loading = null; // try again next time
    throw error;
  });
  return loading;
}

function viewOf(value: string | undefined | null): AuthView {
  return value === 'register' ? 'register' : 'login';
}

function open(view: AuthView, fallbackUrl: string): void {
  // The phone menu (Alpine, public/assets/site.js) closes itself on this event.
  window.dispatchEvent(new CustomEvent('auth-open'));
  load().then(
    (mount) => mount.openAuthModal(view),
    () => window.location.assign(fallbackUrl),
  );
}

function trigger(target: EventTarget | null): HTMLAnchorElement | null {
  return target instanceof Element ? target.closest<HTMLAnchorElement>(TRIGGER) : null;
}

document.addEventListener('click', (event) => {
  if (event.defaultPrevented || event.button !== 0) return;
  if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
  const link = trigger(event.target);
  if (!link) return;
  event.preventDefault();
  open(viewOf(link.dataset.auth), link.href);
});

// Warm up on intent, so the modal opens at once on click.
for (const type of ['pointerover', 'focusin', 'touchstart'] as const) {
  document.addEventListener(
    type,
    (event) => {
      if (trigger(event.target)) load().catch(() => undefined);
    },
    { passive: true },
  );
}

const requested = new URLSearchParams(window.location.search).get('auth');
if (requested === 'login' || requested === 'register') {
  open(viewOf(requested), `/app/${requested}`);
}
