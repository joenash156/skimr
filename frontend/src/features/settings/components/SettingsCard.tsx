import type { ReactNode } from 'react';
import { Card } from '@/components/ui/Card';
import { cn } from '@/lib/cn';

export interface SettingsCardProps {
  title: string;
  description?: ReactNode;
  children: ReactNode;
  tone?: 'default' | 'danger';
  className?: string;
}

/** One block of settings: a heading, a short explanation, then the controls. */
export function SettingsCard({
  title,
  description,
  children,
  tone = 'default',
  className,
}: SettingsCardProps) {
  return (
    <Card className={cn('flex flex-col gap-5', tone === 'danger' && 'border-danger/40', className)}>
      <div className="flex flex-col gap-1">
        <h2 className={cn('text-base', tone === 'danger' && 'text-danger')}>{title}</h2>
        {description && <p className="text-sm text-muted">{description}</p>}
      </div>
      {children}
    </Card>
  );
}
