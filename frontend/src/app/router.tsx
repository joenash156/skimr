import type { ComponentType } from 'react';
import { createBrowserRouter, Navigate, RouterProvider, type RouteObject } from 'react-router';
import { AppShell } from './layout/AppShell';
import { ComingSoonPage } from './routes/ComingSoonPage';
import { GuestOnly } from './routes/GuestOnly';
import { NotFoundPage } from './routes/NotFoundPage';
import { RequireAuth } from './routes/RequireAuth';
import { RequireRole } from './routes/RequireRole';

/** Loads a page module only when its route is first visited (smaller first download). */
const page = <K extends string>(load: () => Promise<Record<K, ComponentType>>, name: K) => ({
  lazy: async () => ({ Component: (await load())[name] }),
});

/**
 * Every route lives under /app (PHP serves the build there; Vite dev too).
 * Paths below are relative to that basename: '/login' is /app/login.
 */
export const routes: RouteObject[] = [
  // Signed-out screens (spec 5.2). Emailed links work signed in or out.
  {
    element: <GuestOnly />,
    children: [
      { path: 'login', ...page(() => import('@/features/auth/pages/LoginPage'), 'LoginPage') },
      {
        path: 'register',
        ...page(() => import('@/features/auth/pages/RegisterPage'), 'RegisterPage'),
      },
    ],
  },
  // Also used signed in: Google-only accounts set their first password here (brief 3a).
  {
    path: 'forgot-password',
    ...page(() => import('@/features/auth/pages/ForgotPasswordPage'), 'ForgotPasswordPage'),
  },
  {
    path: 'verify-email',
    ...page(() => import('@/features/auth/pages/VerifyEmailPage'), 'VerifyEmailPage'),
  },
  {
    path: 'reset-password',
    ...page(() => import('@/features/auth/pages/ResetPasswordPage'), 'ResetPasswordPage'),
  },
  {
    path: 'accept-invite',
    ...page(() => import('@/features/auth/pages/AcceptInvitePage'), 'AcceptInvitePage'),
  },

  // Signed in.
  {
    element: <RequireAuth />,
    children: [
      {
        path: 'complete-profile',
        ...page(() => import('@/features/auth/pages/CompleteProfilePage'), 'CompleteProfilePage'),
      },
      {
        element: <AppShell />,
        children: [
          // B2-B5 replace these placeholders with the real screens.
          { index: true, element: <ComingSoonPage titleKey="nav.overview" /> },
          { path: 'projects/*', element: <ComingSoonPage titleKey="nav.projects" /> },
          { path: 'find-sources', element: <ComingSoonPage titleKey="nav.find_sources" /> },
          { path: 'shared', element: <ComingSoonPage titleKey="nav.shared" /> },
          { path: 'notifications', element: <ComingSoonPage titleKey="nav.notifications" /> },
          {
            path: 'settings',
            ...page(() => import('@/features/settings/SettingsLayout'), 'SettingsLayout'),
            children: [
              { index: true, element: <Navigate to="profile" replace /> },
              {
                path: 'profile',
                ...page(
                  () => import('@/features/settings/sections/ProfileSettings'),
                  'ProfileSettings',
                ),
              },
              {
                path: 'security',
                ...page(
                  () => import('@/features/settings/sections/SecuritySettings'),
                  'SecuritySettings',
                ),
              },
              {
                path: 'preferences',
                ...page(
                  () => import('@/features/settings/sections/PreferencesSettings'),
                  'PreferencesSettings',
                ),
              },
              {
                path: 'notifications',
                ...page(
                  () => import('@/features/settings/sections/NotificationSettings'),
                  'NotificationSettings',
                ),
              },
              {
                path: 'data',
                ...page(() => import('@/features/settings/sections/DataSettings'), 'DataSettings'),
              },
              { path: '*', element: <NotFoundPage /> },
            ],
          },
          {
            // Admin console (P5, src/features/admin). P5 plugs their routes in here.
            path: 'admin/*',
            element: <RequireRole roles={['admin', 'super_admin']} />,
            children: [{ path: '*', element: <ComingSoonPage titleKey="nav.admin" /> }],
          },
        ],
      },
    ],
  },

  { path: '*', element: <NotFoundPage /> },
];

const router = createBrowserRouter(routes, { basename: '/app' });

export function AppRouter() {
  return <RouterProvider router={router} />;
}
