import { useMutation } from '@tanstack/react-query';
import { MailCheck } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Link, Navigate } from 'react-router';
import { Alert } from '@/components/ui/Alert';
import { Button, buttonClass } from '@/components/ui/Button';
import { Skeleton } from '@/components/ui/Skeleton';
import { useAuth } from '@/lib/auth/useAuth';
import { resendVerification } from '../api';
import { alertFromError, type FormAlertState } from '../formAlert';
import { AuthLayout } from './AuthLayout';

/** "Verify your email" for signed-in pending users, with a resend button. */
export function PendingVerification() {
  const { t } = useTranslation('auth');
  const { status, user, signOut } = useAuth();
  const [alert, setAlert] = useState<FormAlertState | null>(null);

  const resend = useMutation({
    mutationFn: resendVerification,
    onMutate: () => setAlert(null),
    onError: (error) => setAlert(alertFromError(error, t)),
  });

  if (status === 'loading') {
    return (
      <AuthLayout title={t('verify.title')}>
        <div aria-busy="true">
          <Skeleton className="h-11 w-full" />
        </div>
      </AuthLayout>
    );
  }
  // Already verified: nothing to do here.
  if (status === 'authenticated' && user?.status === 'active') return <Navigate to="/" replace />;

  if (status !== 'authenticated' || !user) {
    return (
      <AuthLayout title={t('verify.title')} description={t('verify.open_link')}>
        <Link to="/login" className={buttonClass('primary', 'lg', 'w-full')}>
          {t('verify.go_to_login')}
        </Link>
      </AuthLayout>
    );
  }

  return (
    <AuthLayout title={t('verify.title')}>
      <div className="flex flex-col gap-4">
        <MailCheck aria-hidden="true" className="size-8 text-primary" />
        <p className="text-sm">{t('verify.pending_body', { email: user.email })}</p>
        <p className="text-sm text-muted">{t('verify.pending_hint')}</p>
        {resend.isSuccess && <Alert tone="success">{t('verify.resent')}</Alert>}
        {alert && (
          <Alert tone={alert.tone} requestId={alert.requestId}>
            {alert.message}
          </Alert>
        )}
        <Button size="lg" fullWidth loading={resend.isPending} onClick={() => resend.mutate()}>
          {t('verify.resend')}
        </Button>
        <Button variant="ghost" fullWidth onClick={() => void signOut()}>
          {t('verify.use_other_account')}
        </Button>
      </div>
    </AuthLayout>
  );
}
