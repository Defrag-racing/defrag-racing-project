<script setup>
    import { Link } from '@inertiajs/vue3';
    
    const props = defineProps({
        url: String,
        label: String
    });
</script>

<template>
    <Link :href="url" class="ml-2 min-w-10 text-center">
        <div class="py-2 rounded-md cursor-pointer bg-blackop-30 hover:bg-blackop-50 text-gray-400 w-full flex justify-center px-0.5">
            <div v-if="label">{{ label }}</div>
            <slot v-else />
        </div>
    </Link>
</template>