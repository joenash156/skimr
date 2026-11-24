import { describe, expect, it } from 'vitest';
import { MockAdminUsersDataSource } from './adminUsersMock';

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
      user.activity[0].actor = 'Changed locally';
    }

    const unchanged = await source.getUserDetail('a14c82f7-153d-4a90-b2d0-77e6639a1f53');
    expect(unchanged?.activity[0].actor).toBe('Admin Example');
    await expect(source.getUserDetail('missing-user')).resolves.toBeNull();
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
