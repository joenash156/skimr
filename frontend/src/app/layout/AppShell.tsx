import { useCallback, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Outlet } from 'react-router';
import { DesktopSidebar } from './DesktopSidebar';
import { MobileDrawer } from './MobileDrawer';
import { Topbar } from './Topbar';

/**
 * The dashboard frame (brief B1 task 2, spec 14.3), after Nana's sample:
 * a collapsible sidebar on desktop (256 / 64 px, saved in localStorage), a
 * drawer below 1024 px, the 56 px topbar, and content capped at 1280 px.
 */
export function AppShell() {
  const { t } = useTranslation();
  const [drawerOpen, setDrawerOpen] = useState(false);
  const closeDrawer = useCallback(() => setDrawerOpen(false), []);

  return (
    <div className="flex min-h-dvh bg-bg text-fg">
      <a
        href="#main"
        className="sr-only rounded-control bg-inverse px-4 py-2 text-sm font-medium text-inverse-fg focus:not-sr-only focus:fixed focus:top-3 focus:start-3 focus:z-[100]"
      >
        {t('nav.skip')}
      </a>
      <DesktopSidebar />
      <MobileDrawer open={drawerOpen} onClose={closeDrawer} />
      <div className="flex min-w-0 flex-1 flex-col">
        <Topbar drawerOpen={drawerOpen} onOpenMenu={() => setDrawerOpen(true)} />
        <main
          id="main"
          tabIndex={-1}
          className="mx-auto w-full max-w-[1280px] flex-1 px-4 py-6 outline-none sm:px-6 sm:py-8"
        >
          <Outlet />
        </main>
      </div>
    </div>
  );
}
