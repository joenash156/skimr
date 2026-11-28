import { useId } from 'react';
import { useTranslation } from 'react-i18next';
import { MonitorIcon, MoonIcon, SunIcon } from '@/components/icons/ThemeIcons';
import { useThemePreference } from '@/lib/auth/useThemePreference';
import { cn } from '@/lib/cn';
import { THEMES } from '@/lib/theme';

const ICONS = { light: SunIcon, dark: MoonIcon, system: MonitorIcon } as const;

export interface ThemeSwitcherProps {
  /** Show "Theme" above the control (the account menu does, like the sample). */
  showLegend?: boolean;
  /** Icon + label options across the full width (menus), like the public phone menu. */
  fullWidth?: boolean;
  className?: string;
}

/**
 * Light / dark / system (spec 14.4; brief B1 task 5), the same pill switch as
 * the public site (backend/templates/public/partials/theme-switch.html.twig):
 * a radio group, so arrow keys move between options and screen readers hear
 * "Theme, Dark, selected, 2 of 3". Icons redraw on hover and when chosen.
 * Saves to localStorage and, when signed in, to the account (PATCH /me).
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
    <fieldset className={cn('m-0 min-w-0 border-0 p-0', className)}>
      <legend
        className={cn(
          showLegend
            ? 'mb-2 text-xs font-semibold tracking-[0.06em] text-subtle uppercase'
            : 'sr-only',
        )}
      >
        {t('theme.legend')}
      </legend>
      <div
        className={cn(
          'grid grid-cols-3 gap-0.5 border border-border bg-surface p-[3px]',
          fullWidth ? 'w-full rounded-card' : 'w-max rounded-full',
        )}
      >
        {THEMES.map((theme) => {
          const Icon = ICONS[theme];
          const selected = current === theme;
          return (
            <label
              key={theme}
              title={t(`theme.${theme}`)}
              data-checked={selected ? '' : undefined}
              className={cn(
                'anim-host flex cursor-pointer items-center justify-center text-muted transition-[color,background-color,box-shadow] hover:text-fg',
                'has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-1 has-[:focus-visible]:outline-primary',
                fullWidth
                  ? 'flex-col gap-1 rounded-control py-2.5 text-xs font-[550]'
                  : 'size-[1.875rem] rounded-full pointer-coarse:size-9',
                selected &&
                  'bg-bg text-fg shadow-[0_0_0_1px_var(--border),0_1px_2px_rgb(0_0_0/0.06)]',
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
              <Icon className={fullWidth ? 'size-[1.125rem]' : 'size-[0.9375rem]'} />
              <span className={fullWidth ? 'truncate' : 'sr-only'}>{t(`theme.${theme}`)}</span>
            </label>
          );
        })}
      </div>
    </fieldset>
  );
}
