import { cn } from '@/lib/cn';

export interface ProgressBarProps {
  /** 0-100. Leave undefined while the amount is unknown. */
  value?: number | undefined;
  /** Accessible name, e.g. "Processing report.pdf". */
  label: string;
  /** Shows the label and the percentage above the bar. */
  showValue?: boolean;
  tone?: 'neutral' | 'warning' | 'danger';
  className?: string;
}

const TONES = { neutral: 'bg-inverse', warning: 'bg-warning', danger: 'bg-danger' } as const;

export function ProgressBar({
  value,
  label,
  showValue = false,
  tone = 'neutral',
  className,
}: ProgressBarProps) {
  const known = typeof value === 'number' && Number.isFinite(value);
  const clamped = known ? Math.min(100, Math.max(0, value)) : 0;
  const percent = new Intl.NumberFormat(undefined, { style: 'percent' }).format(clamped / 100);
  return (
    <div className={cn('flex flex-col gap-1.5', className)}>
      {showValue && (
        <div className="flex items-center justify-between gap-2 text-xs">
          <span className="truncate text-muted">{label}</span>
          {known && <span className="font-medium text-fg tabular-nums">{percent}</span>}
        </div>
      )}
      <div
        role="progressbar"
        aria-label={label}
        aria-valuemin={0}
        aria-valuemax={100}
        aria-valuenow={known ? Math.round(clamped) : undefined}
        className="h-1.5 w-full overflow-hidden rounded-full bg-surface-2"
      >
        <div
          className={cn(
            'h-full rounded-full transition-[width]',
            TONES[tone],
            !known && 'w-1/3 animate-pulse',
          )}
          style={known ? { width: `${clamped}%` } : undefined}
        />
      </div>
    </div>
  );
}
