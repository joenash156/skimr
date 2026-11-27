import '@testing-library/jest-dom/vitest';
import { cleanup, configure } from '@testing-library/react';
import { afterEach } from 'vitest';

// Full-app tests (real routes, lazy pages, StrictMode) can take a few seconds
// on a busy machine or CI runner; give findBy*/waitFor room before failing.
configure({ asyncUtilTimeout: 5000 });

// jsdom lacks a few browser APIs that Radix primitives call.
class ResizeObserverStub {
  observe() {}
  unobserve() {}
  disconnect() {}
}
globalThis.ResizeObserver ??= ResizeObserverStub as unknown as typeof ResizeObserver;

if (typeof window !== 'undefined') {
  window.HTMLElement.prototype.scrollIntoView ??= function scrollIntoView() {};
  window.HTMLElement.prototype.hasPointerCapture ??= () => false;
  window.HTMLElement.prototype.releasePointerCapture ??= () => {};
  // jsdom has <dialog> but not showModal()/close() (used by the drawer).
  const dialogProto = window.HTMLDialogElement?.prototype;
  if (dialogProto && typeof dialogProto.showModal !== 'function') {
    dialogProto.showModal = function showModal(this: HTMLDialogElement) {
      this.setAttribute('open', '');
    };
    dialogProto.close = function close(this: HTMLDialogElement) {
      if (!this.hasAttribute('open')) return;
      this.removeAttribute('open');
      this.dispatchEvent(new Event('close'));
    };
  }
  window.matchMedia ??= ((query: string) => ({
    matches: false,
    media: query,
    onchange: null,
    addEventListener: () => {},
    removeEventListener: () => {},
    addListener: () => {},
    removeListener: () => {},
    dispatchEvent: () => false,
  })) as unknown as typeof window.matchMedia;
}

afterEach(() => {
  cleanup();
});
