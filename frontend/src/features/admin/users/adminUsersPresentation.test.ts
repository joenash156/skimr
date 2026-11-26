import { describe, expect, it } from 'vitest';
import { MockAdminUsersDataSource } from './adminUsersMock';
import {
  formatAdminUserDateTime,
  toAdminUserDetailViewModel,
  toAdminUsersListContentViewModel,
  toAdminUsersPaginationViewModel,
  toAdminUserRowViewModel,
} from './adminUsersPresentation';

describe('admin user presentation model', () => {
  it('builds a translated row model without exposing detail-only data', async () => {
    const source = new MockAdminUsersDataSource();
    const page = await source.listUsers({ page: 1, perPage: 10 });
    const user = page.items.find((item) => item.email === 'kwame.owusu@example.test');

    expect(user).toBeDefined();
    if (user === undefined) {
      return;
    }

    const model = toAdminUserRowViewModel(user, 'en');

    expect(model).toMatchObject({
      displayName: 'Kwame Kojo Owusu',
      roleTranslationKey: 'users.role.user',
      statusTranslationKey: 'users.status.pending_verification',
      verificationTranslationKey: 'users.verification.notVerified',
      projectCount: '0',
      sourceCount: '0',
      lastLoginAt: null,
    });
    expect(model).not.toHaveProperty('phone');
    expect(model).not.toHaveProperty('activity');
  });

  it('formats numbers and labels using the requested locale', async () => {
    const source = new MockAdminUsersDataSource();
    const page = await source.listUsers({ page: 1, perPage: 10 });
    const user = page.items.find((item) => item.email === 'ama.mensah@example.test');

    expect(user).toBeDefined();
    if (user === undefined) {
      return;
    }

    const model = toAdminUserRowViewModel(user, 'fr');
    expect(model.projectCount).toBe(new Intl.NumberFormat('fr').format(3));
    expect(model.sourceCount).toBe(new Intl.NumberFormat('fr').format(18));
    expect(model.verificationTranslationKey).toBe('users.verification.verified');
    expect(model.createdAt).toBe(
      new Intl.DateTimeFormat('fr', { dateStyle: 'medium', timeStyle: 'short' }).format(
        new Date(user.createdAt),
      ),
    );
  });

  it('builds a localized detail model with profile, usage, and activity', async () => {
    const source = new MockAdminUsersDataSource();
    const user = await source.getUserDetail('a14c82f7-153d-4a90-b2d0-77e6639a1f53');

    expect(user).not.toBeNull();
    if (user === null) {
      return;
    }

    const model = toAdminUserDetailViewModel(user, 'es');
    expect(model).toMatchObject({
      phone: null,
      institution: 'Example Research Institute',
      discipline: 'Sociology',
      aiCallsThisMonth: new Intl.NumberFormat('es').format(11),
      activity: [
        {
          typeTranslationKey: 'users.activity.suspended',
          actorRoleTranslationKey: 'users.role.admin',
          reason: null,
        },
        {
          typeTranslationKey: 'users.activity.signed_in',
          actorRoleTranslationKey: 'users.role.user',
          reason: null,
        },
      ],
    });
    expect(model.activity[0].createdAt).not.toBeNull();
  });

  it('returns null for absent or invalid timestamps', () => {
    expect(formatAdminUserDateTime(null, 'en')).toBeNull();
    expect(formatAdminUserDateTime('not-a-date', 'es')).toBeNull();
  });

  it('calculates page ranges and navigation availability', () => {
    const source = new MockAdminUsersDataSource();

    return source.listUsers({ page: 1, perPage: 4 }).then((firstPage) => {
      expect(toAdminUsersPaginationViewModel(firstPage)).toEqual({
        page: 1,
        perPage: 4,
        totalPages: 2,
        start: 1,
        end: 4,
        total: 6,
        hasPrevious: false,
        hasNext: true,
      });
    });
  });

  it('handles empty results and out-of-range pages without invalid ranges', () => {
    const emptyResult = {
      items: [],
      page: 4,
      perPage: 5,
      total: 0,
    };
    const outOfRangeResult = {
      ...emptyResult,
      total: 12,
    };

    expect(toAdminUsersPaginationViewModel(emptyResult)).toEqual({
      page: 4,
      perPage: 5,
      totalPages: 1,
      start: 0,
      end: 0,
      total: 0,
      hasPrevious: true,
      hasNext: false,
    });
    expect(toAdminUsersPaginationViewModel(outOfRangeResult)).toEqual({
      page: 4,
      perPage: 5,
      totalPages: 3,
      start: 0,
      end: 0,
      total: 12,
      hasPrevious: true,
      hasNext: false,
    });
  });

  it('models loading and initial error states with a translated fallback', () => {
    expect(
      toAdminUsersListContentViewModel({
        isLoading: true,
        errorMessage: null,
        data: null,
        locale: 'en',
      }),
    ).toEqual({ kind: 'loading' });

    expect(
      toAdminUsersListContentViewModel({
        isLoading: false,
        errorMessage: null,
        data: null,
        locale: 'en',
      }),
    ).toEqual({
      kind: 'error',
      message: null,
      fallbackTranslationKey: 'users.loadError',
    });

    expect(
      toAdminUsersListContentViewModel({
        isLoading: false,
        errorMessage: 'API unavailable',
        data: null,
        locale: 'en',
      }),
    ).toMatchObject({ kind: 'error', message: 'API unavailable' });
  });

  it('models empty results and preserves API errors while stale rows remain available', async () => {
    const source = new MockAdminUsersDataSource();
    const emptyPage = await source.listUsers({
      page: 1,
      perPage: 10,
      search: 'no matching account',
    });
    expect(
      toAdminUsersListContentViewModel({
        isLoading: false,
        errorMessage: null,
        data: emptyPage,
        locale: 'en',
      }),
    ).toMatchObject({
      kind: 'empty',
      pagination: { start: 0, end: 0, total: 0 },
      refreshError: null,
    });

    const page = await source.listUsers({ page: 1, perPage: 2 });
    expect(
      toAdminUsersListContentViewModel({
        isLoading: true,
        errorMessage: 'Refresh failed',
        data: page,
        locale: 'es',
      }),
    ).toMatchObject({
      kind: 'results',
      rows: [{ displayName: 'Kwame Kojo Owusu' }, { displayName: 'Ama Mensah' }],
      pagination: { start: 1, end: 2, total: 6 },
      refreshError: 'Refresh failed',
    });
  });
});
