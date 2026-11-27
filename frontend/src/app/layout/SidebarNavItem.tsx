import { useTranslation } from 'react-i18next';
import { NavLink } from 'react-router';
import { Tooltip } from '@/components/ui/Tooltip';
import { cn } from '@/lib/cn';
import type { NavItem } from './navigation';
import { FADE, ROW } from './sidebarClasses';

export interface SidebarNavItemProps {
  item: NavItem;
  /** Desktop rail: labels fade and tooltips appear when collapsed. */
  rail: boolean;
  collapsed: boolean;
  onNavigate?: (() => void) | undefined;
}

export function SidebarNavItem({ item, rail, collapsed, onNavigate }: SidebarNavItemProps) {
  const { t } = useTranslation();
  const label = t(item.labelKey);
  const Icon = item.icon;
  return (
    <Tooltip content={label} side="right" disabled={!(rail && collapsed)}>
      <NavLink
        to={item.to}
        end={item.end ?? false}
        onClick={onNavigate}
        className={({ isActive }) =>
          cn(
            ROW,
            isActive ? 'bg-surface-2 text-fg' : 'text-muted hover:bg-surface-2/70 hover:text-fg',
          )
        }
      >
        {({ isActive }) => (
          <>
            {isActive && (
              // Solid teal marker (the sample's gradient is banned by spec 14).
              <span
                aria-hidden="true"
                className="absolute inset-y-2 start-0 w-[3px] rounded-full bg-primary"
              />
            )}
            <Icon
              aria-hidden="true"
              className={cn('size-4 shrink-0', isActive && 'text-primary')}
            />
            <span className={cn('truncate', rail && FADE)}>{label}</span>
          </>
        )}
      </NavLink>
    </Tooltip>
  );
}
