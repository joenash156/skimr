import { screen, waitFor } from '@testing-library/react';
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import { tokenStore } from '@/lib/auth/tokenStore';
import { apiError, mockApi, respond, session } from '@/test/mockAdapter';
import { loadTranslations } from '@/test/render';
import { meFixture, renderApp } from '@/test/renderApp';

vi.mock('@marsidev/react-turnstile', () => import('@/test/turnstileMock'));

beforeAll(() => loadTranslations(['common', 'auth']));
beforeEach(() => tokenStore.clear());

function signedInAs(overrides: Record<string, unknown> = {}) {
  mockApi((config) => {
    if (config.url === '/auth/refresh') return respond(config, 200, session('t'));
    if (config.url === '/me') return respond(config, 200, { data: meFixture(overrides) });
    return respond(config, 404, apiError('NOT_FOUND'));
  });
}

function signedOut() {
  mockApi((config) => respond(config, 401, apiError('UNAUTHENTICATED')));
}

describe('routing guards (brief B1 task 3)', () => {
  it('sends signed-out visitors to /login and remembers where they were going', async () => {
    signedOut();
    const { router } = renderApp('/projects?sort=title');
    await screen.findByRole('heading', { name: 'Log in to Skimr' });
    expect(router.state.location.pathname).toBe('/login');
    expect(router.state.location.search).toBe(
      `?next=${encodeURIComponent('/projects?sort=title')}`,
    );
  });

  it('lets signed-in users in', async () => {
    signedInAs();
    renderApp('/projects');
    expect(await screen.findByRole('heading', { name: 'Projects' })).toBeInTheDocument();
  });

  it('sends pending_verification users to the verify screen', async () => {
    signedInAs({ status: 'pending_verification', email_verified: false });
    const { router } = renderApp('/projects');
    await screen.findByRole('heading', { name: 'Verify your email' });
    expect(router.state.location.pathname).toBe('/verify-email');
  });

  it('redirects signed-in users away from the login page', async () => {
    signedInAs();
    const { router } = renderApp('/login');
    await waitFor(() => expect(router.state.location.pathname).toBe('/'));
  });

  it('shows the admin area to admins only (RequireRole)', async () => {
    signedInAs({ role: 'user' });
    const first = renderApp('/admin/users');
    expect(await screen.findByRole('heading', { name: 'Page not found' })).toBeInTheDocument();
    first.unmount();

    tokenStore.clear();
    signedInAs({ role: 'super_admin' });
    renderApp('/admin/users');
    expect(await screen.findByRole('heading', { name: 'Admin' })).toBeInTheDocument();
  });

  it('has a 404 page for unknown addresses', async () => {
    signedOut();
    renderApp('/no/such/page');
    expect(await screen.findByRole('heading', { name: 'Page not found' })).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Go to the dashboard' })).toHaveAttribute('href', '/');
  });
});
