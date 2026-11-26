import { describe, expect, it } from 'vitest';
import { getAvailableAdminUserActions, MockAdminUsersDataSource } from './adminUsersMock';

describe('MockAdminUsersDataSource', () => {
  it('filters by search, status, and role before paginating', async () => {
    const source = new MockAdminUsersDataSource();

    const result = await source.listUsers({
      page: 1,
      perPage: 1,
      search: 'example',
      status: 'active',
      role: 'admin',
    });

    expect(result.total).toBe(1);
    expect(result.items).toHaveLength(1);
    expect(result.items[0].email).toBe('nana.admin@example.test');
  });

  it('sorts by sign-up date newest-first unless ascending is requested', async () => {
    const source = new MockAdminUsersDataSource();
    const newest = await source.listUsers({ page: 1, perPage: 10 });
    const oldest = await source.listUsers({ page: 1, perPage: 10, sortDirection: 'asc' });

    expect(newest.items[0].id).toBe('8b22a613-dc71-4b85-93b1-42a76b91c5e0');
    expect(oldest.items[0].id).toBe('92258a47-6496-49f6-b941-fcb3a3b2a026');
  });

  it('returns detail data without exposing mutable mock state', async () => {
    const source = new MockAdminUsersDataSource();
    const user = await source.getUserDetail('a14c82f7-153d-4a90-b2d0-77e6639a1f53');

    expect(user?.activity[0].type).toBe('suspended');
    if (user !== null) {
      user.activity[0].actorRole = 'user';
    }

    const unchanged = await source.getUserDetail('a14c82f7-153d-4a90-b2d0-77e6639a1f53');
    expect(unchanged?.activity[0].actorRole).toBe('admin');
    await expect(source.getUserDetail('missing-user')).resolves.toBeNull();
  });

  it('hides user-management actions from admins for admin and super-admin rows', async () => {
    const source = new MockAdminUsersDataSource();
    const regularUser = await source.getUserDetail('3f2c9a1e-7b5d-4e8a-9c1f-2a6b8d0e4f17');
    const admin = await source.getUserDetail('51cfc492-fd2f-4fa8-83b8-b592f47d3341');
    const superAdmin = await source.getUserDetail('92258a47-6496-49f6-b941-fcb3a3b2a026');

    expect(regularUser).not.toBeNull();
    expect(admin).not.toBeNull();
    expect(superAdmin).not.toBeNull();
    if (regularUser !== null && admin !== null && superAdmin !== null) {
      expect(getAvailableAdminUserActions('user', regularUser)).toEqual([]);
      expect(getAvailableAdminUserActions('admin', admin)).toEqual([]);
      expect(getAvailableAdminUserActions('admin', superAdmin)).toEqual([]);
      expect(getAvailableAdminUserActions('super_admin', admin)).toEqual([]);
      expect(getAvailableAdminUserActions('super_admin', superAdmin)).toEqual([]);
      await expect(
        source.performUserAction(regularUser.id, 'suspend', 'user', 'Attempted action'),
      ).resolves.toEqual({ ok: false, code: 'forbidden' });
      await expect(
        source.performUserAction(admin.id, 'suspend', 'admin', 'Reviewed request'),
      ).resolves.toEqual({ ok: false, code: 'forbidden' });
      await expect(
        source.performUserAction(superAdmin.id, 'suspend', 'super_admin', ''),
      ).resolves.toEqual({ ok: false, code: 'forbidden' });
    }
  });

  it('offers only actions that match the viewer role and user status', async () => {
    const source = new MockAdminUsersDataSource();
    const expectedActions = new Map([
      ['3f2c9a1e-7b5d-4e8a-9c1f-2a6b8d0e4f17', ['suspend', 'delete', 'reset_quota']],
      [
        '8b22a613-dc71-4b85-93b1-42a76b91c5e0',
        ['suspend', 'delete', 'reset_quota', 'resend_verification'],
      ],
      ['a14c82f7-153d-4a90-b2d0-77e6639a1f53', ['reactivate', 'delete', 'reset_quota']],
      ['c991cf52-8e5e-441a-a7e9-e099326ac439', []],
      ['51cfc492-fd2f-4fa8-83b8-b592f47d3341', []],
      ['92258a47-6496-49f6-b941-fcb3a3b2a026', []],
    ]);

    for (const [userId, expected] of expectedActions) {
      const user = await source.getUserDetail(userId);
      expect(user).not.toBeNull();
      if (user !== null) {
        expect(getAvailableAdminUserActions('admin', user)).toEqual(expected);
      }
    }

    const activeUser = await source.getUserDetail('3f2c9a1e-7b5d-4e8a-9c1f-2a6b8d0e4f17');
    expect(activeUser).not.toBeNull();
    if (activeUser !== null) {
      expect(getAvailableAdminUserActions('super_admin', activeUser)).toEqual([
        'suspend',
        'delete',
        'reset_quota',
      ]);
    }
  });

  it('returns not-found for action targets that do not exist', async () => {
    const source = new MockAdminUsersDataSource();

    await expect(
      source.performUserAction('missing-user', 'suspend', 'admin', 'Reviewed request'),
    ).resolves.toEqual({ ok: false, code: 'not_found' });
  });

  it('requires a reason for every allowed action and leaves data unchanged', async () => {
    const cases = [
      { userId: '3f2c9a1e-7b5d-4e8a-9c1f-2a6b8d0e4f17', action: 'suspend' },
      { userId: '3f2c9a1e-7b5d-4e8a-9c1f-2a6b8d0e4f17', action: 'delete' },
      { userId: '3f2c9a1e-7b5d-4e8a-9c1f-2a6b8d0e4f17', action: 'reset_quota' },
      { userId: '8b22a613-dc71-4b85-93b1-42a76b91c5e0', action: 'resend_verification' },
      { userId: 'a14c82f7-153d-4a90-b2d0-77e6639a1f53', action: 'reactivate' },
    ] as const;

    for (const { userId, action } of cases) {
      const source = new MockAdminUsersDataSource();
      const before = await source.getUserDetail(userId);

      await expect(source.performUserAction(userId, action, 'admin', '  ')).resolves.toEqual({
        ok: false,
        code: 'reason_required',
      });
      await expect(source.getUserDetail(userId)).resolves.toEqual(before);
    }
  });

  it('applies allowed actions and records mock activity', async () => {
    const source = new MockAdminUsersDataSource(undefined, () => new Date('2026-11-24T09:00:00Z'));
    const userId = '3f2c9a1e-7b5d-4e8a-9c1f-2a6b8d0e4f17';

    const suspended = await source.performUserAction(
      userId,
      'suspend',
      'admin',
      'Reviewed request',
    );
    expect(suspended.ok).toBe(true);
    if (suspended.ok) {
      expect(suspended.user.status).toBe('suspended');
      expect(suspended.user.activity[0]).toMatchObject({
        type: 'suspended',
        actorRole: 'admin',
        createdAt: '2026-11-24T09:00:00.000Z',
        reason: 'Reviewed request',
      });
    }

    const reactivated = await source.performUserAction(
      userId,
      'reactivate',
      'admin',
      'Review complete',
    );
    expect(reactivated.ok).toBe(true);
    if (reactivated.ok) {
      expect(reactivated.user.status).toBe('active');
      expect(reactivated.user.activity[0].type).toBe('reactivated');
    }

    const deleted = await source.performUserAction(
      userId,
      'delete',
      'admin',
      'User requested removal',
    );
    expect(deleted).toMatchObject({ ok: true, user: { status: 'deleted' } });
    await expect(
      source.performUserAction(userId, 'reactivate', 'admin', 'Review complete'),
    ).resolves.toEqual({ ok: false, code: 'invalid_action' });
  });

  it('resets mock quota and permits verification resend only when pending', async () => {
    const source = new MockAdminUsersDataSource();
    const activeUserId = '3f2c9a1e-7b5d-4e8a-9c1f-2a6b8d0e4f17';
    const pendingUserId = '8b22a613-dc71-4b85-93b1-42a76b91c5e0';

    await expect(
      source.performUserAction(activeUserId, 'reset_quota', 'super_admin', 'Monthly reset'),
    ).resolves.toMatchObject({ ok: true, user: { usage: { aiCallsThisMonth: 0 } } });
    await expect(
      source.performUserAction(activeUserId, 'resend_verification', 'admin', 'Retry requested'),
    ).resolves.toEqual({ ok: false, code: 'invalid_action' });
    await expect(
      source.performUserAction(
        pendingUserId,
        'resend_verification',
        'admin',
        'User requested retry',
      ),
    ).resolves.toEqual(
      expect.objectContaining({
        ok: true,
        user: expect.objectContaining({
          status: 'pending_verification',
          activity: expect.arrayContaining([
            expect.objectContaining({ type: 'verification_resent' }),
          ]),
        }),
      }),
    );
  });

  it('normalizes invalid bounds and caps page size at the API maximum', async () => {
    const source = new MockAdminUsersDataSource();
    const invalid = await source.listUsers({ page: Number.NaN, perPage: Number.NaN });
    const oversized = await source.listUsers({ page: 0, perPage: 500 });

    expect(invalid.page).toBe(1);
    expect(invalid.perPage).toBe(1);
    expect(invalid.items).toHaveLength(1);
    expect(oversized.perPage).toBe(100);
    expect(oversized.items).toHaveLength(6);
  });
});
