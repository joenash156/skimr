import { useTranslation } from 'react-i18next';
import { Skeleton } from '@/components/ui/Skeleton';

/** Full-page loading state while the session is restored (refresh + /me). */
export function PageLoader() {
  const { t } = useTranslation();
  return (
    <div className="flex min-h-dvh flex-col bg-bg" aria-busy="true">
      <span className="sr-only" role="status">
        {t('shell.loading')}
      </span>
      <div className="flex h-14 items-center gap-3 border-b border-border px-4">
        <Skeleton className="size-7 rounded-full" />
        <Skeleton className="h-4 w-24" />
      </div>
      <div className="mx-auto flex w-full max-w-[1280px] flex-col gap-4 p-6">
        <Skeleton className="h-8 w-48" />
        <Skeleton className="h-32 w-full" />
        <Skeleton className="h-32 w-full" />
      </div>
    </div>
  );
}
