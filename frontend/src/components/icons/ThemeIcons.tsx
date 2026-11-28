import type { ReactNode } from 'react';
import { cn } from '@/lib/cn';

/**
 * Sun, moon and monitor drawn exactly like the public site's icons
 * (backend/templates/public/macros/icons.html.twig), so the theme switch looks
 * and moves the same on both sides. Every shape has pathLength="1" for the
 * stroke "draw" animation in src/styles/motion.css. Decorative.
 */
function LineIcon({ className, children }: { className?: string; children: ReactNode }) {
  return (
    <svg
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="1.75"
      strokeLinecap="round"
      strokeLinejoin="round"
      aria-hidden="true"
      focusable="false"
      className={cn('icon--draw size-4 shrink-0', className)}
    >
      {children}
    </svg>
  );
}

export function SunIcon({ className }: { className?: string }) {
  return (
    <LineIcon className={cn('icon--sun', className)}>
      <circle cx="12" cy="12" r="4" pathLength={1} />
      <g className="icon__rays">
        <path d="M12 2v2" pathLength={1} />
        <path d="M12 20v2" pathLength={1} />
        <path d="m4.9 4.9 1.4 1.4" pathLength={1} />
        <path d="m17.7 17.7 1.4 1.4" pathLength={1} />
        <path d="M2 12h2" pathLength={1} />
        <path d="M20 12h2" pathLength={1} />
        <path d="m6.3 17.7-1.4 1.4" pathLength={1} />
        <path d="m19.1 4.9-1.4 1.4" pathLength={1} />
      </g>
    </LineIcon>
  );
}

export function MoonIcon({ className }: { className?: string }) {
  return (
    <LineIcon className={cn('icon--moon', className)}>
      <path d="M20.5 14.5A8.5 8.5 0 0 1 9.5 3.5a8.5 8.5 0 1 0 11 11Z" pathLength={1} />
      <path d="M17 4v3M15.5 5.5h3" pathLength={1} />
    </LineIcon>
  );
}

export function MonitorIcon({ className }: { className?: string }) {
  return (
    <LineIcon className={cn('icon--monitor', className)}>
      <rect x="3" y="4" width="18" height="12" rx="2" pathLength={1} />
      <path d="M8 20h8" pathLength={1} />
      <path d="M12 16v4" pathLength={1} />
    </LineIcon>
  );
}
