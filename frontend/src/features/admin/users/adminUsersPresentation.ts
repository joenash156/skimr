import type {
  AdminUserActivity,
  AdminUserActivityType,
  AdminUserDetail,
  AdminUserRole,
  AdminUserStatus,
  AdminUsersPage,
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

export type AdminUsersPaginationViewModel = {
  page: number;
  perPage: number;
  totalPages: number;
  start: number;
  end: number;
  total: number;
  hasPrevious: boolean;
  hasNext: boolean;
};

export type AdminUsersListContentViewModel =
  | { kind: 'loading' }
  | {
      kind: 'error';
      message: string | null;
      fallbackTranslationKey: 'users.loadError';
    }
  | {
      kind: 'empty';
      pagination: AdminUsersPaginationViewModel;
      refreshError: string | null;
    }
  | {
      kind: 'results';
      rows: AdminUserRowViewModel[];
      pagination: AdminUsersPaginationViewModel;
      refreshError: string | null;
    };

export type AdminUsersListContentInput = {
  isLoading: boolean;
  errorMessage: string | null;
  data: AdminUsersPage | null;
  locale: AdminUsersLocale;
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

export function toAdminUsersPaginationViewModel(
  result: AdminUsersPage,
): AdminUsersPaginationViewModel {
  const page = Number.isFinite(result.page) ? Math.max(1, Math.floor(result.page)) : 1;
  const perPage = Number.isFinite(result.perPage)
    ? Math.min(100, Math.max(1, Math.floor(result.perPage)))
    : 10;
  const total = Number.isFinite(result.total) ? Math.max(0, Math.floor(result.total)) : 0;
  const totalPages = Math.max(1, Math.ceil(total / perPage));
  const start = result.items.length > 0 && page <= totalPages ? (page - 1) * perPage + 1 : 0;
  const end = start === 0 ? 0 : Math.min(total, start + Math.min(result.items.length, perPage) - 1);

  return {
    page,
    perPage,
    totalPages,
    start,
    end,
    total,
    hasPrevious: page > 1,
    hasNext: page < totalPages,
  };
}

export function toAdminUsersListContentViewModel(
  input: AdminUsersListContentInput,
): AdminUsersListContentViewModel {
  const errorMessage = input.errorMessage?.trim() || null;

  if (input.isLoading && input.data === null) {
    return { kind: 'loading' };
  }

  if (input.data === null) {
    return {
      kind: 'error',
      message: errorMessage,
      fallbackTranslationKey: 'users.loadError',
    };
  }

  const pagination = toAdminUsersPaginationViewModel(input.data);
  if (input.data.items.length === 0) {
    return { kind: 'empty', pagination, refreshError: errorMessage };
  }

  return {
    kind: 'results',
    rows: input.data.items.map((user) => toAdminUserRowViewModel(user, input.locale)),
    pagination,
    refreshError: errorMessage,
  };
}
