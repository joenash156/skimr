import { createContext, useContext } from 'react';
import type { Me } from '@/lib/auth/me';

/**
 * The auth modal (design decision, spec 5.2 note): log in, sign up and forgot
 * password are views of ONE modal, not separate pages. The same views run in
 * two places, and this context is the only thing that differs:
 *
 *   - the public site (src/island): "Log in" / "Get started" open the modal over
 *     the page; switching views is local state; signing in loads /app.
 *   - the dashboard routes /app/login, /app/register, /app/forgot-password
 *     (src/features/auth/pages/AuthScreen.tsx): the same modal, open over a
 *     backdrop; switching views changes the URL, so links and redirects work.
 */
export type AuthView = 'login' | 'register' | 'forgot';

export const AUTH_VIEW_PATHS: Record<AuthView, string> = {
  login: '/login',
  register: '/register',
  forgot: '/forgot-password',
};

export interface AuthFlow {
  view: AuthView;
  /** Switch to another view inside the modal. */
  go: (view: AuthView) => void;
  /** The real page of a view, for the link's href (new tab, no JavaScript). */
  href: (view: AuthView) => string;
  /** After a successful sign-in: go into the app (or to the verify screen). */
  done: (me: Me, next?: string) => void;
  /** ?next=, ?error=google ... (empty on the public site). */
  params: URLSearchParams;
  close: () => void;
}

export const AuthFlowContext = createContext<AuthFlow | null>(null);

export function useAuthFlow(): AuthFlow {
  const flow = useContext(AuthFlowContext);
  if (!flow) throw new Error('useAuthFlow() must be used inside an AuthFlowContext provider.');
  return flow;
}
