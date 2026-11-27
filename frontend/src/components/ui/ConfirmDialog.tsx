import { useId, useState, type FormEvent, type ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from './Button';
import { Dialog } from './Dialog';
import { Textarea } from './Textarea';

export interface ConfirmDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  title: string;
  description?: ReactNode;
  /** Name the action, e.g. "Delete project" (never just "OK"). */
  confirmLabel: string;
  cancelLabel?: string;
  tone?: 'default' | 'danger';
  /** Adds a reason field, e.g. for suspending a user (admin, P5). */
  reason?: { label: string; required?: boolean; hint?: string };
  /** May return a promise; the button shows a spinner until it settles. */
  onConfirm: (reason: string) => void | Promise<void>;
}

export function ConfirmDialog({
  open,
  onOpenChange,
  title,
  description,
  confirmLabel,
  cancelLabel,
  tone = 'default',
  reason,
  onConfirm,
}: ConfirmDialogProps) {
  const { t } = useTranslation();
  const [text, setText] = useState('');
  const [pending, setPending] = useState(false);
  const [showError, setShowError] = useState(false);
  const formId = useId();

  const reasonMissing = Boolean(reason?.required) && text.trim() === '';

  function change(next: boolean) {
    if (pending) return;
    if (!next) {
      setText('');
      setShowError(false);
    }
    onOpenChange(next);
  }

  async function submit(event: FormEvent) {
    event.preventDefault();
    if (reasonMissing) {
      setShowError(true);
      return;
    }
    setPending(true);
    try {
      await onConfirm(text.trim());
      setText('');
      setShowError(false);
      onOpenChange(false);
    } finally {
      setPending(false);
    }
  }

  return (
    <Dialog
      open={open}
      onOpenChange={change}
      title={title}
      description={description}
      size="sm"
      dismissible={!pending}
      footer={
        <>
          <Button variant="secondary" onClick={() => change(false)} disabled={pending}>
            {cancelLabel ?? t('ui.cancel')}
          </Button>
          <Button
            type="submit"
            form={formId}
            variant={tone === 'danger' ? 'danger' : 'primary'}
            loading={pending}
          >
            {confirmLabel}
          </Button>
        </>
      }
    >
      <form id={formId} onSubmit={submit} noValidate>
        {reason && (
          <Textarea
            label={reason.label}
            hint={reason.hint}
            required={reason.required}
            value={text}
            onChange={(event) => {
              setText(event.target.value);
              if (showError) setShowError(false);
            }}
            error={showError && reasonMissing ? t('validation.required') : undefined}
            rows={3}
            maxLength={500}
          />
        )}
      </form>
    </Dialog>
  );
}
