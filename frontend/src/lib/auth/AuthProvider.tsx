import { useQuery, useQueryClient } from '@tanstack/react-query';
import {
  useCallback,
  useEffect,
  useMemo,
  useState,
  useSyncExternalStore,
  type ReactNode,
} from 'react';
import { api, onApiEvent, refreshSession, type Session } from '../api/client';
import i18n, { isSupportedLanguage } from '../i18n';
import { AuthContext, type AuthContextValue, type AuthStatus } from './AuthContext';
import { fetchMe, ME_QUERY_KEY, type Me } from './me';
import { tokenStore } from './tokenStore';

/**
 * Session lifecycle (spec 11.1, brief B0 task 6):
 * 1. On load, POST /auth/refresh: the HttpOnly cookie, if any, buys a new access token.
 * 2. Then GET /me (a React Query query, so screens share one cached copy).
 * 3. When the API client reports the session is over, forget the user and their data.
 */
export function AuthProvider({ children }: { children: ReactNode }) {
  const queryClient = useQueryClient();
  const token = useSyncExternalStore(tokenStore.subscribe, tokenStore.get, tokenStore.get);
  const [bootstrapped, setBootstrapped] = useState(false);
  const [sessionEndedReason, setSessionEndedReason] = useState<string | null>(null);

  useEffect(() => {
    let active = true;
    // A 401 here just means "not signed in". refreshSession() is single-flight,
    // so React's double-run of effects in development still sends one request.
    refreshSession()
      .catch(() => undefined)
      .finally(() => {
        if (active) setBootstrapped(true);
      });
    return () => {
      active = false;
    };
  }, []);

  useEffect(
    () =>
      onApiEvent((event) => {
        if (event.type === 'session-expired') {
          setSessionEndedReason(event.code);
          queryClient.clear();
        }
      }),
    [queryClient],
  );

  const meQuery = useQuery({
    queryKey: ME_QUERY_KEY,
    queryFn: fetchMe,
    enabled: bootstrapped && token !== null,
    staleTime: 60_000,
  });
  const user = token !== null ? (meQuery.data ?? null) : null;

  // Language order (spec 13): the signed-in user's locale wins over the browser's.
  const userLocale = user?.locale;
  useEffect(() => {
    if (isSupportedLanguage(userLocale) && userLocale !== i18n.language) {
      void i18n.changeLanguage(userLocale);
    }
  }, [userLocale]);

  let status: AuthStatus;
  if (!bootstrapped) status = 'loading';
  else if (token === null) status = 'unauthenticated';
  else if (meQuery.isError) status = 'error';
  else if (user === null) status = 'loading';
  else status = 'authenticated';

  const signIn = useCallback(
    async (session: Session): Promise<Me> => {
      setSessionEndedReason(null);
      queryClient.removeQueries({ queryKey: ME_QUERY_KEY });
      tokenStore.set(session.access_token);
      return queryClient.fetchQuery({ queryKey: ME_QUERY_KEY, queryFn: fetchMe });
    },
    [queryClient],
  );

  const clearSession = useCallback((): void => {
    setSessionEndedReason(null);
    tokenStore.clear();
    queryClient.clear();
  }, [queryClient]);

  const signOut = useCallback(async (): Promise<void> => {
    try {
      await api.post('/auth/logout');
    } catch {
      // Even if the API call fails, this tab must forget the session.
    } finally {
      clearSession();
    }
  }, [clearSession]);

  const { refetch } = meQuery;
  const retry = useCallback(() => {
    void refetch();
  }, [refetch]);

  const value = useMemo<AuthContextValue>(
    () => ({ status, user, sessionEndedReason, signIn, signOut, clearSession, retry }),
    [status, user, sessionEndedReason, signIn, signOut, clearSession, retry],
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}
