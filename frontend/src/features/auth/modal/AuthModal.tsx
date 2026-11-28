import * as RadixDialog from '@radix-ui/react-dialog';
import { ArrowLeft, X } from 'lucide-react';
import { createContext, useContext, useMemo, useState, type ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { Logo } from '@/components/brand/Logo';
import { cn } from '@/lib/cn';
import { useAuthFlow, type AuthView } from '../flow/authFlow';
import { ViewLink } from '../flow/ViewLink';
import { AuthShowcase } from './AuthShowcase';

/** What the top of the card shows. */
export interface AuthHeader {
  title: string;
  description?: ReactNode;
  /** Log in / Sign up switch under the title. */
  tabs?: boolean;
  /** A "back" link above the logo, to this view. */
  back?: { view: AuthView; label: string };
}

interface AuthModalControls {
  /** Replace the header until the view changes, e.g. "Check your email" after sign-up. */
  showDone: (header: AuthHeader) => void;
}

const AuthModalContext = createContext<AuthModalControls>({ showDone: () => {} });

export function useAuthModal(): AuthModalControls {
  return useContext(AuthModalContext);
}

export interface AuthModalProps {
  open: boolean;
  /** Forces the header, e.g. the Google link form on /app/login?link=google. */
  header?: AuthHeader;
  /** Under the form, e.g. language and theme on the /app routes. */
  footer?: ReactNode;
  /**
   * 'dim' (public site): the page behind is dimmed and blurred.
   * 'clear' (/app routes): nothing to hide, so the dot-grid backdrop shows through.
   */
  backdrop?: 'dim' | 'clear';
  children: ReactNode;
}

/**
 * The auth modal (spec 5.2 note, 14.5) over a dimmed, blurred backdrop. On wide
 * screens it is split: the form on the left, a short pitch with a sample
 * synthesis on the right (AuthShowcase). The modal has a fixed height and never
 * scrolls as a whole: the welcome heading, the Log in | Sign up switch and the
 * footer stay put, and only the form area scrolls. Radix traps focus, closes on
 * Esc or a click outside, locks the page scroll and returns focus.
 * The backdrop blur is the one exception to spec 14's "no blur" rule (P1, 2026-11).
 */
export function AuthModal({ open, header, footer, backdrop = 'dim', children }: AuthModalProps) {
  const { t } = useTranslation(['auth', 'common']);
  const flow = useAuthFlow();
  const [done, setDone] = useState<{ view: AuthView; header: AuthHeader } | null>(null);
  const controls = useMemo<AuthModalControls>(
    () => ({ showDone: (next) => setDone({ view: flow.view, header: next }) }),
    [flow.view],
  );

  const defaults: Record<AuthView, AuthHeader> = {
    login: { title: t('login.title'), description: t('login.description'), tabs: true },
    register: { title: t('register.title'), description: t('register.description'), tabs: true },
    forgot: {
      title: t('forgot.title'),
      description: t('forgot.description'),
      back: { view: 'login', label: t('forgot.back_to_login') },
    },
  };
  const doneHere = done?.view === flow.view ? done.header : null;
  const current = header ?? doneHere ?? defaults[flow.view];

  return (
    <RadixDialog.Root open={open} onOpenChange={(next) => !next && flow.close()}>
      <RadixDialog.Portal>
        <RadixDialog.Overlay
          className={cn(
            'fixed inset-0 z-70 grid place-items-center p-3 sm:p-6',
            backdrop === 'dim' &&
              'bg-black/40 backdrop-blur-md data-[state=closed]:animate-fade-out data-[state=open]:animate-fade-in dark:bg-black/60',
          )}
        >
          <RadixDialog.Content
            {...(current.description ? {} : { 'aria-describedby': undefined })}
            // As a page (/app routes) a stray click or Esc must not leave it; only "Close" does.
            {...(backdrop === 'clear'
              ? {
                  onPointerDownOutside: (event: Event) => event.preventDefault(),
                  onInteractOutside: (event: Event) => event.preventDefault(),
                  onEscapeKeyDown: (event: KeyboardEvent) => event.preventDefault(),
                }
              : {})}
            className={cn(
              // A fixed height, so switching Log in / Sign up never resizes the modal;
              // only the form area scrolls (the welcome, tabs and footer stay put).
              'relative grid grid-rows-[minmax(0,1fr)] h-[min(46rem,calc(100dvh-1.5rem))] w-full max-w-[29rem] overflow-hidden rounded-[1.25rem] border border-border bg-bg text-fg outline-none sm:h-[min(46rem,calc(100dvh-3rem))]',
              'lg:max-w-[62rem] lg:grid-cols-[minmax(0,1fr)_minmax(0,0.94fr)]',
              backdrop === 'dim'
                ? 'shadow-[0_32px_80px_-20px_rgb(0_0_0/0.45)]'
                : 'shadow-[0_32px_80px_-24px_rgb(0_0_0/0.22)]',
              'data-[state=closed]:animate-pop-out data-[state=open]:animate-modal-in',
            )}
          >
            <AuthModalContext.Provider value={controls}>
              <div className="flex min-h-0 flex-col">
                <div className="shrink-0 overflow-hidden px-6 pt-7 [scrollbar-gutter:stable] sm:px-10 sm:pt-9">
                  {/* The logo lives in the showcase on wide screens. */}
                  <Logo markOnly className="mb-5 lg:hidden" />

                  {current.back && (
                    <ViewLink
                      view={current.back.view}
                      className="anim-host mb-4 inline-flex items-center gap-1.5 text-sm text-muted no-underline hover:text-fg hover:no-underline"
                    >
                      <ArrowLeft aria-hidden="true" className="size-4" />
                      {current.back.label}
                    </ViewLink>
                  )}

                  {/* Playwrite's tall loops need the extra line height. */}
                  <RadixDialog.Title className="pe-8 font-script text-[1.625rem] leading-[1.65] font-normal text-fg sm:text-[1.875rem]">
                    {current.title}
                  </RadixDialog.Title>
                  {current.description && (
                    <RadixDialog.Description className="mt-1 text-sm leading-relaxed text-pretty text-muted">
                      {current.description}
                    </RadixDialog.Description>
                  )}

                  {current.tabs && <ViewTabs />}
                </div>

                {/* The header, this scroller and the footer all reserve the scrollbar gutter, so
                    their edges line up whether or not the form overflows.
                    Keyed by view: switching views replays the entrance and starts at the
                    top. Not by the done header, or the view would lose its success state. */}
                <div
                  key={flow.view}
                  className="min-h-0 flex-1 overflow-y-auto overscroll-contain [scrollbar-gutter:stable] [scrollbar-width:thin] px-6 pt-6 pb-8 [mask-image:linear-gradient(to_bottom,transparent,#000_1.25rem)] sm:px-10"
                >
                  <div className="animate-slide-in">{children}</div>
                </div>

                {footer && (
                  <div className="flex shrink-0 items-center justify-between gap-3 overflow-hidden border-t border-border px-6 py-3 [scrollbar-gutter:stable] sm:px-10">
                    {footer}
                  </div>
                )}
              </div>

              <AuthShowcase />
            </AuthModalContext.Provider>

            {/* Last in the DOM, so focus starts on the form, not on "Close". */}
            <RadixDialog.Close
              aria-label={t('common:ui.close')}
              className="absolute inset-e-3 top-3 flex size-9 items-center justify-center rounded-full text-muted transition-colors hover:bg-surface-2 hover:text-fg pointer-coarse:size-11 lg:border lg:border-border lg:bg-bg/80 lg:hover:bg-bg"
            >
              <X aria-hidden="true" className="size-4" />
            </RadixDialog.Close>
          </RadixDialog.Content>
        </RadixDialog.Overlay>
      </RadixDialog.Portal>
    </RadixDialog.Root>
  );
}

/**
 * Log in | Sign up: links between the two views (both are real pages under
 * /app), styled as a segmented control whose white pill slides across.
 */
function ViewTabs() {
  const { t } = useTranslation('auth');
  const flow = useAuthFlow();
  const items = [
    { view: 'login', label: t('modal.tab_login') },
    { view: 'register', label: t('modal.tab_register') },
  ] as const;

  return (
    <nav
      aria-label={t('modal.tabs_label')}
      className="relative mt-5 grid grid-cols-2 rounded-full bg-surface-2 p-1"
    >
      <span
        aria-hidden="true"
        className={cn(
          'absolute inset-y-1 inset-s-1 w-[calc(50%-0.25rem)] rounded-full bg-bg shadow-[0_0_0_1px_var(--border),0_1px_2px_rgb(0_0_0/0.08)]',
          'transition-transform duration-300 ease-[cubic-bezier(0.16,1,0.3,1)]',
          flow.view === 'register' && 'translate-x-full',
        )}
      />
      {items.map((item) => {
        const selected = flow.view === item.view;
        return (
          <a
            key={item.view}
            href={flow.href(item.view)}
            aria-current={selected ? 'page' : undefined}
            onClick={(event) => {
              if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey) return;
              event.preventDefault();
              flow.go(item.view);
            }}
            className={cn(
              'relative z-10 flex h-9 items-center justify-center rounded-full text-sm font-semibold transition-colors pointer-coarse:h-10',
              selected ? 'text-fg' : 'text-muted hover:text-fg',
            )}
          >
            {item.label}
          </a>
        );
      })}
    </nav>
  );
}
