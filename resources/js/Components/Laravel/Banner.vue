<script setup>
    import { ref, onMounted } from 'vue';

    const props = defineProps({
        styling: String,
        show: Boolean,
        handle: String
    });

    const shown = ref(false);

    onMounted(() => {
        if (! props.show) {
            return;
        }

        const storedItem = localStorage.getItem(props.handle);

        if (storedItem) {
            shown.value = false
            return;
        }

        shown.value = true
    });

    const closeBanner = () => {
        console.log('here')
        localStorage.setItem(props.handle, JSON.stringify(true));
        shown.value = false;
    }
</script>

<template>
    <div>
        <div v-if="shown" class="bg-blue-900">
            <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8 py-2">
                <div class="flex items-center justify-between flex-wrap">
                    <div class="w-0 flex-1 flex items-center min-w-0">
                        <span class="flex p-2 rounded-lg" :class="{ 'bg-blue-700': styling == 'success', 'bg-red-600': styling == 'danger' }">
                            <svg v-if="styling == 'success'" class="h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>

                            <svg v-if="styling == 'danger'" class="h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                            </svg>
                        </span>

                        <p class="ms-3 font-medium text-sm text-white truncate">
                            <slot />
                        </p>
                    </div>

                    <div class="shrink-0 sm:ms-3">
                        <button
                            type="button"
                            class="-me-1 flex p-2 rounded-md focus:outline-none sm:-me-2 transition"
                            :class="{ 'hover:bg-blue-600 focus:bg-blue-600': styling == 'success', 'hover:bg-red-600 focus:bg-red-600': styling == 'danger' }"
                            aria-label="Dismiss"
                            @click.prevent="closeBanner"
                        >
                            <svg class="h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
