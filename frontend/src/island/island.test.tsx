import { act, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeAll, describe, expect, it, vi } from 'vitest';
import { goTo } from '@/lib/browser';
import { apiError, mockApi, respond, session } from '@/test/mockAdapter';
import { loadTranslations } from '@/test/render';
import { meFixture } from '@/test/renderApp';
import { openAuthModal } from './mountAuthModal';

vi.mock('@marsidev/react-turnstile', () => import('@/test/turnstileMock'));
vi.mock('@/lib/browser', () => ({ goTo: vi.fn() }));

beforeAll(() => loadTranslations(['common', 'auth']));

describe('auth modal on the public site (src/island)', () => {
  it('opens over the page, switches views in place, and enters the app after logging in', async () => {
    mockApi((config) => {
      if (config.url === '/auth/login') return respond(config, 200, session('t1'));
      if (config.url === '/me') return respond(config, 200, { data: meFixture() });
      return respond(config, 401, apiError('UNAUTHENTICATED'));
    });
    const user = userEvent.setup();

    act(() => openAuthModal('register'));
    expect(await screen.findByRole('dialog', { name: 'Welcome to Skimr' })).toBeInTheDocument();

    const tabs = within(screen.getByRole('navigation', { name: 'Log in or sign up' }));
    await user.click(tabs.getByRole('link', { name: 'Log in' }));
    const dialog = await screen.findByRole('dialog', { name: 'Welcome back' });
    expect(goTo).not.toHaveBeenCalled();

    await user.type(within(dialog).getByLabelText(/^Email/), 'ama@example.com');
    await user.type(within(dialog).getByLabelText(/^Password/), 'quietriver7');
    await user.click(within(dialog).getByRole('button', { name: 'Pass bot check' }));
    await user.click(within(dialog).getByRole('button', { name: 'Log in' }));

    await waitFor(() => expect(goTo).toHaveBeenCalledWith('/app/'));
  });
});
