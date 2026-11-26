import type { ComponentProps } from 'react';
import { cn } from '@/lib/cn';

/**
 * Loading placeholder (spec 14.5 state rule). Decorative: the surrounding
 * region should carry aria-busy and a screen-reader "Loading" text.
 */
export function Skeleton({ className, ...props }: ComponentProps<'div'>) {
  return (
    <div
      aria-hidden="true"
      className={cn('animate-pulse rounded-control bg-surface-2', className)}
      {...props}
    />
  );
}
