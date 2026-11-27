import { ArrowDown, ArrowUp, ArrowUpDown } from 'lucide-react';
import { useMemo, useState, type ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { cn } from '@/lib/cn';
import { Alert } from './Alert';
import { Button } from './Button';
import { Skeleton } from './Skeleton';

export type SortDirection = 'asc' | 'desc';
export interface SortState {
  key: string;
  direction: SortDirection;
}

export interface DataTableColumn<Row> {
  key: string;
  header: string;
  cell: (row: Row) => ReactNode;
  /** Makes the header a sort button. Give `sortValue` for client-side sorting. */
  sortable?: boolean;
  sortValue?: (row: Row) => string | number | null | undefined;
  align?: 'start' | 'end';
  /** Leave the column out of the stacked mobile cards (e.g. an actions column shown elsewhere). */
  hideOnMobile?: boolean;
  className?: string;
}

export interface DataTableProps<Row> {
  columns: readonly DataTableColumn<Row>[];
  rows: readonly Row[];
  getRowId: (row: Row) => string;
  /** Read by screen readers above the table, e.g. "Sources in this project". */
  caption: string;
  loading?: boolean;
  /** An error message; shows it with a Retry button (spec 14.5 state rule). */
  error?: string | null;
  onRetry?: () => void;
  /** What to show when there are no rows: usually an <EmptyState> with an action. */
  empty?: ReactNode;
  /** Controlled sorting (e.g. server-side); omit to sort in the browser with `sortValue`. */
  sort?: SortState | null;
  onSortChange?: (sort: SortState) => void;
  defaultSort?: SortState;
  selectedRowId?: string | null;
}

function compare(a: unknown, b: unknown): number {
  if (a === b) return 0;
  if (a === null || a === undefined) return 1;
  if (b === null || b === undefined) return -1;
  if (typeof a === 'number' && typeof b === 'number') return a - b;
  return String(a).localeCompare(String(b), undefined, { numeric: true, sensitivity: 'base' });
}

/**
 * Spec 14.5 DataTable: sortable, sticky header, and stacked cards below
 * 640 px (spec 14.3). Handles the loading, empty, error and filled states.
 */
export function DataTable<Row>({
  columns,
  rows,
  getRowId,
  caption,
  loading = false,
  error,
  onRetry,
  empty,
  sort: controlledSort,
  onSortChange,
  defaultSort,
  selectedRowId,
}: DataTableProps<Row>) {
  const { t } = useTranslation();
  const [localSort, setLocalSort] = useState<SortState | null>(defaultSort ?? null);
  const sort = controlledSort !== undefined ? controlledSort : localSort;

  const sorted = useMemo(() => {
    if (controlledSort !== undefined || !sort) return rows;
    const column = columns.find((c) => c.key === sort.key);
    if (!column?.sortValue) return rows;
    const factor = sort.direction === 'asc' ? 1 : -1;
    const value = column.sortValue;
    const blank = (v: unknown) => v === null || v === undefined || v === '';
    return [...rows].sort((a, b) => {
      const va = value(a);
      const vb = value(b);
      // Empty values stay last in both directions.
      if (blank(va) || blank(vb)) return blank(va) === blank(vb) ? 0 : blank(va) ? 1 : -1;
      return factor * compare(va, vb);
    });
  }, [rows, columns, sort, controlledSort]);

  function toggleSort(key: string) {
    const next: SortState =
      sort?.key === key && sort.direction === 'asc'
        ? { key, direction: 'desc' }
        : { key, direction: 'asc' };
    if (controlledSort === undefined) setLocalSort(next);
    onSortChange?.(next);
  }

  if (error) {
    return (
      <div className="flex flex-col items-start gap-3">
        <Alert tone="danger">{error}</Alert>
        {onRetry && (
          <Button variant="secondary" onClick={onRetry}>
            {t('actions.retry')}
          </Button>
        )}
      </div>
    );
  }

  if (loading) {
    return (
      <div aria-busy="true" className="flex flex-col gap-2">
        <span className="sr-only" role="status">
          {t('shell.loading')}
        </span>
        {[0, 1, 2, 3].map((i) => (
          <Skeleton key={i} className="h-12 w-full" />
        ))}
      </div>
    );
  }

  if (rows.length === 0) return <>{empty}</>;

  const mobileColumns = columns.filter((c) => !c.hideOnMobile);
  const [titleColumn, ...detailColumns] = mobileColumns;

  return (
    <>
      {/* 640 px and up: a real table. */}
      <div className="hidden max-h-[70dvh] overflow-auto rounded-card border border-border sm:block">
        <table className="w-full border-collapse text-sm">
          <caption className="sr-only">{caption}</caption>
          <thead className="sticky top-0 z-[1] bg-surface">
            <tr className="border-b border-border">
              {columns.map((column) => {
                const active = sort?.key === column.key;
                const ariaSort = active
                  ? sort.direction === 'asc'
                    ? 'ascending'
                    : 'descending'
                  : column.sortable
                    ? 'none'
                    : undefined;
                const Icon = !active ? ArrowUpDown : sort.direction === 'asc' ? ArrowUp : ArrowDown;
                return (
                  <th
                    key={column.key}
                    scope="col"
                    aria-sort={ariaSort}
                    className={cn(
                      'h-10 px-3 text-xs font-medium whitespace-nowrap text-muted',
                      column.align === 'end' ? 'text-end' : 'text-start',
                      column.className,
                    )}
                  >
                    {column.sortable ? (
                      <button
                        type="button"
                        onClick={() => toggleSort(column.key)}
                        className={cn(
                          '-mx-1.5 inline-flex h-8 items-center gap-1 rounded-control px-1.5 hover:bg-surface-2 hover:text-fg',
                          active && 'text-fg',
                        )}
                      >
                        {column.header}
                        <Icon
                          aria-hidden="true"
                          className={cn('size-3.5', !active && 'opacity-50')}
                        />
                      </button>
                    ) : (
                      column.header
                    )}
                  </th>
                );
              })}
            </tr>
          </thead>
          <tbody>
            {sorted.map((row) => {
              const id = getRowId(row);
              return (
                <tr
                  key={id}
                  aria-selected={selectedRowId === undefined ? undefined : selectedRowId === id}
                  className={cn(
                    'border-b border-border last:border-0 hover:bg-surface-2/60',
                    selectedRowId === id && 'bg-primary-subtle hover:bg-primary-subtle',
                  )}
                >
                  {columns.map((column) => (
                    <td
                      key={column.key}
                      className={cn(
                        'px-3 py-2.5 align-middle',
                        column.align === 'end' ? 'text-end' : 'text-start',
                        column.className,
                      )}
                    >
                      {column.cell(row)}
                    </td>
                  ))}
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>

      {/* Below 640 px: one card per row (spec 14.3). */}
      <ul aria-label={caption} className="flex flex-col gap-2 sm:hidden">
        {sorted.map((row) => (
          <li
            key={getRowId(row)}
            className={cn(
              'flex flex-col gap-2 rounded-card border border-border bg-surface p-3 text-sm',
              selectedRowId === getRowId(row) && 'border-primary bg-primary-subtle',
            )}
          >
            {titleColumn && <div className="font-medium">{titleColumn.cell(row)}</div>}
            {detailColumns.length > 0 && (
              <dl className="grid grid-cols-[auto_1fr] gap-x-3 gap-y-1">
                {detailColumns.map((column) => (
                  <div key={column.key} className="contents">
                    <dt className="text-xs text-muted">{column.header}</dt>
                    <dd className="min-w-0">{column.cell(row)}</dd>
                  </div>
                ))}
              </dl>
            )}
          </li>
        ))}
      </ul>
    </>
  );
}
