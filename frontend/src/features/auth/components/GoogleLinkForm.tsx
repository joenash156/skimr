import { useMutation } from '@tanstack/react-query';
import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { PasswordInput } from '@/components/ui/PasswordInput';
import { normalizeError } from '@/lib/api/errors';
import { useAuth } from '@/lib/auth/useAuth';
import { SERVER_ERROR } from '@/lib/forms/serverErrors';
import { useFieldError } from '@/lib/forms/useFieldError';
import { zodResolver } from '@/lib/forms/zodResolver';
import { linkGoogle } from '../api';
import { alertFromError, type FormAlertState } from '../formAlert';
import { useAuthFlow } from '../flow/authFlow';
import { ViewLink } from '../flow/ViewLink';
import { googleLinkSchema, type GoogleLinkValues } from '../schemas';
import { GoogleButton } from './GoogleButton';

/**
 * /app/login?link=google (spec 5.2, brief 3a): a password account already uses
 * this Google email, so the password is required once to link them. Shown in
 * the auth modal (AuthScreen gives it the google_link title).
 */
export function GoogleLinkForm() {
  const { t } = useTranslation('auth');
  const { signIn } = useAuth();
  const flow = useAuthFlow();
  const fieldError = useFieldError();
  const [alert, setAlert] = useState<FormAlertState | null>(null);
  const [expired, setExpired] = useState(false);

  const { register, handleSubmit, setError, formState } = useForm<GoogleLinkValues>({
    resolver: zodResolver(googleLinkSchema),
    defaultValues: { password: '' },
  });

  const mutation = useMutation({
    mutationFn: async ({ password }: GoogleLinkValues) => signIn(await linkGoogle(password)),
    onSuccess: (me) => flow.done(me),
    onError: (error) => {
      const apiError = normalizeError(error);
      if (apiError.code === 'INVALID_TOKEN') {
        setExpired(true); // the 10-minute window passed: start Google again
      } else if (apiError.code === 'INVALID_CREDENTIALS' && apiError.message) {
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

  return (
    <div className="flex flex-col gap-5">
      {expired ? (
        <div className="flex flex-col gap-4">
          <Alert tone="warning">{t('google_link.expired')}</Alert>
          <GoogleButton />
        </div>
      ) : (
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
            label={t('fields.password')}
            autoComplete="current-password"
            required
            autoFocus
            error={fieldError(formState.errors.password)}
            {...register('password')}
          />
          <Button type="submit" size="lg" fullWidth loading={mutation.isPending}>
            {t('google_link.submit')}
          </Button>
          <ViewLink view="forgot" className="self-center text-sm">
            {t('login.forgot')}
          </ViewLink>
        </form>
      )}
      <p className="text-center text-sm">
        <ViewLink view="login">{t('google_link.cancel')}</ViewLink>
      </p>
    </div>
  );
}
