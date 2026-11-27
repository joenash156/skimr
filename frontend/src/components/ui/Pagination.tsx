import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { cn } from '@/lib/cn';

export interface PaginationProps {
  /** 1-based, like the API's meta.page (spec 10.1). */
  page: number;
  perPage: number;
  total: number;
  onPageChange: (page: number) => void;
  className?: string;
}

/** Page numbers to show: always first and last, a window around the current, gaps as null. */
export function pageItems(page: number, pageCount: number): (number | null)[] {
  if (pageCount <= 7) return Array.from({ length: pageCount }, (_, i) => i + 1);
  const pages = new Set([1, pageCount, page - 1, page, page + 1]);
  const sorted = [...pages].filter((p) => p >= 1 && p <= pageCount).sort((a, b) => a - b);
  const items: (number | null)[] = [];
  sorted.forEach((p, i) => {
    const previous = sorted[i - 1];
    if (previous !== undefined && p - previous > 1) items.push(null);
    items.push(p);
  });
  return items;
}

const buttonClass =
  'inline-flex h-9 min-w-9 items-center justify-center rounded-control px-2 text-sm transition-colors pointer-coarse:h-11 pointer-coarse:min-w-11 disabled:pointer-events-none disabled:opacity-40';

/** Spec 14.5 Pagination, for lists with meta.page / per_page / total. */
export function Pagination({ page, perPage, total, onPageChange, className }: PaginationProps) {
  const { t, i18n } = useTranslation();
  const pageCount = Math.max(1, Math.ceil(total / perPage));
  if (total === 0) return null;

  const number = new Intl.NumberFormat(i18n.language);
  const from = (page - 1) * perPage + 1;
  const to = Math.min(total, page * perPage);

  return (
    <nav
      aria-label={t('pagination.label')}
      className={cn('flex flex-col items-center gap-3 sm:flex-row sm:justify-between', className)}
    >
      <p className="text-sm text-muted" aria-live="polite">
        {t('pagination.showing', {
          from: number.format(from),
          to: number.format(to),
          total: number.format(total),
        })}
      </p>
      {pageCount > 1 && (
        <ul className="flex items-center gap-1">
          <li>
            <button
              type="button"
              className={cn(buttonClass, 'text-muted hover:bg-surface-2 hover:text-fg')}
              onClick={() => onPageChange(page - 1)}
              disabled={page <= 1}
              aria-label={t('pagination.previous')}
            >
              <ChevronLeft aria-hidden="true" className="size-4" />
            </button>
          </li>
          {pageItems(page, pageCount).map((item, index) =>
            item === null ? (
              <li key={`gap-${index}`} aria-hidden="true" className="px-1 text-sm text-muted">
                …
              </li>
            ) : (
              <li key={item}>
                <button
                  type="button"
                  onClick={() => onPageChange(item)}
                  aria-current={item === page ? 'page' : undefined}
                  aria-label={t('pagination.page', { page: number.format(item) })}
                  className={cn(
                    buttonClass,
                    item === page ? 'bg-inverse text-inverse-fg' : 'text-fg hover:bg-surface-2',
                  )}
                >
                  {number.format(item)}
                </button>
              </li>
            ),
          )}
          <li>
            <button
              type="button"
              className={cn(buttonClass, 'text-muted hover:bg-surface-2 hover:text-fg')}
              onClick={() => onPageChange(page + 1)}
              disabled={page >= pageCount}
              aria-label={t('pagination.next')}
            >
              <ChevronRight aria-hidden="true" className="size-4" />
            </button>
          </li>
        </ul>
      )}
    </nav>
  );
}
