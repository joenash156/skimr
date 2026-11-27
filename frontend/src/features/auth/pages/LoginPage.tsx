import type { TurnstileInstance } from '@marsidev/react-turnstile';
import { useMutation } from '@tanstack/react-query';
import { useRef, useState } from 'react';
import { useForm } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { useNavigate, useSearchParams } from 'react-router';
import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { PasswordInput } from '@/components/ui/PasswordInput';
import { useAuth } from '@/lib/auth/useAuth';
import { applyServerFieldErrors } from '@/lib/forms/serverErrors';
import { useFieldError } from '@/lib/forms/useFieldError';
import { zodResolver } from '@/lib/forms/zodResolver';
import { login } from '../api';
import { AuthLayout } from '../components/AuthLayout';
import { GoogleButton } from '../components/GoogleButton';
import { GoogleLinkForm } from '../components/GoogleLinkForm';
import { OrDivider } from '../components/OrDivider';
import { TextLink } from '../components/TextLink';
import { TurnstileWidget } from '../components/TurnstileWidget';
import { alertFromError, type FormAlertState } from '../formAlert';
import { safeNext } from '../safeNext';
import { loginSchema, type LoginValues } from '../schemas';

/**
 * /app/login (spec 5.2). Also handles the Google return trips (brief 3a):
 * ?link=google asks for the password to link Google; ?error=google says it failed.
 */
export function LoginPage() {
  const { t } = useTranslation('auth');
  const [params] = useSearchParams();
  const { signIn, sessionEndedReason } = useAuth();
  const navigate = useNavigate();
  const fieldError = useFieldError();
  const turnstile = useRef<TurnstileInstance | undefined>(undefined);
  const [token, setToken] = useState<string | null>(null);
  const [turnstileError, setTurnstileError] = useState<string | undefined>();
  const [alert, setAlert] = useState<FormAlertState | null>(null);

  const form = useForm<LoginValues>({
    resolver: zodResolver(loginSchema),
    defaultValues: { email: '', password: '' },
    mode: 'onTouched',
  });
  const { register, handleSubmit, setError, formState } = form;

  const mutation = useMutation({
    mutationFn: async (values: LoginValues & { turnstile_token: string }) => {
      const session = await login(values);
      return signIn(session);
    },
    onSuccess: (me) => {
      const target =
        me.status === 'pending_verification' ? '/verify-email' : safeNext(params.get('next'));
      void navigate(target, { replace: true });
    },
    onError: (error) => {
      // Tokens work once: get a fresh one for the next attempt.
      turnstile.current?.reset();
      setToken(null);
      if (!applyServerFieldErrors(error, setError, ['email', 'password'])) {
        setAlert(alertFromError(error, t));
      }
    },
  });

  if (params.get('link') === 'google') return <GoogleLinkForm />;

  const onSubmit = handleSubmit((values) => {
    setAlert(null);
    if (!token) {
      setTurnstileError(t('turnstile.required'));
      return;
    }
    mutation.mutate({ ...values, turnstile_token: token });
  });

  return (
    <AuthLayout
      title={t('login.title')}
      description={t('login.description')}
      footer={
        <>
          {t('login.no_account')} <TextLink to="/register">{t('login.sign_up')}</TextLink>
        </>
      }
    >
      <div className="flex flex-col gap-5">
        {sessionEndedReason === 'PASSWORD_CHANGED' && (
          <Alert tone="success">{t('login.notice_password_changed')}</Alert>
        )}
        {sessionEndedReason === 'ACCOUNT_DELETED' && (
          <Alert tone="success">{t('login.notice_account_deleted')}</Alert>
        )}
        {sessionEndedReason === 'REFRESH_REUSED' && (
          <Alert tone="warning" title={t('login.signed_out_security_title')}>
            {t('login.signed_out_security')}
          </Alert>
        )}
        {params.get('error') === 'google' && <Alert tone="danger">{t('google.failed')}</Alert>}
        {alert && (
          <Alert tone={alert.tone} requestId={alert.requestId}>
            {alert.message}
          </Alert>
        )}

        <GoogleButton />
        <OrDivider />

        <form onSubmit={onSubmit} noValidate className="flex flex-col gap-4">
          <Input
            label={t('fields.email')}
            type="email"
            autoComplete="email"
            inputMode="email"
            required
            error={fieldError(formState.errors.email)}
            {...register('email')}
          />
          <div className="flex flex-col gap-2">
            <PasswordInput
              label={t('fields.password')}
              autoComplete="current-password"
              required
              error={fieldError(formState.errors.password)}
              {...register('password')}
            />
            <TextLink to="/forgot-password" className="self-end text-sm">
              {t('login.forgot')}
            </TextLink>
          </div>
          <TurnstileWidget
            ref={turnstile}
            action="login"
            error={turnstileError}
            onToken={(value) => {
              setToken(value);
              if (value) setTurnstileError(undefined);
            }}
          />
          <Button type="submit" size="lg" fullWidth loading={mutation.isPending}>
            {t('login.submit')}
          </Button>
        </form>
      </div>
    </AuthLayout>
  );
}
