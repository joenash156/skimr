/**
 * Client-side file checks for uploads. The API checks again (real type from
 * the first bytes, spec 7), so this is for quick, friendly feedback only.
 */
export interface FileRule {
  /** MIME types, e.g. ['application/pdf']. */
  mimeTypes: readonly string[];
  /** Lower-case extensions with the dot, e.g. ['.pdf'] (some browsers send no MIME type). */
  extensions: readonly string[];
  maxBytes: number;
}

export type RejectionReason = 'type' | 'size' | 'empty';

export interface FileRejection {
  file: File;
  reason: RejectionReason;
}

/** PDF uploads (spec 5.3): 10 MB each. */
export const PDF_RULE: FileRule = {
  mimeTypes: ['application/pdf'],
  extensions: ['.pdf'],
  maxBytes: 10 * 1024 * 1024,
};

function extension(name: string): string {
  const dot = name.lastIndexOf('.');
  return dot === -1 ? '' : name.slice(dot).toLowerCase();
}

export function checkFile(file: File, rule: FileRule): RejectionReason | null {
  const typeOk =
    (file.type !== '' && rule.mimeTypes.includes(file.type)) ||
    rule.extensions.includes(extension(file.name));
  if (!typeOk) return 'type';
  if (file.size === 0) return 'empty';
  if (file.size > rule.maxBytes) return 'size';
  return null;
}

export function splitFiles(
  files: readonly File[],
  rule: FileRule,
): { accepted: File[]; rejected: FileRejection[] } {
  const accepted: File[] = [];
  const rejected: FileRejection[] = [];
  for (const file of files) {
    const reason = checkFile(file, rule);
    if (reason) rejected.push({ file, reason });
    else accepted.push(file);
  }
  return { accepted, rejected };
}

/** "10 MB", "2.5 MB", "820 kB" in the UI language. */
export function formatBytes(bytes: number, language: string): string {
  const mb = bytes / (1024 * 1024);
  if (mb >= 1) {
    return new Intl.NumberFormat(language, {
      style: 'unit',
      unit: 'megabyte',
      maximumFractionDigits: mb >= 10 ? 0 : 1,
    }).format(mb);
  }
  return new Intl.NumberFormat(language, {
    style: 'unit',
    unit: 'kilobyte',
    maximumFractionDigits: 0,
  }).format(Math.max(1, bytes / 1024));
}
