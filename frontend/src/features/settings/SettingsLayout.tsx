import {
  Bell,
  Database,
  Shield,
  SlidersHorizontal,
  UserRound,
  type LucideIcon,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { NavLink, Outlet } from 'react-router';
import { cn } from '@/lib/cn';

const SECTIONS: readonly { to: string; key: string; icon: LucideIcon }[] = [
  { to: 'profile', key: 'profile', icon: UserRound },
  { to: 'security', key: 'security', icon: Shield },
  { to: 'preferences', key: 'preferences', icon: SlidersHorizontal },
  { to: 'notifications', key: 'notifications', icon: Bell },
  { to: 'data', key: 'data', icon: Database },
];

/**
 * /app/settings/* (spec 5.3, brief B2 task 5). Each section has its own URL,
 * so emails can link straight to one (e.g. /app/settings/notifications).
 * Below 1024 px the section list becomes a sideways-scrolling bar (spec 14.3).
 */
export function SettingsLayout() {
  const { t } = useTranslation('settings');
  return (
    <div className="flex flex-col gap-6">
      <div className="flex flex-col gap-1">
        <h1 className="text-2xl">{t('title')}</h1>
        <p className="text-sm text-muted">{t('description')}</p>
      </div>
      <div className="flex flex-col gap-6 lg:flex-row lg:items-start lg:gap-10">
        <nav
          aria-label={t('sections_label')}
          className="-mx-4 overflow-x-auto border-b border-border px-4 sm:-mx-6 sm:px-6 lg:sticky lg:top-20 lg:mx-0 lg:w-52 lg:shrink-0 lg:overflow-visible lg:border-0 lg:px-0"
        >
          <ul className="flex gap-1 lg:flex-col">
            {SECTIONS.map(({ to, key, icon: Icon }) => (
              <li key={key} className="shrink-0">
                <NavLink
                  to={to}
                  className={({ isActive }) =>
                    cn(
                      'flex h-11 items-center gap-2.5 px-3 text-sm font-medium whitespace-nowrap transition-colors lg:h-10 lg:rounded-control',
                      '-mb-px border-b-2 lg:mb-0 lg:border-b-0',
                      isActive
                        ? 'border-fg text-fg lg:bg-surface-2'
                        : 'border-transparent text-muted hover:text-fg lg:hover:bg-surface-2/70',
                    )
                  }
                >
                  <Icon aria-hidden="true" className="size-4 shrink-0" />
                  {t(`sections.${key}`)}
                </NavLink>
              </li>
            ))}
          </ul>
        </nav>
        <div className="min-w-0 flex-1">
          <Outlet />
        </div>
      </div>
    </div>
  );
}
