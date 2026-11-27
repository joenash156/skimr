import * as RadixToast from '@radix-ui/react-toast';
import { CircleAlert, CircleCheck, X } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { cn } from '@/lib/cn';
import { dismissToast, type ToastItem } from '@/lib/toast';

const ICONS = { neutral: null, success: CircleCheck, danger: CircleAlert } as const;
const ICON_COLOURS = { neutral: '', success: 'text-success', danger: 'text-danger' } as const;

/** One toast; rendered by <Toaster />. */
export function ToastCard({ item }: { item: ToastItem }) {
  const { t } = useTranslation();
  const Icon = ICONS[item.tone];
  return (
    <RadixToast.Root
      // Errors stay longer so there is time to read the reference.
      duration={item.tone === 'danger' ? 10_000 : 5_000}
      type={item.tone === 'danger' ? 'foreground' : 'background'}
      onOpenChange={(open) => {
        if (!open) dismissToast(item.id);
      }}
      className={cn(
        'flex w-full items-start gap-3 rounded-card border border-border bg-bg p-4 text-fg shadow-popover',
        'data-[state=open]:animate-slide-in data-[swipe=move]:translate-x-(--radix-toast-swipe-move-x)',
      )}
    >
      {Icon && (
        <Icon
          aria-hidden="true"
          className={cn('mt-0.5 size-4 shrink-0', ICON_COLOURS[item.tone])}
        />
      )}
      <div className="flex min-w-0 flex-1 flex-col gap-1">
        <RadixToast.Title className="text-sm font-medium">{item.title}</RadixToast.Title>
        {item.description && (
          <RadixToast.Description className="text-sm text-muted">
            {item.description}
          </RadixToast.Description>
        )}
        {item.requestId && (
          <p className="font-mono text-xs text-muted select-all">
            {t('errors.reference', { id: item.requestId })}
          </p>
        )}
      </div>
      <RadixToast.Close
        aria-label={t('ui.close')}
        className="-me-2 -mt-2 flex size-9 shrink-0 items-center justify-center rounded-control text-muted hover:bg-surface-2 hover:text-fg pointer-coarse:size-11"
      >
        <X aria-hidden="true" className="size-4" />
      </RadixToast.Close>
    </RadixToast.Root>
  );
}
