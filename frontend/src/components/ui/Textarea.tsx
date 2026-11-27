import { useId, type ComponentProps, type ReactNode } from 'react';
import { controlClass, describedBy, Field } from './Field';

export interface TextareaProps extends Omit<ComponentProps<'textarea'>, 'id'> {
  label: ReactNode;
  hint?: ReactNode;
  error?: string | undefined;
  hideLabel?: boolean;
  id?: string;
}

export function Textarea({
  label,
  hint,
  error,
  hideLabel,
  id,
  className,
  ...props
}: TextareaProps) {
  const autoId = useId();
  const textareaId = id ?? autoId;
  return (
    <Field
      id={textareaId}
      label={label}
      hint={hint}
      error={error}
      required={props.required}
      hideLabel={hideLabel}
      className={className}
    >
      <textarea
        id={textareaId}
        rows={4}
        aria-invalid={error ? true : undefined}
        aria-describedby={describedBy(textareaId, hint, error)}
        className={controlClass(Boolean(error), 'min-h-24 py-2.5 leading-normal')}
        {...props}
      />
    </Field>
  );
}
