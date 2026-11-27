import { Check } from 'lucide-react';
import { useId, type ComponentProps, type ReactNode } from 'react';
import { cn } from '@/lib/cn';

export interface CheckboxProps extends Omit<ComponentProps<'input'>, 'type' | 'id'> {
  /** May contain links, e.g. "I accept the Terms and Privacy policy". */
  label: ReactNode;
  error?: string | undefined;
  id?: string;
}

/** A native checkbox (keyboard and form support for free), drawn with tokens. */
export function Checkbox({ label, error, id, className, ...props }: CheckboxProps) {
  const autoId = useId();
  const inputId = id ?? autoId;
  const errorId = `${inputId}-error`;
  return (
    <div className={cn('flex flex-col gap-1', className)}>
      <div className="flex items-start gap-3">
        {/* 44 px hit area around an 18 px box (spec 14.6). */}
        <span className="relative -m-[13px] flex size-11 shrink-0 items-center justify-center">
          <input
            id={inputId}
            type="checkbox"
            aria-invalid={error ? true : undefined}
            aria-describedby={error ? errorId : undefined}
            className={cn(
              'peer size-[18px] cursor-pointer appearance-none rounded-[4px] border bg-bg transition-colors',
              'checked:border-inverse checked:bg-inverse disabled:cursor-not-allowed disabled:opacity-50',
              error ? 'border-danger' : 'border-muted',
            )}
            {...props}
          />
          <Check
            aria-hidden="true"
            strokeWidth={3}
            className="pointer-events-none absolute size-3 text-inverse-fg opacity-0 peer-checked:opacity-100"
          />
        </span>
        <label htmlFor={inputId} className="cursor-pointer text-sm leading-[18px] text-fg">
          {label}
        </label>
      </div>
      {error && (
        <p id={errorId} className="ms-[30px] text-xs font-medium text-danger">
          {error}
        </p>
      )}
    </div>
  );
}
