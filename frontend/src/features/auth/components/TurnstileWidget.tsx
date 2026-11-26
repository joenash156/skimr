import { Turnstile, type TurnstileInstance } from '@marsidev/react-turnstile';
import type { Ref } from 'react';
import { useTranslation } from 'react-i18next';
import { isSupportedLanguage } from '@/lib/i18n';
import { getStoredTheme, resolveTheme } from '@/lib/theme';
import { getTurnstileSiteKey, type TurnstileAction } from '@/lib/turnstile';

export interface TurnstileWidgetProps {
  /** The API checks the action on each token (brief 3a). */
  action: TurnstileAction;
  /** A fresh token, or null when it expired or failed. Tokens work once. */
  onToken: (token: string | null) => void;
  /** Call `.reset()` after every submit: each token is single-use. */
  ref?: Ref<TurnstileInstance | undefined>;
  error?: string | undefined;
}

/** Cloudflare Turnstile bot check on login, register and forgot password (spec 11.2). */
export function TurnstileWidget({ action, onToken, ref, error }: TurnstileWidgetProps) {
  const { i18n } = useTranslation();
  return (
    <div className="flex flex-col gap-1.5">
      <Turnstile
        ref={ref}
        siteKey={getTurnstileSiteKey()}
        options={{
          action,
          size: 'flexible',
          theme: resolveTheme(getStoredTheme()),
          language: isSupportedLanguage(i18n.language) ? i18n.language : 'auto',
        }}
        onSuccess={(token) => onToken(token)}
        onExpire={() => onToken(null)}
        onError={() => onToken(null)}
      />
      {error && (
        <p role="alert" className="text-xs font-medium text-danger">
          {error}
        </p>
      )}
    </div>
  );
}
