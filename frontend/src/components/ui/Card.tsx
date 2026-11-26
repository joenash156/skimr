import type { ComponentProps } from 'react';
import { cn } from '@/lib/cn';

export interface CardProps extends ComponentProps<'div'> {
  /** Inner spacing; `none` for cards that hold a table or list edge to edge. */
  padding?: 'none' | 'sm' | 'md';
}

const PADDING = { none: '', sm: 'p-4', md: 'p-6' } as const;

/** Flat surface with a 1 px border and 8 px radius (spec 14, 14.2). No shadow. */
export function Card({ padding = 'md', className, ...props }: CardProps) {
  return (
    <div
      className={cn('rounded-card border border-border bg-surface', PADDING[padding], className)}
      {...props}
    />
  );
}
