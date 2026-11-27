import { ChevronDown, Languages } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { useAuth } from '@/lib/auth/useAuth';
import { useUpdateMe } from '@/lib/auth/useUpdateMe';
import { cn } from '@/lib/cn';
import { isSupportedLanguage, SUPPORTED_LANGUAGES } from '@/lib/i18n';
import { toastError } from '@/lib/toast';

/**
 * Language switcher (brief B1 task 5, spec 13): switches the UI at once and,
 * when signed in, saves users.locale with PATCH /me.
 */
export function LanguageSwitcher({ className }: { className?: string }) {
  const { t, i18n } = useTranslation();
  const { status, user } = useAuth();
  const updateMe = useUpdateMe();
  const current = isSupportedLanguage(i18n.language) ? i18n.language : 'en';

  async function change(language: string) {
    if (!isSupportedLanguage(language) || language === current) return;
    await i18n.changeLanguage(language);
    if (status === 'authenticated' && user && user.locale !== language) {
      updateMe.mutate({ locale: language }, { onError: (error) => toastError(error, t) });
    }
  }

  return (
    <div className={cn('relative', className)}>
      <Languages
        aria-hidden="true"
        className="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-muted"
      />
      <select
        aria-label={t('language.label')}
        value={current}
        onChange={(event) => void change(event.target.value)}
        className="h-11 cursor-pointer appearance-none rounded-control border border-border bg-bg ps-9 pe-8 text-sm text-fg hover:bg-surface-2"
      >
        {SUPPORTED_LANGUAGES.map((language) => (
          <option key={language} value={language} lang={language}>
            {t(`language.names.${language}`)}
          </option>
        ))}
      </select>
      <ChevronDown
        aria-hidden="true"
        className="pointer-events-none absolute end-2.5 top-1/2 size-4 -translate-y-1/2 text-muted"
      />
    </div>
  );
}
