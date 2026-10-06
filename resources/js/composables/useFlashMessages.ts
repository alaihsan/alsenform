import type { ToastType } from '@/composables/useToast';
import { router, usePage } from '@inertiajs/vue3';
import { onMounted, onUnmounted } from 'vue';

/**
 * Show the server's flash messages (->with('success') / withErrors(['error' => ...])) as toasts,
 * once per request, so the page reports what really happened.
 */
export function useFlashMessages(showToast: (message: string, type?: ToastType) => void): void {
    const page = usePage<any>();

    const report = (props: any): void => {
        const error = props?.flash?.error || props?.errors?.error;
        if (error) {
            showToast(String(error), 'error');
            return;
        }
        if (props?.flash?.success) {
            showToast(String(props.flash.success), 'success');
        }
    };

    const removeListeners = [
        router.on('success', (event) => report(event.detail.page.props)),
        router.on('error', (event) => report({ errors: event.detail.errors })),
    ];

    onMounted(() => report(page.props));
    onUnmounted(() => removeListeners.forEach((remove) => remove()));
}
