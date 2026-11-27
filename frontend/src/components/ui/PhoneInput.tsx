import { ChevronDown } from 'lucide-react';
import { useId, useMemo, useState, type ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { countryOptions, fromE164, toE164 } from '@/lib/phone';
import { controlClass, describedBy, Field } from './Field';

export interface PhoneInputProps {
  label: ReactNode;
  /** E.164 (e.g. +233241234567), or '' when empty. */
  value: string;
  onChange: (e164: string) => void;
  onBlur?: () => void;
  hint?: ReactNode;
  error?: string | undefined;
  required?: boolean;
  name?: string;
  id?: string;
}

/**
 * Country picker + number, combined into E.164 (spec 5.2; default Ghana +233).
 * Use with react-hook-form's <Controller>.
 */
export function PhoneInput({
  label,
  value,
  onChange,
  onBlur,
  hint,
  error,
  required,
  name,
  id,
}: PhoneInputProps) {
  const { t, i18n } = useTranslation();
  const autoId = useId();
  const inputId = id ?? autoId;
  const [parts, setParts] = useState(() => fromE164(value));
  const options = useMemo(() => countryOptions(i18n.language), [i18n.language]);

  function update(next: { country?: string; national?: string }) {
    const merged = { ...parts, ...next };
    setParts(merged);
    onChange(toE164(merged));
  }

  return (
    <Field id={inputId} label={label} hint={hint} error={error} required={required}>
      <div className="flex gap-2">
        <div className="relative w-36 shrink-0 sm:w-40">
          <select
            aria-label={t('ui.phone_country')}
            value={parts.country}
            onChange={(event) => update({ country: event.target.value })}
            onBlur={onBlur}
            className={controlClass(false, 'h-11 cursor-pointer appearance-none truncate pe-8')}
          >
            {options.map((option) => (
              <option key={option.value} value={option.value}>
                {option.label}
              </option>
            ))}
          </select>
          <ChevronDown
            aria-hidden="true"
            className="pointer-events-none absolute end-2.5 top-1/2 size-4 -translate-y-1/2 text-muted"
          />
        </div>
        <input
          id={inputId}
          name={name}
          type="tel"
          inputMode="tel"
          autoComplete="tel-national"
          value={parts.national}
          onChange={(event) => update({ national: event.target.value })}
          onBlur={onBlur}
          required={required}
          aria-invalid={error ? true : undefined}
          aria-describedby={describedBy(inputId, hint, error)}
          className={controlClass(Boolean(error), 'h-11 min-w-0 flex-1')}
        />
      </div>
    </Field>
  );
}
