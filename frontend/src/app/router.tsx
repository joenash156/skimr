import { createBrowserRouter, RouterProvider } from 'react-router';
import { AppShell } from './layout/AppShell';

/**
 * PHP serves the build at /app/* and Vite dev serves it at /app/, so every
 * route lives under the /app basename. B1 adds the auth routes, RequireAuth,
 * RequireRole and the 404 page.
 */
const router = createBrowserRouter([{ path: '*', element: <AppShell /> }], { basename: '/app' });

export function AppRouter() {
  return <RouterProvider router={router} />;
}
