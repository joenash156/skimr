import * as RadixPopover from '@radix-ui/react-popover';
import { useState, type ReactElement, type ReactNode } from 'react';
import { cn } from '@/lib/cn';

export interface PopoverProps {
  /** One focusable element (e.g. an icon button with an aria-label). */
  trigger: ReactElement;
  /** Content, or a function that gets `close()` (e.g. to close after a link). */
  children: ReactNode | ((close: () => void) => ReactNode);
  /** Accessible name of the panel. */
  label: string;
  side?: 'top' | 'right' | 'bottom' | 'left';
  align?: 'start' | 'center' | 'end';
  className?: string;
}

/**
 * A small floating panel (account menu, filters). Esc and outside clicks
 * close it; focus moves in on open and back to the trigger on close (Radix).
 */
export function Popover({
  trigger,
  children,
  label,
  side = 'bottom',
  align = 'end',
  className,
}: PopoverProps) {
  const [open, setOpen] = useState(false);
  const close = () => setOpen(false);
  return (
    <RadixPopover.Root open={open} onOpenChange={setOpen}>
      <RadixPopover.Trigger asChild>{trigger}</RadixPopover.Trigger>
      <RadixPopover.Portal>
        <RadixPopover.Content
          side={side}
          align={align}
          sideOffset={8}
          collisionPadding={8}
          aria-label={label}
          className={cn(
            'z-50 w-72 max-w-[calc(100vw-1rem)] rounded-card border border-border bg-bg p-3 text-fg shadow-popover',
            'data-[state=open]:animate-fade-in',
            className,
          )}
        >
          {typeof children === 'function' ? children(close) : children}
        </RadixPopover.Content>
      </RadixPopover.Portal>
    </RadixPopover.Root>
  );
}
