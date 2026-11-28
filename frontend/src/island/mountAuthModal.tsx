import './island.css';
import { StrictMode, useEffect, useMemo, useSyncExternalStore } from 'react';
import { createRoot, type Root } from 'react-dom/client';
import { Providers } from '@/app/providers';
import {
  AUTH_VIEW_PATHS,
  AuthFlowContext,
  type AuthFlow,
  type AuthView,
} from '@/features/auth/flow/authFlow';
import { AuthModal } from '@/features/auth/modal/AuthModal';
import { ForgotPasswordView } from '@/features/auth/views/ForgotPasswordView';
import { LoginView } from '@/features/auth/views/LoginView';
import { RegisterView } from '@/features/auth/views/RegisterView';
import type { Me } from '@/lib/auth/me';
import { useAuth } from '@/lib/auth/useAuth';
import { goTo } from '@/lib/browser';
import i18n, { isSupportedLanguage } from '@/lib/i18n';
import { watchSystemTheme } from '@/lib/theme';

/**
 * The auth modal on the public site (loaded by ./auth-island.ts on demand).
 * One React root, created on first open; later opens reuse it. The page's own
 * language (<html lang>) and theme (the `dark` class, same rule as /app) apply.
 */
const VIEWS = { login: LoginView, register: RegisterView, forgot: ForgotPasswordView } as const;

interface State {
  open: boolean;
  view: AuthView;
}

let state: State = { open: false, view: 'login' };
const listeners = new Set<() => void>();
const store = {
  get: () => state,
  set(next: Partial<State>) {
    state = { ...state, ...next };
    for (const listener of listeners) listener();
  },
  subscribe(listener: () => void) {
    listeners.add(listener);
    return () => listeners.delete(listener);
  },
};

/** Where a signed-in visitor goes: the app, or the verify screen first. */
function enterApp(me: Me | null): void {
  goTo(me?.status === 'pending_verification' ? '/app/verify-email' : '/app/');
}

function IslandAuthModal() {
  const { open, view } = useSyncExternalStore(store.subscribe, store.get, store.get);
  const { status, user } = useAuth();

  // Already signed in (the refresh cookie worked): "Log in" just means "take me in".
  useEffect(() => {
    if (open && status === 'authenticated') enterApp(user);
  }, [open, status, user]);

  const flow = useMemo<AuthFlow>(
    () => ({
      view,
      go: (next) => store.set({ view: next }),
      href: (next) => `/app${AUTH_VIEW_PATHS[next]}`,
      done: (me) => enterApp(me),
      params: new URLSearchParams(),
      close: () => store.set({ open: false }),
    }),
    [view],
  );
  const View = VIEWS[view];

  return (
    <AuthFlowContext.Provider value={flow}>
      <AuthModal open={open}>
        <View />
      </AuthModal>
    </AuthFlowContext.Provider>
  );
}

let root: Root | null = null;

export function openAuthModal(view: AuthView): void {
  store.set({ open: true, view });
  if (root) return;

  const language = document.documentElement.lang;
  if (isSupportedLanguage(language) && language !== i18n.language)
    void i18n.changeLanguage(language);
  watchSystemTheme();

  const container = document.createElement('div');
  container.id = 'skimr-auth';
  document.body.append(container);
  root = createRoot(container);
  root.render(
    <StrictMode>
      <Providers>
        <IslandAuthModal />
      </Providers>
    </StrictMode>,
  );
}
