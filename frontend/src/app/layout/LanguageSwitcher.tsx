import * as RadixMenu from '@radix-ui/react-dropdown-menu';
import { Check, ChevronDown } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Flag } from '@/components/icons/Flag';
import { useAuth } from '@/lib/auth/useAuth';
import { useUpdateMe } from '@/lib/auth/useUpdateMe';
import { cn } from '@/lib/cn';
import { isSupportedLanguage, SUPPORTED_LANGUAGES, type Language } from '@/lib/i18n';
import { toastError } from '@/lib/toast';

export interface LanguageSwitcherProps {
  /** 'menu': flag + code pill that opens a list (top bar). 'list': all three side by side (phone menus). */
  variant?: 'menu' | 'list';
  className?: string;
}

/**
 * Language switcher with national flags (brief B1 task 5, spec 13), the same
 * look as the public site's (backend/templates/public/partials/languages.html.twig).
 * Switches the UI at once and, when signed in, saves users.locale (PATCH /me).
 * The menu is a Radix radio menu: arrow keys, typeahead, Esc and focus return.
 */
export function LanguageSwitcher({ variant = 'menu', className }: LanguageSwitcherProps) {
  const { t, i18n } = useTranslation();
  const { status, user } = useAuth();
  const updateMe = useUpdateMe();
  const current: Language = isSupportedLanguage(i18n.language) ? i18n.language : 'en';

  async function change(language: string) {
    if (!isSupportedLanguage(language) || language === current) return;
    await i18n.changeLanguage(language);
    if (status === 'authenticated' && user && user.locale !== language) {
      updateMe.mutate({ locale: language }, { onError: (error) => toastError(error, t) });
    }
  }

  if (variant === 'list') {
    return (
      <div
        role="group"
        aria-label={t('language.label')}
        className={cn('grid grid-cols-3 gap-1.5', className)}
      >
        {SUPPORTED_LANGUAGES.map((language) => (
          <button
            key={language}
            type="button"
            lang={language}
            aria-pressed={language === current}
            onClick={() => void change(language)}
            className={cn(
              'flex min-h-11 items-center justify-center gap-2 rounded-control border px-2 text-sm transition-colors',
              language === current
                ? 'border-fg font-semibold text-fg'
                : 'border-border text-muted hover:bg-surface-2 hover:text-fg',
            )}
          >
            <Flag code={language} />
            <span className="truncate">{t(`language.names.${language}`)}</span>
          </button>
        ))}
      </div>
    );
  }

  return (
    <RadixMenu.Root modal={false}>
      <RadixMenu.Trigger
        aria-label={`${t('language.label')}: ${t(`language.names.${current}`)}`}
        className={cn(
          'group inline-flex h-9 items-center gap-2 rounded-full border border-border bg-bg px-2.5 text-xs font-semibold tracking-[0.04em] text-fg transition-colors hover:bg-surface pointer-coarse:h-11',
          className,
        )}
      >
        <Flag code={current} />
        <span>{current.toUpperCase()}</span>
        <ChevronDown
          aria-hidden="true"
          className="size-3.5 text-muted transition-transform duration-[250ms] group-data-[state=open]:rotate-180"
        />
      </RadixMenu.Trigger>
      <RadixMenu.Portal>
        <RadixMenu.Content
          align="end"
          sideOffset={8}
          collisionPadding={8}
          className="z-80 min-w-44 rounded-card border border-border bg-bg p-1.5 text-fg shadow-popover data-[state=open]:animate-slide-in"
        >
          <RadixMenu.RadioGroup value={current} onValueChange={(value) => void change(value)}>
            {SUPPORTED_LANGUAGES.map((language) => (
              <RadixMenu.RadioItem
                key={language}
                value={language}
                lang={language}
                className="flex min-h-10 cursor-pointer items-center gap-2.5 rounded-control px-2.5 text-sm outline-none select-none data-[highlighted]:bg-surface-2 data-[state=checked]:font-semibold pointer-coarse:min-h-11"
              >
                <Flag code={language} />
                <span>{t(`language.names.${language}`)}</span>
                <RadixMenu.ItemIndicator className="ms-auto">
                  <Check aria-hidden="true" className="size-4" />
                </RadixMenu.ItemIndicator>
              </RadixMenu.RadioItem>
            ))}
          </RadixMenu.RadioGroup>
        </RadixMenu.Content>
      </RadixMenu.Portal>
    </RadixMenu.Root>
  );
}
