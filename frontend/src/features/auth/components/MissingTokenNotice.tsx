import { useTranslation } from 'react-i18next';
import { Link } from 'react-router';
import { Alert } from '@/components/ui/Alert';
import { buttonClass } from '@/components/ui/Button';
import { AuthLayout } from './AuthLayout';

/** Shown when an emailed-link page is opened without its ?token=. */
export function MissingTokenNotice({
  title,
  retryTo,
  retryLabel,
}: {
  title: string;
  retryTo: string;
  retryLabel: string;
}) {
  const { t } = useTranslation('auth');
  return (
    <AuthLayout title={title}>
      <div className="flex flex-col gap-4">
        <Alert tone="warning">{t('link.missing_token')}</Alert>
        <Link to={retryTo} className={buttonClass('primary', 'lg', 'w-full')}>
          {retryLabel}
        </Link>
      </div>
    </AuthLayout>
  );
}
