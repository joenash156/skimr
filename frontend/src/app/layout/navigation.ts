import {
  Bell,
  FileSearch,
  FolderKanban,
  LayoutDashboard,
  Settings,
  ShieldCheck,
  Users,
  type LucideIcon,
} from 'lucide-react';
import type { Me } from '@/lib/auth/me';

export interface NavItem {
  to: string;
  labelKey: string;
  icon: LucideIcon;
  /** Match the path exactly (Overview at "/"). */
  end?: boolean;
  /** Only these roles see the item (the routes are guarded too). */
  roles?: readonly Me['role'][];
}

export interface NavGroup {
  labelKey: string;
  items: readonly NavItem[];
}

/** Sidebar items (spec 5.3), grouped like the reference sample. */
export const NAV: readonly NavGroup[] = [
  {
    labelKey: 'nav.groups.research',
    items: [
      { to: '/', labelKey: 'nav.overview', icon: LayoutDashboard, end: true },
      { to: '/projects', labelKey: 'nav.projects', icon: FolderKanban },
      { to: '/find-sources', labelKey: 'nav.find_sources', icon: FileSearch },
      { to: '/shared', labelKey: 'nav.shared', icon: Users },
    ],
  },
  {
    labelKey: 'nav.groups.account',
    items: [
      { to: '/notifications', labelKey: 'nav.notifications', icon: Bell },
      { to: '/settings', labelKey: 'nav.settings', icon: Settings },
    ],
  },
  {
    // The admin nav slot for P5's console (brief section 3).
    labelKey: 'nav.groups.admin',
    items: [
      { to: '/admin', labelKey: 'nav.admin', icon: ShieldCheck, roles: ['admin', 'super_admin'] },
    ],
  },
];

/** The groups and items this user may see; empty groups are dropped. */
export function visibleNav(role: Me['role'] | undefined): NavGroup[] {
  return NAV.map((group) => ({
    ...group,
    items: group.items.filter((item) => !item.roles || (role && item.roles.includes(role))),
  })).filter((group) => group.items.length > 0);
}

export function isCurrent(pathname: string, item: NavItem): boolean {
  if (item.end) return pathname === item.to;
  return pathname === item.to || pathname.startsWith(`${item.to}/`);
}
