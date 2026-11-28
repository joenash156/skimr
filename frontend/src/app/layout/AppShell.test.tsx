import { act, fireEvent, screen, waitFor, within } from '@testing-library/react';
import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import { tokenStore } from '@/lib/auth/tokenStore';
import i18n from '@/lib/i18n';
import { setSidebarCollapsed } from '@/lib/sidebar';
import { setTheme } from '@/lib/theme';
import { apiError, mockApi, respond, session } from '@/test/mockAdapter';
import { loadTranslations } from '@/test/render';
import { meFixture, renderApp } from '@/test/renderApp';

vi.mock('@marsidev/react-turnstile', () => import('@/test/turnstileMock'));

beforeAll(() => loadTranslations(['common', 'auth']));
beforeEach(async () => {
  tokenStore.clear();
  localStorage.clear();
  setTheme('system');
  setSidebarCollapsed(false);
  await i18n.changeLanguage('en');
});
afterEach(() => vi.useRealTimers());

function signedIn(overrides: Record<string, unknown> = {}) {
  const calls: { method: string | undefined; url: string | undefined; body: unknown }[] = [];
  let me = meFixture(overrides);
  mockApi((config) => {
    const body = config.data ? JSON.parse(String(config.data)) : null;
    calls.push({ method: config.method, url: config.url, body });
    if (config.url === '/auth/refresh') return respond(config, 200, session('t'));
    if (config.url === '/auth/logout') return respond(config, 204);
    if (config.url === '/me' && config.method === 'patch') {
      me = { ...me, ...body };
      return respond(config, 200, { data: me });
    }
    if (config.url === '/me') return respond(config, 200, { data: me });
    return respond(config, 404, apiError('NOT_FOUND'));
  });
  return calls;
}

/** The first match, or a clear failure (no non-null assertions, per lint). */
function first<T>(items: T[]): T {
  const [item] = items;
  if (item === undefined) throw new Error('Expected at least one element');
  return item;
}

const sidebar = () => first(screen.getAllByRole('navigation', { name: 'Main' }));

describe('AppShell sidebar (brief B1 task 2)', () => {
  it('lists the spec 5.3 items and marks the current page', async () => {
    signedIn();
    renderApp('/projects');
    await screen.findByRole('heading', { name: 'Projects' });
    const nav = within(sidebar());
    for (const name of [
      'Overview',
      'Projects',
      'Find sources',
      'Shared with me',
      'Notifications',
      'Settings',
    ]) {
      expect(nav.getByRole('link', { name })).toBeInTheDocument();
    }
    expect(nav.getByRole('link', { name: 'Projects' })).toHaveAttribute('aria-current', 'page');
    expect(nav.getByRole('link', { name: 'Overview' })).not.toHaveAttribute('aria-current');
    expect(nav.queryByRole('link', { name: 'Admin' })).not.toBeInTheDocument();
  });

  it('shows Admin only to admins and super admins', async () => {
    signedIn({ role: 'admin' });
    renderApp('/');
    await screen.findByRole('heading', { name: 'Overview' });
    expect(within(sidebar()).getByRole('link', { name: 'Admin' })).toHaveAttribute(
      'href',
      '/admin',
    );
  });

  it('collapses to the icon rail and remembers it (spec 14.3)', async () => {
    signedIn();
    const { user } = renderApp('/');
    const collapse = await screen.findByRole('button', { name: 'Collapse sidebar' });
    expect(collapse).toHaveAttribute('aria-expanded', 'true');
    await user.click(collapse);
    expect(document.documentElement.dataset.sidebar).toBe('collapsed');
    expect(localStorage.getItem('sidebar')).toBe('collapsed');
    const expand = screen.getByRole('button', { name: 'Expand sidebar' });
    expect(expand).toHaveAttribute('aria-expanded', 'false');
    await user.click(expand);
    expect(localStorage.getItem('sidebar')).toBe('expanded');
  });

  it('opens the drawer from the menu button and closes it on navigation and Esc', async () => {
    signedIn();
    const { user } = renderApp('/');
    const menu = await screen.findByRole('button', { name: 'Open menu' });
    await user.click(menu);

    const drawer = document.getElementById('app-drawer') as HTMLDialogElement;
    expect(drawer).toHaveAttribute('open');
    expect(menu).toHaveAttribute('aria-expanded', 'true');

    await user.click(within(drawer).getByRole('link', { name: 'Settings' }));
    await waitFor(() => expect(drawer).not.toHaveAttribute('open'));
    expect(await screen.findByRole('heading', { name: 'Settings' })).toBeInTheDocument();

    await user.click(menu);
    expect(drawer).toHaveAttribute('open');
    fireEvent(drawer, new Event('cancel', { cancelable: true }));
    await waitFor(() => expect(drawer).not.toHaveAttribute('open'));
  });

  it('has a skip link to the main content', async () => {
    signedIn();
    renderApp('/');
    const skip = await screen.findByRole('link', { name: 'Skip to content' });
    expect(skip).toHaveAttribute('href', '#main');
    expect(document.getElementById('main')).toBeInTheDocument();
  });
});

describe('Topbar (spec 5.3, brief B1 task 5)', () => {
  it('saves the theme choice locally and to the account (PATCH /me)', async () => {
    const calls = signedIn({ theme: 'system' });
    const { user } = renderApp('/');
    await screen.findByRole('heading', { name: 'Overview' });
    const topbarTheme = first(screen.getAllByRole('group', { name: 'Theme' }));
    await user.click(within(topbarTheme).getByRole('radio', { name: 'Dark' }));

    expect(localStorage.getItem('theme')).toBe('dark');
    expect(document.documentElement).toHaveClass('dark');
    await waitFor(() =>
      expect(calls).toContainEqual({ method: 'patch', url: '/me', body: { theme: 'dark' } }),
    );
  });

  it("applies the account's saved theme after sign-in (users.theme)", async () => {
    signedIn({ theme: 'dark' });
    renderApp('/');
    await screen.findByRole('heading', { name: 'Overview' });
    await waitFor(() => expect(document.documentElement).toHaveClass('dark'));
    expect(localStorage.getItem('theme')).toBe('dark');
  });

  it('switches language and saves users.locale (spec 13)', async () => {
    const calls = signedIn();
    const { user } = renderApp('/');
    await screen.findByRole('heading', { name: 'Overview' });
    await user.click(first(screen.getAllByRole('button', { name: 'Language: English' })));
    await user.click(await screen.findByRole('menuitemradio', { name: 'Français' }));
    expect(await screen.findByRole('heading', { name: "Vue d'ensemble" })).toBeInTheDocument();
    await waitFor(() =>
      expect(calls).toContainEqual({ method: 'patch', url: '/me', body: { locale: 'fr' } }),
    );
  });

  it('searches projects from the global search box', async () => {
    signedIn();
    const { user, router } = renderApp('/');
    const search = await screen.findByRole('searchbox', { name: 'Search' });
    await user.type(search, 'remote work{Enter}');
    expect(router.state.location.pathname).toBe('/projects');
    expect(router.state.location.search).toBe('?q=remote%20work');
  });

  it('has a notifications bell link', async () => {
    signedIn();
    renderApp('/');
    const header = await screen.findByRole('banner');
    expect(within(header).getByRole('link', { name: 'Notifications' })).toHaveAttribute(
      'href',
      '/notifications',
    );
  });

  it('logs out from the avatar menu', async () => {
    const calls = signedIn();
    const { user, router } = renderApp('/');
    await user.click(await screen.findByRole('button', { name: 'Account menu' }));
    const panel = await screen.findByRole('dialog', { name: 'Account menu' });
    expect(within(panel).getByText('ama@example.com')).toBeInTheDocument();
    await act(async () => {
      await user.click(within(panel).getByRole('button', { name: 'Log out' }));
    });
    await waitFor(() => expect(router.state.location.pathname).toBe('/login'));
    expect(calls.some((c) => c.url === '/auth/logout')).toBe(true);
    expect(tokenStore.get()).toBeNull();
  });
});
