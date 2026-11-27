import { useMutation } from '@tanstack/react-query';
import { useState } from 'react';
import { useForm, useWatch } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router';
import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { PasswordInput } from '@/components/ui/PasswordInput';
import { alertFromError, type FormAlertState } from '@/features/auth/formAlert';
import { normalizeError } from '@/lib/api/errors';
import { useAuth } from '@/lib/auth/useAuth';
import { applyServerFieldErrors, SERVER_ERROR } from '@/lib/forms/serverErrors';
import { useFieldError } from '@/lib/forms/useFieldError';
import { zodResolver } from '@/lib/forms/zodResolver';
import { changePassword } from '../api';
import { changePasswordSchema, type ChangePasswordValues } from '../schemas';

/**
 * POST /me/password. Every session ends afterwards, this one included, so the
 * local session is cleared and the user goes to the login screen (brief 3a).
 */
export function ChangePasswordForm() {
  const { t } = useTranslation('settings');
  const { clearSession } = useAuth();
  const navigate = useNavigate();
  const fieldError = useFieldError();
  const [alert, setAlert] = useState<FormAlertState | null>(null);
  const { register, control, handleSubmit, setError, formState } = useForm<ChangePasswordValues>({
    resolver: zodResolver(changePasswordSchema),
    defaultValues: { current_password: '', password: '', password_confirmation: '' },
    mode: 'onTouched',
  });
  const password = useWatch({ control, name: 'password' });

  const mutation = useMutation({
    mutationFn: changePassword,
    onSuccess: () => {
      clearSession('PASSWORD_CHANGED');
      void navigate('/login', { replace: true });
    },
    onError: (error) => {
      const apiError = normalizeError(error);
      if (apiError.code === 'INVALID_CREDENTIALS' && apiError.message) {
        setError(
          'current_password',
          { type: SERVER_ERROR, message: apiError.message },
          { shouldFocus: true },
        );
        return;
      }
      const fields = ['current_password', 'password', 'password_confirmation'] as const;
      if (!applyServerFieldErrors(error, setError, fields)) setAlert(alertFromError(error, t));
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
      {alert && (
        <Alert tone={alert.tone} requestId={alert.requestId}>
          {alert.message}
        </Alert>
      )}
      <PasswordInput
        label={t('security.current_password')}
        autoComplete="current-password"
        required
        error={fieldError(formState.errors.current_password)}
        {...register('current_password')}
      />
      <PasswordInput
        label={t('security.new_password')}
        hint={t('security.password_hint')}
        autoComplete="new-password"
        required
        strengthOf={password}
        error={fieldError(formState.errors.password)}
        {...register('password')}
      />
      <PasswordInput
        label={t('security.confirm_password')}
        autoComplete="new-password"
        required
        error={fieldError(formState.errors.password_confirmation)}
        {...register('password_confirmation')}
      />
      <div className="flex justify-end">
        <Button type="submit" loading={mutation.isPending}>
          {t('security.change_password')}
        </Button>
      </div>
    </form>
  );
}
