import { useId, type ComponentProps, type ReactNode } from 'react';
import { controlClass, describedBy, Field } from './Field';

export interface InputProps extends Omit<ComponentProps<'input'>, 'id'> {
  label: ReactNode;
  hint?: ReactNode;
  /** Already-translated message; also sets aria-invalid. */
  error?: string | undefined;
  hideLabel?: boolean;
  id?: string;
}

export function Input({ label, hint, error, hideLabel, id, className, ...props }: InputProps) {
  const autoId = useId();
  const inputId = id ?? autoId;
  return (
    <Field
      id={inputId}
      label={label}
      hint={hint}
      error={error}
      required={props.required}
      hideLabel={hideLabel}
      className={className}
    >
      <input
        id={inputId}
        aria-invalid={error ? true : undefined}
        aria-describedby={describedBy(inputId, hint, error)}
        className={controlClass(Boolean(error), 'h-11')}
        {...props}
      />
    </Field>
  );
}
