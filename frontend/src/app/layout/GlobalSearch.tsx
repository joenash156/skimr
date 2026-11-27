import { Search } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router';
import { cn } from '@/lib/cn';

/**
 * Global search (spec 5.3). For now it searches the projects list
 * (/projects?q=..., built in B2); cross-project search can come later.
 */
export function GlobalSearch({ className }: { className?: string }) {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const [query, setQuery] = useState('');

  function submit(event: FormEvent) {
    event.preventDefault();
    const q = query.trim();
    void navigate(q ? `/projects?q=${encodeURIComponent(q)}` : '/projects');
  }

  return (
    <form role="search" onSubmit={submit} className={cn('relative w-full max-w-sm', className)}>
      <Search
        aria-hidden="true"
        className="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-muted"
      />
      <input
        type="search"
        value={query}
        onChange={(event) => setQuery(event.target.value)}
        aria-label={t('search.label')}
        placeholder={t('search.placeholder')}
        className="h-10 w-full rounded-control border border-border bg-surface ps-9 pe-3 text-sm text-fg placeholder:text-subtle hover:border-muted"
      />
    </form>
  );
}
