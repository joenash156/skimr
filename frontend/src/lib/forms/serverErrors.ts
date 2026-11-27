import type { FieldValues, Path, UseFormSetError } from 'react-hook-form';
import { normalizeError } from '../api/errors';

/** Marks an error that came from the API: its message is already translated. */
export const SERVER_ERROR = 'server';

/**
 * Puts the API's per-field messages (422 `fields`, keyed by request field
 * name) under the matching inputs. Returns true if at least one field got
 * an error, so the caller knows whether to also show a form-level alert.
 */
export function applyServerFieldErrors<T extends FieldValues>(
  error: unknown,
  setError: UseFormSetError<T>,
  fieldNames: readonly Path<T>[],
): boolean {
  const { fields } = normalizeError(error);
  let applied = false;
  let first = true;
  for (const name of fieldNames) {
    const message = fields[name];
    if (message) {
      setError(name, { type: SERVER_ERROR, message }, { shouldFocus: first });
      first = false;
      applied = true;
    }
  }
  return applied;
}
