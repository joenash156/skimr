import type { TurnstileInstance } from '@marsidev/react-turnstile';
import { useMutation } from '@tanstack/react-query';
import { useRef, useState } from 'react';
import { useForm } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { applyServerFieldErrors } from '@/lib/forms/serverErrors';
import { useFieldError } from '@/lib/forms/useFieldError';
import { zodResolver } from '@/lib/forms/zodResolver';
import { forgotPassword } from '../api';
import { TurnstileWidget } from '../components/TurnstileWidget';
import { useAuthFlow } from '../flow/authFlow';
import { alertFromError, type FormAlertState } from '../formAlert';
import { useAuthModal } from '../modal/AuthModal';
import { forgotPasswordSchema, type ForgotPasswordValues } from '../schemas';

/**
 * The "Forgot password" view (spec 5.2). The API always answers "If that email
 * exists, we've sent a link", so accounts can't be discovered; we show its message.
 */
export function ForgotPasswordView() {
  const { t } = useTranslation('auth');
  const flow = useAuthFlow();
  const modal = useAuthModal();
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
    onSuccess: () => modal.showDone({ title: t('forgot.sent_title') }),
    onError: (error) => {
      turnstile.current?.reset();
      setToken(null);
      if (!applyServerFieldErrors(error, setError, ['email'])) setAlert(alertFromError(error, t));
    },
  });

  if (mutation.isSuccess) {
    return (
      <div className="flex flex-col gap-4">
        <Alert tone="success">{mutation.data}</Alert>
        <p className="text-center text-sm text-muted">{t('forgot.sent_hint')}</p>
        <Button size="lg" fullWidth onClick={() => flow.go('login')}>
          {t('forgot.back_to_login')}
        </Button>
      </div>
    );
  }

  return (
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
  );
}
