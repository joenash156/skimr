import { Navigate, Outlet, useSearchParams } from 'react-router';
import { safeNext } from '@/features/auth/safeNext';
import { useAuth } from '@/lib/auth/useAuth';
import { PageLoader } from './PageLoader';

/** Login, register and forgot password: signed-in users go straight to the app. */
export function GuestOnly() {
  const { status, user } = useAuth();
  const [params] = useSearchParams();
  if (status === 'loading') return <PageLoader />;
  if (status === 'authenticated' && user) {
    const target =
      user.status === 'pending_verification' ? '/verify-email' : safeNext(params.get('next'));
    return <Navigate to={target} replace />;
  }
  return <Outlet />;
}
