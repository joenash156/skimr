import { PendingVerification } from '../components/PendingVerification';
import { VerifyWithToken } from '../components/VerifyWithToken';
import { useTokenFromUrl } from '../useTokenFromUrl';

/**
 * /app/verify-email (spec 5.2).
 * - With ?token= (sign-up link, or confirming a new email address): verify it.
 * - Without: the "Verify your email" screen for pending users, with a resend
 *   button (at most 3 per hour; the API's 429 message is shown).
 */
export function VerifyEmailPage() {
  const token = useTokenFromUrl();
  return token ? <VerifyWithToken token={token} /> : <PendingVerification />;
}
