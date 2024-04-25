<script setup>
defineProps({
    type: {
        type: String,
        default: 'button',
    },
});
</script>

<template>
    <button :type="type" class="inline-flex items-center px-4 py-2 bg-grayop-800 border border-gray-500 rounded-md font-semibold text-xs text-gray-300 uppercase tracking-widest shadow-sm hover:bg-grayop-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-gray-800 disabled:opacity-25 transition ease-in-out duration-150">
        <slot />
    </button>
</template>
