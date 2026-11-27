import type { ReactNode } from 'react';
import { Outlet } from 'react-router';
import type { Me } from '@/lib/auth/me';
import { useAuth } from '@/lib/auth/useAuth';
import { NotFoundPage } from './NotFoundPage';

export type Role = Me['role'];

export interface RequireRoleProps {
  roles: readonly Role[];
  /** Optional: guard children directly instead of nested routes. */
  children?: ReactNode;
}

/**
 * Role guard, used inside <RequireAuth> (brief B1 task 3), e.g.
 * <RequireRole roles={['admin', 'super_admin']} />. Others see the 404 page,
 * so the admin area isn't advertised. The API enforces roles too.
 */
export function RequireRole({ roles, children }: RequireRoleProps) {
  const { user } = useAuth();
  if (!user || !roles.includes(user.role)) return <NotFoundPage />;
  return children ? <>{children}</> : <Outlet />;
}
