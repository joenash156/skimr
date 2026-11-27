import * as RadixTabs from '@radix-ui/react-tabs';
import type { ReactNode } from 'react';
import { cn } from '@/lib/cn';

export interface TabItem {
  value: string;
  label: string;
  content: ReactNode;
  disabled?: boolean;
}

export interface TabsProps {
  items: readonly TabItem[];
  /** Accessible name for the tab list, e.g. "Settings sections". */
  label: string;
  value?: string;
  defaultValue?: string;
  onValueChange?: (value: string) => void;
  className?: string;
}

/**
 * In-page tabs (arrow keys move between tabs). The project workspace uses
 * URL-based tabs instead (B2). Below 640 px the list scrolls sideways (spec 14.3).
 */
export function Tabs({ items, label, value, defaultValue, onValueChange, className }: TabsProps) {
  const controlled =
    value !== undefined ? { value } : { defaultValue: defaultValue ?? items[0]?.value };
  return (
    <RadixTabs.Root
      {...controlled}
      {...(onValueChange ? { onValueChange } : {})}
      className={cn('flex flex-col gap-4', className)}
    >
      <RadixTabs.List
        aria-label={label}
        className="-mx-4 flex overflow-x-auto border-b border-border px-4 sm:mx-0 sm:px-0"
      >
        {items.map((item) => (
          <RadixTabs.Trigger
            key={item.value}
            value={item.value}
            disabled={item.disabled}
            className={cn(
              '-mb-px flex h-11 shrink-0 items-center border-b-2 border-transparent px-3 text-sm font-medium whitespace-nowrap text-muted transition-colors',
              'hover:text-fg data-[state=active]:border-fg data-[state=active]:text-fg',
              'disabled:pointer-events-none disabled:opacity-50',
            )}
          >
            {item.label}
          </RadixTabs.Trigger>
        ))}
      </RadixTabs.List>
      {items.map((item) => (
        <RadixTabs.Content key={item.value} value={item.value}>
          {item.content}
        </RadixTabs.Content>
      ))}
    </RadixTabs.Root>
  );
}
