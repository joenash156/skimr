import { LogOut } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Link, Outlet, useNavigate } from 'react-router';
import { Logo } from '@/components/brand/Logo';
import { Button } from '@/components/ui/Button';
import { useAuth } from '@/lib/auth/useAuth';
import { LanguageSwitcher } from './LanguageSwitcher';
import { ThemeSwitcher } from './ThemeSwitcher';

/**
 * INTERIM shell (B1, before task 2): top bar + content capped at 1280 px
 * (spec 14.3). Task 2 replaces it with the real AppShell (collapsible sidebar,
 * topbar, drawer) built from Nana's reference sample.
 */
export function AppShell() {
  const { t } = useTranslation();
  const { user, signOut } = useAuth();
  const navigate = useNavigate();

  async function logOut() {
    await signOut();
    void navigate('/login', { replace: true });
  }

  return (
    <div className="flex min-h-dvh flex-col bg-bg text-fg">
      <header className="sticky top-0 z-10 flex h-14 items-center justify-between gap-3 border-b border-border bg-bg px-4">
        <Link to="/" className="rounded-control" aria-label={t('nav.overview')}>
          <Logo />
        </Link>
        <div className="flex items-center gap-2">
          {user && (
            <span className="hidden text-sm text-muted md:inline">
              {t('shell.signed_in_as', { name: `${user.first_name} ${user.last_name}` })}
            </span>
          )}
          <LanguageSwitcher />
          <ThemeSwitcher />
          <Button
            variant="secondary"
            onClick={() => void logOut()}
            aria-label={t('session.log_out')}
          >
            <LogOut aria-hidden="true" className="size-4" />
            <span className="hidden sm:inline">{t('session.log_out')}</span>
          </Button>
        </div>
      </header>

      <main className="mx-auto w-full max-w-[1280px] flex-1 px-4 py-8 sm:px-6">
        <Outlet />
      </main>
    </div>
  );
}
