import { Upload } from 'lucide-react';
import { useId, useState, type DragEvent, type ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { cn } from '@/lib/cn';
import { formatBytes, splitFiles, type FileRejection, type FileRule } from '@/lib/files';

export interface FileDropzoneProps {
  rule: FileRule;
  /** Files that passed the checks. */
  onFiles: (files: File[]) => void;
  multiple?: boolean;
  disabled?: boolean;
  /** Main text, e.g. "Drop PDFs here or browse". */
  label: ReactNode;
  /** Accepted types and size, e.g. "PDF, up to 10 MB each". */
  hint?: ReactNode;
  className?: string;
}

/**
 * Drag-and-drop or click/keyboard to pick files (spec 14.5). Files that are
 * the wrong type, empty or too large are listed with the reason (brief B2).
 * The input stays focusable, so Tab + Enter/Space opens the file picker.
 */
export function FileDropzone({
  rule,
  onFiles,
  multiple = false,
  disabled = false,
  label,
  hint,
  className,
}: FileDropzoneProps) {
  const { t, i18n } = useTranslation();
  const id = useId();
  const [dragging, setDragging] = useState(false);
  const [rejected, setRejected] = useState<FileRejection[]>([]);
  const maxSize = formatBytes(rule.maxBytes, i18n.language);

  function take(list: FileList | null) {
    if (!list || disabled) return;
    const files = multiple ? Array.from(list) : Array.from(list).slice(0, 1);
    const result = splitFiles(files, rule);
    setRejected(result.rejected);
    if (result.accepted.length > 0) onFiles(result.accepted);
  }

  function onDrop(event: DragEvent<HTMLLabelElement>) {
    event.preventDefault();
    setDragging(false);
    take(event.dataTransfer.files);
  }

  return (
    <div className={cn('flex flex-col gap-2', className)}>
      <label
        htmlFor={id}
        onDragOver={(event) => {
          event.preventDefault();
          if (!disabled) setDragging(true);
        }}
        onDragLeave={() => setDragging(false)}
        onDrop={onDrop}
        className={cn(
          'flex cursor-pointer flex-col items-center gap-2 rounded-card border border-dashed px-6 py-8 text-center transition-colors',
          'has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-primary',
          dragging
            ? 'border-primary bg-primary-subtle'
            : 'border-border bg-surface hover:bg-surface-2',
          disabled && 'pointer-events-none opacity-50',
        )}
      >
        <span className="flex size-10 items-center justify-center rounded-full border border-border bg-bg text-muted">
          <Upload aria-hidden="true" className="size-5" />
        </span>
        <span className="text-sm font-medium text-fg">{label}</span>
        {hint && <span className="text-xs text-muted">{hint}</span>}
        <input
          id={id}
          type="file"
          className="sr-only"
          multiple={multiple}
          disabled={disabled}
          accept={[...rule.mimeTypes, ...rule.extensions].join(',')}
          onChange={(event) => {
            take(event.target.files);
            event.target.value = ''; // picking the same file again still fires
          }}
        />
      </label>

      {rejected.length > 0 && (
        <ul role="alert" className="flex flex-col gap-1">
          {rejected.map(({ file, reason }) => (
            <li key={`${file.name}-${file.size}`} className="text-xs font-medium text-danger">
              {t(`dropzone.rejected.${reason}`, { name: file.name, max: maxSize })}
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
