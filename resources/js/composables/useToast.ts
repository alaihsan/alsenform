import { ref } from 'vue';

export function useToast(timeout = 2500) {
    const toastMessage = ref('');

    function showToast(message: string): void {
        toastMessage.value = message;

        setTimeout(() => {
            if (toastMessage.value === message) {
                toastMessage.value = '';
            }
        }, timeout);
    }

    return {
        toastMessage,
        showToast,
    };
}
