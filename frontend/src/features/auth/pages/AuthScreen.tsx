import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { useNavigate, useSearchParams } from 'react-router';
import { LanguageSwitcher } from '@/app/layout/LanguageSwitcher';
import { ThemeSwitcher } from '@/app/layout/ThemeSwitcher';
import { goTo } from '@/lib/browser';
import { isSupportedLanguage } from '@/lib/i18n';
import { AuthBackdrop } from '../components/AuthBackdrop';
import { GoogleLinkForm } from '../components/GoogleLinkForm';
import { AUTH_VIEW_PATHS, AuthFlowContext, type AuthFlow, type AuthView } from '../flow/authFlow';
import { AuthModal } from '../modal/AuthModal';
import { safeNext } from '../safeNext';
import { ForgotPasswordView } from '../views/ForgotPasswordView';
import { LoginView } from '../views/LoginView';
import { RegisterView } from '../views/RegisterView';

const VIEWS = { login: LoginView, register: RegisterView, forgot: ForgotPasswordView } as const;

/**
 * /app/login, /app/register and /app/forgot-password: the auth modal, open over
 * a quiet backdrop. These URLs still matter (RequireAuth sends people to
 * /app/login?next=..., Google comes back to /app/login?link=google, and the
 * public site's links fall back to them before the modal script loads).
 * Switching views replaces the URL and keeps ?next; closing goes to the public site.
 */
export function AuthScreen({ view }: { view: AuthView }) {
  const { t, i18n } = useTranslation('auth');
  const navigate = useNavigate();
  const [params] = useSearchParams();
  const language = isSupportedLanguage(i18n.language) ? i18n.language : 'en';

  const flow = useMemo<AuthFlow>(() => {
    const next = params.get('next');
    const search = next ? `?${new URLSearchParams({ next }).toString()}` : '';
    return {
      view,
      go: (target) => void navigate(`${AUTH_VIEW_PATHS[target]}${search}`, { replace: true }),
      href: (target) => `/app${AUTH_VIEW_PATHS[target]}${search}`,
      done: (me, nextPath) =>
        void navigate(me.status === 'pending_verification' ? '/verify-email' : safeNext(nextPath), {
          replace: true,
        }),
      params,
      close: () => goTo(`/${language}/`),
    };
  }, [view, params, navigate, language]);

  const linkGoogle = view === 'login' && params.get('link') === 'google';
  const View = VIEWS[view];

  return (
    <AuthFlowContext.Provider value={flow}>
      <AuthBackdrop />
      <AuthModal
        open
        backdrop="clear"
        {...(linkGoogle
          ? { header: { title: t('google_link.title'), description: t('google_link.description') } }
          : {})}
        footer={
          <>
            <LanguageSwitcher />
            <ThemeSwitcher />
          </>
        }
      >
        {linkGoogle ? <GoogleLinkForm /> : <View />}
      </AuthModal>
    </AuthFlowContext.Provider>
  );
}
