/**
 * The access token, in memory only (spec 11.1, brief rule 5).
 *
 * Never put it in localStorage, sessionStorage, cookies or URLs. After a page
 * reload it is gone on purpose: AuthProvider calls /auth/refresh, which uses
 * the HttpOnly refresh cookie to issue a new one.
 */
type Listener = () => void;

let accessToken: string | null = null;
const listeners = new Set<Listener>();

function emit(): void {
  for (const listener of listeners) listener();
}

export const tokenStore = {
  get(): string | null {
    return accessToken;
  },

  set(token: string): void {
    if (token === accessToken) return;
    accessToken = token;
    emit();
  },

  clear(): void {
    if (accessToken === null) return;
    accessToken = null;
    emit();
  },

  /** For useSyncExternalStore: returns an unsubscribe function. */
  subscribe(listener: Listener): () => void {
    listeners.add(listener);
    return () => {
      listeners.delete(listener);
    };
  },
};
