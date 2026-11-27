import * as RadixDialog from '@radix-ui/react-dialog';
import { X } from 'lucide-react';
import { useRef, type ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { cn } from '@/lib/cn';

export interface DialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  title: string;
  description?: ReactNode;
  children?: ReactNode;
  /** Buttons, right-aligned; on phones they stack full width. */
  footer?: ReactNode;
  size?: 'sm' | 'md' | 'lg';
  /** Block Esc and outside clicks, e.g. while saving. */
  dismissible?: boolean;
}

const SIZES = { sm: 'sm:max-w-sm', md: 'sm:max-w-lg', lg: 'sm:max-w-2xl' } as const;

/**
 * Modal dialog (spec 14.5, 14.6): focus is trapped inside and returns to
 * whatever opened it. Full screen below 640 px (spec 14.3).
 */
export function Dialog({
  open,
  onOpenChange,
  title,
  description,
  children,
  footer,
  size = 'md',
  dismissible = true,
}: DialogProps) {
  const { t } = useTranslation();
  const returnFocusTo = useRef<HTMLElement | null>(null);
  const block = (event: Event) => {
    if (!dismissible) event.preventDefault();
  };
  // Radix only returns focus to its own <Dialog.Trigger>. Our dialogs are
  // opened by ordinary buttons and menu items, so remember what had focus
  // when the dialog opened and give it back on close (spec 14.6).
  const rememberFocus = () => {
    returnFocusTo.current =
      document.activeElement instanceof HTMLElement ? document.activeElement : null;
  };
  const restoreFocus = (event: Event) => {
    const target = returnFocusTo.current;
    if (target?.isConnected) {
      event.preventDefault();
      target.focus();
    }
  };
  return (
    <RadixDialog.Root open={open} onOpenChange={onOpenChange}>
      <RadixDialog.Portal>
        <RadixDialog.Overlay className="fixed inset-0 z-50 bg-black/50 data-[state=open]:animate-fade-in" />
        <RadixDialog.Content
          onOpenAutoFocus={rememberFocus}
          onCloseAutoFocus={restoreFocus}
          onEscapeKeyDown={block}
          onPointerDownOutside={block}
          onInteractOutside={block}
          {...(description ? {} : { 'aria-describedby': undefined })}
          className={cn(
            'fixed inset-0 z-50 flex flex-col bg-bg text-fg data-[state=open]:animate-pop-in',
            'sm:inset-auto sm:top-1/2 sm:left-1/2 sm:max-h-[85dvh] sm:w-[calc(100%-2rem)] sm:-translate-x-1/2 sm:-translate-y-1/2',
            'sm:rounded-dialog sm:border sm:border-border sm:shadow-popover',
            SIZES[size],
          )}
        >
          <div className="flex items-start justify-between gap-4 border-b border-border px-5 py-4">
            <div className="flex min-w-0 flex-col gap-1">
              <RadixDialog.Title className="text-lg font-semibold">{title}</RadixDialog.Title>
              {description && (
                <RadixDialog.Description className="text-sm text-muted">
                  {description}
                </RadixDialog.Description>
              )}
            </div>
            {dismissible && (
              <RadixDialog.Close
                aria-label={t('ui.close')}
                className="-me-2 -mt-1 flex size-11 shrink-0 items-center justify-center rounded-control text-muted hover:bg-surface-2 hover:text-fg"
              >
                <X aria-hidden="true" className="size-4" />
              </RadixDialog.Close>
            )}
          </div>

          {children && <div className="flex-1 overflow-y-auto px-5 py-4">{children}</div>}

          {footer && (
            <div className="flex flex-col-reverse gap-2 border-t border-border px-5 py-4 sm:flex-row sm:justify-end">
              {footer}
            </div>
          )}
        </RadixDialog.Content>
      </RadixDialog.Portal>
    </RadixDialog.Root>
  );
}
