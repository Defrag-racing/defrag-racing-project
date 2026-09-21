import { onMounted, ref, watch } from 'vue';

/**
 * Ask for the props the server leaves out of the first response.
 *
 * Pages that carry a long list send the page first and the list after, so
 * the frame is on screen while the query runs. That second request used to
 * be fired from onMounted, which runs once - and anything that re-renders
 * the page with the same component hands the page empty props again with
 * nobody left to ask. Switching the language does exactly that: it posts
 * to /locale and comes back here, and the list never returned.
 *
 * So the request hangs on the props rather than on mount, and a request in
 * flight is never fired twice.
 *
 * @param {() => boolean} missing  true while the props are not here yet
 * @param {(done: () => void) => void} fetch  asks for them, calls done when finished
 */
export const useDeferredProps = (missing, fetch) => {
    const loading = ref(false);

    const run = () => {
        if (loading.value || !missing()) {
            return;
        }

        loading.value = true;
        fetch(() => {
            loading.value = false;
        });
    };

    onMounted(run);
    watch(missing, (isMissing) => {
        if (isMissing) {
            run();
        }
    });

    return { loading, run };
};
