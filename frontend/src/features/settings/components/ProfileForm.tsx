import { useState } from 'react';
import { Controller, useForm } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { Alert } from '@/components/ui/Alert';
import { Avatar } from '@/components/ui/Avatar';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { PhoneInput } from '@/components/ui/PhoneInput';
import { alertFromError, type FormAlertState } from '@/features/auth/formAlert';
import type { Me, UpdateMeBody } from '@/lib/auth/me';
import { useUpdateMe } from '@/lib/auth/useUpdateMe';
import { applyServerFieldErrors } from '@/lib/forms/serverErrors';
import { useFieldError } from '@/lib/forms/useFieldError';
import { zodResolver } from '@/lib/forms/zodResolver';
import { toast } from '@/lib/toast';
import { SettingsCard } from './SettingsCard';
import { profileSchema, type ProfileValues } from '../schemas';

const FIELDS = [
  'first_name',
  'last_name',
  'other_names',
  'phone',
  'alt_phone',
  'institution',
  'discipline',
] as const;

function toValues(user: Me): ProfileValues {
  return {
    first_name: user.first_name,
    last_name: user.last_name,
    other_names: user.other_names ?? '',
    phone: user.phone ?? '',
    alt_phone: user.alt_phone ?? '',
    institution: user.institution ?? '',
    discipline: user.discipline ?? '',
  };
}

/** Only the changed fields; empty optional fields are sent as null (cleared). */
export function changedFields(
  values: ProfileValues,
  dirty: Partial<Record<keyof ProfileValues, boolean | undefined>>,
): UpdateMeBody {
  const body: UpdateMeBody = {};
  if (dirty.first_name) body.first_name = values.first_name.trim();
  if (dirty.last_name) body.last_name = values.last_name.trim();
  if (dirty.other_names) body.other_names = values.other_names?.trim() || null;
  if (dirty.phone) body.phone = values.phone || null;
  if (dirty.alt_phone) body.alt_phone = values.alt_phone || null;
  if (dirty.institution) body.institution = values.institution?.trim() || null;
  if (dirty.discipline) body.discipline = values.discipline?.trim() || null;
  return body;
}

/** The profile form; remounted (by key) whenever the saved user changes. */
export function ProfileForm({ user }: { user: Me }) {
  const { t } = useTranslation('settings');
  const fieldError = useFieldError();
  const updateMe = useUpdateMe();
  const [alert, setAlert] = useState<FormAlertState | null>(null);
  const { register, control, handleSubmit, setError, reset, formState } = useForm<ProfileValues>({
    resolver: zodResolver(profileSchema),
    defaultValues: toValues(user),
    mode: 'onTouched',
  });
  const name = `${user.first_name} ${user.last_name}`;

  const onSubmit = handleSubmit(async (values) => {
    setAlert(null);
    const body = changedFields(values, formState.dirtyFields);
    if (Object.keys(body).length === 0) return;
    try {
      const saved = await updateMe.mutateAsync(body);
      reset(toValues(saved));
      toast({ tone: 'success', title: t('profile.saved') });
    } catch (error) {
      if (!applyServerFieldErrors(error, setError, FIELDS)) setAlert(alertFromError(error, t));
    }
  });

  return (
    <SettingsCard title={t('profile.title')} description={t('profile.description')}>
      <div className="flex items-center gap-4">
        <Avatar name={name} src={user.avatar_url} size="lg" />
        <div className="min-w-0">
          <p className="truncate font-medium">{name}</p>
          <p className="truncate text-sm text-muted">{user.email}</p>
        </div>
      </div>

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
        <div className="grid gap-4 sm:grid-cols-2">
          {(['phone', 'alt_phone'] as const).map((field) => (
            <Controller
              key={field}
              control={control}
              name={field}
              render={({ field: f, fieldState }) => (
                <PhoneInput
                  label={t(`fields.${field}`)}
                  hint={t('fields.optional')}
                  name={f.name}
                  value={f.value ?? ''}
                  onChange={f.onChange}
                  onBlur={f.onBlur}
                  error={fieldError(fieldState.error)}
                />
              )}
            />
          ))}
        </div>
        <div className="grid gap-4 sm:grid-cols-2">
          <Input
            label={t('fields.institution')}
            hint={t('fields.optional')}
            autoComplete="organization"
            error={fieldError(formState.errors.institution)}
            {...register('institution')}
          />
          <Input
            label={t('fields.discipline')}
            hint={t('fields.optional')}
            error={fieldError(formState.errors.discipline)}
            {...register('discipline')}
          />
        </div>
        <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
          <Button
            variant="secondary"
            disabled={!formState.isDirty || updateMe.isPending}
            onClick={() => {
              setAlert(null);
              reset(toValues(user));
            }}
          >
            {t('actions.discard')}
          </Button>
          <Button type="submit" loading={updateMe.isPending} disabled={!formState.isDirty}>
            {t('actions.save')}
          </Button>
        </div>
      </form>
    </SettingsCard>
  );
}
