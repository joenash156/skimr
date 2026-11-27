import { ChevronDown } from 'lucide-react';
import { useId, type ComponentProps, type ReactNode } from 'react';
import { controlClass, describedBy, Field } from './Field';

export interface SelectOption {
  value: string;
  label: string;
  disabled?: boolean;
}

export interface SelectProps extends Omit<ComponentProps<'select'>, 'id' | 'children'> {
  label: ReactNode;
  options: readonly SelectOption[];
  /** Optional first, empty option, e.g. "Choose a discipline". */
  placeholder?: string;
  hint?: ReactNode;
  error?: string | undefined;
  hideLabel?: boolean;
  id?: string;
}

/**
 * A native <select>: the most reliable choice for keyboards, screen readers
 * and phones, styled to match the other controls.
 */
export function Select({
  label,
  options,
  placeholder,
  hint,
  error,
  hideLabel,
  id,
  className,
  ...props
}: SelectProps) {
  const autoId = useId();
  const selectId = id ?? autoId;
  return (
    <Field
      id={selectId}
      label={label}
      hint={hint}
      error={error}
      required={props.required}
      hideLabel={hideLabel}
      className={className}
    >
      <div className="relative">
        <select
          id={selectId}
          aria-invalid={error ? true : undefined}
          aria-describedby={describedBy(selectId, hint, error)}
          className={controlClass(Boolean(error), 'h-11 cursor-pointer appearance-none pe-10')}
          {...props}
        >
          {placeholder !== undefined && <option value="">{placeholder}</option>}
          {options.map((option) => (
            <option key={option.value} value={option.value} disabled={option.disabled}>
              {option.label}
            </option>
          ))}
        </select>
        <ChevronDown
          aria-hidden="true"
          className="pointer-events-none absolute end-3 top-1/2 size-4 -translate-y-1/2 text-muted"
        />
      </div>
    </Field>
  );
}
