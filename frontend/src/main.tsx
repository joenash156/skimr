import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import './index.css';
import './lib/i18n';
import { Providers } from './app/providers';
import { AppRouter } from './app/router';
import { watchSystemTheme } from './lib/theme';

// index.html already applied the saved theme before paint; keep "system" live.
watchSystemTheme();

const root = document.getElementById('root');
if (!root) throw new Error('Missing #root element in index.html');

createRoot(root).render(
  <StrictMode>
    <Providers>
      <AppRouter />
    </Providers>
  </StrictMode>,
);
