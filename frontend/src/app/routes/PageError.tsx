import { useTranslation } from 'react-i18next';
import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';

/** Signed in, but the account couldn't be loaded: explain and offer Retry (spec 14.5). */
export function PageError({ onRetry, onSignOut }: { onRetry: () => void; onSignOut: () => void }) {
  const { t } = useTranslation();
  return (
    <main className="flex min-h-dvh items-center justify-center bg-bg px-4 text-fg">
      <div className="flex w-full max-w-sm flex-col gap-4">
        <Alert tone="danger" title={t('session.load_failed_title')}>
          {t('session.load_failed')}
        </Alert>
        <Button size="lg" fullWidth onClick={onRetry}>
          {t('actions.retry')}
        </Button>
        <Button variant="ghost" fullWidth onClick={onSignOut}>
          {t('session.sign_out')}
        </Button>
      </div>
    </main>
  );
}
