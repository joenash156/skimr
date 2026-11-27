import { X } from 'lucide-react';
import { useEffect, useRef } from 'react';
import { useTranslation } from 'react-i18next';
import { useLocation } from 'react-router';
import { SidebarBody } from './SidebarBody';

export interface MobileDrawerProps {
  open: boolean;
  onClose: () => void;
}

const CLOSE_MS = 180;

/**
 * Below 1024 px the sidebar is an off-canvas drawer (spec 14.3), built on the
 * native <dialog> like the sample: the page behind is inert, focus stays
 * inside and returns to the menu button, Esc and the dimmed overlay close it.
 */
export function MobileDrawer({ open, onClose }: MobileDrawerProps) {
  const { t } = useTranslation();
  const dialog = useRef<HTMLDialogElement>(null);
  const { pathname } = useLocation();

  // Keep the native dialog in step with `open`, sliding out before closing.
  useEffect(() => {
    const element = dialog.current;
    if (!element) return;
    if (open && !element.open) {
      element.showModal();
      return;
    }
    if (!open && element.open) {
      if (matchMedia('(prefers-reduced-motion: reduce)').matches) {
        element.close();
        return;
      }
      element.dataset.closing = '';
      const timer = window.setTimeout(() => {
        delete element.dataset.closing;
        element.close();
      }, CLOSE_MS);
      return () => window.clearTimeout(timer);
    }
  }, [open]);

  // A new page means the drawer has done its job.
  const lastPath = useRef(pathname);
  useEffect(() => {
    if (pathname !== lastPath.current) {
      lastPath.current = pathname;
      onClose();
    }
  }, [pathname, onClose]);

  return (
    <dialog
      ref={dialog}
      id="app-drawer"
      aria-label={t('nav.main_label')}
      onCancel={(event) => {
        event.preventDefault(); // animate out instead of closing at once
        onClose();
      }}
      onClose={onClose}
      onClick={(event) => {
        if (event.target === event.currentTarget) onClose(); // the dimmed overlay
      }}
      className="app-drawer fixed inset-y-0 start-0 m-0 h-dvh max-h-dvh w-[min(18rem,86vw)] max-w-none border-e border-border bg-surface p-0 text-fg shadow-popover backdrop:bg-black/50 lg:hidden"
    >
      <SidebarBody
        rail={false}
        onNavigate={onClose}
        headerAction={
          <button
            type="button"
            onClick={onClose}
            aria-label={t('nav.close_menu')}
            className="inline-flex size-11 items-center justify-center rounded-control text-fg hover:bg-surface-2"
          >
            <X aria-hidden="true" className="size-5" />
          </button>
        }
      />
    </dialog>
  );
}
