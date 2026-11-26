import { useMutation } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { Link } from 'react-router';
import { Alert } from '@/components/ui/Alert';
import { buttonClass } from '@/components/ui/Button';
import { useAuth } from '@/lib/auth/useAuth';
import { resetPassword } from '../api';
import { AuthLayout } from '../components/AuthLayout';
import { MissingTokenNotice } from '../components/MissingTokenNotice';
import { SetPasswordForm } from '../components/SetPasswordForm';
import { useTokenFromUrl } from '../useTokenFromUrl';

/** /app/reset-password?token=... (spec 5.2): a reset signs out every session. */
export function ResetPasswordPage() {
  const { t } = useTranslation('auth');
  const token = useTokenFromUrl();
  const { clearSession } = useAuth();
  const mutation = useMutation({
    mutationFn: resetPassword,
    // Every session ended on the server, this tab's included.
    onSuccess: () => clearSession(),
  });

  if (!token) {
    return (
      <MissingTokenNotice
        title={t('reset.title')}
        retryTo="/forgot-password"
        retryLabel={t('reset.request_new')}
      />
    );
  }

  if (mutation.isSuccess) {
    return (
      <AuthLayout title={t('reset.done_title')}>
        <div className="flex flex-col gap-4">
          <Alert tone="success">{t('reset.done_body')}</Alert>
          <Link to="/login" className={buttonClass('primary', 'lg', 'w-full')}>
            {t('reset.go_to_login')}
          </Link>
        </div>
      </AuthLayout>
    );
  }

  return (
    <AuthLayout title={t('reset.title')} description={t('reset.description')}>
      <SetPasswordForm
        submitLabel={t('reset.submit')}
        pending={mutation.isPending}
        onSubmit={(values) => mutation.mutateAsync({ token, ...values })}
      />
    </AuthLayout>
  );
}
