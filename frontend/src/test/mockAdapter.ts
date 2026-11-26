import {
  AxiosError,
  AxiosHeaders,
  type AxiosAdapter,
  type AxiosResponse,
  type InternalAxiosRequestConfig,
} from 'axios';
import { api, refreshClient } from '@/lib/api/client';

export type Handler = (config: InternalAxiosRequestConfig) => Promise<AxiosResponse>;

/** Builds the response axios would; rejects with an AxiosError for non-2xx like the real adapter. */
export function respond(
  config: InternalAxiosRequestConfig,
  status: number,
  data: unknown = null,
  headers: Record<string, string> = {},
): Promise<AxiosResponse> {
  const response: AxiosResponse = {
    data,
    status,
    statusText: String(status),
    headers: new AxiosHeaders(headers),
    config,
    request: {},
  };
  if (status >= 200 && status < 300) return Promise.resolve(response);
  return Promise.reject(
    new AxiosError(
      `Request failed with status code ${status}`,
      'ERR_BAD_RESPONSE',
      config,
      {},
      response,
    ),
  );
}

export function apiError(code: string, message = code, extra: Record<string, unknown> = {}) {
  return { error: { code, message, request_id: 'req-test', ...extra } };
}

/** Routes every request from both clients (api and the refresh client) to `handler`. */
export function mockApi(handler: Handler): void {
  const adapter: AxiosAdapter = (config) => handler(config);
  api.defaults.adapter = adapter;
  refreshClient.defaults.adapter = adapter;
}

export function session(accessToken: string) {
  return {
    data: {
      access_token: accessToken,
      token_type: 'Bearer',
      expires_in: 900,
      user: { id: 'u1', first_name: 'Ama', last_name: 'Mensah' },
    },
  };
}

/** Resolves after pending promise callbacks run, so "simultaneous" really overlaps. */
export function tick(ms = 5): Promise<void> {
  return new Promise((resolve) => setTimeout(resolve, ms));
}
