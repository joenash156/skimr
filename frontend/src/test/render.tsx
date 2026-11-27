import { QueryClientProvider } from '@tanstack/react-query';
import { render, type RenderResult } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { Suspense, type ReactElement } from 'react';
import { MemoryRouter } from 'react-router';
import { createQueryClient } from '@/app/providers';
import i18n, { i18nReady } from '@/lib/i18n';

/** Loads the namespaces a test needs, in English, before rendering. */
export async function loadTranslations(namespaces: string[] = ['common'], language = 'en') {
  await i18nReady;
  await i18n.changeLanguage(language);
  await i18n.loadNamespaces(namespaces);
}

/**
 * Renders inside the same providers the app uses (minus AuthProvider, which
 * tests opt into), with a router and a user-event instance.
 */
export function renderUi(
  ui: ReactElement,
  { route = '/' }: { route?: string } = {},
): RenderResult & { user: ReturnType<typeof userEvent.setup> } {
  const user = userEvent.setup();
  const result = render(
    <QueryClientProvider client={createQueryClient()}>
      <MemoryRouter initialEntries={[route]}>
        <Suspense fallback={null}>{ui}</Suspense>
      </MemoryRouter>
    </QueryClientProvider>,
  );
  return { ...result, user };
}
