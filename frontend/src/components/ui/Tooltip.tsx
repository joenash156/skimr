import * as RadixTooltip from '@radix-ui/react-tooltip';
import type { ReactElement, ReactNode } from 'react';

export interface TooltipProps {
  content: ReactNode;
  /** One focusable element. Icon-only buttons still need their own aria-label (spec 14.6). */
  children: ReactElement;
  side?: 'top' | 'right' | 'bottom' | 'left';
  /** Hide the tooltip (e.g. when the sidebar is expanded and the label is visible). */
  disabled?: boolean;
}

/** Shows on hover and on keyboard focus; Esc closes it. */
export function Tooltip({ content, children, side = 'top', disabled = false }: TooltipProps) {
  if (disabled) return children;
  return (
    <RadixTooltip.Provider delayDuration={300} skipDelayDuration={200}>
      <RadixTooltip.Root>
        <RadixTooltip.Trigger asChild>{children}</RadixTooltip.Trigger>
        <RadixTooltip.Portal>
          <RadixTooltip.Content
            side={side}
            sideOffset={6}
            collisionPadding={8}
            className="z-50 max-w-64 rounded-control bg-inverse px-2.5 py-1.5 text-xs font-medium text-inverse-fg shadow-popover data-[state=delayed-open]:animate-fade-in"
          >
            {content}
          </RadixTooltip.Content>
        </RadixTooltip.Portal>
      </RadixTooltip.Root>
    </RadixTooltip.Provider>
  );
}
