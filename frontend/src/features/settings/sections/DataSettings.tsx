import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/Button';
import { useAuth } from '@/lib/auth/useAuth';
import { DeleteAccountDialog } from '../components/DeleteAccountDialog';
import { SetPasswordFirstNotice } from '../components/SetPasswordFirstNotice';
import { SettingsCard } from '../components/SettingsCard';

/**
 * Settings > Data (spec 5.3): delete my account. "Export all my data" joins
 * this page once GET /me/export is in the API contract (P2).
 */
export function DataSettings() {
  const { t } = useTranslation('settings');
  const { user } = useAuth();
  const [open, setOpen] = useState(false);
  if (!user) return null;

  return (
    <SettingsCard
      title={t('data.delete_title')}
      description={t('data.delete_description')}
      tone="danger"
    >
      {user.has_password ? (
        <div>
          <Button variant="danger" onClick={() => setOpen(true)}>
            {t('data.delete_button')}
          </Button>
          <DeleteAccountDialog open={open} onOpenChange={setOpen} />
        </div>
      ) : (
        <SetPasswordFirstNotice message={t('data.google_only')} />
      )}
    </SettingsCard>
  );
}
