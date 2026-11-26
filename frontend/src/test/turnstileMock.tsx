import { useImperativeHandle, type Ref } from 'react';
import { vi } from 'vitest';

/**
 * Stand-in for @marsidev/react-turnstile in tests (no network, no iframe):
 * a button that "passes" the bot check. Use with
 *   vi.mock('@marsidev/react-turnstile', () => import('@/test/turnstileMock'));
 */
export const turnstileReset = vi.fn();

interface Props {
  onSuccess?: (token: string) => void;
  options?: { action?: string };
  ref?: Ref<{ reset: () => void } | undefined>;
}

export function Turnstile({ onSuccess, options, ref }: Props) {
  useImperativeHandle(ref, () => ({ reset: turnstileReset }));
  return (
    <button type="button" onClick={() => onSuccess?.(`token-${options?.action ?? 'none'}`)}>
      Pass bot check
    </button>
  );
}
