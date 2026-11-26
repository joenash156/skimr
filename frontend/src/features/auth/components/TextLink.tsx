import type { ReactNode } from 'react';
import { Link } from 'react-router';
import { cn } from '@/lib/cn';

/** An inline link in running text: teal, underlined on hover (spec 14.1 accent use). */
export function TextLink({
  to,
  children,
  className,
}: {
  to: string;
  children: ReactNode;
  className?: string;
}) {
  return (
    <Link
      to={to}
      className={cn(
        'rounded-[2px] font-medium text-primary underline-offset-4 hover:text-primary-hover hover:underline',
        className,
      )}
    >
      {children}
    </Link>
  );
}
