import type { TurnstileInstance } from '@marsidev/react-turnstile';
import { useMutation } from '@tanstack/react-query';
import { MailCheck } from 'lucide-react';
import { useRef, useState } from 'react';
import { Controller, useForm, useWatch } from 'react-hook-form';
import { Trans, useTranslation } from 'react-i18next';
import { Alert } from '@/components/ui/Alert';
import { Button, buttonClass } from '@/components/ui/Button';
import { Checkbox } from '@/components/ui/Checkbox';
import { Input } from '@/components/ui/Input';
import { PasswordInput } from '@/components/ui/PasswordInput';
import { PhoneInput } from '@/components/ui/PhoneInput';
import { applyServerFieldErrors } from '@/lib/forms/serverErrors';
import { useFieldError } from '@/lib/forms/useFieldError';
import { zodResolver } from '@/lib/forms/zodResolver';
import { isSupportedLanguage } from '@/lib/i18n';
import { Link } from 'react-router';
import { register as registerAccount } from '../api';
import { AuthLayout } from '../components/AuthLayout';
import { GoogleButton } from '../components/GoogleButton';
import { OrDivider } from '../components/OrDivider';
import { TextLink } from '../components/TextLink';
import { TurnstileWidget } from '../components/TurnstileWidget';
import { alertFromError, type FormAlertState } from '../formAlert';
import { legalUrl } from '../legalLinks';
import { registerSchema, type RegisterValues } from '../schemas';

const FIELDS = [
  'first_name',
  'last_name',
  'other_names',
  'email',
  'phone',
  'password',
  'password_confirmation',
  'accept_terms',
] as const;

/** /app/register (spec 5.2 sign-up fields). */
export function RegisterPage() {
  const { t, i18n } = useTranslation('auth');
  const language = isSupportedLanguage(i18n.language) ? i18n.language : 'en';
  const fieldError = useFieldError();
  const turnstile = useRef<TurnstileInstance | undefined>(undefined);
  const [token, setToken] = useState<string | null>(null);
  const [turnstileError, setTurnstileError] = useState<string | undefined>();
  const [alert, setAlert] = useState<FormAlertState | null>(null);
  const [sentTo, setSentTo] = useState<string | null>(null);

  const { register, control, handleSubmit, setError, formState } = useForm<RegisterValues>({
    resolver: zodResolver(registerSchema),
    defaultValues: {
      first_name: '',
      last_name: '',
      other_names: '',
      email: '',
      phone: '',
      password: '',
      password_confirmation: '',
      accept_terms: false,
    },
    mode: 'onTouched',
  });
  const password = useWatch({ control, name: 'password' });

  const mutation = useMutation({
    mutationFn: registerAccount,
    onSuccess: (user) => setSentTo(user.email),
    onError: (error) => {
      turnstile.current?.reset();
      setToken(null);
      if (!applyServerFieldErrors(error, setError, FIELDS)) {
        setAlert(alertFromError(error, t));
      }
    },
  });

  if (sentTo) {
    return (
      <AuthLayout title={t('register.sent_title')}>
        <div className="flex flex-col items-start gap-4">
          <MailCheck aria-hidden="true" className="size-8 text-primary" />
          <p className="text-sm">
            <Trans
              t={t}
              i18nKey="register.sent_body"
              values={{ email: sentTo }}
              components={{ strong: <strong className="font-semibold" /> }}
            />
          </p>
          <p className="text-sm text-muted">{t('register.sent_next')}</p>
          <Link to="/login" className={buttonClass('primary', 'lg', 'w-full')}>
            {t('register.go_to_login')}
          </Link>
        </div>
      </AuthLayout>
    );
  }

  const onSubmit = handleSubmit((values) => {
    setAlert(null);
    if (!token) {
      setTurnstileError(t('turnstile.required'));
      return;
    }
    mutation.mutate({
      first_name: values.first_name,
      last_name: values.last_name,
      ...(values.other_names ? { other_names: values.other_names } : {}),
      email: values.email,
      ...(values.phone ? { phone: values.phone } : {}),
      password: values.password,
      password_confirmation: values.password_confirmation,
      accept_terms: true,
      locale: language,
      turnstile_token: token,
    });
  });

  return (
    <AuthLayout
      title={t('register.title')}
      description={t('register.description')}
      footer={
        <>
          {t('register.have_account')} <TextLink to="/login">{t('register.log_in')}</TextLink>
        </>
      }
    >
      <div className="flex flex-col gap-5">
        {alert && (
          <Alert tone={alert.tone} requestId={alert.requestId}>
            {alert.message}
          </Alert>
        )}
        <GoogleButton />
        <OrDivider />

        <form onSubmit={onSubmit} noValidate className="flex flex-col gap-4">
          <div className="grid gap-4 sm:grid-cols-2">
            <Input
              label={t('fields.first_name')}
              autoComplete="given-name"
              required
              error={fieldError(formState.errors.first_name)}
              {...register('first_name')}
            />
            <Input
              label={t('fields.last_name')}
              autoComplete="family-name"
              required
              error={fieldError(formState.errors.last_name)}
              {...register('last_name')}
            />
          </div>
          <Input
            label={t('fields.other_names')}
            hint={t('fields.optional')}
            autoComplete="additional-name"
            error={fieldError(formState.errors.other_names)}
            {...register('other_names')}
          />
          <Input
            label={t('fields.email')}
            type="email"
            autoComplete="email"
            inputMode="email"
            required
            error={fieldError(formState.errors.email)}
            {...register('email')}
          />
          <Controller
            control={control}
            name="phone"
            render={({ field, fieldState }) => (
              <PhoneInput
                label={t('fields.phone')}
                hint={t('fields.optional')}
                name={field.name}
                value={field.value ?? ''}
                onChange={field.onChange}
                onBlur={field.onBlur}
                error={fieldError(fieldState.error)}
              />
            )}
          />
          <PasswordInput
            label={t('fields.password')}
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
          <Checkbox
            label={
              <Trans
                t={t}
                i18nKey="register.accept_terms"
                components={{
                  terms: (
                    <a
                      href={legalUrl('terms', language)}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="font-medium text-primary underline-offset-4 hover:underline"
                    />
                  ),
                  privacy: (
                    <a
                      href={legalUrl('privacy', language)}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="font-medium text-primary underline-offset-4 hover:underline"
                    />
                  ),
                }}
              />
            }
            required
            error={fieldError(formState.errors.accept_terms)}
            {...register('accept_terms')}
          />
          <TurnstileWidget
            ref={turnstile}
            action="register"
            error={turnstileError}
            onToken={(value) => {
              setToken(value);
              if (value) setTurnstileError(undefined);
            }}
          />
          <Button type="submit" size="lg" fullWidth loading={mutation.isPending}>
            {t('register.submit')}
          </Button>
        </form>
      </div>
    </AuthLayout>
  );
}
