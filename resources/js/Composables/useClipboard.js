import { ref } from 'vue';

export function useClipboard() {
    const state = ref(false);

    const copy = (arg) => {
        const textarea = document.createElement('textarea');
        textarea.value = arg;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        state.value = true;

        setTimeout(() => {
            state.value = false;
        }, 1000);
    };

    return {
        copy,
        state,
    };
}
