import { CircleAlert, CircleCheck, Info } from 'lucide-react';
import type { ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { cn } from '@/lib/cn';

export type AlertTone = 'info' | 'success' | 'danger' | 'warning';

const STYLES: Record<AlertTone, { box: string; icon: string; Icon: typeof Info }> = {
  info: { box: 'border-border bg-surface', icon: 'text-muted', Icon: Info },
  success: { box: 'border-success/40 bg-bg', icon: 'text-success', Icon: CircleCheck },
  warning: { box: 'border-warning/40 bg-bg', icon: 'text-warning', Icon: CircleAlert },
  danger: { box: 'border-danger/40 bg-bg', icon: 'text-danger', Icon: CircleAlert },
};

export interface AlertProps {
  tone?: AlertTone;
  title?: ReactNode;
  children?: ReactNode;
  /** Shown as "Reference: ..." under errors (brief 3a). */
  requestId?: string | null | undefined;
  className?: string;
}

/**
 * An inline message, e.g. above a form. Errors are announced immediately
 * (role="alert"); other tones politely (role="status").
 */
export function Alert({ tone = 'info', title, children, requestId, className }: AlertProps) {
  const { t } = useTranslation();
  const { box, icon, Icon } = STYLES[tone];
  return (
    <div
      role={tone === 'danger' ? 'alert' : 'status'}
      className={cn('flex gap-3 rounded-card border p-3 text-sm', box, className)}
    >
      <Icon aria-hidden="true" className={cn('mt-0.5 size-4 shrink-0', icon)} />
      <div className="flex min-w-0 flex-col gap-1">
        {title && <p className="font-medium text-fg">{title}</p>}
        {children && <div className="text-muted">{children}</div>}
        {requestId && (
          <p className="font-mono text-xs text-muted select-all">
            {t('errors.reference', { id: requestId })}
          </p>
        )}
      </div>
    </div>
  );
}
