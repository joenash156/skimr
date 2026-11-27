import { Eye, EyeOff } from 'lucide-react';
import { useId, useState, type ComponentProps, type ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { cn } from '@/lib/cn';
import { passwordStrength } from '@/lib/passwordStrength';
import { controlClass, describedBy, Field } from './Field';

export interface PasswordInputProps extends Omit<ComponentProps<'input'>, 'type' | 'id'> {
  label: ReactNode;
  hint?: ReactNode;
  error?: string | undefined;
  /**
   * The current value, to show the strength meter (sign-up, reset, change
   * password). With react-hook-form, pass `watch('password')`.
   */
  strengthOf?: string | undefined;
  id?: string;
}

const BAR_COLOURS = ['', 'bg-danger', 'bg-warning', 'bg-success', 'bg-success'] as const;

export function PasswordInput({
  label,
  hint,
  error,
  strengthOf,
  id,
  className,
  ...props
}: PasswordInputProps) {
  const { t } = useTranslation();
  const autoId = useId();
  const inputId = id ?? autoId;
  const [visible, setVisible] = useState(false);

  const strength = strengthOf !== undefined ? passwordStrength(strengthOf) : null;
  const strengthId = `${inputId}-strength`;
  const described = [describedBy(inputId, hint, error), strength && strengthOf ? strengthId : null]
    .filter(Boolean)
    .join(' ');

  return (
    <Field
      id={inputId}
      label={label}
      hint={hint}
      error={error}
      required={props.required}
      className={className}
    >
      <div className="relative">
        <input
          id={inputId}
          type={visible ? 'text' : 'password'}
          autoCapitalize="none"
          spellCheck={false}
          aria-invalid={error ? true : undefined}
          aria-describedby={described || undefined}
          className={controlClass(Boolean(error), 'h-11 pe-12')}
          {...props}
        />
        <button
          type="button"
          onClick={() => setVisible((v) => !v)}
          aria-label={visible ? t('ui.hide_password') : t('ui.show_password')}
          aria-controls={inputId}
          className="absolute end-0 top-0 flex size-11 items-center justify-center rounded-control text-muted hover:text-fg"
        >
          {visible ? (
            <EyeOff aria-hidden="true" className="size-4" />
          ) : (
            <Eye aria-hidden="true" className="size-4" />
          )}
        </button>
      </div>

      {strength && strengthOf && (
        <div id={strengthId} className="flex items-center gap-3" aria-live="polite">
          <div className="flex flex-1 gap-1" aria-hidden="true">
            {[1, 2, 3, 4].map((bar) => (
              <span
                key={bar}
                className={cn(
                  'h-1 flex-1 rounded-full transition-colors',
                  bar <= strength.score ? BAR_COLOURS[strength.score] : 'bg-surface-2',
                )}
              />
            ))}
          </div>
          <span className="shrink-0 text-xs text-muted">
            {t(`ui.password_strength.${strength.level}`)}
          </span>
        </div>
      )}
    </Field>
  );
}
