import type { AdminUserRole, AdminUserStatus, AdminUsersQuery } from './adminUsersMock';

export type AdminUsersListState = {
  page: number;
  perPage: number;
  search: string;
  status: AdminUserStatus | null;
  role: AdminUserRole | null;
  sortDirection: 'asc' | 'desc';
};

export type AdminUsersListAction =
  | { type: 'search_changed'; value: string }
  | { type: 'status_changed'; value: AdminUserStatus | null }
  | { type: 'role_changed'; value: AdminUserRole | null }
  | { type: 'page_changed'; value: number }
  | { type: 'page_size_changed'; value: number }
  | { type: 'sort_direction_changed'; value: 'asc' | 'desc' };

export const initialAdminUsersListState: AdminUsersListState = {
  page: 1,
  perPage: 10,
  search: '',
  status: null,
  role: null,
  sortDirection: 'desc',
};

const pageValue = (value: number): number =>
  Number.isFinite(value) ? Math.max(1, Math.floor(value)) : 1;

const pageSizeValue = (value: number): number =>
  Number.isFinite(value) ? Math.min(100, Math.max(1, Math.floor(value))) : 10;

export function reduceAdminUsersListState(
  state: AdminUsersListState,
  action: AdminUsersListAction,
): AdminUsersListState {
  switch (action.type) {
    case 'search_changed':
      return action.value === state.search ? state : { ...state, search: action.value, page: 1 };
    case 'status_changed':
      return action.value === state.status ? state : { ...state, status: action.value, page: 1 };
    case 'role_changed':
      return action.value === state.role ? state : { ...state, role: action.value, page: 1 };
    case 'page_changed':
      return { ...state, page: pageValue(action.value) };
    case 'page_size_changed': {
      const perPage = pageSizeValue(action.value);
      return perPage === state.perPage ? state : { ...state, perPage, page: 1 };
    }
    case 'sort_direction_changed':
      return action.value === state.sortDirection
        ? state
        : { ...state, sortDirection: action.value, page: 1 };
  }
}

export function toAdminUsersQuery(state: AdminUsersListState): AdminUsersQuery {
  const search = state.search.trim();

  return {
    page: pageValue(state.page),
    perPage: pageSizeValue(state.perPage),
    search: search === '' ? undefined : search,
    status: state.status ?? undefined,
    role: state.role ?? undefined,
    sortDirection: state.sortDirection,
  };
}
