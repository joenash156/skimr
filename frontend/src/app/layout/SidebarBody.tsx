import { PanelLeftClose } from 'lucide-react';
import type { ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { Link } from 'react-router';
import { Logo } from '@/components/brand/Logo';
import { Tooltip } from '@/components/ui/Tooltip';
import { useAuth } from '@/lib/auth/useAuth';
import { cn } from '@/lib/cn';
import { useSidebarCollapsed } from '@/lib/sidebar';
import { visibleNav } from './navigation';
import { FADE, ROW } from './sidebarClasses';
import { SidebarNavItem } from './SidebarNavItem';

export interface SidebarBodyProps {
  /** Desktop rail (collapsible) or phone/tablet drawer (always labelled). */
  rail: boolean;
  onNavigate?: () => void;
  /** E.g. the drawer's close button, at the end of the header row. */
  headerAction?: ReactNode;
}

/** The sidebar's contents, shared by the desktop rail and the drawer (as in the sample). */
export function SidebarBody({ rail, onNavigate, headerAction }: SidebarBodyProps) {
  const { t } = useTranslation();
  const { user } = useAuth();
  const [collapsed, toggle] = useSidebarCollapsed();
  const groups = visibleNav(user?.role);
  const toggleLabel = collapsed ? t('nav.expand') : t('nav.collapse');

  return (
    <div className="flex h-full min-h-0 flex-col">
      <div className="flex h-14 shrink-0 items-center gap-3 border-b border-border ps-[18px] pe-3">
        <Link
          to="/"
          onClick={onNavigate}
          aria-label={t('nav.overview')}
          className="flex min-w-0 items-center rounded-control"
        >
          {/* The wordmark fades with the labels when the rail collapses. */}
          <Logo wordmarkClassName={rail ? FADE : undefined} />
        </Link>
        {headerAction && <span className="ms-auto">{headerAction}</span>}
      </div>

      <nav
        aria-label={t('nav.main_label')}
        className="min-h-0 flex-1 overflow-x-hidden overflow-y-auto px-3 py-4"
      >
        {groups.map((group) => (
          <div key={group.labelKey} className="mb-5 last:mb-0">
            <p
              className={cn(
                'mb-1.5 px-3 text-[11px] font-semibold tracking-[0.12em] whitespace-nowrap text-subtle uppercase',
                rail && FADE,
              )}
            >
              {t(group.labelKey)}
            </p>
            <ul className="flex flex-col gap-0.5">
              {group.items.map((item) => (
                <li key={item.to}>
                  <SidebarNavItem
                    item={item}
                    rail={rail}
                    collapsed={collapsed}
                    onNavigate={onNavigate}
                  />
                </li>
              ))}
            </ul>
          </div>
        ))}
      </nav>

      {rail && (
        <div className="shrink-0 border-t border-border p-3">
          <Tooltip content={toggleLabel} side="right" disabled={!collapsed}>
            <button
              type="button"
              onClick={toggle}
              aria-label={toggleLabel}
              aria-controls="app-sidebar"
              aria-expanded={!collapsed}
              className={cn(ROW, 'text-muted hover:bg-surface-2/70 hover:text-fg')}
            >
              <PanelLeftClose
                aria-hidden="true"
                className={cn('size-4 shrink-0 transition-transform', collapsed && 'rotate-180')}
              />
              <span className={FADE}>{t('nav.collapse_short')}</span>
            </button>
          </Tooltip>
        </div>
      )}
    </div>
  );
}
