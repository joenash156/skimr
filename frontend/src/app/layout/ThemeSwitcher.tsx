import { Monitor, Moon, Sun } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { getStoredTheme, setTheme, THEMES, type Theme } from '@/lib/theme';

const ICONS = { light: Sun, dark: Moon, system: Monitor } as const;

/**
 * B0 placeholder: light / dark / system as a radio group. B1 restyles it to
 * match Nana's reference sample and adds PATCH /me for signed-in users.
 */
export function ThemeSwitcher() {
  const { t } = useTranslation();
  const [current, setCurrent] = useState<Theme>(getStoredTheme);

  function choose(theme: Theme) {
    setTheme(theme);
    setCurrent(theme);
  }

  return (
    <fieldset className="flex items-center gap-1 rounded-control border border-border bg-surface p-0.5">
      <legend className="sr-only">{t('theme.legend')}</legend>
      {THEMES.map((theme) => {
        const Icon = ICONS[theme];
        const selected = current === theme;
        return (
          <label
            key={theme}
            title={t(`theme.${theme}`)}
            className={[
              'relative flex size-11 cursor-pointer items-center justify-center rounded-[5px] transition-colors',
              'has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-primary',
              selected
                ? 'bg-inverse text-inverse-fg'
                : 'text-muted hover:bg-surface-2 hover:text-fg',
            ].join(' ')}
          >
            <input
              type="radio"
              name="theme"
              value={theme}
              checked={selected}
              onChange={() => choose(theme)}
              className="sr-only"
            />
            <Icon aria-hidden="true" className="size-4" />
            <span className="sr-only">{t(`theme.${theme}`)}</span>
          </label>
        );
      })}
    </fieldset>
  );
}
