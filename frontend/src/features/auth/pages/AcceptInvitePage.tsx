import { useMutation } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router';
import { useAuth } from '@/lib/auth/useAuth';
import { acceptInvite, type SetPasswordBody } from '../api';
import { AuthLayout } from '../components/AuthLayout';
import { MissingTokenNotice } from '../components/MissingTokenNotice';
import { SetPasswordForm } from '../components/SetPasswordForm';
import { useTokenFromUrl } from '../useTokenFromUrl';

/** /app/accept-invite?token=...: an invited admin sets their first password, then is signed in. */
export function AcceptInvitePage() {
  const { t } = useTranslation('auth');
  const token = useTokenFromUrl();
  const { signIn } = useAuth();
  const navigate = useNavigate();
  const mutation = useMutation({
    mutationFn: async (body: SetPasswordBody) => signIn(await acceptInvite(body)),
    onSuccess: () => void navigate('/', { replace: true }),
  });

  if (!token) {
    return (
      <MissingTokenNotice
        title={t('invite.title')}
        retryTo="/login"
        retryLabel={t('verify.go_to_login')}
      />
    );
  }

  return (
    <AuthLayout title={t('invite.title')} description={t('invite.description')}>
      <SetPasswordForm
        submitLabel={t('invite.submit')}
        pending={mutation.isPending}
        onSubmit={(values) => mutation.mutateAsync({ token, ...values })}
      />
    </AuthLayout>
  );
}
