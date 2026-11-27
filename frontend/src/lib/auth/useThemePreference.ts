import { useCallback } from 'react';
import { useTranslation } from 'react-i18next';
import { setTheme, useStoredTheme, type Theme } from '../theme';
import { toastError } from '../toast';
import { useAuth } from './useAuth';
import { useUpdateMe } from './useUpdateMe';

/**
 * The theme choice (brief B1 task 5): applies at once and saves to
 * localStorage, plus users.theme with PATCH /me when signed in.
 */
export function useThemePreference(): [Theme, (theme: Theme) => void] {
  const { t } = useTranslation();
  const theme = useStoredTheme();
  const { status, user } = useAuth();
  const { mutate } = useUpdateMe();

  const choose = useCallback(
    (next: Theme) => {
      setTheme(next);
      if (status === 'authenticated' && user && user.theme !== next) {
        mutate({ theme: next }, { onError: (error) => toastError(error, t) });
      }
    },
    [status, user, mutate, t],
  );

  return [theme, choose];
}
