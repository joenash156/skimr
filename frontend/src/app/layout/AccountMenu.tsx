import { ExternalLink, LogOut, Settings } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Link, useNavigate } from 'react-router';
import { Avatar } from '@/components/ui/Avatar';
import { Badge } from '@/components/ui/Badge';
import { Popover } from '@/components/ui/Popover';
import { useAuth } from '@/lib/auth/useAuth';
import { isSupportedLanguage } from '@/lib/i18n';
import { LanguageSwitcher } from './LanguageSwitcher';
import { ThemeSwitcher } from './ThemeSwitcher';

const ITEM =
  'flex min-h-10 w-full items-center gap-3 rounded-control px-2.5 text-start text-sm text-fg transition-colors hover:bg-surface-2 pointer-coarse:min-h-11';

/**
 * The avatar menu (spec 5.3), shaped like the sample's account menu: who is
 * signed in, display settings, the public site and signing out. On small
 * screens the language and theme controls move in here from the topbar.
 */
export function AccountMenu() {
  const { t, i18n } = useTranslation();
  const { user, signOut } = useAuth();
  const navigate = useNavigate();
  if (!user) return null;

  const name = `${user.first_name} ${user.last_name}`;
  const lang = isSupportedLanguage(i18n.language) ? i18n.language : 'en';

  return (
    <Popover
      label={t('account.menu_label')}
      trigger={
        <button
          type="button"
          aria-label={t('account.menu_label')}
          className="inline-flex size-11 shrink-0 items-center justify-center rounded-full hover:bg-surface-2"
        >
          <Avatar name={name} src={user.avatar_url} size="sm" />
        </button>
      }
    >
      {(close) => (
        <div className="flex flex-col gap-3">
          <div className="flex items-center gap-3 px-1">
            <Avatar name={name} src={user.avatar_url} />
            <div className="min-w-0 flex-1">
              <p className="truncate text-sm font-medium">{name}</p>
              <p className="truncate text-xs text-muted">{user.email}</p>
            </div>
            {user.role !== 'user' && <Badge tone="primary">{t(`roles.${user.role}`)}</Badge>}
          </div>

          <div className="flex flex-col gap-3 border-t border-border pt-3 md:hidden">
            <ThemeSwitcher showLegend fullWidth />
            <div className="sm:hidden">
              <p className="mb-2 text-xs font-semibold tracking-[0.06em] text-subtle uppercase">
                {t('language.label')}
              </p>
              <LanguageSwitcher variant="list" />
            </div>
          </div>

          <div className="flex flex-col border-t border-border pt-2">
            <Link to="/settings" className={ITEM} onClick={close}>
              <Settings aria-hidden="true" className="size-4 text-muted" />
              {t('nav.settings')}
            </Link>
            <a
              href={`/${lang}/`}
              target="_blank"
              rel="noopener noreferrer"
              className={ITEM}
              onClick={close}
            >
              <ExternalLink aria-hidden="true" className="size-4 text-muted" />
              {t('account.view_site')}
            </a>
            <button
              type="button"
              className={ITEM}
              onClick={() => {
                close();
                void signOut().finally(() => navigate('/login', { replace: true }));
              }}
            >
              <LogOut aria-hidden="true" className="size-4 text-muted" />
              {t('session.log_out')}
            </button>
          </div>
        </div>
      )}
    </Popover>
  );
}
