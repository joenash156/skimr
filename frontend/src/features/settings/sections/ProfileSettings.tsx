import { useAuth } from '@/lib/auth/useAuth';
import { ProfileForm } from '../components/ProfileForm';

/** Settings > Profile (spec 5.3): names, phone, other phone, institution, discipline. */
export function ProfileSettings() {
  const { user } = useAuth();
  if (!user) return null;
  // One form per account; after a save it resets to the saved values itself.
  return <ProfileForm key={user.id} user={user} />;
}
