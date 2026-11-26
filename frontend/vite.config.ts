import { fileURLToPath, URL } from 'node:url';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import { defineConfig } from 'vitest/config';

// Where /api goes in development (brief B0 task 1, 3a):
//   pnpm dev        -> P1's local PHP API on :8080 (same origin for the refresh cookie)
//   pnpm dev:mock   -> the Prism mock on :4010 (`pnpm mock` in the repo root), whose
//                      paths have no /api/v1 prefix, so it is stripped here.
const API_TARGET = 'http://localhost:8080';
const MOCK_TARGET = 'http://localhost:4010';

export default defineConfig(({ mode }) => ({
  // PHP serves the build at /app/* (backend SpaController).
  base: '/app/',
  plugins: [react(), tailwindcss()],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url)),
    },
  },
  server: {
    port: 5173,
    strictPort: true,
    proxy: {
      '/api':
        mode === 'mock'
          ? {
              target: MOCK_TARGET,
              changeOrigin: true,
              rewrite: (path) => path.replace(/^\/api\/v1/, ''),
            }
          : { target: API_TARGET },
    },
  },
  build: {
    outDir: '../backend/public/app',
    emptyOutDir: true,
    sourcemap: false,
  },
  test: {
    environment: 'jsdom',
    setupFiles: ['./src/test/setup.ts'],
    include: ['src/**/*.test.{ts,tsx}', 'scripts/**/*.test.mjs'],
    restoreMocks: true,
    css: false,
  },
}));
