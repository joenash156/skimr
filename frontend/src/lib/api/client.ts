import axios, { type AxiosError, type InternalAxiosRequestConfig } from 'axios';
import { tokenStore } from '../auth/tokenStore';
import { normalizeError, type ApiError } from './errors';
import type { components } from './schema';

export type Session = components['schemas']['SessionResponse']['data'];

const BASE_URL = '/api/v1';

/** Required on the cookie endpoints (/auth/refresh, /auth/logout) as a CSRF guard (spec 11.1). */
const CSRF_HEADERS = { 'X-Requested-With': 'skimr' } as const;

declare module 'axios' {
  interface AxiosRequestConfig {
    /** Set internally once a request has been retried after a refresh. */
    _retried?: boolean;
  }
}

// ---------------------------------------------------------------------------
// Events the UI reacts to (AuthProvider, and the router in B1)
// ---------------------------------------------------------------------------

export type ApiEvent =
  /** The session is over: refresh failed after a 401. `code` is e.g. REFRESH_REUSED. */
  | { type: 'session-expired'; code: string }
  /** 403 EMAIL_NOT_VERIFIED: route the user to the verify screen (brief 3a). */
  | { type: 'email-not-verified' };

type ApiEventListener = (event: ApiEvent) => void;
const eventListeners = new Set<ApiEventListener>();

export function onApiEvent(listener: ApiEventListener): () => void {
  eventListeners.add(listener);
  return () => {
    eventListeners.delete(listener);
  };
}

function emitApiEvent(event: ApiEvent): void {
  for (const listener of eventListeners) listener(event);
}

// ---------------------------------------------------------------------------
// Language: the API translates its messages from Accept-Language (spec 10.1)
// ---------------------------------------------------------------------------

let requestLanguage: string | null = null;

/** Called by lib/i18n.ts whenever the UI language changes. */
export function setRequestLanguage(language: string): void {
  requestLanguage = language;
}

function applyCommonHeaders(config: InternalAxiosRequestConfig): InternalAxiosRequestConfig {
  if (requestLanguage) config.headers.set('Accept-Language', requestLanguage);
  return config;
}

// ---------------------------------------------------------------------------
// Clients
// ---------------------------------------------------------------------------

/** The API client every feature uses (through React Query hooks). */
export const api = axios.create({
  baseURL: BASE_URL,
  withCredentials: true,
  headers: { ...CSRF_HEADERS, Accept: 'application/json' },
});

/**
 * A bare client for /auth/refresh only, with no interceptors, so a failed
 * refresh can never trigger another refresh. Exported for tests only.
 */
export const refreshClient = axios.create({
  baseURL: BASE_URL,
  withCredentials: true,
  headers: { ...CSRF_HEADERS, Accept: 'application/json' },
});
refreshClient.interceptors.request.use(applyCommonHeaders);

// ---------------------------------------------------------------------------
// Single-flight refresh (brief rule 5)
// ---------------------------------------------------------------------------

let refreshInFlight: Promise<Session> | null = null;

/**
 * Swaps the refresh cookie for a new access token. Concurrent callers share
 * ONE request: the refresh token rotates on every call, so two parallel
 * refreshes would look like token theft (REFRESH_REUSED) to the API.
 */
export function refreshSession(): Promise<Session> {
  if (!refreshInFlight) {
    refreshInFlight = refreshClient
      .post<components['schemas']['SessionResponse']>('/auth/refresh', null)
      .then((response) => {
        tokenStore.set(response.data.data.access_token);
        return response.data.data;
      })
      .catch((error: unknown) => {
        tokenStore.clear();
        throw normalizeError(error);
      })
      .finally(() => {
        refreshInFlight = null;
      });
  }
  return refreshInFlight;
}

// ---------------------------------------------------------------------------
// Interceptors
// ---------------------------------------------------------------------------

api.interceptors.request.use((config) => {
  applyCommonHeaders(config);
  const token = tokenStore.get();
  if (token && !config.headers.has('Authorization')) {
    config.headers.set('Authorization', `Bearer ${token}`);
  }
  return config;
});

/**
 * 401 on /auth/* means wrong credentials or an expired link, not an expired
 * access token, so it must not trigger a refresh.
 */
function isAuthEndpoint(url: string | undefined): boolean {
  return url !== undefined && /^\/?auth\//.test(url);
}

api.interceptors.response.use(undefined, async (error: AxiosError) => {
  const config = error.config;
  const status = error.response?.status;

  if (status === 401 && config && !config._retried && !isAuthEndpoint(config.url)) {
    config._retried = true;
    let session: Session;
    try {
      session = await refreshSession();
    } catch (refreshError) {
      const apiError: ApiError = normalizeError(refreshError);
      emitApiEvent({ type: 'session-expired', code: apiError.code });
      throw apiError;
    }
    config.headers.set('Authorization', `Bearer ${session.access_token}`);
    return api.request(config);
  }

  const apiError = normalizeError(error);
  if (status === 401 && config?._retried) {
    // Refreshed, retried, and still 401 (e.g. suspended): the session is over.
    tokenStore.clear();
    emitApiEvent({ type: 'session-expired', code: apiError.code });
  }
  if (status === 403 && apiError.code === 'EMAIL_NOT_VERIFIED') {
    emitApiEvent({ type: 'email-not-verified' });
  }
  throw apiError;
});
