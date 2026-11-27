import { screen } from '@testing-library/react';
import { useState } from 'react';
import { beforeAll, describe, expect, it } from 'vitest';
import { loadTranslations, renderUi } from '@/test/render';
import { Checkbox } from './Checkbox';
import { Input } from './Input';
import { PasswordInput } from './PasswordInput';
import { Select } from './Select';
import { Switch } from './Switch';

beforeAll(() => loadTranslations());

describe('Input', () => {
  it('has a visible label and links hint and error with aria-describedby (spec 14.6)', () => {
    renderUi(
      <Input label="Email" hint="We never share it." error="Enter a valid email." required />,
    );
    const input = screen.getByLabelText(/Email/);
    expect(input).toBeRequired();
    expect(input).toHaveAttribute('aria-invalid', 'true');
    expect(input).toHaveAccessibleDescription('We never share it. Enter a valid email.');
  });

  it('has no aria-invalid or description when there is nothing to say', () => {
    renderUi(<Input label="First name" />);
    const input = screen.getByLabelText('First name');
    expect(input).not.toHaveAttribute('aria-invalid');
    expect(input).not.toHaveAttribute('aria-describedby');
  });
});

describe('PasswordInput', () => {
  function Controlled() {
    const [value, setValue] = useState('');
    return (
      <PasswordInput
        label="Password"
        value={value}
        onChange={(e) => setValue(e.target.value)}
        strengthOf={value}
      />
    );
  }

  it('shows and hides the password with a labelled button', async () => {
    const { user } = renderUi(<PasswordInput label="Password" />);
    const input = screen.getByLabelText('Password');
    expect(input).toHaveAttribute('type', 'password');
    await user.click(screen.getByRole('button', { name: 'Show password' }));
    expect(input).toHaveAttribute('type', 'text');
    await user.click(screen.getByRole('button', { name: 'Hide password' }));
    expect(input).toHaveAttribute('type', 'password');
  });

  it('shows a strength meter as the user types', async () => {
    const { user } = renderUi(<Controlled />);
    expect(screen.queryByText('Too short')).not.toBeInTheDocument();
    await user.type(screen.getByLabelText('Password'), 'abc');
    expect(screen.getByText('Too short')).toBeInTheDocument();
    await user.type(screen.getByLabelText('Password'), '-Quiet-Lamp-2026');
    expect(screen.getByText('Strong')).toBeInTheDocument();
  });
});

describe('Select', () => {
  it('renders a native select with a placeholder option', async () => {
    const { user } = renderUi(
      <Select
        label="Language"
        placeholder="Choose"
        options={[
          { value: 'en', label: 'English' },
          { value: 'fr', label: 'Français' },
        ]}
      />,
    );
    const select = screen.getByLabelText('Language');
    expect(screen.getAllByRole('option')).toHaveLength(3);
    await user.selectOptions(select, 'fr');
    expect(select).toHaveValue('fr');
  });
});

describe('Checkbox', () => {
  it('toggles with the keyboard and shows its error', async () => {
    const { user } = renderUi(<Checkbox label="I accept the terms" error="Please accept." />);
    const box = screen.getByRole('checkbox', { name: 'I accept the terms' });
    expect(box).toHaveAccessibleDescription('Please accept.');
    box.focus();
    await user.keyboard(' ');
    expect(box).toBeChecked();
  });
});

describe('Switch', () => {
  it('is a labelled switch that reports changes', async () => {
    function Controlled() {
      const [on, setOn] = useState(false);
      return <Switch label="Email me" checked={on} onCheckedChange={setOn} />;
    }
    const { user } = renderUi(<Controlled />);
    const toggle = screen.getByRole('switch', { name: 'Email me' });
    expect(toggle).not.toBeChecked();
    await user.click(toggle);
    expect(toggle).toBeChecked();
  });
});
