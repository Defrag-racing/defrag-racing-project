import { ref } from 'vue';

export function useClipboard() {
    const copyState = ref(false);

    const copy = (arg) => {
        const textarea = document.createElement('textarea');
        textarea.value = arg;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        copyState.value = true;

        setTimeout(() => {
            copyState.value = false;
        }, 1000);
    };

    return {
        copy,
        copyState,
    };
}
