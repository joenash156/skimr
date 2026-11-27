import { Monitor, Moon, Sun } from 'lucide-react';
import { useId } from 'react';
import { useTranslation } from 'react-i18next';
import { useThemePreference } from '@/lib/auth/useThemePreference';
import { cn } from '@/lib/cn';
import { THEMES } from '@/lib/theme';

const ICONS = { light: Sun, dark: Moon, system: Monitor } as const;

export interface ThemeSwitcherProps {
  /** Show "Theme" above the control (the account menu does, like the sample). */
  showLegend?: boolean;
  /** Stretch the three options across the full width (menus). */
  fullWidth?: boolean;
  className?: string;
}

/**
 * Light / dark / system as a radio group (spec 14.4; brief B1 task 5): arrow
 * keys move between options. Saves to localStorage and, when signed in,
 * to the account with PATCH /me. Every instance stays in sync.
 */
export function ThemeSwitcher({
  showLegend = false,
  fullWidth = false,
  className,
}: ThemeSwitcherProps) {
  const { t } = useTranslation();
  const [current, choose] = useThemePreference();
  const name = useId();

  return (
    <fieldset className={cn('flex flex-col gap-2', className)}>
      <legend className={cn(showLegend ? 'mb-2 text-xs font-medium text-muted' : 'sr-only')}>
        {t('theme.legend')}
      </legend>
      <div
        className={cn(
          'flex items-center gap-0.5 rounded-control border border-border bg-surface p-0.5',
          fullWidth && 'w-full',
        )}
      >
        {THEMES.map((theme) => {
          const Icon = ICONS[theme];
          const selected = current === theme;
          return (
            <label
              key={theme}
              title={t(`theme.${theme}`)}
              className={cn(
                'relative flex h-10 cursor-pointer items-center justify-center gap-2 rounded-[5px] text-sm transition-colors pointer-coarse:h-11',
                'has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-primary',
                fullWidth ? 'flex-1 px-2' : 'w-10 pointer-coarse:w-11',
                selected
                  ? 'bg-inverse text-inverse-fg'
                  : 'text-muted hover:bg-surface-2 hover:text-fg',
              )}
            >
              <input
                type="radio"
                name={name}
                value={theme}
                checked={selected}
                onChange={() => choose(theme)}
                className="sr-only"
              />
              <Icon aria-hidden="true" className="size-4 shrink-0" />
              <span className={fullWidth ? 'truncate' : 'sr-only'}>{t(`theme.${theme}`)}</span>
            </label>
          );
        })}
      </div>
    </fieldset>
  );
}
