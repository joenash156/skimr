import { useTranslation } from 'react-i18next';

/** "or" between "Continue with Google" and the email form. */
export function OrDivider() {
  const { t } = useTranslation('auth');
  return (
    <div className="flex items-center gap-3 text-xs text-muted" role="separator">
      <span className="h-px flex-1 bg-border" />
      {t('or')}
      <span className="h-px flex-1 bg-border" />
    </div>
  );
}
