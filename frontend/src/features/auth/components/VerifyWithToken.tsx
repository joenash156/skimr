import { useMutation, useQueryClient } from '@tanstack/react-query';
import { useEffect, useRef } from 'react';
import { useTranslation } from 'react-i18next';
import { Link } from 'react-router';
import { Alert } from '@/components/ui/Alert';
import { buttonClass } from '@/components/ui/Button';
import { Skeleton } from '@/components/ui/Skeleton';
import { ME_QUERY_KEY } from '@/lib/auth/me';
import { useAuth } from '@/lib/auth/useAuth';
import { verifyEmail } from '../api';
import { alertFromError } from '../formAlert';
import { AuthLayout } from './AuthLayout';

/** Verifies the emailed token once, then offers the next step. */
export function VerifyWithToken({ token }: { token: string }) {
  const { t } = useTranslation('auth');
  const { status } = useAuth();
  const queryClient = useQueryClient();
  const started = useRef(false);

  const mutation = useMutation({
    mutationFn: verifyEmail,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ME_QUERY_KEY }),
  });
  const { mutate } = mutation;

  useEffect(() => {
    // Once only: React runs effects twice in development, and tokens work once.
    if (started.current) return;
    started.current = true;
    mutate(token);
  }, [mutate, token]);

  const continueTo = status === 'authenticated' ? '/' : '/login';

  if (mutation.isError) {
    const alert = alertFromError(mutation.error, t);
    return (
      <AuthLayout title={t('verify.failed_title')}>
        <div className="flex flex-col gap-4">
          <Alert tone="danger" requestId={alert.requestId}>
            {alert.message}
          </Alert>
          <Link
            to={status === 'authenticated' ? '/verify-email' : '/login'}
            className={buttonClass('primary', 'lg', 'w-full')}
          >
            {status === 'authenticated' ? t('verify.get_new_link') : t('verify.go_to_login')}
          </Link>
        </div>
      </AuthLayout>
    );
  }

  if (mutation.isSuccess) {
    return (
      <AuthLayout title={t('verify.done_title')} description={t('verify.done_body')}>
        <Link to={continueTo} className={buttonClass('primary', 'lg', 'w-full')}>
          {status === 'authenticated' ? t('verify.continue') : t('verify.go_to_login')}
        </Link>
      </AuthLayout>
    );
  }

  return (
    <AuthLayout title={t('verify.checking')}>
      <div aria-busy="true" className="flex flex-col gap-3">
        <span className="sr-only">{t('verify.checking')}</span>
        <Skeleton className="h-4 w-3/4" />
        <Skeleton className="h-11 w-full" />
      </div>
    </AuthLayout>
  );
}
