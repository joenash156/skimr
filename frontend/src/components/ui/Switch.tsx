import * as RadixSwitch from '@radix-ui/react-switch';
import { useId, type ReactNode } from 'react';
import { cn } from '@/lib/cn';

export interface SwitchProps {
  label: ReactNode;
  description?: ReactNode;
  checked: boolean;
  onCheckedChange: (checked: boolean) => void;
  disabled?: boolean;
  id?: string;
  className?: string;
}

export function Switch({
  label,
  description,
  checked,
  onCheckedChange,
  disabled,
  id,
  className,
}: SwitchProps) {
  const autoId = useId();
  const switchId = id ?? autoId;
  const descriptionId = description ? `${switchId}-description` : undefined;
  return (
    <div className={cn('flex items-center justify-between gap-4', className)}>
      <div className="flex min-w-0 flex-col gap-0.5">
        <label htmlFor={switchId} className="cursor-pointer text-sm font-medium text-fg">
          {label}
        </label>
        {description && (
          <p id={descriptionId} className="text-xs text-muted">
            {description}
          </p>
        )}
      </div>
      <RadixSwitch.Root
        id={switchId}
        checked={checked}
        onCheckedChange={onCheckedChange}
        disabled={disabled}
        aria-describedby={descriptionId}
        // The visible track is 40x24; the padding makes the hit area 44 px tall.
        className={cn(
          'group relative inline-flex h-11 w-12 shrink-0 cursor-pointer items-center justify-center',
          'disabled:cursor-not-allowed disabled:opacity-50',
        )}
      >
        <span
          aria-hidden="true"
          className={cn(
            'flex h-6 w-10 items-center rounded-full border p-0.5 transition-colors',
            'border-border bg-surface-2 group-data-[state=checked]:border-inverse group-data-[state=checked]:bg-inverse',
          )}
        >
          <RadixSwitch.Thumb
            className={cn(
              'block size-[18px] rounded-full bg-muted transition-transform',
              'data-[state=checked]:translate-x-4 data-[state=checked]:bg-inverse-fg rtl:data-[state=checked]:-translate-x-4',
            )}
          />
        </span>
      </RadixSwitch.Root>
    </div>
  );
}
