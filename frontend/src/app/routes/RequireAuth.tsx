import { useEffect } from 'react';
import { Navigate, Outlet, useLocation, useNavigate } from 'react-router';
import { onApiEvent } from '@/lib/api/client';
import { useAuth } from '@/lib/auth/useAuth';
import { PageError } from './PageError';
import { PageLoader } from './PageLoader';

export interface RequireAuthProps {
  /** Let pending_verification users through (only the verify screen does). */
  allowPending?: boolean;
}

/**
 * Guards every signed-in route (brief B1 task 3):
 * - no session -> /login?next=<where they were going>
 * - pending_verification -> /verify-email (spec 5.2), also on a 403 EMAIL_NOT_VERIFIED
 */
export function RequireAuth({ allowPending = false }: RequireAuthProps) {
  const { status, user, retry, signOut } = useAuth();
  const location = useLocation();
  const navigate = useNavigate();

  useEffect(
    () =>
      onApiEvent((event) => {
        if (event.type === 'email-not-verified') void navigate('/verify-email', { replace: true });
      }),
    [navigate],
  );

  if (status === 'loading') return <PageLoader />;
  if (status === 'error') return <PageError onRetry={retry} onSignOut={() => void signOut()} />;
  if (status === 'unauthenticated' || !user) {
    const next = `${location.pathname}${location.search}`;
    const search = next === '/' ? '' : `?next=${encodeURIComponent(next)}`;
    return <Navigate to={`/login${search}`} replace />;
  }
  if (user.status === 'pending_verification' && !allowPending) {
    return <Navigate to="/verify-email" replace />;
  }
  return <Outlet />;
}
