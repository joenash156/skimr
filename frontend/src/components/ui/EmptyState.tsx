import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/cn';

export interface EmptyStateProps {
  icon?: LucideIcon;
  title: string;
  /** What this place is for, and what to do next. */
  description?: ReactNode;
  /** Usually one primary Button. */
  action?: ReactNode;
  className?: string;
}

/** Spec 14.5: every empty view explains itself and offers an action. No illustrations. */
export function EmptyState({ icon: Icon, title, description, action, className }: EmptyStateProps) {
  return (
    <div
      className={cn(
        'flex flex-col items-center gap-3 rounded-card border border-dashed border-border px-6 py-12 text-center',
        className,
      )}
    >
      {Icon && (
        <span className="flex size-10 items-center justify-center rounded-full border border-border bg-surface text-muted">
          <Icon aria-hidden="true" className="size-5" />
        </span>
      )}
      <div className="flex max-w-md flex-col gap-1">
        <h2 className="text-base font-semibold text-fg">{title}</h2>
        {description && <p className="text-sm text-muted">{description}</p>}
      </div>
      {action && <div className="mt-2">{action}</div>}
    </div>
  );
}
