import type { ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { LanguageSwitcher } from '@/app/layout/LanguageSwitcher';
import { ThemeSwitcher } from '@/app/layout/ThemeSwitcher';
import { Logo } from '@/components/brand/Logo';
import { isSupportedLanguage } from '@/lib/i18n';
import { AuthBackdrop } from './AuthBackdrop';

export interface AuthLayoutProps {
  title: string;
  description?: ReactNode;
  children: ReactNode;
  /** Under the card, e.g. "Use a different account". */
  footer?: ReactNode;
}

/**
 * The frame for the signed-out screens that are pages, not the modal: verify
 * email, reset password, accept invite, complete profile (emailed links land
 * here). Same card as the auth modal (features/auth/modal/AuthModal.tsx) on the
 * same dot-grid backdrop, with the logo (back to the public site), language
 * and theme above it.
 */
export function AuthLayout({ title, description, children, footer }: AuthLayoutProps) {
  const { t, i18n } = useTranslation();
  const lang = isSupportedLanguage(i18n.language) ? i18n.language : 'en';
  return (
    <div className="relative isolate flex min-h-dvh flex-col text-fg">
      <AuthBackdrop />
      <header className="flex h-16 items-center justify-between gap-3 px-4 sm:px-6">
        <a href={`/${lang}/`} className="rounded-control" aria-label={t('auth_layout.home')}>
          <Logo />
        </a>
        <div className="flex items-center gap-2">
          <LanguageSwitcher />
          <ThemeSwitcher className="hidden sm:block" />
        </div>
      </header>

      <main className="flex flex-1 flex-col items-center px-4 pt-4 pb-12 sm:justify-center sm:pt-0">
        <div className="w-full max-w-106 animate-modal-in">
          <div className="rounded-[1.25rem] border border-border bg-bg px-6 pt-7 pb-6 shadow-[0_24px_64px_-16px_rgb(0_0_0/0.18)] sm:px-8 sm:pt-8">
            <div className="flex flex-col items-center text-center">
              <span className="flex size-12 items-center justify-center rounded-2xl border border-border bg-surface">
                <Logo markOnly />
              </span>
              <h1 className="mt-4 font-script text-[1.625rem] leading-[1.65] font-normal">
                {title}
              </h1>
              {description && <p className="mt-1.5 max-w-88 text-sm text-muted">{description}</p>}
            </div>
            <div className="mt-6">{children}</div>
          </div>
          {footer && <div className="mt-6 text-center text-sm text-muted">{footer}</div>}
        </div>
      </main>
    </div>
  );
}
