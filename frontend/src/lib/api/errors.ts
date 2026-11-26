import { isAxiosError } from 'axios';
import type { components } from './schema';

type ErrorBody = components['schemas']['Error'];

/** Client-side codes, for failures that never reached the API's error handler. */
export const NETWORK_ERROR = 'NETWORK_ERROR';
export const UNKNOWN_ERROR = 'UNKNOWN_ERROR';

/**
 * Every failed API call is thrown as an ApiError (brief B0 task 5).
 *
 * - `code`: switch on this, never on `message` (spec 10.1)
 * - `message`: already translated by the API; empty for NETWORK_ERROR / UNKNOWN_ERROR,
 *   so use `errorMessage()` to show something
 * - `fields`: per-field messages on 422, keyed by request field name
 * - `requestId`: show it in error toasts ("Reference: ...") so users can report it (brief 3a)
 */
export class ApiError extends Error {
  readonly code: string;
  readonly fields: Record<string, string>;
  readonly requestId: string | null;
  readonly status: number | null;
  /** Seconds from the Retry-After header (429 RATE_LIMITED / ACCOUNT_LOCKED). */
  readonly retryAfter: number | null;

  constructor(init: {
    code: string;
    message: string;
    fields?: Record<string, string> | undefined;
    requestId?: string | null | undefined;
    status?: number | null | undefined;
    retryAfter?: number | null | undefined;
  }) {
    super(init.message);
    this.name = 'ApiError';
    this.code = init.code;
    this.fields = init.fields ?? {};
    this.requestId = init.requestId ?? null;
    this.status = init.status ?? null;
    this.retryAfter = init.retryAfter ?? null;
  }
}

function isErrorBody(value: unknown): value is ErrorBody {
  if (typeof value !== 'object' || value === null || !('error' in value)) return false;
  const error = (value as { error: unknown }).error;
  return (
    typeof error === 'object' &&
    error !== null &&
    typeof (error as { code?: unknown }).code === 'string' &&
    typeof (error as { message?: unknown }).message === 'string'
  );
}

function parseRetryAfter(value: unknown): number | null {
  if (typeof value !== 'string' && typeof value !== 'number') return null;
  const seconds = Number(value);
  return Number.isFinite(seconds) && seconds >= 0 ? seconds : null;
}

/** Turns anything axios (or our own code) throws into an ApiError. */
export function normalizeError(error: unknown): ApiError {
  if (error instanceof ApiError) return error;

  if (isAxiosError(error)) {
    const response = error.response;
    if (!response) {
      return new ApiError({ code: NETWORK_ERROR, message: '' });
    }
    const retryAfter = parseRetryAfter(response.headers?.['retry-after']);
    if (isErrorBody(response.data)) {
      const { code, message, fields, request_id } = response.data.error;
      return new ApiError({
        code,
        message,
        fields,
        requestId: request_id,
        status: response.status,
        retryAfter,
      });
    }
    return new ApiError({ code: UNKNOWN_ERROR, message: '', status: response.status, retryAfter });
  }

  return new ApiError({ code: UNKNOWN_ERROR, message: '' });
}

/** The text to show for an error: the API's translated message, or a generic one. */
export function errorMessage(error: unknown, t: (key: string) => string): string {
  const apiError = normalizeError(error);
  if (apiError.message) return apiError.message;
  return apiError.code === NETWORK_ERROR ? t('common:errors.network') : t('common:errors.unknown');
}
