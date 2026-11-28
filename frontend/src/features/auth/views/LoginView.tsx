import type { TurnstileInstance } from '@marsidev/react-turnstile';
import { useMutation } from '@tanstack/react-query';
import { useRef, useState } from 'react';
import { useForm } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { PasswordInput } from '@/components/ui/PasswordInput';
import { useAuth } from '@/lib/auth/useAuth';
import { applyServerFieldErrors } from '@/lib/forms/serverErrors';
import { useFieldError } from '@/lib/forms/useFieldError';
import { zodResolver } from '@/lib/forms/zodResolver';
import { login } from '../api';
import { GoogleButton } from '../components/GoogleButton';
import { OrDivider } from '../components/OrDivider';
import { TurnstileWidget } from '../components/TurnstileWidget';
import { useAuthFlow } from '../flow/authFlow';
import { ViewLink } from '../flow/ViewLink';
import { alertFromError, type FormAlertState } from '../formAlert';
import { loginSchema, type LoginValues } from '../schemas';

/** The "Log in" view of the auth modal (spec 5.2). */
export function LoginView() {
  const { t } = useTranslation('auth');
  const flow = useAuthFlow();
  const { signIn, sessionEndedReason } = useAuth();
  const fieldError = useFieldError();
  const turnstile = useRef<TurnstileInstance | undefined>(undefined);
  const [token, setToken] = useState<string | null>(null);
  const [turnstileError, setTurnstileError] = useState<string | undefined>();
  const [alert, setAlert] = useState<FormAlertState | null>(null);

  const { register, handleSubmit, setError, formState } = useForm<LoginValues>({
    resolver: zodResolver(loginSchema),
    defaultValues: { email: '', password: '' },
    mode: 'onTouched',
  });

  const mutation = useMutation({
    mutationFn: async (values: LoginValues & { turnstile_token: string }) =>
      signIn(await login(values)),
    onSuccess: (me) => flow.done(me, flow.params.get('next') ?? undefined),
    onError: (error) => {
      // Tokens work once: get a fresh one for the next attempt.
      turnstile.current?.reset();
      setToken(null);
      if (!applyServerFieldErrors(error, setError, ['email', 'password'])) {
        setAlert(alertFromError(error, t));
      }
    },
  });

  const onSubmit = handleSubmit((values) => {
    setAlert(null);
    if (!token) {
      setTurnstileError(t('turnstile.required'));
      return;
    }
    mutation.mutate({ ...values, turnstile_token: token });
  });

  return (
    <div className="flex flex-col gap-5">
      {sessionEndedReason === 'REFRESH_REUSED' && (
        <Alert tone="warning" title={t('login.signed_out_security_title')}>
          {t('login.signed_out_security')}
        </Alert>
      )}
      {flow.params.get('error') === 'google' && <Alert tone="danger">{t('google.failed')}</Alert>}
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
          <ViewLink view="forgot" className="self-end text-sm">
            {t('login.forgot')}
          </ViewLink>
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

      <p className="text-center text-sm text-muted">
        {t('login.no_account')} <ViewLink view="register">{t('login.sign_up')}</ViewLink>
      </p>
    </div>
  );
}
