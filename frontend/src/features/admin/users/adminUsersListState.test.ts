import { describe, expect, it } from 'vitest';
import {
  initialAdminUsersListState,
  reduceAdminUsersListState,
  toAdminUsersQuery,
} from './adminUsersListState';

describe('admin users list state', () => {
  it('starts on the first page sorted by newest sign-up date', () => {
    expect(initialAdminUsersListState).toEqual({
      page: 1,
      perPage: 10,
      search: '',
      status: null,
      role: null,
      sortDirection: 'desc',
    });
  });

  it('resets to page one when the search, filters, or sort changes', () => {
    const state = {
      ...initialAdminUsersListState,
      page: 4,
    };

    expect(
      reduceAdminUsersListState(state, {
        type: 'search_changed',
        value: 'ama',
      }),
    ).toMatchObject({
      page: 1,
      search: 'ama',
    });
    expect(
      reduceAdminUsersListState(state, {
        type: 'status_changed',
        value: 'suspended',
      }),
    ).toMatchObject({
      page: 1,
      status: 'suspended',
    });
    expect(
      reduceAdminUsersListState(state, {
        type: 'role_changed',
        value: 'admin',
      }),
    ).toMatchObject({
      page: 1,
      role: 'admin',
    });
    expect(
      reduceAdminUsersListState(state, {
        type: 'sort_direction_changed',
        value: 'asc',
      }),
    ).toMatchObject({
      page: 1,
      sortDirection: 'asc',
    });
  });

  it('keeps the current state when a filter value has not changed', () => {
    const state = { ...initialAdminUsersListState, page: 3, search: 'ama' };

    expect(
      reduceAdminUsersListState(state, {
        type: 'search_changed',
        value: 'ama',
      }),
    ).toBe(state);
    expect(reduceAdminUsersListState(state, { type: 'role_changed', value: null })).toBe(state);
    expect(
      reduceAdminUsersListState(state, {
        type: 'sort_direction_changed',
        value: 'desc',
      }),
    ).toBe(state);
  });

  it('normalizes page and page-size values', () => {
    expect(
      reduceAdminUsersListState(initialAdminUsersListState, {
        type: 'page_changed',
        value: Number.NaN,
      }).page,
    ).toBe(1);
    expect(
      reduceAdminUsersListState(initialAdminUsersListState, {
        type: 'page_changed',
        value: 2.8,
      }).page,
    ).toBe(2);
    expect(
      reduceAdminUsersListState(initialAdminUsersListState, {
        type: 'page_size_changed',
        value: 500,
      }),
    ).toMatchObject({ page: 1, perPage: 100 });
    expect(
      reduceAdminUsersListState(initialAdminUsersListState, {
        type: 'page_size_changed',
        value: Number.NaN,
      }).perPage,
    ).toBe(10);
  });

  it('converts list controls into a normalized data-source query', () => {
    const state = {
      ...initialAdminUsersListState,
      page: 2.8,
      perPage: 500,
      search: '  Ama Mensah  ',
      status: 'active' as const,
      role: 'user' as const,
    };

    expect(toAdminUsersQuery(state)).toEqual({
      page: 2,
      perPage: 100,
      search: 'Ama Mensah',
      status: 'active',
      role: 'user',
      sortDirection: 'desc',
    });
    expect(
      toAdminUsersQuery({ ...initialAdminUsersListState, search: '   ' }).search,
    ).toBeUndefined();
  });
});
