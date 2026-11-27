import { useTranslation } from 'react-i18next';
import { LanguageSwitcher } from '@/app/layout/LanguageSwitcher';
import { ThemeSwitcher } from '@/app/layout/ThemeSwitcher';
import { Select } from '@/components/ui/Select';
import type { Me } from '@/lib/auth/me';
import { useAuth } from '@/lib/auth/useAuth';
import { useUpdateMe } from '@/lib/auth/useUpdateMe';
import { toast, toastError } from '@/lib/toast';
import { SettingsCard } from '../components/SettingsCard';

const CITATION_STYLES: readonly Me['citation_style'][] = ['apa7', 'mla9', 'harvard'];

function isCitationStyle(value: string): value is Me['citation_style'] {
  return (CITATION_STYLES as readonly string[]).includes(value);
}

/**
 * Settings > Preferences (spec 5.3): language, theme and citation style.
 * Each one saves as soon as it changes (PATCH /me).
 */
export function PreferencesSettings() {
  const { t } = useTranslation('settings');
  const { user } = useAuth();
  const updateMe = useUpdateMe();
  if (!user) return null;

  function changeStyle(value: string) {
    if (!isCitationStyle(value) || value === user?.citation_style) return;
    updateMe.mutate(
      { citation_style: value },
      {
        onSuccess: () => toast({ tone: 'success', title: t('preferences.saved') }),
        onError: (error) => toastError(error, t),
      },
    );
  }

  return (
    <SettingsCard title={t('preferences.title')} description={t('preferences.description')}>
      <div className="flex flex-col gap-1.5">
        <p className="text-sm font-medium">{t('preferences.language')}</p>
        <LanguageSwitcher className="w-full max-w-xs [&_select]:w-full" />
      </div>
      <ThemeSwitcher
        showLegend
        fullWidth
        className="max-w-sm [&_legend]:text-sm [&_legend]:text-fg"
      />
      <Select
        label={t('preferences.citation_style')}
        hint={t('preferences.citation_hint')}
        value={user.citation_style}
        disabled={updateMe.isPending}
        onChange={(event) => changeStyle(event.target.value)}
        options={CITATION_STYLES.map((style) => ({ value: style, label: t(`citation.${style}`) }))}
        className="max-w-xs"
      />
    </SettingsCard>
  );
}
