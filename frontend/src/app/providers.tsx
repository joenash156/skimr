import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { Suspense, useState, type ReactNode } from 'react';
import { Toaster } from '@/components/ui/Toast';
import { ApiError } from '@/lib/api/errors';
import { AuthProvider } from '@/lib/auth/AuthProvider';

/** Don't retry what retrying can't fix: 4xx answers (validation, auth, quota...). */
function shouldRetry(failureCount: number, error: unknown): boolean {
  if (error instanceof ApiError && error.status !== null && error.status < 500) return false;
  return failureCount < 2;
}

export function createQueryClient(): QueryClient {
  return new QueryClient({
    defaultOptions: {
      queries: { retry: shouldRetry, staleTime: 30_000 },
      mutations: { retry: false },
    },
  });
}

export function Providers({ children }: { children: ReactNode }) {
  const [queryClient] = useState(createQueryClient);
  return (
    <QueryClientProvider client={queryClient}>
      <AuthProvider>
        {/* Translations load lazily (spec 13); render nothing until `common` is ready. */}
        <Suspense fallback={null}>
          {children}
          <Toaster />
        </Suspense>
      </AuthProvider>
    </QueryClientProvider>
  );
}
