import { Construction } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { EmptyState } from '@/components/ui/EmptyState';

/** Placeholder for screens built in later batches (B2-B5), so every nav item works. */
export function ComingSoonPage({ titleKey }: { titleKey: string }) {
  const { t } = useTranslation();
  const title = t(titleKey);
  return (
    <div className="flex flex-col gap-6">
      <h1 className="text-2xl">{title}</h1>
      <EmptyState
        icon={Construction}
        title={t('coming_soon.title')}
        description={t('coming_soon.body')}
      />
    </div>
  );
}
