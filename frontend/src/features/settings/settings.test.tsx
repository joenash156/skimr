import { screen, waitFor, within } from '@testing-library/react';
import type { InternalAxiosRequestConfig } from 'axios';
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import { tokenStore } from '@/lib/auth/tokenStore';
import i18n from '@/lib/i18n';
import { apiError, mockApi, respond, session } from '@/test/mockAdapter';
import { loadTranslations } from '@/test/render';
import { meFixture, renderApp } from '@/test/renderApp';

vi.mock('@marsidev/react-turnstile', () => import('@/test/turnstileMock'));

beforeAll(() => loadTranslations(['common', 'auth', 'settings']));
beforeEach(async () => {
  tokenStore.clear();
  localStorage.clear();
  await i18n.changeLanguage('en');
});

type Call = { method: string | undefined; url: string | undefined; body: unknown };
type Route = (
  config: InternalAxiosRequestConfig,
  body: unknown,
) => ReturnType<typeof respond> | undefined;

/** Signed in as the fixture user; PATCH /me merges into it; `route` answers the rest. */
function signedIn(overrides: Record<string, unknown> = {}, route: Route = () => undefined) {
  const calls: Call[] = [];
  let me = meFixture(overrides);
  mockApi((config) => {
    const body = config.data ? JSON.parse(String(config.data)) : null;
    calls.push({ method: config.method, url: config.url, body });
    const answer = route(config, body);
    if (answer) return answer;
    if (config.url === '/auth/refresh') return respond(config, 200, session('t'));
    if (config.url === '/me' && config.method === 'patch') {
      me = { ...me, ...body };
      return respond(config, 200, { data: me });
    }
    if (config.url === '/me') return respond(config, 200, { data: me });
    return respond(config, 404, apiError('NOT_FOUND'));
  });
  return calls;
}

describe('Settings layout (brief B2 task 5)', () => {
  it('opens on Profile and has a URL per section', async () => {
    signedIn();
    const { router, user } = renderApp('/settings');
    await screen.findByRole('heading', { name: 'Profile' });
    expect(router.state.location.pathname).toBe('/settings/profile');

    const sections = screen.getByRole('navigation', { name: 'Settings sections' });
    await user.click(within(sections).getByRole('link', { name: 'Notifications' }));
    expect(await screen.findByRole('heading', { name: 'Email notifications' })).toBeInTheDocument();
    expect(router.state.location.pathname).toBe('/settings/notifications');
  });
});

describe('Profile', () => {
  it('sends only the fields that changed (brief 3a)', async () => {
    const calls = signedIn({ other_names: 'Akosua' });
    const { user } = renderApp('/settings/profile');
    const institution = await screen.findByLabelText(/^Institution/);
    expect(screen.getByRole('button', { name: 'Save changes' })).toBeDisabled();

    await user.type(institution, 'University of Ghana');
    await user.clear(screen.getByLabelText(/^Other names/));
    await user.click(screen.getByRole('button', { name: 'Save changes' }));

    expect(await screen.findByText('Profile saved')).toBeInTheDocument();
    expect(calls.filter((c) => c.method === 'patch')).toEqual([
      {
        method: 'patch',
        url: '/me',
        body: { other_names: null, institution: 'University of Ghana' },
      },
    ]);
  });

  it('shows API field errors under the inputs', async () => {
    signedIn({}, (config) =>
      config.method === 'patch'
        ? respond(
            config,
            422,
            apiError('VALIDATION_FAILED', 'Check the form.', {
              fields: { discipline: 'Too long.' },
            }),
          )
        : undefined,
    );
    const { user } = renderApp('/settings/profile');
    await user.type(await screen.findByLabelText(/^Discipline/), 'Economics');
    await user.click(screen.getByRole('button', { name: 'Save changes' }));
    expect(await screen.findByText('Too long.')).toBeInTheDocument();
  });
});

describe('Security', () => {
  it('changes the password, then ends the session and asks to log in again', async () => {
    const calls = signedIn({}, (config) =>
      config.url === '/me/password' ? respond(config, 204) : undefined,
    );
    const { user, router } = renderApp('/settings/security');
    await user.type(await screen.findByLabelText(/^Current password/), 'old-password-1');
    await user.type(screen.getByLabelText(/^New password/), 'Quiet-River-2026');
    await user.type(screen.getByLabelText(/^Confirm new password/), 'Quiet-River-2026');
    await user.click(screen.getByRole('button', { name: 'Change password' }));

    await waitFor(() => expect(router.state.location.pathname).toBe('/login'));
    expect(
      await screen.findByText('Password changed. Log in with your new password.'),
    ).toBeInTheDocument();
    expect(tokenStore.get()).toBeNull();
    expect(calls.find((c) => c.url === '/me/password')?.body).toEqual({
      current_password: 'old-password-1',
      password: 'Quiet-River-2026',
      password_confirmation: 'Quiet-River-2026',
    });
  });

  it('puts a wrong current password under its field', async () => {
    signedIn({}, (config) =>
      config.url === '/me/password'
        ? respond(config, 401, apiError('INVALID_CREDENTIALS', 'That password is not right.'))
        : undefined,
    );
    const { user } = renderApp('/settings/security');
    await user.type(await screen.findByLabelText(/^Current password/), 'wrong');
    await user.type(screen.getByLabelText(/^New password/), 'Quiet-River-2026');
    await user.type(screen.getByLabelText(/^Confirm new password/), 'Quiet-River-2026');
    await user.click(screen.getByRole('button', { name: 'Change password' }));
    expect(await screen.findByText('That password is not right.')).toBeInTheDocument();
    expect(screen.getByLabelText(/^Current password/)).toHaveAttribute('aria-invalid', 'true');
  });

  it("starts an email change and shows the API's message", async () => {
    const calls = signedIn({}, (config) =>
      config.url === '/me/email'
        ? respond(config, 202, { data: { message: 'Check new@example.com for a link.' } })
        : undefined,
    );
    const { user } = renderApp('/settings/security');
    await user.type(await screen.findByLabelText(/^New email address/), 'new@example.com');
    await user.type(screen.getByLabelText(/^Your password/), 'quietriver7');
    await user.click(screen.getByRole('button', { name: 'Send verification link' }));
    expect(await screen.findByText('Check new@example.com for a link.')).toBeInTheDocument();
    expect(calls.find((c) => c.url === '/me/email')?.body).toEqual({
      email: 'new@example.com',
      password: 'quietriver7',
    });
  });

  it('points Google-only accounts to "forgot password", which works signed in', async () => {
    signedIn({ has_password: false, google_linked: true });
    const { user, router } = renderApp('/settings/security');
    expect(await screen.findByText('Connected')).toBeInTheDocument();
    expect(screen.queryByLabelText(/^Current password/)).not.toBeInTheDocument();
    const links = screen.getAllByRole('link', { name: 'Set a password' });
    await user.click(links[0] as HTMLElement);
    expect(await screen.findByRole('heading', { name: 'Reset your password' })).toBeInTheDocument();
    expect(router.state.location.pathname).toBe('/forgot-password');
  });
});

describe('Preferences and notifications', () => {
  it('saves the citation style straight away', async () => {
    const calls = signedIn();
    const { user } = renderApp('/settings/preferences');
    await user.selectOptions(await screen.findByLabelText(/^Citation style/), 'mla9');
    await waitFor(() =>
      expect(calls).toContainEqual({
        method: 'patch',
        url: '/me',
        body: { citation_style: 'mla9' },
      }),
    );
  });

  it('saves each email switch with the others unchanged', async () => {
    const calls = signedIn({
      notification_prefs: { processing_done: true, report_ready: false, project_invite: true },
    });
    const { user } = renderApp('/settings/notifications');
    const digest = await screen.findByRole('switch', { name: 'Weekly digest' });
    expect(digest).not.toBeChecked();
    expect(screen.getByRole('switch', { name: 'Reports ready' })).not.toBeChecked();
    await user.click(digest);
    await waitFor(() =>
      expect(screen.getByRole('switch', { name: 'Weekly digest' })).toBeChecked(),
    );
    expect(calls.filter((c) => c.method === 'patch')).toEqual([
      {
        method: 'patch',
        url: '/me',
        body: {
          notification_prefs: {
            processing_done: true,
            report_ready: false,
            project_invite: true,
            weekly_digest: true,
          },
        },
      },
    ]);
  });
});

describe('Data', () => {
  it('deletes the account after the password, then signs out', async () => {
    const calls = signedIn({}, (config) =>
      config.url === '/me' && config.method === 'delete' ? respond(config, 204) : undefined,
    );
    const { user, router } = renderApp('/settings/data');
    await user.click(await screen.findByRole('button', { name: 'Delete my account' }));
    const dialog = await screen.findByRole('dialog', { name: 'Delete your account?' });
    await user.type(within(dialog).getByLabelText(/^Enter your password/), 'quietriver7');
    await user.click(within(dialog).getByRole('button', { name: 'Delete account' }));

    await waitFor(() => expect(router.state.location.pathname).toBe('/login'));
    expect(calls.find((c) => c.method === 'delete')?.body).toEqual({ password: 'quietriver7' });
    expect(tokenStore.get()).toBeNull();
  });
});
