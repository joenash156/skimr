import type {
  AdminUserActivity,
  AdminUserActivityType,
  AdminUserDetail,
  AdminUserRole,
  AdminUserStatus,
  AdminUserSummary,
} from './adminUsersMock';

export type AdminUsersLocale = 'en' | 'fr' | 'es';

export type AdminUserRowViewModel = {
  id: string;
  displayName: string;
  email: string;
  roleTranslationKey: `users.role.${AdminUserRole}`;
  statusTranslationKey: `users.status.${AdminUserStatus}`;
  verificationTranslationKey: 'users.verification.verified' | 'users.verification.notVerified';
  projectCount: string;
  sourceCount: string;
  createdAt: string | null;
  lastLoginAt: string | null;
};

export type AdminUserActivityViewModel = {
  id: string;
  typeTranslationKey: `users.activity.${AdminUserActivityType}`;
  actorRoleTranslationKey: `users.role.${AdminUserRole}`;
  createdAt: string | null;
  reason: string | null;
};

export type AdminUserDetailViewModel = {
  user: AdminUserRowViewModel;
  phone: string | null;
  institution: string | null;
  discipline: string | null;
  aiCallsThisMonth: string;
  activity: AdminUserActivityViewModel[];
};

const localeTags: Record<AdminUsersLocale, string> = {
  en: 'en',
  fr: 'fr',
  es: 'es',
};

export function formatAdminUserDateTime(
  value: string | null,
  locale: AdminUsersLocale,
): string | null {
  if (value === null) {
    return null;
  }

  const date = new Date(value);
  if (!Number.isFinite(date.getTime())) {
    return null;
  }

  return new Intl.DateTimeFormat(localeTags[locale], {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(date);
}

export function toAdminUserRowViewModel(
  user: AdminUserSummary,
  locale: AdminUsersLocale,
): AdminUserRowViewModel {
  const displayName = [user.firstName, user.otherNames, user.lastName]
    .filter((name): name is string => name !== null && name.trim() !== '')
    .join(' ');

  return {
    id: user.id,
    displayName,
    email: user.email,
    roleTranslationKey: `users.role.${user.role}`,
    statusTranslationKey: `users.status.${user.status}`,
    verificationTranslationKey: user.emailVerified
      ? 'users.verification.verified'
      : 'users.verification.notVerified',
    projectCount: new Intl.NumberFormat(localeTags[locale]).format(user.projectCount),
    sourceCount: new Intl.NumberFormat(localeTags[locale]).format(user.sourceCount),
    createdAt: formatAdminUserDateTime(user.createdAt, locale),
    lastLoginAt: formatAdminUserDateTime(user.lastLoginAt, locale),
  };
}

function toActivityViewModel(
  activity: AdminUserActivity,
  locale: AdminUsersLocale,
): AdminUserActivityViewModel {
  return {
    id: activity.id,
    typeTranslationKey: `users.activity.${activity.type}`,
    actorRoleTranslationKey: `users.role.${activity.actorRole}`,
    createdAt: formatAdminUserDateTime(activity.createdAt, locale),
    reason: activity.reason ?? null,
  };
}

export function toAdminUserDetailViewModel(
  user: AdminUserDetail,
  locale: AdminUsersLocale,
): AdminUserDetailViewModel {
  return {
    user: toAdminUserRowViewModel(user, locale),
    phone: user.phone,
    institution: user.institution,
    discipline: user.discipline,
    aiCallsThisMonth: new Intl.NumberFormat(localeTags[locale]).format(user.usage.aiCallsThisMonth),
    activity: user.activity.map((activity) => toActivityViewModel(activity, locale)),
  };
}
