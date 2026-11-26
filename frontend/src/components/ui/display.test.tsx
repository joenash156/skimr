import { screen } from '@testing-library/react';
import { Inbox } from 'lucide-react';
import { beforeAll, describe, expect, it } from 'vitest';
import { loadTranslations, renderUi } from '@/test/render';
import { Avatar, initials } from './Avatar';
import { Badge } from './Badge';
import { Button } from './Button';
import { EmptyState } from './EmptyState';
import { ProgressBar } from './ProgressBar';

beforeAll(() => loadTranslations());

describe('ProgressBar', () => {
  it('exposes its value to assistive technology, clamped to 0-100', () => {
    renderUi(<ProgressBar label="Processing report.pdf" value={140} />);
    const bar = screen.getByRole('progressbar', { name: 'Processing report.pdf' });
    expect(bar).toHaveAttribute('aria-valuenow', '100');
  });

  it('has no value while indeterminate', () => {
    renderUi(<ProgressBar label="Waking up" />);
    expect(screen.getByRole('progressbar')).not.toHaveAttribute('aria-valuenow');
  });
});

describe('Avatar', () => {
  it('falls back to initials with the name as its label', () => {
    renderUi(<Avatar name="Ama Mensah" />);
    expect(screen.getByRole('img', { name: 'Ama Mensah' })).toHaveTextContent('AM');
    expect(initials('kofi')).toBe('K');
    expect(initials('  Akosua  Ama   Boateng ')).toBe('AB');
  });
});

describe('EmptyState', () => {
  it('explains the empty view and offers an action (spec 14.5)', () => {
    renderUi(
      <EmptyState
        icon={Inbox}
        title="No projects yet"
        description="Create a project to start adding sources."
        action={<Button>New project</Button>}
      />,
    );
    expect(screen.getByRole('heading', { name: 'No projects yet' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'New project' })).toBeInTheDocument();
  });
});

describe('Badge', () => {
  it('renders its text', () => {
    renderUi(<Badge tone="success">Ready</Badge>);
    expect(screen.getByText('Ready')).toBeInTheDocument();
  });
});
