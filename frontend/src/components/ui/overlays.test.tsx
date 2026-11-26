import { act, screen, waitFor } from '@testing-library/react';
import { useState } from 'react';
import { afterEach, beforeAll, describe, expect, it, vi } from 'vitest';
import { ApiError } from '@/lib/api/errors';
import { toast, toastError, toastStore } from '@/lib/toast';
import { loadTranslations, renderUi } from '@/test/render';
import { Button } from './Button';
import { ConfirmDialog } from './ConfirmDialog';
import { Dialog } from './Dialog';
import { DropdownMenu } from './DropdownMenu';
import { Tabs } from './Tabs';
import { Toaster } from './Toast';

beforeAll(() => loadTranslations());
afterEach(() => act(() => toastStore.reset()));

describe('Dialog', () => {
  function Demo() {
    const [open, setOpen] = useState(false);
    return (
      <>
        <Button onClick={() => setOpen(true)}>Open</Button>
        <Dialog open={open} onOpenChange={setOpen} title="Rename project">
          <input aria-label="Title" />
        </Dialog>
      </>
    );
  }

  it('opens with a title, closes with Esc and returns focus to the trigger (spec 14.6)', async () => {
    const { user } = renderUi(<Demo />);
    const trigger = screen.getByRole('button', { name: 'Open' });
    await user.click(trigger);
    expect(screen.getByRole('dialog', { name: 'Rename project' })).toBeInTheDocument();
    await user.keyboard('{Escape}');
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
    await waitFor(() => expect(trigger).toHaveFocus());
  });
});

describe('ConfirmDialog', () => {
  it('requires the reason when asked, then passes it to onConfirm', async () => {
    const onConfirm = vi.fn();
    const onOpenChange = vi.fn();
    const { user } = renderUi(
      <ConfirmDialog
        open
        onOpenChange={onOpenChange}
        title="Suspend user?"
        confirmLabel="Suspend user"
        tone="danger"
        reason={{ label: 'Reason', required: true }}
        onConfirm={onConfirm}
      />,
    );
    await user.click(screen.getByRole('button', { name: 'Suspend user' }));
    expect(onConfirm).not.toHaveBeenCalled();
    expect(screen.getByText('This field is required.')).toBeInTheDocument();

    await user.type(screen.getByLabelText(/Reason/), '  Spam uploads  ');
    await user.click(screen.getByRole('button', { name: 'Suspend user' }));
    expect(onConfirm).toHaveBeenCalledWith('Spam uploads');
    expect(onOpenChange).toHaveBeenCalledWith(false);
  });

  it('stays open and busy while an async confirm runs', async () => {
    let finish: () => void = () => {};
    const onConfirm = vi.fn(() => new Promise<void>((resolve) => (finish = resolve)));
    const { user } = renderUi(
      <ConfirmDialog
        open
        onOpenChange={() => {}}
        title="Delete?"
        confirmLabel="Delete project"
        onConfirm={onConfirm}
      />,
    );
    await user.click(screen.getByRole('button', { name: 'Delete project' }));
    expect(screen.getByRole('button', { name: 'Delete project' })).toHaveAttribute(
      'aria-busy',
      'true',
    );
    expect(screen.getByRole('button', { name: 'Cancel' })).toBeDisabled();
    await act(async () => finish());
  });
});

describe('DropdownMenu', () => {
  it('opens from the keyboard and runs the chosen item', async () => {
    const onSelect = vi.fn();
    const { user } = renderUi(
      <DropdownMenu
        trigger={<Button aria-label="Account menu">A</Button>}
        items={[
          { type: 'label', key: 'who', label: 'ama@example.com' },
          { key: 'settings', label: 'Settings', to: '/settings' },
          { type: 'separator', key: 's1' },
          { key: 'out', label: 'Log out', onSelect, tone: 'danger' },
        ]}
      />,
    );
    screen.getByRole('button', { name: 'Account menu' }).focus();
    await user.keyboard('{Enter}');
    expect(await screen.findByRole('menu')).toBeInTheDocument();
    expect(screen.getByRole('menuitem', { name: 'Settings' })).toHaveAttribute('href', '/settings');
    await user.click(screen.getByRole('menuitem', { name: 'Log out' }));
    expect(onSelect).toHaveBeenCalledOnce();
  });
});

describe('Tabs', () => {
  it('switches panels with the arrow keys', async () => {
    const { user } = renderUi(
      <Tabs
        label="Sections"
        items={[
          { value: 'a', label: 'Profile', content: <p>Profile panel</p> },
          { value: 'b', label: 'Security', content: <p>Security panel</p> },
        ]}
      />,
    );
    expect(screen.getByText('Profile panel')).toBeVisible();
    screen.getByRole('tab', { name: 'Profile' }).focus();
    await user.keyboard('{ArrowRight}');
    expect(screen.getByRole('tab', { name: 'Security' })).toHaveAttribute('aria-selected', 'true');
    expect(screen.getByText('Security panel')).toBeVisible();
  });
});

describe('Toast', () => {
  it('shows queued toasts, and error toasts carry the request id (brief 3a)', async () => {
    renderUi(<Toaster />);
    act(() => {
      toast({ title: 'Project saved', tone: 'success' });
      toastError(
        new ApiError({
          code: 'QUOTA_EXCEEDED',
          message: 'Daily AI limit reached.',
          requestId: 'abc123',
        }),
        (key) => key,
      );
    });
    expect(await screen.findByText('Project saved')).toBeInTheDocument();
    expect(screen.getByText('Daily AI limit reached.')).toBeInTheDocument();
    expect(screen.getByText('Reference: abc123')).toBeInTheDocument();
  });
});
