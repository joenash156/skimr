import { useId } from 'react';
import { cn } from '@/lib/cn';

export interface LogoProps {
  /** Show only the mark (e.g. the collapsed sidebar). */
  markOnly?: boolean;
  className?: string;
}

/**
 * The Skimr logo, copied from P1's backend/templates/public/macros/brand.html.twig
 * (mark) and the public site's wordmark, so the dashboard matches the site.
 * The wordmark uses the text colour, so the same markup works in both themes.
 * Decorative: the surrounding link or heading carries the accessible name.
 */
export function Logo({ markOnly = false, className }: LogoProps) {
  const uid = useId().replace(/:/g, '');
  return (
    <span className={cn('inline-flex items-center gap-2', className)} aria-hidden="true">
      <svg viewBox="0 0 64 64" fill="none" className="size-7 shrink-0" focusable="false">
        <defs>
          <linearGradient
            id={`sk-t${uid}`}
            gradientUnits="userSpaceOnUse"
            x1="13"
            y1="25"
            x2="58"
            y2="9"
          >
            <stop offset="0" stopColor="#5eead4" />
            <stop offset="1" stopColor="#14b8a6" />
          </linearGradient>
          <linearGradient
            id={`sk-m${uid}`}
            gradientUnits="userSpaceOnUse"
            x1="17"
            y1="23"
            x2="47"
            y2="38"
          >
            <stop offset="0" stopColor="#0f766e" />
            <stop offset="1" stopColor="#134e4a" />
          </linearGradient>
          <linearGradient
            id={`sk-b${uid}`}
            gradientUnits="userSpaceOnUse"
            x1="6"
            y1="56"
            x2="51"
            y2="40"
          >
            <stop offset="0" stopColor="#0d9488" />
            <stop offset="1" stopColor="#2dd4bf" />
          </linearGradient>
        </defs>
        <path d="M18 23 L46 37" stroke={`url(#sk-m${uid})`} strokeWidth="11" />
        <path d="M18 23 L58 8.8" stroke={`url(#sk-t${uid})`} strokeWidth="11" />
        <circle cx="18" cy="23" r="5.5" fill={`url(#sk-t${uid})`} />
        <path d="M46 37 L6 51.2" stroke={`url(#sk-b${uid})`} strokeWidth="11" />
        <circle cx="46" cy="37" r="5.5" fill={`url(#sk-b${uid})`} />
      </svg>
      {!markOnly && (
        <span className="text-lg font-bold tracking-[-0.035em] text-fg">
          Sk
          <span className="relative">
            {'ı'}
            <span className="absolute top-[0.08em] left-1/2 size-[0.2em] -translate-x-1/2 rounded-full bg-[#14b8a6]" />
          </span>
          mr
        </span>
      )}
    </span>
  );
}
