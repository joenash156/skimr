import { QueryClientProvider } from '@tanstack/react-query';
import { act, render, screen, waitFor } from '@testing-library/react';
import { StrictMode } from 'react';
import { beforeEach, describe, expect, it } from 'vitest';
import { createQueryClient } from '@/app/providers';
import { apiError, mockApi, respond, session } from '@/test/mockAdapter';
import i18n, { i18nReady } from '../i18n';
import { AuthProvider } from './AuthProvider';
import { tokenStore } from './tokenStore';
import { useAuth } from './useAuth';

const ME = {
  id: '3f2c9a1e-7b5d-4e8a-9c1f-2a6b8d0e4f17',
  role: 'user',
  status: 'active',
  first_name: 'Ama',
  last_name: 'Mensah',
  email: 'ama@example.com',
  email_verified: true,
  locale: 'fr',
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
};

function Probe() {
  const { status, user, sessionEndedReason, signOut } = useAuth();
  return (
    <div>
      <p data-testid="status">{status}</p>
      <p data-testid="user">{user?.email ?? '-'}</p>
      <p data-testid="reason">{sessionEndedReason ?? '-'}</p>
      <button type="button" onClick={() => void signOut()}>
        out
      </button>
    </div>
  );
}

function renderWithAuth() {
  return render(
    <StrictMode>
      <QueryClientProvider client={createQueryClient()}>
        <AuthProvider>
          <Probe />
        </AuthProvider>
      </QueryClientProvider>
    </StrictMode>,
  );
}

beforeEach(async () => {
  tokenStore.clear();
  await i18nReady;
  await i18n.changeLanguage('en');
});

describe('AuthProvider', () => {
  it('refreshes once on load (even in StrictMode), then loads /me', async () => {
    const calls: string[] = [];
    mockApi((config) => {
      calls.push(`${config.method} ${config.url}`);
      if (config.url === '/auth/refresh') return respond(config, 200, session('t1'));
      if (config.url === '/me') return respond(config, 200, { data: ME });
      return respond(config, 404, apiError('NOT_FOUND'));
    });

    renderWithAuth();
    expect(screen.getByTestId('status')).toHaveTextContent('loading');

    await waitFor(() => expect(screen.getByTestId('status')).toHaveTextContent('authenticated'));
    expect(screen.getByTestId('user')).toHaveTextContent('ama@example.com');
    expect(calls.filter((c) => c === 'post /auth/refresh')).toHaveLength(1);
    expect(calls).toContain('get /me');
    // The user's locale wins over the browser language (spec 13).
    await waitFor(() => expect(i18n.language).toBe('fr'));
  });

  it('is unauthenticated when there is no refresh cookie', async () => {
    mockApi((config) => respond(config, 401, apiError('UNAUTHENTICATED')));
    renderWithAuth();
    await waitFor(() => expect(screen.getByTestId('status')).toHaveTextContent('unauthenticated'));
    expect(tokenStore.get()).toBeNull();
  });

  it('signs out: calls /auth/logout and forgets the token and user', async () => {
    const calls: string[] = [];
    mockApi((config) => {
      calls.push(`${config.method} ${config.url}`);
      if (config.url === '/auth/refresh') return respond(config, 200, session('t1'));
      if (config.url === '/me') return respond(config, 200, { data: ME });
      if (config.url === '/auth/logout') return respond(config, 204);
      return respond(config, 404, apiError('NOT_FOUND'));
    });

    renderWithAuth();
    await waitFor(() => expect(screen.getByTestId('status')).toHaveTextContent('authenticated'));

    await act(async () => {
      screen.getByRole('button', { name: 'out' }).click();
    });
    await waitFor(() => expect(screen.getByTestId('status')).toHaveTextContent('unauthenticated'));
    expect(calls).toContain('post /auth/logout');
    expect(tokenStore.get()).toBeNull();
    expect(screen.getByTestId('user')).toHaveTextContent('-');
  });

  it('records why a session ended (REFRESH_REUSED) when a later refresh fails', async () => {
    let refreshes = 0;
    mockApi((config) => {
      if (config.url === '/auth/refresh') {
        refreshes += 1;
        return refreshes === 1
          ? respond(config, 200, session('t1'))
          : respond(config, 401, apiError('REFRESH_REUSED'));
      }
      if (config.url === '/me') {
        return config.headers.get('Authorization') === 'Bearer t1' && refreshes === 1
          ? respond(config, 200, { data: ME })
          : respond(config, 401, apiError('UNAUTHENTICATED'));
      }
      // Any other call: the access token was revoked elsewhere.
      return respond(config, 401, apiError('UNAUTHENTICATED'));
    });

    renderWithAuth();
    await waitFor(() => expect(screen.getByTestId('status')).toHaveTextContent('authenticated'));

    const { api } = await import('../api/client');
    await act(async () => {
      await api.get('/projects').catch(() => undefined);
    });

    await waitFor(() => expect(screen.getByTestId('status')).toHaveTextContent('unauthenticated'));
    expect(screen.getByTestId('reason')).toHaveTextContent('REFRESH_REUSED');
  });
});
