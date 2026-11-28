import type { MouseEvent, ReactNode } from 'react';
import { cn } from '@/lib/cn';
import { useAuthFlow, type AuthView } from './authFlow';

/**
 * A link to another view of the auth modal. A plain click switches the view in
 * place; Ctrl/Cmd/middle click still opens the real page (/app/login ...).
 */
export function ViewLink({
  view,
  children,
  className,
}: {
  view: AuthView;
  children: ReactNode;
  className?: string;
}) {
  const flow = useAuthFlow();
  const onClick = (event: MouseEvent<HTMLAnchorElement>) => {
    if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
      return;
    }
    event.preventDefault();
    flow.go(view);
  };
  return (
    <a
      href={flow.href(view)}
      onClick={onClick}
      className={cn(
        'rounded-[2px] font-medium text-primary underline-offset-4 hover:text-primary-hover hover:underline',
        className,
      )}
    >
      {children}
    </a>
  );
}
