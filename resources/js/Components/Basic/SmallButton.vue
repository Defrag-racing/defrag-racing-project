<script setup>
    import { Link } from '@inertiajs/vue3';
    
    const props = defineProps({
        url: String,
        label: String
    });
</script>

<template>
    <Link :href="url" class="ml-2 w-10 text-center">
        <div class="py-2 rounded-md cursor-pointer bg-blackop-30 hover:bg-blackop-50 text-gray-400 w-full flex justify-center">
            <div v-if="label">{{ label }}</div>
            <slot v-else />
        </div>
    </Link>
</template>