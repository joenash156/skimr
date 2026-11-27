import { useTranslation } from 'react-i18next';
import { Badge } from '@/components/ui/Badge';
import { GoogleMark } from '@/components/brand/GoogleMark';
import { useAuth } from '@/lib/auth/useAuth';
import { ChangeEmailForm } from '../components/ChangeEmailForm';
import { ChangePasswordForm } from '../components/ChangePasswordForm';
import { SetPasswordFirstNotice } from '../components/SetPasswordFirstNotice';
import { SettingsCard } from '../components/SettingsCard';

/**
 * Settings > Security (spec 5.3): change password, change email, the
 * connected Google account. Changing the password also signs out every
 * device (spec 11.1).
 */
export function SecuritySettings() {
  const { t } = useTranslation('settings');
  const { user } = useAuth();
  if (!user) return null;

  return (
    <div className="flex flex-col gap-6">
      <SettingsCard
        title={t('security.password_title')}
        description={t('security.password_description')}
      >
        {user.has_password ? (
          <ChangePasswordForm />
        ) : (
          <SetPasswordFirstNotice message={t('security.google_only_password')} />
        )}
      </SettingsCard>

      <SettingsCard title={t('security.email_title')} description={t('security.email_description')}>
        {user.has_password ? (
          <ChangeEmailForm currentEmail={user.email} />
        ) : (
          <SetPasswordFirstNotice message={t('security.google_only_email')} />
        )}
      </SettingsCard>

      <SettingsCard
        title={t('security.google_title')}
        description={t('security.google_description')}
      >
        <div className="flex items-center gap-3">
          <span className="flex size-9 items-center justify-center rounded-full border border-border bg-bg">
            <GoogleMark />
          </span>
          <span className="flex-1 text-sm font-medium">{t('security.google_name')}</span>
          {user.google_linked ? (
            <Badge tone="success">{t('security.google_connected')}</Badge>
          ) : (
            <Badge>{t('security.google_not_connected')}</Badge>
          )}
        </div>
        {!user.google_linked && <p className="text-sm text-muted">{t('security.google_how')}</p>}
      </SettingsCard>
    </div>
  );
}
