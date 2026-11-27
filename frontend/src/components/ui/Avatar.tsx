import { useState } from 'react';
import { cn } from '@/lib/cn';

export interface AvatarProps {
  /** Full name, used for the initials and the alt text. */
  name: string;
  src?: string | null | undefined;
  size?: 'sm' | 'md' | 'lg';
  className?: string;
}

const SIZES = { sm: 'size-7 text-xs', md: 'size-9 text-sm', lg: 'size-16 text-xl' } as const;

export function initials(name: string): string {
  const parts = name.trim().split(/\s+/).filter(Boolean);
  const first = parts[0]?.[0] ?? '';
  const last = parts.length > 1 ? (parts[parts.length - 1]?.[0] ?? '') : '';
  return (first + last).toLocaleUpperCase();
}

export function Avatar({ name, src, size = 'md', className }: AvatarProps) {
  const [failed, setFailed] = useState(false);
  const classes = cn(
    'inline-flex shrink-0 items-center justify-center overflow-hidden rounded-full border border-border bg-surface-2 font-semibold text-muted select-none',
    SIZES[size],
    className,
  );
  if (src && !failed) {
    return (
      <img
        src={src}
        alt={name}
        className={cn(classes, 'object-cover')}
        onError={() => setFailed(true)}
      />
    );
  }
  return (
    <span role="img" aria-label={name} className={classes}>
      {initials(name)}
    </span>
  );
}
