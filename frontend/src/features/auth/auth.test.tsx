import { screen, waitFor } from '@testing-library/react';
import type { InternalAxiosRequestConfig } from 'axios';
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import { tokenStore } from '@/lib/auth/tokenStore';
import { apiError, mockApi, respond, session } from '@/test/mockAdapter';
import { loadTranslations } from '@/test/render';
import { meFixture, renderApp } from '@/test/renderApp';
import { turnstileReset } from '@/test/turnstileMock';

vi.mock('@marsidev/react-turnstile', () => import('@/test/turnstileMock'));

beforeAll(() => loadTranslations(['common', 'auth']));
beforeEach(() => {
  tokenStore.clear();
  turnstileReset.mockClear();
});

type Route = (config: InternalAxiosRequestConfig) => ReturnType<typeof respond> | undefined;

/** Signed out on load; `route` answers everything else. */
function api(route: Route) {
  const calls: { url: string | undefined; body: unknown }[] = [];
  mockApi((config) => {
    calls.push({ url: config.url, body: config.data ? JSON.parse(String(config.data)) : null });
    const answer = route(config);
    if (answer) return answer;
    if (config.url === '/auth/refresh') return respond(config, 401, apiError('UNAUTHENTICATED'));
    return respond(config, 404, apiError('NOT_FOUND'));
  });
  return calls;
}

describe('LoginPage (spec 5.2)', () => {
  it('logs in with Turnstile and goes to the page asked for', async () => {
    const calls = api((config) => {
      if (config.url === '/auth/login') return respond(config, 200, session('t1'));
      if (config.url === '/me') return respond(config, 200, { data: meFixture() });
      return undefined;
    });
    const { user, router } = renderApp(`/login?next=${encodeURIComponent('/projects')}`);

    await user.type(await screen.findByLabelText(/^Email/), 'ama@example.com');
    await user.type(screen.getByLabelText(/^Password/), 'quietriver7');
    await user.click(screen.getByRole('button', { name: 'Pass bot check' }));
    await user.click(screen.getByRole('button', { name: 'Log in' }));

    await waitFor(() => expect(router.state.location.pathname).toBe('/projects'));
    expect(calls.find((c) => c.url === '/auth/login')?.body).toEqual({
      email: 'ama@example.com',
      password: 'quietriver7',
      turnstile_token: 'token-login',
    });
    expect(tokenStore.get()).toBe('t1');
  });

  it('asks for the bot check before sending anything', async () => {
    const calls = api(() => undefined);
    const { user } = renderApp('/login');
    await user.type(await screen.findByLabelText(/^Email/), 'ama@example.com');
    await user.type(screen.getByLabelText(/^Password/), 'x');
    await user.click(screen.getByRole('button', { name: 'Log in' }));
    expect(await screen.findByText('Please complete the security check.')).toBeInTheDocument();
    expect(calls.some((c) => c.url === '/auth/login')).toBe(false);
  });

  it('validates on the client first', async () => {
    api(() => undefined);
    const { user } = renderApp('/login');
    await user.click(await screen.findByRole('button', { name: 'Log in' }));
    expect(await screen.findAllByText('This field is required.')).toHaveLength(2);
    expect(screen.getByLabelText(/^Email/)).toHaveAttribute('aria-invalid', 'true');
    expect(screen.getByLabelText(/^Password/)).toHaveAttribute('aria-invalid', 'true');
  });

  it('shows the API message with its reference, and resets Turnstile (tokens work once)', async () => {
    api((config) =>
      config.url === '/auth/login'
        ? respond(config, 401, apiError('INVALID_CREDENTIALS', 'Wrong email or password.'))
        : undefined,
    );
    const { user } = renderApp('/login');
    await user.type(await screen.findByLabelText(/^Email/), 'ama@example.com');
    await user.type(screen.getByLabelText(/^Password/), 'nope');
    await user.click(screen.getByRole('button', { name: 'Pass bot check' }));
    await user.click(screen.getByRole('button', { name: 'Log in' }));

    const alert = await screen.findByRole('alert');
    expect(alert).toHaveTextContent('Wrong email or password.');
    expect(alert).toHaveTextContent('Reference: req-test');
    expect(turnstileReset).toHaveBeenCalled();
  });

  it('sends pending users to the verify screen after login', async () => {
    api((config) => {
      if (config.url === '/auth/login') return respond(config, 200, session('t1'));
      if (config.url === '/me') {
        return respond(config, 200, { data: meFixture({ status: 'pending_verification' }) });
      }
      return undefined;
    });
    const { user, router } = renderApp('/login');
    await user.type(await screen.findByLabelText(/^Email/), 'ama@example.com');
    await user.type(screen.getByLabelText(/^Password/), 'quietriver7');
    await user.click(screen.getByRole('button', { name: 'Pass bot check' }));
    await user.click(screen.getByRole('button', { name: 'Log in' }));
    await waitFor(() => expect(router.state.location.pathname).toBe('/verify-email'));
  });

  it('explains a failed Google sign-in (?error=google)', async () => {
    api(() => undefined);
    renderApp('/login?error=google');
    expect(await screen.findByText(/Google sign-in didn't work/)).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Continue with Google' })).toHaveAttribute(
      'href',
      '/api/v1/auth/google/redirect',
    );
  });

  it('links Google after the password (?link=google)', async () => {
    const calls = api((config) => {
      if (config.url === '/auth/google/link') return respond(config, 200, session('t2'));
      if (config.url === '/me') return respond(config, 200, { data: meFixture() });
      return undefined;
    });
    const { user, router } = renderApp('/login?link=google');
    expect(
      await screen.findByRole('heading', { name: 'Confirm your password to link Google' }),
    ).toBeInTheDocument();
    await user.type(screen.getByLabelText(/^Password/), 'quietriver7');
    await user.click(screen.getByRole('button', { name: 'Link Google and log in' }));
    await waitFor(() => expect(router.state.location.pathname).toBe('/'));
    expect(calls.find((c) => c.url === '/auth/google/link')?.body).toEqual({
      password: 'quietriver7',
    });
  });
});

describe('RegisterPage (spec 5.2)', () => {
  async function fill(user: ReturnType<typeof renderApp>['user']) {
    await user.type(await screen.findByLabelText(/^First name/), 'Ama');
    await user.type(screen.getByLabelText(/^Last name/), 'Mensah');
    await user.type(screen.getByLabelText(/^Email/), 'ama@example.com');
    await user.type(screen.getByRole('textbox', { name: /^Phone/ }), '024 123 4567');
    await user.type(screen.getByLabelText(/^Password/), 'Quiet-River-77');
    await user.type(screen.getByLabelText(/^Confirm password/), 'Quiet-River-77');
    await user.click(screen.getByRole('checkbox'));
    await user.click(screen.getByRole('button', { name: 'Pass bot check' }));
  }

  it('creates the account, then asks the user to check their email', async () => {
    const calls = api((config) =>
      config.url === '/auth/register'
        ? respond(config, 201, { data: { user: meFixture({ status: 'pending_verification' }) } })
        : undefined,
    );
    const { user } = renderApp('/register');
    await fill(user);
    await user.click(screen.getByRole('button', { name: 'Create account' }));

    expect(await screen.findByRole('heading', { name: 'Check your email' })).toBeInTheDocument();
    expect(calls.find((c) => c.url === '/auth/register')?.body).toEqual({
      first_name: 'Ama',
      last_name: 'Mensah',
      email: 'ama@example.com',
      phone: '+233241234567',
      password: 'Quiet-River-77',
      password_confirmation: 'Quiet-River-77',
      accept_terms: true,
      locale: 'en',
      turnstile_token: 'token-register',
    });
  });

  it('puts API field errors under their inputs (brief B1 task 4)', async () => {
    api((config) =>
      config.url === '/auth/register'
        ? respond(
            config,
            422,
            apiError('VALIDATION_FAILED', 'Check the form.', {
              fields: { password: 'This password is too common.' },
            }),
          )
        : undefined,
    );
    const { user } = renderApp('/register');
    await fill(user);
    await user.click(screen.getByRole('button', { name: 'Create account' }));
    expect(await screen.findByText('This password is too common.')).toBeInTheDocument();
    expect(screen.getByLabelText(/^Password/)).toHaveAttribute('aria-invalid', 'true');
  });

  it('checks the password rules and the terms on the client', async () => {
    api(() => undefined);
    const { user } = renderApp('/register');
    await user.type(await screen.findByLabelText(/^Password/), 'short1');
    await user.type(screen.getByLabelText(/^Confirm password/), 'different');
    await user.click(screen.getByRole('button', { name: 'Create account' }));
    expect(await screen.findByText('Use at least 10 characters.')).toBeInTheDocument();
    expect(screen.getByText("The passwords don't match.")).toBeInTheDocument();
    expect(
      screen.getByText('Please accept the Terms and Privacy policy to continue.'),
    ).toBeInTheDocument();
  });
});

describe('emailed links (brief 3a)', () => {
  it('verifies the token once, even in StrictMode, and removes it from the address bar', async () => {
    const calls = api((config) =>
      config.url === '/auth/verify-email'
        ? respond(config, 200, { data: { user: meFixture() } })
        : undefined,
    );
    const { router } = renderApp('/verify-email?token=abc123');
    expect(await screen.findByRole('heading', { name: 'Email verified' })).toBeInTheDocument();
    expect(calls.filter((c) => c.url === '/auth/verify-email')).toEqual([
      { url: '/auth/verify-email', body: { token: 'abc123' } },
    ]);
    expect(router.state.location.search).toBe('');
  });

  it('explains an expired verification link', async () => {
    api((config) =>
      config.url === '/auth/verify-email'
        ? respond(config, 400, apiError('INVALID_TOKEN', 'This link has expired.'))
        : undefined,
    );
    renderApp('/verify-email?token=old');
    expect(await screen.findByText('This link has expired.')).toBeInTheDocument();
  });

  it('resets the password with the token, then sends the user to log in', async () => {
    const calls = api((config) =>
      config.url === '/auth/password/reset' ? respond(config, 204) : undefined,
    );
    const { user } = renderApp('/reset-password?token=r1');
    await user.type(await screen.findByLabelText(/^New password/), 'Quiet-River-77');
    await user.type(screen.getByLabelText(/^Confirm password/), 'Quiet-River-77');
    await user.click(screen.getByRole('button', { name: 'Set new password' }));
    expect(await screen.findByRole('heading', { name: 'Password changed' })).toBeInTheDocument();
    expect(calls.find((c) => c.url === '/auth/password/reset')?.body).toEqual({
      token: 'r1',
      password: 'Quiet-River-77',
      password_confirmation: 'Quiet-River-77',
    });
  });

  it('explains a reset link without a token', async () => {
    api(() => undefined);
    renderApp('/reset-password');
    expect(await screen.findByText(/This link is incomplete/)).toBeInTheDocument();
  });
});

describe('ForgotPasswordPage', () => {
  it("shows the API's neutral answer", async () => {
    const calls = api((config) =>
      config.url === '/auth/password/forgot'
        ? respond(config, 200, { data: { message: "If that email exists, we've sent a link." } })
        : undefined,
    );
    const { user } = renderApp('/forgot-password');
    await user.type(await screen.findByLabelText(/^Email/), 'ama@example.com');
    await user.click(screen.getByRole('button', { name: 'Pass bot check' }));
    await user.click(screen.getByRole('button', { name: 'Send reset link' }));
    expect(await screen.findByText("If that email exists, we've sent a link.")).toBeInTheDocument();
    expect(calls.find((c) => c.url === '/auth/password/forgot')?.body).toEqual({
      email: 'ama@example.com',
      turnstile_token: 'token-forgot_password',
    });
  });
});
