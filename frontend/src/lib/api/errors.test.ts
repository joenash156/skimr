import { AxiosError, AxiosHeaders, type InternalAxiosRequestConfig } from 'axios';
import { describe, expect, it } from 'vitest';
import { ApiError, errorMessage, NETWORK_ERROR, normalizeError, UNKNOWN_ERROR } from './errors';

const config = { headers: new AxiosHeaders() } as InternalAxiosRequestConfig;

function axiosErrorWith(status: number, data: unknown, headers: Record<string, string> = {}) {
  return new AxiosError(
    'failed',
    'ERR_BAD_RESPONSE',
    config,
    {},
    {
      status,
      statusText: '',
      data,
      headers: new AxiosHeaders(headers),
      config,
    },
  );
}

describe('normalizeError', () => {
  it('reads the spec 10.1 error body', () => {
    const error = normalizeError(
      axiosErrorWith(
        429,
        { error: { code: 'ACCOUNT_LOCKED', message: 'Try again later.', request_id: 'r1' } },
        { 'retry-after': '900' },
      ),
    );
    expect(error).toBeInstanceOf(ApiError);
    expect(error).toMatchObject({
      code: 'ACCOUNT_LOCKED',
      message: 'Try again later.',
      fields: {},
      requestId: 'r1',
      status: 429,
      retryAfter: 900,
    });
  });

  it('maps a request with no response to NETWORK_ERROR', () => {
    const error = normalizeError(new AxiosError('Network Error', 'ERR_NETWORK', config));
    expect(error.code).toBe(NETWORK_ERROR);
    expect(error.status).toBeNull();
  });

  it('maps an unexpected body (e.g. an HTML error page) to UNKNOWN_ERROR', () => {
    const error = normalizeError(axiosErrorWith(502, '<html>Bad gateway</html>'));
    expect(error).toMatchObject({ code: UNKNOWN_ERROR, status: 502, message: '' });
  });

  it('returns an ApiError unchanged and wraps anything else', () => {
    const original = new ApiError({ code: 'X', message: 'y' });
    expect(normalizeError(original)).toBe(original);
    expect(normalizeError(new Error('boom')).code).toBe(UNKNOWN_ERROR);
  });
});

describe('errorMessage', () => {
  const t = (key: string) => `t:${key}`;

  it("prefers the API's translated message", () => {
    expect(errorMessage(new ApiError({ code: 'X', message: 'Translated.' }), t)).toBe(
      'Translated.',
    );
  });

  it('falls back to translated generic messages', () => {
    expect(errorMessage(new ApiError({ code: NETWORK_ERROR, message: '' }), t)).toBe(
      't:common:errors.network',
    );
    expect(errorMessage(new Error('?'), t)).toBe('t:common:errors.unknown');
  });
});
