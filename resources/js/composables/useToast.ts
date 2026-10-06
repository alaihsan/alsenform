import { ref } from 'vue';

export type ToastType = 'success' | 'error';

export function useToast(timeout = 2500) {
    const toastMessage = ref('');
    const toastType = ref<ToastType>('success');

    function showToast(message: string, type: ToastType = 'success'): void {
        toastMessage.value = message;
        toastType.value = type;

        // Errors stay a little longer so they can be read.
        setTimeout(
            () => {
                if (toastMessage.value === message) {
                    toastMessage.value = '';
                }
            },
            type === 'error' ? Math.max(timeout, 5000) : timeout,
        );
    }

    return {
        toastMessage,
        toastType,
        showToast,
    };
}
