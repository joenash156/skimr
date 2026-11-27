import type { ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { LanguageSwitcher } from '@/app/layout/LanguageSwitcher';
import { ThemeSwitcher } from '@/app/layout/ThemeSwitcher';
import { Logo } from '@/components/brand/Logo';
import { isSupportedLanguage } from '@/lib/i18n';

export interface AuthLayoutProps {
  title: string;
  description?: ReactNode;
  children: ReactNode;
  /** Under the card, e.g. "Don't have an account? Sign up". */
  footer?: ReactNode;
}

/**
 * The frame for every signed-out screen: logo (back to the public site),
 * language and theme controls, one centred card. Full width on phones.
 */
export function AuthLayout({ title, description, children, footer }: AuthLayoutProps) {
  const { t, i18n } = useTranslation();
  const lang = isSupportedLanguage(i18n.language) ? i18n.language : 'en';
  return (
    <div className="flex min-h-dvh flex-col bg-bg text-fg">
      <header className="flex h-14 items-center justify-between gap-3 px-4 sm:px-6">
        <a href={`/${lang}/`} className="rounded-control" aria-label={t('auth_layout.home')}>
          <Logo />
        </a>
        <div className="flex items-center gap-2">
          <LanguageSwitcher />
          <ThemeSwitcher />
        </div>
      </header>

      <main className="flex flex-1 flex-col items-center px-4 pt-6 pb-12 sm:justify-center sm:pt-0">
        <div className="w-full max-w-[26rem]">
          <div className="rounded-card border-border sm:border sm:bg-surface sm:p-8">
            <h1 className="text-2xl">{title}</h1>
            {description && <p className="mt-2 text-sm text-muted">{description}</p>}
            <div className="mt-6">{children}</div>
          </div>
          {footer && <div className="mt-6 text-center text-sm text-muted">{footer}</div>}
        </div>
      </main>
    </div>
  );
}
