import type { LucideIcon } from 'lucide-react';
import { Link } from 'react-router';
import { Tooltip } from '@/components/ui/Tooltip';
import { cn } from '@/lib/cn';

/** A 44 px icon-only link with a tooltip and an aria-label (spec 14.6). */
export function IconLink({
  to,
  icon: Icon,
  label,
  className,
}: {
  to: string;
  icon: LucideIcon;
  label: string;
  className?: string;
}) {
  return (
    <Tooltip content={label} side="bottom">
      <Link
        to={to}
        aria-label={label}
        className={cn(
          'inline-flex size-11 shrink-0 items-center justify-center rounded-control text-muted hover:bg-surface-2 hover:text-fg',
          className,
        )}
      >
        <Icon aria-hidden="true" className="size-5" />
      </Link>
    </Tooltip>
  );
}
