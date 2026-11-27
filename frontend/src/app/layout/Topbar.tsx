import { Bell, Menu, Search } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Link, useLocation } from 'react-router';
import { Logo } from '@/components/brand/Logo';
import { useAuth } from '@/lib/auth/useAuth';
import { AccountMenu } from './AccountMenu';
import { GlobalSearch } from './GlobalSearch';
import { IconLink } from './IconLink';
import { LanguageSwitcher } from './LanguageSwitcher';
import { isCurrent, visibleNav } from './navigation';
import { ThemeSwitcher } from './ThemeSwitcher';

export interface TopbarProps {
  drawerOpen: boolean;
  onOpenMenu: () => void;
}

/**
 * The 56 px top bar (spec 5.3, 14.3): global search, language switcher,
 * theme toggle, notifications bell and avatar menu. Below 1024 px it also
 * holds the menu button for the drawer and the current page's name (sample).
 */
export function Topbar({ drawerOpen, onOpenMenu }: TopbarProps) {
  const { t } = useTranslation();
  const { user } = useAuth();
  const { pathname } = useLocation();
  const current = visibleNav(user?.role)
    .flatMap((group) => group.items)
    .find((item) => isCurrent(pathname, item));

  return (
    <header className="sticky top-0 z-30 flex h-14 shrink-0 items-center gap-2 border-b border-border bg-bg px-2 sm:px-4">
      <button
        type="button"
        onClick={onOpenMenu}
        aria-label={t('nav.open_menu')}
        aria-expanded={drawerOpen}
        aria-controls="app-drawer"
        className="inline-flex size-11 shrink-0 items-center justify-center rounded-control text-fg hover:bg-surface-2 lg:hidden"
      >
        <Menu aria-hidden="true" className="size-5" />
      </button>
      <Link
        to="/"
        aria-label={t('nav.overview')}
        className="flex min-w-0 items-center gap-2 rounded-control lg:hidden"
      >
        <Logo markOnly />
        <span className="truncate text-sm font-semibold">
          {current ? t(current.labelKey) : t('app.name')}
        </span>
      </Link>

      <GlobalSearch className="hidden md:block" />

      <div className="ms-auto flex items-center gap-1 sm:gap-2">
        <IconLink to="/projects" icon={Search} label={t('search.label')} className="md:hidden" />
        <LanguageSwitcher className="hidden sm:block" />
        <ThemeSwitcher className="hidden md:flex" />
        <IconLink to="/notifications" icon={Bell} label={t('nav.notifications')} />
        <AccountMenu />
      </div>
    </header>
  );
}
