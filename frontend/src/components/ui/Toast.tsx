import * as RadixToast from '@radix-ui/react-toast';
import { useSyncExternalStore } from 'react';
import { useTranslation } from 'react-i18next';
import { toastStore } from '@/lib/toast';
import { ToastCard } from './ToastCard';

/** Renders queued toasts (see lib/toast.ts). Mounted once in the providers. */
export function Toaster() {
  const { t } = useTranslation();
  const items = useSyncExternalStore(
    toastStore.subscribe,
    toastStore.getSnapshot,
    toastStore.getSnapshot,
  );
  return (
    <RadixToast.Provider swipeDirection="right" label={t('ui.notifications_region')}>
      {items.map((item) => (
        <ToastCard key={item.id} item={item} />
      ))}
      <RadixToast.Viewport className="fixed inset-x-0 bottom-0 z-[60] flex flex-col gap-2 p-4 outline-none sm:start-auto sm:end-0 sm:w-[24rem]" />
    </RadixToast.Provider>
  );
}
