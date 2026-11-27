import { useState } from 'react';
import { Controller, useForm } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router';
import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { PhoneInput } from '@/components/ui/PhoneInput';
import { useAuth } from '@/lib/auth/useAuth';
import { useUpdateMe } from '@/lib/auth/useUpdateMe';
import { applyServerFieldErrors } from '@/lib/forms/serverErrors';
import { useFieldError } from '@/lib/forms/useFieldError';
import { zodResolver } from '@/lib/forms/zodResolver';
import { AuthLayout } from '../components/AuthLayout';
import { alertFromError, type FormAlertState } from '../formAlert';
import { completeProfileSchema, type CompleteProfileValues } from '../schemas';

/**
 * /app/complete-profile: a new Google account confirms its name, and may add
 * a phone (optional, spec 5.2). AuthProvider already refreshed on load.
 */
export function CompleteProfilePage() {
  const { t } = useTranslation('auth');
  const { user } = useAuth();
  const navigate = useNavigate();
  const fieldError = useFieldError();
  const updateMe = useUpdateMe();
  const [alert, setAlert] = useState<FormAlertState | null>(null);

  const { register, control, handleSubmit, setError, formState } = useForm<CompleteProfileValues>({
    resolver: zodResolver(completeProfileSchema),
    defaultValues: {
      first_name: user?.first_name ?? '',
      last_name: user?.last_name ?? '',
      other_names: user?.other_names ?? '',
      phone: user?.phone ?? '',
    },
    mode: 'onTouched',
  });

  const onSubmit = handleSubmit(async (values) => {
    setAlert(null);
    try {
      await updateMe.mutateAsync({
        first_name: values.first_name,
        last_name: values.last_name,
        other_names: values.other_names ? values.other_names : null,
        phone: values.phone ? values.phone : null,
      });
      void navigate('/', { replace: true });
    } catch (error) {
      if (
        !applyServerFieldErrors(error, setError, [
          'first_name',
          'last_name',
          'other_names',
          'phone',
        ])
      ) {
        setAlert(alertFromError(error, t));
      }
    }
  });

  return (
    <AuthLayout title={t('complete_profile.title')} description={t('complete_profile.description')}>
      <form onSubmit={onSubmit} noValidate className="flex flex-col gap-4">
        {alert && (
          <Alert tone={alert.tone} requestId={alert.requestId}>
            {alert.message}
          </Alert>
        )}
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
        <Button type="submit" size="lg" fullWidth loading={updateMe.isPending}>
          {t('complete_profile.submit')}
        </Button>
      </form>
    </AuthLayout>
  );
}
