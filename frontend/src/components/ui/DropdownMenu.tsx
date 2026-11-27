import * as RadixMenu from '@radix-ui/react-dropdown-menu';
import type { LucideIcon } from 'lucide-react';
import type { ReactElement } from 'react';
import { Link } from 'react-router';
import { cn } from '@/lib/cn';

export type DropdownMenuItem =
  | { type: 'separator'; key: string }
  | { type: 'label'; key: string; label: string }
  | {
      type?: 'item';
      key: string;
      label: string;
      icon?: LucideIcon;
      /** An app route (rendered as a link) ... */
      to?: string;
      /** ... a full-page URL, e.g. /api/v1/auth/google/redirect ... */
      href?: string;
      /** ... or an action. */
      onSelect?: () => void;
      tone?: 'default' | 'danger';
      disabled?: boolean;
    };

export interface DropdownMenuProps {
  /** One focusable element, e.g. an icon Button with an aria-label. */
  trigger: ReactElement;
  items: readonly DropdownMenuItem[];
  align?: 'start' | 'center' | 'end';
  /** Accessible name for the menu if the trigger has none. */
  label?: string;
}

const itemClass = (tone: 'default' | 'danger' = 'default') =>
  cn(
    'flex min-h-10 cursor-pointer items-center gap-2 rounded-[4px] px-2.5 text-sm outline-none select-none pointer-coarse:min-h-11',
    'data-[disabled]:pointer-events-none data-[disabled]:opacity-50',
    tone === 'danger'
      ? 'text-danger data-[highlighted]:bg-danger/10'
      : 'text-fg data-[highlighted]:bg-surface-2',
  );

/** Keyboard navigation, typeahead and focus return come from Radix (spec 14.6). */
export function DropdownMenu({ trigger, items, align = 'end', label }: DropdownMenuProps) {
  return (
    <RadixMenu.Root modal={false}>
      <RadixMenu.Trigger asChild>{trigger}</RadixMenu.Trigger>
      <RadixMenu.Portal>
        <RadixMenu.Content
          align={align}
          sideOffset={6}
          collisionPadding={8}
          aria-label={label}
          className={cn(
            'z-50 min-w-48 rounded-card border border-border bg-bg p-1 text-fg shadow-popover',
            'data-[state=open]:animate-fade-in',
          )}
        >
          {items.map((item) => {
            if (item.type === 'separator') {
              return <RadixMenu.Separator key={item.key} className="my-1 h-px bg-border" />;
            }
            if (item.type === 'label') {
              return (
                <RadixMenu.Label key={item.key} className="truncate px-2.5 py-2 text-xs text-muted">
                  {item.label}
                </RadixMenu.Label>
              );
            }
            const Icon = item.icon;
            const content = (
              <>
                {Icon && <Icon aria-hidden="true" className="size-4 shrink-0 opacity-80" />}
                <span className="truncate">{item.label}</span>
              </>
            );
            const common = {
              disabled: item.disabled,
              className: itemClass(item.tone),
            };
            if (item.to) {
              return (
                <RadixMenu.Item key={item.key} asChild {...common}>
                  <Link to={item.to}>{content}</Link>
                </RadixMenu.Item>
              );
            }
            if (item.href) {
              return (
                <RadixMenu.Item key={item.key} asChild {...common}>
                  <a href={item.href}>{content}</a>
                </RadixMenu.Item>
              );
            }
            return (
              <RadixMenu.Item key={item.key} onSelect={item.onSelect} {...common}>
                {content}
              </RadixMenu.Item>
            );
          })}
        </RadixMenu.Content>
      </RadixMenu.Portal>
    </RadixMenu.Root>
  );
}
