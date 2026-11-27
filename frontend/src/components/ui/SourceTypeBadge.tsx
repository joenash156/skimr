import {
  BookOpen,
  ClipboardType,
  FileMinus,
  FileText,
  Globe,
  NotebookText,
  type LucideIcon,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Badge, type BadgeTone } from './Badge';

/** The badges of spec 5.3, shown wherever a source appears (lists, search, findings). */
export const SOURCE_KINDS = [
  'journal',
  'book',
  'report',
  'web',
  'pasted',
  'abstract_only',
] as const;
export type SourceKind = (typeof SOURCE_KINDS)[number];

const STYLE: Record<SourceKind, { icon: LucideIcon; tone: BadgeTone }> = {
  journal: { icon: FileText, tone: 'neutral' },
  book: { icon: BookOpen, tone: 'neutral' },
  report: { icon: NotebookText, tone: 'neutral' },
  // Not peer-reviewed: set apart so users never mistake a blog post for a paper.
  web: { icon: Globe, tone: 'warning' },
  pasted: { icon: ClipboardType, tone: 'neutral' },
  abstract_only: { icon: FileMinus, tone: 'neutral' },
};

/**
 * Picks the badge from a source's fields (spec 9.2 `sources`: source_type,
 * work_type, status). Inputs are plain strings until the sources endpoints
 * are in the API contract; then pass the generated type's fields.
 */
export function sourceKind(source: {
  source_type?: string | null;
  work_type?: string | null;
  status?: string | null;
}): SourceKind {
  if (source.status === 'abstract_only') return 'abstract_only';
  if (source.source_type === 'text') return 'pasted';
  switch (source.work_type) {
    case 'journal_article':
      return 'journal';
    case 'book':
    case 'chapter':
      return 'book';
    case 'report':
    case 'thesis':
      return 'report';
    case 'web_page':
      return 'web';
    default:
      // Unknown or "other": a link is a web page; anything else is treated as a paper.
      return source.source_type === 'url' ? 'web' : 'journal';
  }
}

export function SourceTypeBadge({ kind, className }: { kind: SourceKind; className?: string }) {
  const { t } = useTranslation();
  const { icon: Icon, tone } = STYLE[kind];
  return (
    <Badge tone={tone} className={className}>
      <Icon aria-hidden="true" className="size-3" />
      {t(`source_kind.${kind}`)}
    </Badge>
  );
}
