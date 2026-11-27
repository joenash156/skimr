import { screen } from '@testing-library/react';
import { beforeAll, describe, expect, it, vi } from 'vitest';
import { loadTranslations, renderUi } from '@/test/render';
import { Button } from './Button';

beforeAll(() => loadTranslations());

describe('Button', () => {
  it('is a type="button" by default and runs onClick', async () => {
    const onClick = vi.fn();
    const { user } = renderUi(<Button onClick={onClick}>Save</Button>);
    const button = screen.getByRole('button', { name: 'Save' });
    expect(button).toHaveAttribute('type', 'button');
    await user.click(button);
    expect(onClick).toHaveBeenCalledOnce();
  });

  it('blocks clicks and announces busy while loading', async () => {
    const onClick = vi.fn();
    const { user } = renderUi(
      <Button loading onClick={onClick}>
        Save
      </Button>,
    );
    const button = screen.getByRole('button', { name: 'Save' });
    expect(button).toBeDisabled();
    expect(button).toHaveAttribute('aria-busy', 'true');
    await user.click(button);
    expect(onClick).not.toHaveBeenCalled();
  });

  it('uses the inverse (black/white) colours for primary and danger tokens for danger', () => {
    renderUi(
      <>
        <Button>Primary</Button>
        <Button variant="danger">Delete</Button>
      </>,
    );
    expect(screen.getByRole('button', { name: 'Primary' }).className).toContain('bg-inverse');
    expect(screen.getByRole('button', { name: 'Delete' }).className).toContain('bg-danger');
  });
});
