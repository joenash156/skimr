import { useTranslation } from 'react-i18next';
import { Link } from 'react-router';
import { Alert } from '@/components/ui/Alert';
import { buttonClass } from '@/components/ui/Button';

/**
 * Google-only accounts (has_password: false) have no current password, so
 * they set one through "forgot password" first (brief 3a).
 */
export function SetPasswordFirstNotice({ message }: { message: string }) {
  const { t } = useTranslation('settings');
  return (
    <div className="flex flex-col items-start gap-4">
      <Alert tone="info">{message}</Alert>
      <Link to="/forgot-password" className={buttonClass('secondary', 'md')}>
        {t('security.set_password')}
      </Link>
    </div>
  );
}
