import { errorMessage, normalizeError } from '@/lib/api/errors';

export interface FormAlertState {
  message: string;
  requestId: string | null;
  tone: 'danger' | 'warning';
}

/** Codes that mean "wait and try later" rather than "something is broken". */
const WAIT_CODES = new Set(['ACCOUNT_LOCKED', 'RATE_LIMITED']);

/** A form-level alert for an API error: its translated message and request id. */
export function alertFromError(error: unknown, t: (key: string) => string): FormAlertState {
  const apiError = normalizeError(error);
  return {
    message: errorMessage(apiError, t),
    requestId: apiError.requestId,
    tone: WAIT_CODES.has(apiError.code) ? 'warning' : 'danger',
  };
}
