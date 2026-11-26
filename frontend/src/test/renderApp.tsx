import { QueryClientProvider } from '@tanstack/react-query';
import { render } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { StrictMode, Suspense } from 'react';
import { createMemoryRouter, RouterProvider } from 'react-router';
import { createQueryClient } from '@/app/providers';
import { routes } from '@/app/router';
import { AuthProvider } from '@/lib/auth/AuthProvider';

/** Renders the real app routes (StrictMode, React Query, AuthProvider) at `path`. */
export function renderApp(path: string) {
  const router = createMemoryRouter(routes, { initialEntries: [path] });
  const user = userEvent.setup();
  const result = render(
    <StrictMode>
      <QueryClientProvider client={createQueryClient()}>
        <AuthProvider>
          <Suspense fallback={null}>
            <RouterProvider router={router} />
          </Suspense>
        </AuthProvider>
      </QueryClientProvider>
    </StrictMode>,
  );
  return { ...result, router, user };
}

/** A GET /me body (contract example), with overrides. */
export function meFixture(overrides: Record<string, unknown> = {}) {
  return {
    id: '3f2c9a1e-7b5d-4e8a-9c1f-2a6b8d0e4f17',
    role: 'user',
    status: 'active',
    first_name: 'Ama',
    last_name: 'Mensah',
    other_names: null,
    email: 'ama@example.com',
    email_verified: true,
    phone: null,
    alt_phone: null,
    institution: null,
    discipline: null,
    avatar_url: null,
    has_password: true,
    google_linked: false,
    locale: 'en',
    theme: 'system',
    citation_style: 'apa7',
    created_at: '2026-11-20T09:30:00+00:00',
    notification_prefs: {},
    features: {
      upload_images: false,
      add_link: false,
      paste_text: false,
      discover: false,
      chat: false,
    },
    ...overrides,
  };
}
