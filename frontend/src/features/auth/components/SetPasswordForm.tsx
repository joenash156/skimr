import { useState } from 'react';
import { useForm, useWatch } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { PasswordInput } from '@/components/ui/PasswordInput';
import { applyServerFieldErrors } from '@/lib/forms/serverErrors';
import { useFieldError } from '@/lib/forms/useFieldError';
import { zodResolver } from '@/lib/forms/zodResolver';
import { alertFromError, type FormAlertState } from '../formAlert';
import { setPasswordSchema, type SetPasswordValues } from '../schemas';

export interface SetPasswordFormProps {
  submitLabel: string;
  pending: boolean;
  /** Resolve on success; throw the API error otherwise. */
  onSubmit: (values: SetPasswordValues) => Promise<unknown>;
}

/** New password + confirmation with the strength meter (reset password, accept invite). */
export function SetPasswordForm({ submitLabel, pending, onSubmit }: SetPasswordFormProps) {
  const { t } = useTranslation('auth');
  const fieldError = useFieldError();
  const [alert, setAlert] = useState<FormAlertState | null>(null);
  const { register, control, handleSubmit, setError, formState } = useForm<SetPasswordValues>({
    resolver: zodResolver(setPasswordSchema),
    defaultValues: { password: '', password_confirmation: '' },
    mode: 'onTouched',
  });
  const password = useWatch({ control, name: 'password' });

  return (
    <form
      onSubmit={handleSubmit(async (values) => {
        setAlert(null);
        try {
          await onSubmit(values);
        } catch (error) {
          if (!applyServerFieldErrors(error, setError, ['password', 'password_confirmation'])) {
            setAlert(alertFromError(error, t));
          }
        }
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
        label={t('fields.new_password')}
        hint={t('fields.password_hint')}
        autoComplete="new-password"
        required
        strengthOf={password}
        error={fieldError(formState.errors.password)}
        {...register('password')}
      />
      <PasswordInput
        label={t('fields.password_confirmation')}
        autoComplete="new-password"
        required
        error={fieldError(formState.errors.password_confirmation)}
        {...register('password_confirmation')}
      />
      <Button type="submit" size="lg" fullWidth loading={pending}>
        {submitLabel}
      </Button>
    </form>
  );
}
