import type { TurnstileInstance } from '@marsidev/react-turnstile';
import { useMutation } from '@tanstack/react-query';
import { useRef, useState } from 'react';
import { useForm } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { Link } from 'react-router';
import { Alert } from '@/components/ui/Alert';
import { Button, buttonClass } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { applyServerFieldErrors } from '@/lib/forms/serverErrors';
import { useFieldError } from '@/lib/forms/useFieldError';
import { zodResolver } from '@/lib/forms/zodResolver';
import { forgotPassword } from '../api';
import { AuthLayout } from '../components/AuthLayout';
import { TextLink } from '../components/TextLink';
import { TurnstileWidget } from '../components/TurnstileWidget';
import { alertFromError, type FormAlertState } from '../formAlert';
import { forgotPasswordSchema, type ForgotPasswordValues } from '../schemas';

/**
 * /app/forgot-password (spec 5.2). The API always answers "If that email
 * exists, we've sent a link", so accounts can't be discovered; we show its message.
 */
export function ForgotPasswordPage() {
  const { t } = useTranslation('auth');
  const fieldError = useFieldError();
  const turnstile = useRef<TurnstileInstance | undefined>(undefined);
  const [token, setToken] = useState<string | null>(null);
  const [turnstileError, setTurnstileError] = useState<string | undefined>();
  const [alert, setAlert] = useState<FormAlertState | null>(null);

  const { register, handleSubmit, setError, formState } = useForm<ForgotPasswordValues>({
    resolver: zodResolver(forgotPasswordSchema),
    defaultValues: { email: '' },
    mode: 'onTouched',
  });

  const mutation = useMutation({
    mutationFn: forgotPassword,
    onError: (error) => {
      turnstile.current?.reset();
      setToken(null);
      if (!applyServerFieldErrors(error, setError, ['email'])) setAlert(alertFromError(error, t));
    },
  });

  if (mutation.isSuccess) {
    return (
      <AuthLayout title={t('forgot.sent_title')}>
        <div className="flex flex-col gap-4">
          <Alert tone="success">{mutation.data}</Alert>
          <p className="text-sm text-muted">{t('forgot.sent_hint')}</p>
          <Link to="/login" className={buttonClass('primary', 'lg', 'w-full')}>
            {t('forgot.back_to_login')}
          </Link>
        </div>
      </AuthLayout>
    );
  }

  return (
    <AuthLayout
      title={t('forgot.title')}
      description={t('forgot.description')}
      footer={<TextLink to="/login">{t('forgot.back_to_login')}</TextLink>}
    >
      <form
        onSubmit={handleSubmit((values) => {
          setAlert(null);
          if (!token) {
            setTurnstileError(t('turnstile.required'));
            return;
          }
          mutation.mutate({ email: values.email, turnstile_token: token });
        })}
        noValidate
        className="flex flex-col gap-4"
      >
        {alert && (
          <Alert tone={alert.tone} requestId={alert.requestId}>
            {alert.message}
          </Alert>
        )}
        <Input
          label={t('fields.email')}
          type="email"
          autoComplete="email"
          inputMode="email"
          required
          error={fieldError(formState.errors.email)}
          {...register('email')}
        />
        <TurnstileWidget
          ref={turnstile}
          action="forgot_password"
          error={turnstileError}
          onToken={(value) => {
            setToken(value);
            if (value) setTurnstileError(undefined);
          }}
        />
        <Button type="submit" size="lg" fullWidth loading={mutation.isPending}>
          {t('forgot.submit')}
        </Button>
      </form>
    </AuthLayout>
  );
}
