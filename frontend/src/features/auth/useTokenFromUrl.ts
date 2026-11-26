import { useEffect, useState } from 'react';
import { useSearchParams } from 'react-router';

/**
 * Reads ?token= from an emailed link (verify-email, reset-password,
 * accept-invite), then removes it from the address bar so it doesn't stay in
 * history or leak through screenshots (brief 3a). The token is kept in state.
 */
export function useTokenFromUrl(): string | null {
  const [searchParams, setSearchParams] = useSearchParams();
  const [token] = useState(() => searchParams.get('token'));

  useEffect(() => {
    if (!searchParams.has('token')) return;
    setSearchParams(
      (params) => {
        const next = new URLSearchParams(params);
        next.delete('token');
        return next;
      },
      { replace: true },
    );
  }, [searchParams, setSearchParams]);

  return token && token.trim() !== '' ? token : null;
}
