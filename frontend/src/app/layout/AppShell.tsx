import { useTranslation } from 'react-i18next';
import { useAuth } from '@/lib/auth/useAuth';
import { ThemeSwitcher } from './ThemeSwitcher';

/**
 * B0 stub shell: a 56 px top bar and a content area capped at 1280 px
 * (spec 14.3). B1 replaces it with the real AppShell (sidebar, topbar, drawer).
 */
export function AppShell() {
  const { t } = useTranslation();
  const { status, user, retry } = useAuth();

  return (
    <div className="flex min-h-dvh flex-col bg-bg text-fg">
      <header className="sticky top-0 z-10 flex h-14 items-center justify-between border-b border-border bg-bg px-4">
        <span className="text-base font-semibold tracking-tight">{t('app.name')}</span>
        <ThemeSwitcher />
      </header>

      <main className="mx-auto w-full max-w-[1280px] flex-1 px-4 py-8 sm:px-6">
        <section className="rounded-card border border-border bg-surface p-6">
          <h1 className="text-2xl">{t('shell.stub_title')}</h1>
          <p className="mt-2 text-muted">{t('shell.stub_body')}</p>

          <p className="mt-6 text-sm" role="status" aria-live="polite">
            {status === 'loading' && <span className="text-muted">{t('shell.loading')}</span>}
            {status === 'authenticated' && user && (
              <span>
                {t('shell.signed_in_as', { name: `${user.first_name} ${user.last_name}` })}
              </span>
            )}
            {status === 'unauthenticated' && (
              <span className="text-muted">{t('shell.signed_out')}</span>
            )}
            {status === 'error' && (
              <span className="flex flex-wrap items-center gap-3">
                <span className="text-danger">{t('errors.unknown')}</span>
                <button
                  type="button"
                  onClick={retry}
                  className="min-h-11 rounded-control border border-border px-3 font-medium hover:bg-surface-2"
                >
                  {t('actions.retry')}
                </button>
              </span>
            )}
          </p>
        </section>
      </main>
    </div>
  );
}
