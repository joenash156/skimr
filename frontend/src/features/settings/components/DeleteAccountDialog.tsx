import { useMutation } from '@tanstack/react-query';
import { useId, useState } from 'react';
import { useForm } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router';
import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { Dialog } from '@/components/ui/Dialog';
import { PasswordInput } from '@/components/ui/PasswordInput';
import { alertFromError, type FormAlertState } from '@/features/auth/formAlert';
import { normalizeError } from '@/lib/api/errors';
import { useAuth } from '@/lib/auth/useAuth';
import { SERVER_ERROR } from '@/lib/forms/serverErrors';
import { useFieldError } from '@/lib/forms/useFieldError';
import { zodResolver } from '@/lib/forms/zodResolver';
import { deleteAccount } from '../api';
import { deleteAccountSchema, type DeleteAccountValues } from '../schemas';

/**
 * DELETE /me with the password (spec 5.3: 30-day soft delete, then purge).
 * Every session ends, so the local session is cleared and the user goes to login.
 */
export function DeleteAccountDialog({
  open,
  onOpenChange,
}: {
  open: boolean;
  onOpenChange: (open: boolean) => void;
}) {
  const { t } = useTranslation('settings');
  const { clearSession } = useAuth();
  const navigate = useNavigate();
  const fieldError = useFieldError();
  const formId = useId();
  const [alert, setAlert] = useState<FormAlertState | null>(null);
  const { register, handleSubmit, setError, reset, formState } = useForm<DeleteAccountValues>({
    resolver: zodResolver(deleteAccountSchema),
    defaultValues: { password: '' },
  });

  const mutation = useMutation({
    mutationFn: deleteAccount,
    onSuccess: () => {
      clearSession('ACCOUNT_DELETED');
      void navigate('/login', { replace: true });
    },
    onError: (error) => {
      const apiError = normalizeError(error);
      if (apiError.code === 'INVALID_CREDENTIALS' && apiError.message) {
        setError(
          'password',
          { type: SERVER_ERROR, message: apiError.message },
          { shouldFocus: true },
        );
      } else {
        setAlert(alertFromError(error, t));
      }
    },
  });

  function change(next: boolean) {
    if (mutation.isPending) return;
    if (!next) {
      reset();
      setAlert(null);
    }
    onOpenChange(next);
  }

  return (
    <Dialog
      open={open}
      onOpenChange={change}
      title={t('data.delete_dialog_title')}
      description={t('data.delete_dialog_body')}
      size="sm"
      dismissible={!mutation.isPending}
      footer={
        <>
          <Button variant="secondary" onClick={() => change(false)} disabled={mutation.isPending}>
            {t('actions.cancel')}
          </Button>
          <Button type="submit" form={formId} variant="danger" loading={mutation.isPending}>
            {t('data.delete_confirm')}
          </Button>
        </>
      }
    >
      <form
        id={formId}
        noValidate
        onSubmit={handleSubmit((values) => {
          setAlert(null);
          mutation.mutate(values);
        })}
        className="flex flex-col gap-4"
      >
        {alert && (
          <Alert tone={alert.tone} requestId={alert.requestId}>
            {alert.message}
          </Alert>
        )}
        <PasswordInput
          label={t('data.delete_password')}
          autoComplete="current-password"
          required
          error={fieldError(formState.errors.password)}
          {...register('password')}
        />
      </form>
    </Dialog>
  );
}
