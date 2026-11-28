import { AuthScreen } from './AuthScreen';

/** /app/login (spec 5.2). Also handles the Google return trips (brief 3a): ?link=google asks for the password to link Google; ?error=google says it failed. The auth modal on its own page (AuthScreen). */
export function LoginPage() {
  return <AuthScreen view="login" />;
}
