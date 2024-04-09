<script setup>
    import { computed, ref } from 'vue';
    import { Link } from '@inertiajs/vue3';

    const props = defineProps({
        tournament: Object,
        item: Object
    });

    const title = computed(() => {
        return props.item.title + (props.item.type == 'round_start' ? ' Started' : props.item.type == 'round_end' ? ' Ended' : '');
    });
</script>

<template>
    <div>
        <div class="mb-3 rounded-md mx-auto bg-blackop-30 rounded-md p-2 flex items-center">
            <span class="text-xs bg-blue-600 rounded-full text-white px-4 py-1.5 me-3">
                <span class="font-bold">News</span>
            </span>
            <Link :href="route('tournaments.news.index', tournament)" class="text-white font-bold text-xl hover:underline">
                {{ title }}
            </Link>
        </div>
    </div>
</template>