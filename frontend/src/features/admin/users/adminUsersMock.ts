export type AdminUserRole = 'user' | 'admin' | 'super_admin';

export type AdminUserStatus =
  | 'pending_verification'
  | 'active'
  | 'invited'
  | 'suspended'
  | 'deleted';

export type AdminUserSummary = {
  id: string;
  firstName: string;
  lastName: string;
  otherNames: string | null;
  email: string;
  role: AdminUserRole;
  status: AdminUserStatus;
  emailVerified: boolean;
  projectCount: number;
  sourceCount: number;
  createdAt: string;
  lastLoginAt: string | null;
};

export type AdminUserActivityType =
  | 'account_created'
  | 'email_verified'
  | 'signed_in'
  | 'suspended'
  | 'reactivated'
  | 'user_deleted'
  | 'quota_reset'
  | 'verification_resent';

export type AdminUserActivity = {
  id: string;
  type: AdminUserActivityType;
  actorRole: AdminUserRole;
  createdAt: string;
  reason?: string;
};

export type AdminUserDetail = AdminUserSummary & {
  phone: string | null;
  institution: string | null;
  discipline: string | null;
  usage: {
    aiCallsThisMonth: number;
  };
  activity: AdminUserActivity[];
};

export type AdminUsersQuery = {
  page: number;
  perPage: number;
  search?: string;
  status?: AdminUserStatus;
  role?: AdminUserRole;
  sortDirection?: 'asc' | 'desc';
};

export type AdminUserAction =
  | 'suspend'
  | 'reactivate'
  | 'delete'
  | 'reset_quota'
  | 'resend_verification';

export type AdminUserActionErrorCode =
  | 'not_found'
  | 'forbidden'
  | 'reason_required'
  | 'invalid_action';

export type AdminUserActionResult =
  | { ok: true; user: AdminUserDetail }
  | { ok: false; code: AdminUserActionErrorCode };

export type AdminUsersPage = {
  items: AdminUserSummary[];
  page: number;
  perPage: number;
  total: number;
};

export interface AdminUsersDataSource {
  listUsers(query: AdminUsersQuery): Promise<AdminUsersPage>;
  getUserDetail(id: string): Promise<AdminUserDetail | null>;
  performUserAction(
    id: string,
    action: AdminUserAction,
    actorRole: AdminUserRole,
    reason: string,
  ): Promise<AdminUserActionResult>;
}

export function getAvailableAdminUserActions(
  actorRole: AdminUserRole,
  user: AdminUserSummary,
): AdminUserAction[] {
  if (actorRole === 'user' || user.role !== 'user') {
    return [];
  }

  const actions: AdminUserAction[] = [];

  if (user.status === 'active' || user.status === 'pending_verification') {
    actions.push('suspend');
  }
  if (user.status === 'suspended') {
    actions.push('reactivate');
  }
  if (user.status !== 'deleted') {
    actions.push('delete', 'reset_quota');
  }
  if (user.status === 'pending_verification' && !user.emailVerified) {
    actions.push('resend_verification');
  }

  return actions;
}

const mockUsers: AdminUserDetail[] = [
  {
    id: '3f2c9a1e-7b5d-4e8a-9c1f-2a6b8d0e4f17',
    firstName: 'Ama',
    lastName: 'Mensah',
    otherNames: null,
    email: 'ama.mensah@example.test',
    role: 'user',
    status: 'active',
    emailVerified: true,
    projectCount: 3,
    sourceCount: 18,
    createdAt: '2026-11-20T09:30:00+00:00',
    lastLoginAt: '2026-11-23T14:12:00+00:00',
    phone: '+233241234567',
    institution: 'Example University',
    discipline: 'Public health',
    usage: { aiCallsThisMonth: 42 },
    activity: [
      {
        id: 'activity-ama-1',
        type: 'signed_in',
        actorRole: 'user',
        createdAt: '2026-11-23T14:12:00+00:00',
      },
      {
        id: 'activity-ama-2',
        type: 'email_verified',
        actorRole: 'user',
        createdAt: '2026-11-20T09:35:00+00:00',
      },
      {
        id: 'activity-ama-3',
        type: 'account_created',
        actorRole: 'user',
        createdAt: '2026-11-20T09:30:00+00:00',
      },
    ],
  },
  {
    id: '8b22a613-dc71-4b85-93b1-42a76b91c5e0',
    firstName: 'Kwame',
    lastName: 'Owusu',
    otherNames: 'Kojo',
    email: 'kwame.owusu@example.test',
    role: 'user',
    status: 'pending_verification',
    emailVerified: false,
    projectCount: 0,
    sourceCount: 0,
    createdAt: '2026-11-23T11:05:00+00:00',
    lastLoginAt: null,
    phone: null,
    institution: null,
    discipline: null,
    usage: { aiCallsThisMonth: 0 },
    activity: [
      {
        id: 'activity-kwame-1',
        type: 'account_created',
        actorRole: 'user',
        createdAt: '2026-11-23T11:05:00+00:00',
      },
    ],
  },
  {
    id: 'a14c82f7-153d-4a90-b2d0-77e6639a1f53',
    firstName: 'Esi',
    lastName: 'Boateng',
    otherNames: null,
    email: 'esi.boateng@example.test',
    role: 'user',
    status: 'suspended',
    emailVerified: true,
    projectCount: 1,
    sourceCount: 6,
    createdAt: '2026-11-18T08:20:00+00:00',
    lastLoginAt: '2026-11-21T16:40:00+00:00',
    phone: null,
    institution: 'Example Research Institute',
    discipline: 'Sociology',
    usage: { aiCallsThisMonth: 11 },
    activity: [
      {
        id: 'activity-esi-1',
        type: 'suspended',
        actorRole: 'admin',
        createdAt: '2026-11-22T10:00:00+00:00',
      },
      {
        id: 'activity-esi-2',
        type: 'signed_in',
        actorRole: 'user',
        createdAt: '2026-11-21T16:40:00+00:00',
      },
    ],
  },
  {
    id: 'c991cf52-8e5e-441a-a7e9-e099326ac439',
    firstName: 'Kojo',
    lastName: 'Asare',
    otherNames: null,
    email: 'kojo.asare@example.test',
    role: 'user',
    status: 'deleted',
    emailVerified: true,
    projectCount: 2,
    sourceCount: 9,
    createdAt: '2026-11-12T13:45:00+00:00',
    lastLoginAt: '2026-11-19T07:15:00+00:00',
    phone: null,
    institution: null,
    discipline: 'History',
    usage: { aiCallsThisMonth: 8 },
    activity: [
      {
        id: 'activity-kojo-1',
        type: 'account_created',
        actorRole: 'user',
        createdAt: '2026-11-12T13:45:00+00:00',
      },
    ],
  },
  {
    id: '51cfc492-fd2f-4fa8-83b8-b592f47d3341',
    firstName: 'Nana',
    lastName: 'Example',
    otherNames: null,
    email: 'nana.admin@example.test',
    role: 'admin',
    status: 'active',
    emailVerified: true,
    projectCount: 0,
    sourceCount: 0,
    createdAt: '2026-11-10T12:00:00+00:00',
    lastLoginAt: '2026-11-24T07:40:00+00:00',
    phone: null,
    institution: null,
    discipline: null,
    usage: { aiCallsThisMonth: 0 },
    activity: [
      {
        id: 'activity-admin-1',
        type: 'signed_in',
        actorRole: 'admin',
        createdAt: '2026-11-24T07:40:00+00:00',
      },
    ],
  },
  {
    id: '92258a47-6496-49f6-b941-fcb3a3b2a026',
    firstName: 'Yaw',
    lastName: 'Example',
    otherNames: null,
    email: 'yaw.superadmin@example.test',
    role: 'super_admin',
    status: 'active',
    emailVerified: true,
    projectCount: 0,
    sourceCount: 0,
    createdAt: '2026-11-01T10:00:00+00:00',
    lastLoginAt: '2026-11-24T06:55:00+00:00',
    phone: null,
    institution: null,
    discipline: null,
    usage: { aiCallsThisMonth: 0 },
    activity: [
      {
        id: 'activity-superadmin-1',
        type: 'signed_in',
        actorRole: 'super_admin',
        createdAt: '2026-11-24T06:55:00+00:00',
      },
    ],
  },
];

const copyDetail = (user: AdminUserDetail): AdminUserDetail => ({
  ...user,
  usage: { ...user.usage },
  activity: user.activity.map((activity) => ({ ...activity })),
});

const toSummary = (user: AdminUserDetail): AdminUserSummary => ({
  id: user.id,
  firstName: user.firstName,
  lastName: user.lastName,
  otherNames: user.otherNames,
  email: user.email,
  role: user.role,
  status: user.status,
  emailVerified: user.emailVerified,
  projectCount: user.projectCount,
  sourceCount: user.sourceCount,
  createdAt: user.createdAt,
  lastLoginAt: user.lastLoginAt,
});

const normalizePageValue = (value: number): number =>
  Number.isFinite(value) ? Math.max(1, Math.floor(value)) : 1;

export class MockAdminUsersDataSource implements AdminUsersDataSource {
  private readonly users: AdminUserDetail[];
  private nextActivityId = 1;

  constructor(
    users: readonly AdminUserDetail[] = mockUsers,
    private readonly clock: () => Date = () => new Date(),
  ) {
    this.users = users.map(copyDetail);
  }

  async listUsers(query: AdminUsersQuery): Promise<AdminUsersPage> {
    const page = normalizePageValue(query.page);
    const perPage = Math.min(100, normalizePageValue(query.perPage));
    const search = query.search?.trim().toLowerCase() ?? '';
    const direction = query.sortDirection === 'asc' ? 1 : -1;

    const filtered = this.users
      .filter((user) => query.status === undefined || user.status === query.status)
      .filter((user) => query.role === undefined || user.role === query.role)
      .filter((user) => {
        if (search === '') {
          return true;
        }

        return [
          user.firstName,
          user.otherNames ?? '',
          user.lastName,
          user.email,
        ].some((value) => value.toLowerCase().includes(search));
      })
      .sort((left, right) => direction * left.createdAt.localeCompare(right.createdAt));

    const start = (page - 1) * perPage;

    return {
      items: filtered.slice(start, start + perPage).map(toSummary),
      page,
      perPage,
      total: filtered.length,
    };
  }

  async getUserDetail(id: string): Promise<AdminUserDetail | null> {
    const user = this.users.find((candidate) => candidate.id === id);

    return user === undefined ? null : copyDetail(user);
  }

  async performUserAction(
    id: string,
    action: AdminUserAction,
    actorRole: AdminUserRole,
    reason: string,
  ): Promise<AdminUserActionResult> {
    const user = this.users.find((candidate) => candidate.id === id);
    if (user === undefined) {
      return { ok: false, code: 'not_found' };
    }

    if (actorRole === 'user' || user.role !== 'user') {
      return { ok: false, code: 'forbidden' };
    }

    if (reason.trim() === '') {
      return { ok: false, code: 'reason_required' };
    }

    if (!getAvailableAdminUserActions(actorRole, user).includes(action)) {
      return { ok: false, code: 'invalid_action' };
    }

    const activityType: Record<AdminUserAction, AdminUserActivityType> = {
      suspend: 'suspended',
      reactivate: 'reactivated',
      delete: 'user_deleted',
      reset_quota: 'quota_reset',
      resend_verification: 'verification_resent',
    };

    if (action === 'suspend') {
      user.status = 'suspended';
    } else if (action === 'reactivate') {
      user.status = 'active';
    } else if (action === 'delete') {
      user.status = 'deleted';
    } else if (action === 'reset_quota') {
      user.usage.aiCallsThisMonth = 0;
    }

    user.activity.unshift({
      id: `mock-activity-${this.nextActivityId}`,
      type: activityType[action],
      actorRole,
      createdAt: this.clock().toISOString(),
      reason: reason.trim(),
    });
    this.nextActivityId += 1;

    return { ok: true, user: copyDetail(user) };
  }
}
