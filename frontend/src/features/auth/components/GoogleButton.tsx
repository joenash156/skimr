import { useTranslation } from 'react-i18next';
import { GoogleMark } from '@/components/brand/GoogleMark';
import { buttonClass } from '@/components/ui/Button';
import { GOOGLE_SIGN_IN_URL } from '../api';

/** A plain link, not a fetch: Google comes back through the API (brief 3a). */
export function GoogleButton() {
  const { t } = useTranslation('auth');
  return (
    <a href={GOOGLE_SIGN_IN_URL} className={buttonClass('secondary', 'md', 'w-full')}>
      <GoogleMark />
      {t('google.continue')}
    </a>
  );
}
