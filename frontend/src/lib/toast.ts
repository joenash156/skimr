import { errorMessage, normalizeError } from './api/errors';

/**
 * A tiny toast store: call `toast(...)` from anywhere (mutations, the API
 * layer); <Toaster /> in the providers renders the queue.
 */
export type ToastTone = 'neutral' | 'success' | 'danger';

export interface ToastItem {
  id: number;
  title: string;
  description?: string | undefined;
  tone: ToastTone;
  /** Shown as "Reference: ..." so users can report a problem (brief 3a). */
  requestId?: string | null | undefined;
}

type Listener = () => void;

let nextId = 1;
let items: readonly ToastItem[] = [];
const listeners = new Set<Listener>();
const MAX_VISIBLE = 4;

function emit() {
  for (const listener of listeners) listener();
}

export function toast(input: Omit<ToastItem, 'id' | 'tone'> & { tone?: ToastTone }): number {
  const id = nextId++;
  const item: ToastItem = { ...input, tone: input.tone ?? 'neutral', id };
  items = [...items, item].slice(-MAX_VISIBLE);
  emit();
  return id;
}

export function dismissToast(id: number): void {
  items = items.filter((item) => item.id !== id);
  emit();
}

/** An error toast with the API's translated message and its request id. */
export function toastError(error: unknown, t: (key: string) => string, title?: string): number {
  const apiError = normalizeError(error);
  const message = errorMessage(apiError, t);
  return toast({
    tone: 'danger',
    title: title ?? message,
    description: title ? message : undefined,
    requestId: apiError.requestId,
  });
}

export const toastStore = {
  subscribe(listener: Listener): () => void {
    listeners.add(listener);
    return () => {
      listeners.delete(listener);
    };
  },
  getSnapshot(): readonly ToastItem[] {
    return items;
  },
  /** Tests only. */
  reset(): void {
    items = [];
    emit();
  },
};
