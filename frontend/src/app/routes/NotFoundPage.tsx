import { useTranslation } from 'react-i18next';
import { Link } from 'react-router';
import { Logo } from '@/components/brand/Logo';
import { buttonClass } from '@/components/ui/Button';

/** 404 for any unknown /app/... address. */
export function NotFoundPage() {
  const { t } = useTranslation();
  return (
    <main className="flex min-h-dvh flex-col items-center justify-center gap-6 bg-bg px-4 text-center text-fg">
      <Logo markOnly />
      <div className="flex max-w-md flex-col gap-2">
        <p className="font-mono text-sm text-muted">404</p>
        <h1 className="text-2xl">{t('not_found.title')}</h1>
        <p className="text-sm text-muted">{t('not_found.body')}</p>
      </div>
      <Link to="/" className={buttonClass('primary', 'md')}>
        {t('not_found.home')}
      </Link>
    </main>
  );
}
