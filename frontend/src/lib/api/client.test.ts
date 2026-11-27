import { beforeEach, describe, expect, it, vi } from 'vitest';
import { apiError, mockApi, respond, session, tick } from '@/test/mockAdapter';
import { tokenStore } from '../auth/tokenStore';
import { api, onApiEvent, refreshSession, setRequestLanguage, type ApiEvent } from './client';
import { ApiError } from './errors';

beforeEach(() => {
  tokenStore.clear();
  setRequestLanguage('en');
});

describe('api client: single-flight refresh (brief B0 acceptance)', () => {
  it('turns 3 simultaneous 401s into exactly ONE refresh call, then retries each once', async () => {
    tokenStore.set('expired-token');
    let refreshCalls = 0;
    const seenTokens: string[] = [];

    mockApi(async (config) => {
      if (config.url === '/auth/refresh') {
        refreshCalls += 1;
        await tick(); // keep the refresh in flight while the other 401s arrive
        return respond(config, 200, session('fresh-token'));
      }
      const auth = String(config.headers.get('Authorization'));
      seenTokens.push(auth);
      return auth === 'Bearer fresh-token'
        ? respond(config, 200, { data: { url: config.url } })
        : respond(config, 401, apiError('UNAUTHENTICATED'));
    });

    const results = await Promise.all([api.get('/projects'), api.get('/me'), api.get('/me/usage')]);

    expect(refreshCalls).toBe(1);
    expect(results.map((r) => r.data.data.url)).toEqual(['/projects', '/me', '/me/usage']);
    expect(tokenStore.get()).toBe('fresh-token');
    // 3 first attempts with the old token + 3 retries with the new one; nothing retried twice.
    expect(seenTokens.filter((t) => t === 'Bearer expired-token')).toHaveLength(3);
    expect(seenTokens.filter((t) => t === 'Bearer fresh-token')).toHaveLength(3);
  });

  it('sends the refresh with the CSRF header and cookies', async () => {
    const seen: { withCredentials?: boolean; xrw?: unknown } = {};
    mockApi((config) => {
      seen.withCredentials = config.withCredentials;
      seen.xrw = config.headers.get('X-Requested-With');
      return respond(config, 200, session('t'));
    });
    await refreshSession();
    expect(seen).toEqual({ withCredentials: true, xrw: 'skimr' });
  });

  it('clears the token and reports session-expired when the refresh fails', async () => {
    tokenStore.set('expired-token');
    const events: ApiEvent[] = [];
    const off = onApiEvent((e) => events.push(e));
    let refreshCalls = 0;

    mockApi((config) => {
      if (config.url === '/auth/refresh') {
        refreshCalls += 1;
        return respond(config, 401, apiError('REFRESH_REUSED'));
      }
      return respond(config, 401, apiError('UNAUTHENTICATED'));
    });

    const results = await Promise.allSettled([api.get('/a'), api.get('/b'), api.get('/c')]);
    off();

    expect(results.every((r) => r.status === 'rejected')).toBe(true);
    expect(refreshCalls).toBe(1);
    expect(tokenStore.get()).toBeNull();
    expect(events).toContainEqual({ type: 'session-expired', code: 'REFRESH_REUSED' });
  });

  it('ends the session if the retried request is still 401', async () => {
    tokenStore.set('old');
    const events: ApiEvent[] = [];
    const off = onApiEvent((e) => events.push(e));
    let refreshCalls = 0;
    mockApi((config) => {
      if (config.url === '/auth/refresh') {
        refreshCalls += 1;
        return respond(config, 200, session('new'));
      }
      return respond(config, 401, apiError('UNAUTHENTICATED'));
    });

    await expect(api.get('/projects')).rejects.toBeInstanceOf(ApiError);
    off();
    expect(refreshCalls).toBe(1);
    expect(tokenStore.get()).toBeNull();
    expect(events).toEqual([{ type: 'session-expired', code: 'UNAUTHENTICATED' }]);
  });

  it('never refreshes on a 401 from /auth/* (wrong password is not an expired token)', async () => {
    const handler = vi.fn((config) => respond(config, 401, apiError('INVALID_CREDENTIALS')));
    mockApi(handler);
    await expect(api.post('/auth/login', {})).rejects.toMatchObject({
      code: 'INVALID_CREDENTIALS',
    });
    expect(handler).toHaveBeenCalledTimes(1);
  });
});

describe('api client: requests', () => {
  it('adds the bearer token, the CSRF header and the UI language', async () => {
    tokenStore.set('abc');
    setRequestLanguage('fr');
    let headers: Record<string, unknown> = {};
    mockApi((config) => {
      headers = {
        auth: config.headers.get('Authorization'),
        xrw: config.headers.get('X-Requested-With'),
        lang: config.headers.get('Accept-Language'),
        baseURL: config.baseURL,
        withCredentials: config.withCredentials,
      };
      return respond(config, 200, { data: {} });
    });
    await api.get('/me');
    expect(headers).toEqual({
      auth: 'Bearer abc',
      xrw: 'skimr',
      lang: 'fr',
      baseURL: '/api/v1',
      withCredentials: true,
    });
  });

  it('sends no Authorization header when signed out', async () => {
    let auth: unknown = 'unset';
    mockApi((config) => {
      auth = config.headers.get('Authorization');
      return respond(config, 200, { data: {} });
    });
    await api.get('/health');
    expect(auth).toBeUndefined();
  });

  it('throws normalised errors with code, message, fields and request id', async () => {
    mockApi((config) =>
      respond(
        config,
        422,
        apiError('VALIDATION_FAILED', 'Check the fields.', { fields: { email: 'Invalid email.' } }),
      ),
    );
    await expect(api.patch('/me', {})).rejects.toMatchObject({
      code: 'VALIDATION_FAILED',
      message: 'Check the fields.',
      fields: { email: 'Invalid email.' },
      requestId: 'req-test',
      status: 422,
    });
  });

  it('reports EMAIL_NOT_VERIFIED so the UI can show the verify screen', async () => {
    const events: ApiEvent[] = [];
    const off = onApiEvent((e) => events.push(e));
    mockApi((config) => respond(config, 403, apiError('EMAIL_NOT_VERIFIED')));
    await expect(api.get('/projects')).rejects.toMatchObject({ code: 'EMAIL_NOT_VERIFIED' });
    off();
    expect(events).toEqual([{ type: 'email-not-verified' }]);
  });
});
