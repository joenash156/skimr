import { fireEvent, screen, within } from '@testing-library/react';
import { beforeAll, describe, expect, it, vi } from 'vitest';
import { checkFile, formatBytes, PDF_RULE } from '@/lib/files';
import { loadTranslations, renderUi } from '@/test/render';
import { DataTable, type DataTableColumn } from './DataTable';
import { FileDropzone } from './FileDropzone';
import { pageItems, Pagination } from './Pagination';
import { sourceKind, SourceTypeBadge } from './SourceTypeBadge';

beforeAll(() => loadTranslations());

interface Row {
  id: string;
  title: string;
  year: number | null;
}
const ROWS: Row[] = [
  { id: 'a', title: 'Remote work and stress', year: 2021 },
  { id: 'b', title: 'Commuting in Accra', year: 2019 },
  { id: 'c', title: 'Flexible hours', year: null },
];
const COLUMNS: DataTableColumn<Row>[] = [
  {
    key: 'title',
    header: 'Title',
    cell: (r) => r.title,
    sortable: true,
    sortValue: (r) => r.title,
  },
  {
    key: 'year',
    header: 'Year',
    cell: (r) => r.year ?? '–',
    sortable: true,
    sortValue: (r) => r.year,
  },
];

describe('DataTable (spec 14.5)', () => {
  it('sorts by a column and reports it with aria-sort', async () => {
    const { user } = renderUi(
      <DataTable caption="Sources" columns={COLUMNS} rows={ROWS} getRowId={(r) => r.id} />,
    );
    const table = screen.getByRole('table', { name: 'Sources' });
    await user.click(within(table).getByRole('button', { name: 'Year' }));
    const header = within(table).getByRole('columnheader', { name: 'Year' });
    expect(header).toHaveAttribute('aria-sort', 'ascending');
    const firstCells = () =>
      within(table)
        .getAllByRole('row')
        .slice(1)
        .map((r) => r.firstChild?.textContent);
    expect(firstCells()).toEqual([
      'Commuting in Accra',
      'Remote work and stress',
      'Flexible hours',
    ]);

    await user.click(within(table).getByRole('button', { name: 'Year' }));
    expect(header).toHaveAttribute('aria-sort', 'descending');
    expect(firstCells()[0]).toBe('Remote work and stress');
  });

  it('also renders the rows as stacked cards for phones', () => {
    renderUi(<DataTable caption="Sources" columns={COLUMNS} rows={ROWS} getRowId={(r) => r.id} />);
    const cards = screen.getByRole('list', { name: 'Sources' });
    expect(within(cards).getAllByRole('listitem')).toHaveLength(3);
    expect(within(cards).getAllByText('Year')).toHaveLength(3);
  });

  it('has loading, empty and error states (spec 14.5)', async () => {
    const onRetry = vi.fn();
    const { rerender, user } = renderUi(
      <DataTable caption="Sources" columns={COLUMNS} rows={[]} getRowId={(r) => r.id} loading />,
    );
    expect(screen.getByRole('status')).toHaveTextContent('Loading');

    rerender(
      <DataTable
        caption="Sources"
        columns={COLUMNS}
        rows={[]}
        getRowId={(r) => r.id}
        empty={<p>No sources yet</p>}
      />,
    );
    expect(screen.getByText('No sources yet')).toBeInTheDocument();

    rerender(
      <DataTable
        caption="Sources"
        columns={COLUMNS}
        rows={[]}
        getRowId={(r) => r.id}
        error="Could not load sources."
        onRetry={onRetry}
      />,
    );
    expect(screen.getByRole('alert')).toHaveTextContent('Could not load sources.');
    await user.click(screen.getByRole('button', { name: 'Try again' }));
    expect(onRetry).toHaveBeenCalledOnce();
  });
});

describe('Pagination', () => {
  it('shows the range and moves between pages', async () => {
    const onPageChange = vi.fn();
    const { user } = renderUi(
      <Pagination page={2} perPage={20} total={95} onPageChange={onPageChange} />,
    );
    expect(screen.getByText('Showing 21–40 of 95')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Page 2' })).toHaveAttribute('aria-current', 'page');
    await user.click(screen.getByRole('button', { name: 'Next page' }));
    expect(onPageChange).toHaveBeenCalledWith(3);
    await user.click(screen.getByRole('button', { name: 'Page 5' }));
    expect(onPageChange).toHaveBeenCalledWith(5);
  });

  it('collapses long page lists with gaps', () => {
    expect(pageItems(1, 5)).toEqual([1, 2, 3, 4, 5]);
    expect(pageItems(6, 12)).toEqual([1, null, 5, 6, 7, null, 12]);
    expect(pageItems(1, 12)).toEqual([1, 2, null, 12]);
  });
});

describe('FileDropzone (PDF only, 10 MB)', () => {
  const pdf = (name: string, bytes: number) =>
    new File([new Uint8Array(bytes)], name, { type: 'application/pdf' });

  it('passes on accepted files and lists rejected ones with the reason', async () => {
    const onFiles = vi.fn();
    const { container } = renderUi(
      <FileDropzone
        rule={PDF_RULE}
        multiple
        onFiles={onFiles}
        label="Drop PDFs here"
        hint="PDF, up to 10 MB"
      />,
    );
    const input = container.querySelector('input[type="file"]') as HTMLInputElement;
    const ok = pdf('paper.pdf', 1000);
    const big = pdf('huge.pdf', 11 * 1024 * 1024);
    const word = new File(['x'], 'notes.docx', {
      type: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    });
    // Like a drop: the browser hands over every file, whatever the accept attribute says.
    fireEvent.change(input, { target: { files: [ok, big, word] } });

    expect(onFiles).toHaveBeenCalledWith([ok]);
    const alert = screen.getByRole('alert');
    expect(alert).toHaveTextContent('huge.pdf is larger than 10 MB.');
    expect(alert).toHaveTextContent("notes.docx: this file type isn't supported.");
  });

  it('is reachable from the keyboard', () => {
    const { container } = renderUi(
      <FileDropzone rule={PDF_RULE} onFiles={() => {}} label="Drop a PDF" />,
    );
    const input = container.querySelector('input[type="file"]') as HTMLInputElement;
    expect(input).toHaveAccessibleName('Drop a PDF');
    expect(input).not.toHaveAttribute('tabindex', '-1');
  });

  it('checks type by MIME or extension, and size', () => {
    expect(checkFile(new File(['x'], 'a.PDF', { type: '' }), PDF_RULE)).toBeNull();
    expect(checkFile(new File([], 'a.pdf', { type: 'application/pdf' }), PDF_RULE)).toBe('empty');
    expect(formatBytes(10 * 1024 * 1024, 'en')).toBe('10 MB');
  });
});

describe('SourceTypeBadge (spec 5.3)', () => {
  it('picks the badge from the source fields', () => {
    expect(sourceKind({ source_type: 'pdf', work_type: 'journal_article', status: 'ready' })).toBe(
      'journal',
    );
    expect(sourceKind({ source_type: 'images', work_type: 'chapter' })).toBe('book');
    expect(sourceKind({ source_type: 'pdf', work_type: 'thesis' })).toBe('report');
    expect(sourceKind({ source_type: 'url', work_type: 'web_page' })).toBe('web');
    expect(sourceKind({ source_type: 'url', work_type: null })).toBe('web');
    expect(sourceKind({ source_type: 'url', work_type: 'journal_article' })).toBe('journal');
    expect(sourceKind({ source_type: 'text' })).toBe('pasted');
    expect(sourceKind({ source_type: 'discovered', status: 'abstract_only' })).toBe(
      'abstract_only',
    );
  });

  it('labels web pages as not peer-reviewed', () => {
    renderUi(<SourceTypeBadge kind="web" />);
    expect(screen.getByText('Web page (not peer-reviewed)')).toBeInTheDocument();
  });
});
