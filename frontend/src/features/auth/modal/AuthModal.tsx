import * as RadixDialog from '@radix-ui/react-dialog';
import { ArrowLeft, X } from 'lucide-react';
import { createContext, useContext, useMemo, useState, type ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { Logo } from '@/components/brand/Logo';
import { cn } from '@/lib/cn';
import { useAuthFlow, type AuthView } from '../flow/authFlow';
import { ViewLink } from '../flow/ViewLink';

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
 * The auth modal (spec 5.2 note, 14.5): a centred card over a dimmed, blurred
 * backdrop. Radix traps focus, closes on Esc or a click outside, locks the page
 * scroll and returns focus. Long forms scroll inside the backdrop on phones.
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
            'fixed inset-0 z-70 grid place-items-center overflow-y-auto p-4 sm:p-6',
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
              'relative w-full max-w-106 rounded-[1.25rem] border border-border bg-bg text-fg outline-none',
              backdrop === 'dim'
                ? 'shadow-[0_24px_64px_-16px_rgb(0_0_0/0.35)]'
                : 'shadow-[0_24px_64px_-16px_rgb(0_0_0/0.18)]',
              'data-[state=closed]:animate-pop-out data-[state=open]:animate-modal-in',
            )}
          >
            <AuthModalContext.Provider value={controls}>
              <div className="px-6 pt-7 pb-6 sm:px-8 sm:pt-8">
                {current.back && (
                  <ViewLink
                    view={current.back.view}
                    className="anim-host mb-4 inline-flex items-center gap-1.5 text-sm text-muted no-underline hover:text-fg hover:no-underline"
                  >
                    <ArrowLeft aria-hidden="true" className="size-4" />
                    {current.back.label}
                  </ViewLink>
                )}

                <div className="flex flex-col items-center text-center">
                  <span className="flex size-12 items-center justify-center rounded-2xl border border-border bg-surface">
                    <Logo markOnly />
                  </span>
                  <RadixDialog.Title className="mt-4 text-xl font-semibold tracking-[-0.02em]">
                    {current.title}
                  </RadixDialog.Title>
                  {current.description && (
                    <RadixDialog.Description className="mt-1.5 max-w-88 text-sm text-muted">
                      {current.description}
                    </RadixDialog.Description>
                  )}
                </div>

                {current.tabs && <ViewTabs />}

                {/* Keyed by view: switching views replays the entrance. Not by the done
                    header, or the view would remount and lose its success state. */}
                <div key={flow.view} className="mt-6 animate-slide-in">
                  {children}
                </div>

                {footer && (
                  <div className="mt-6 flex items-center justify-between gap-3 border-t border-border pt-4">
                    {footer}
                  </div>
                )}
              </div>
            </AuthModalContext.Provider>

            {/* Last in the DOM, so focus starts on the form, not on "Close". */}
            <RadixDialog.Close
              aria-label={t('common:ui.close')}
              className="absolute inset-e-3 top-3 flex size-9 items-center justify-center rounded-full text-muted transition-colors hover:bg-surface-2 hover:text-fg pointer-coarse:size-11"
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
      className="relative mt-6 grid grid-cols-2 rounded-full bg-surface-2 p-1"
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
