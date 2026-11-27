import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Switch } from '@/components/ui/Switch';
import type { components } from '@/lib/api/schema';
import { useAuth } from '@/lib/auth/useAuth';
import { useUpdateMe } from '@/lib/auth/useUpdateMe';
import { toastError } from '@/lib/toast';
import { SettingsCard } from '../components/SettingsCard';

type Prefs = components['schemas']['NotificationPrefs'];
type PrefKey = keyof Prefs;

/** The optional email types (contract NotificationPrefs, spec 15.2). */
const TYPES: readonly PrefKey[] = [
  'processing_done',
  'report_ready',
  'project_invite',
  'weekly_digest',
];

/**
 * Settings > Notifications (spec 5.3): an email switch per type. Each switch
 * saves on its own (PATCH /me). Security emails can't be turned off.
 */
export function NotificationSettings() {
  const { t } = useTranslation('settings');
  const { user } = useAuth();
  const updateMe = useUpdateMe();
  const [changing, setChanging] = useState<PrefKey | null>(null);
  if (!user) return null;
  // Missing keys fall back to their defaults (spec 15.2).
  const prefs: Required<Prefs> = {
    processing_done: user.notification_prefs.processing_done ?? true,
    report_ready: user.notification_prefs.report_ready ?? true,
    project_invite: user.notification_prefs.project_invite ?? true,
    weekly_digest: user.notification_prefs.weekly_digest ?? false,
  };

  return (
    <SettingsCard title={t('notifications.title')} description={t('notifications.description')}>
      <ul className="flex flex-col divide-y divide-border">
        {TYPES.map((key) => (
          <li key={key} className="py-3 first:pt-0 last:pb-0">
            <Switch
              label={t(`notifications.types.${key}.label`)}
              description={t(`notifications.types.${key}.description`)}
              checked={prefs[key]}
              disabled={changing === key}
              onCheckedChange={(checked) => {
                setChanging(key);
                // The whole set, with this one switch changed (the contract types all four).
                updateMe.mutate(
                  { notification_prefs: { ...prefs, [key]: checked } },
                  {
                    onError: (error) => toastError(error, t),
                    onSettled: () => setChanging(null),
                  },
                );
              }}
            />
          </li>
        ))}
      </ul>
      <p className="text-xs text-muted">{t('notifications.security_note')}</p>
    </SettingsCard>
  );
}
