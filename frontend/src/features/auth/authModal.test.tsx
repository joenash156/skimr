import { screen, waitFor, within } from '@testing-library/react';
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import { tokenStore } from '@/lib/auth/tokenStore';
import { goTo } from '@/lib/browser';
import { apiError, mockApi, respond } from '@/test/mockAdapter';
import { loadTranslations } from '@/test/render';
import { meFixture, renderApp } from '@/test/renderApp';

vi.mock('@marsidev/react-turnstile', () => import('@/test/turnstileMock'));
vi.mock('@/lib/browser', () => ({ goTo: vi.fn() }));

beforeAll(() => loadTranslations(['common', 'auth']));
beforeEach(() => {
  tokenStore.clear();
  vi.mocked(goTo).mockClear();
  mockApi((config) =>
    config.url === '/auth/register'
      ? respond(config, 201, { data: { user: meFixture({ status: 'pending_verification' }) } })
      : respond(config, 401, apiError('UNAUTHENTICATED')),
  );
});

const tabs = () => within(screen.getByRole('navigation', { name: 'Log in or sign up' }));

describe('auth modal on the /app routes (spec 5.2 note)', () => {
  it('is a dialog with Log in | Sign up that switches in place and keeps ?next', async () => {
    const { user, router } = renderApp(`/login?next=${encodeURIComponent('/projects')}`);

    const dialog = await screen.findByRole('dialog', { name: 'Log in to Skimr' });
    expect(within(dialog).getByLabelText(/^Email/)).toBeInTheDocument();
    expect(tabs().getByRole('link', { name: 'Log in' })).toHaveAttribute('aria-current', 'page');

    await user.click(tabs().getByRole('link', { name: 'Sign up' }));

    expect(await screen.findByRole('dialog', { name: 'Create your account' })).toBeInTheDocument();
    expect(router.state.location.pathname).toBe('/register');
    expect(router.state.location.search).toBe('?next=%2Fprojects');
    expect(tabs().getByRole('link', { name: 'Sign up' })).toHaveAttribute('aria-current', 'page');
  });

  it('opens "Forgot password?" inside the modal, with a way back', async () => {
    const { user, router } = renderApp('/login');

    await user.click(await screen.findByRole('link', { name: 'Forgot password?' }));

    expect(await screen.findByRole('dialog', { name: 'Reset your password' })).toBeInTheDocument();
    expect(router.state.location.pathname).toBe('/forgot-password');
    await user.click(screen.getByRole('link', { name: 'Back to log in' }));
    expect(await screen.findByRole('dialog', { name: 'Log in to Skimr' })).toBeInTheDocument();
  });

  it('goes back to the public site when closed', async () => {
    const { user } = renderApp('/register');

    await user.click(await screen.findByRole('button', { name: 'Close' }));

    expect(goTo).toHaveBeenCalledWith('/en/');
  });

  it('keeps the "check your email" screen after sign-up, then returns to log in', async () => {
    const { user, router } = renderApp('/register');
    await user.type(await screen.findByLabelText(/^First name/), 'Ama');
    await user.type(screen.getByLabelText(/^Last name/), 'Mensah');
    await user.type(screen.getByLabelText(/^Email/), 'ama@example.com');
    await user.type(screen.getByLabelText(/^Password/), 'Quiet-River-77');
    await user.type(screen.getByLabelText(/^Confirm password/), 'Quiet-River-77');
    await user.click(screen.getByRole('checkbox'));
    await user.click(screen.getByRole('button', { name: 'Pass bot check' }));
    await user.click(screen.getByRole('button', { name: 'Create account' }));

    const dialog = await screen.findByRole('dialog', { name: 'Check your email' });
    expect(within(dialog).getByText('ama@example.com')).toBeInTheDocument();
    expect(screen.queryByRole('navigation', { name: 'Log in or sign up' })).not.toBeInTheDocument();

    await user.click(within(dialog).getByRole('button', { name: 'Go to log in' }));
    await waitFor(() => expect(router.state.location.pathname).toBe('/login'));
  });
});
