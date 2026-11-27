import type { FieldError } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { SERVER_ERROR } from './serverErrors';

/**
 * Turns a react-hook-form error into the text to show: schema messages are
 * i18n keys (common:validation.*); API messages are already translated.
 */
export function useFieldError(): (error: FieldError | undefined) => string | undefined {
  const { t } = useTranslation('common');
  return (error) => {
    if (!error?.message) return undefined;
    return error.type === SERVER_ERROR ? error.message : t(error.message);
  };
}
