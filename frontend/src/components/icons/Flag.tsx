import { useId } from 'react';
import type { Language } from '@/lib/i18n';
import { cn } from '@/lib/cn';

/**
 * National flags for the language switcher, the same drawings as the public
 * site (backend/templates/public/macros/flags.html.twig): en = United Kingdom,
 * fr = France, es = Spain (simplified, without the coat of arms, at icon size).
 * Decorative: the language name next to it is the accessible text.
 */
export function Flag({ code, className }: { code: Language; className?: string }) {
  const id = `f${useId().replace(/:/g, '')}`;
  return (
    <svg
      viewBox="0 0 30 20"
      aria-hidden="true"
      focusable="false"
      className={cn(
        'aspect-[3/2] w-5 shrink-0 rounded-[3px] shadow-[0_0_0_1px_rgb(0_0_0/0.08)] dark:shadow-[0_0_0_1px_rgb(255_255_255/0.12)]',
        className,
      )}
    >
      {code === 'fr' && (
        <>
          <rect width="10" height="20" fill="#002654" />
          <rect x="10" width="10" height="20" fill="#ffffff" />
          <rect x="20" width="10" height="20" fill="#ce1126" />
        </>
      )}
      {code === 'es' && (
        <>
          <rect width="30" height="20" fill="#aa151b" />
          <rect y="5" width="30" height="10" fill="#f1bf00" />
        </>
      )}
      {code === 'en' && (
        <>
          <clipPath id={`${id}-s`}>
            <rect width="30" height="20" />
          </clipPath>
          <clipPath id={`${id}-t`}>
            <path d="M15 10h15v10zv10H0zH0V0zV0h15z" />
          </clipPath>
          <g clipPath={`url(#${id}-s)`}>
            <rect width="30" height="20" fill="#012169" />
            <path d="M0 0l30 20M30 0 0 20" stroke="#ffffff" strokeWidth="4" />
            <path
              d="M0 0l30 20M30 0 0 20"
              clipPath={`url(#${id}-t)`}
              stroke="#c8102e"
              strokeWidth="2.4"
            />
            <path d="M15 0v20M0 10h30" stroke="#ffffff" strokeWidth="6" />
            <path d="M15 0v20M0 10h30" stroke="#c8102e" strokeWidth="3.6" />
          </g>
        </>
      )}
    </svg>
  );
}
