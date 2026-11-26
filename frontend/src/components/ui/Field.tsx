import type { ReactNode } from 'react';
import { cn } from '@/lib/cn';

/** Shared look for text-like controls: 44 px tall (spec 14.6 touch targets), 6 px radius. */
export function controlClass(invalid: boolean, extra?: string): string {
  return cn(
    'w-full rounded-control border bg-bg px-3 text-base text-fg sm:text-sm',
    'placeholder:text-subtle transition-colors',
    'disabled:cursor-not-allowed disabled:bg-surface-2 disabled:text-muted',
    invalid ? 'border-danger' : 'border-border hover:border-muted',
    extra,
  );
}

/** The ids a control should list in aria-describedby (spec 14.6). */
export function describedBy(id: string, hint: unknown, error: unknown): string | undefined {
  const ids = [hint ? `${id}-hint` : null, error ? `${id}-error` : null].filter(Boolean);
  return ids.length > 0 ? ids.join(' ') : undefined;
}

interface FieldProps {
  id: string;
  label: ReactNode;
  hint?: ReactNode | undefined;
  error?: ReactNode | undefined;
  required?: boolean | undefined;
  /** Keep the label for screen readers only (always prefer a visible label). */
  hideLabel?: boolean | undefined;
  children: ReactNode;
  className?: string | undefined;
}

/**
 * A visible label, the control, then the hint and the error, linked to the
 * control by id (Input, Textarea, Select and PasswordInput all use it).
 */
export function Field({
  id,
  label,
  hint,
  error,
  required,
  hideLabel,
  children,
  className,
}: FieldProps) {
  return (
    <div className={cn('flex flex-col gap-1.5', className)}>
      <label htmlFor={id} className={cn('text-sm font-medium text-fg', hideLabel && 'sr-only')}>
        {label}
        {required && (
          <span aria-hidden="true" className="ms-0.5 text-danger">
            *
          </span>
        )}
      </label>
      {children}
      {hint && (
        <p id={`${id}-hint`} className="text-xs text-muted">
          {hint}
        </p>
      )}
      {error && (
        <p id={`${id}-error`} className="text-xs font-medium text-danger">
          {error}
        </p>
      )}
    </div>
  );
}
