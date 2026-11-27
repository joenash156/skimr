import { useMutation } from '@tanstack/react-query';
import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { PasswordInput } from '@/components/ui/PasswordInput';
import { alertFromError, type FormAlertState } from '@/features/auth/formAlert';
import { normalizeError } from '@/lib/api/errors';
import { applyServerFieldErrors, SERVER_ERROR } from '@/lib/forms/serverErrors';
import { useFieldError } from '@/lib/forms/useFieldError';
import { zodResolver } from '@/lib/forms/zodResolver';
import { changeEmail } from '../api';
import { changeEmailSchema, type ChangeEmailValues } from '../schemas';

/**
 * POST /me/email (spec 5.3: re-verification required). Nothing changes until
 * the link sent to the new address is opened; it lands on /app/verify-email.
 * Meanwhile the API's 202 message is shown (brief 3a).
 */
export function ChangeEmailForm({ currentEmail }: { currentEmail: string }) {
  const { t } = useTranslation('settings');
  const fieldError = useFieldError();
  const [alert, setAlert] = useState<FormAlertState | null>(null);
  const { register, handleSubmit, setError, reset, formState } = useForm<ChangeEmailValues>({
    resolver: zodResolver(changeEmailSchema),
    defaultValues: { email: '', password: '' },
    mode: 'onTouched',
  });

  const mutation = useMutation({
    mutationFn: changeEmail,
    onSuccess: () => reset(),
    onError: (error) => {
      const apiError = normalizeError(error);
      if (apiError.code === 'INVALID_CREDENTIALS' && apiError.message) {
        setError(
          'password',
          { type: SERVER_ERROR, message: apiError.message },
          { shouldFocus: true },
        );
        return;
      }
      if (!applyServerFieldErrors(error, setError, ['email', 'password'])) {
        setAlert(alertFromError(error, t));
      }
    },
  });

  return (
    <form
      onSubmit={handleSubmit((values) => {
        setAlert(null);
        mutation.mutate(values);
      })}
      noValidate
      className="flex flex-col gap-4"
    >
      <p className="text-sm">
        {t('security.current_email')} <span className="font-medium">{currentEmail}</span>
      </p>
      {mutation.isSuccess && <Alert tone="success">{mutation.data}</Alert>}
      {alert && (
        <Alert tone={alert.tone} requestId={alert.requestId}>
          {alert.message}
        </Alert>
      )}
      <Input
        label={t('security.new_email')}
        type="email"
        autoComplete="email"
        inputMode="email"
        required
        error={fieldError(formState.errors.email)}
        {...register('email')}
      />
      <PasswordInput
        label={t('security.confirm_with_password')}
        autoComplete="current-password"
        required
        error={fieldError(formState.errors.password)}
        {...register('password')}
      />
      <div className="flex justify-end">
        <Button type="submit" variant="secondary" loading={mutation.isPending}>
          {t('security.send_link')}
        </Button>
      </div>
    </form>
  );
}
