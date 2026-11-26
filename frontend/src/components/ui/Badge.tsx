import type { ComponentProps } from 'react';
import { cn } from '@/lib/cn';

export type BadgeTone = 'neutral' | 'primary' | 'success' | 'warning' | 'danger';

// Coloured text and border on the page colour: readable in both themes (spec 14.1).
const TONES: Record<BadgeTone, string> = {
  neutral: 'border-border bg-surface-2 text-muted',
  primary: 'border-primary/40 bg-primary-subtle text-primary',
  success: 'border-success/40 bg-bg text-success',
  warning: 'border-warning/40 bg-bg text-warning',
  danger: 'border-danger/40 bg-bg text-danger',
};

export interface BadgeProps extends ComponentProps<'span'> {
  tone?: BadgeTone;
}

export function Badge({ tone = 'neutral', className, ...props }: BadgeProps) {
  return (
    <span
      className={cn(
        'inline-flex h-6 items-center gap-1 rounded-full border px-2 text-xs font-medium whitespace-nowrap',
        TONES[tone],
        className,
      )}
      {...props}
    />
  );
}
