import { createContext } from 'react';
import type { Session } from '../api/client';
import type { Me } from './me';

/**
 * - `loading`: refreshing on load, or fetching /me
 * - `authenticated`: we have a token and the user
 * - `unauthenticated`: no session (RequireAuth sends these to /app/login in B1)
 * - `error`: signed in, but /me failed for another reason (offer Retry)
 */
export type AuthStatus = 'loading' | 'authenticated' | 'unauthenticated' | 'error';

export interface AuthContextValue {
  status: AuthStatus;
  user: Me | null;
  /**
   * Why the last session ended on its own, e.g. REFRESH_REUSED ("You've been
   * signed out for your security", brief 3a). Null after a normal sign-in or sign-out.
   */
  sessionEndedReason: string | null;
  /** After login, register-then-verify, Google link, etc.: store the token and load /me. */
  signIn(session: Session): Promise<Me>;
  /** POST /auth/logout, then forget everything about this user. */
  signOut(): Promise<void>;
  /** Retry loading /me after an `error` status. */
  retry(): void;
}

export const AuthContext = createContext<AuthContextValue | null>(null);
