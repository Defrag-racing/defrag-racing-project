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
        <div class="mb-5 p-5 rounded-md mx-auto bg-grayop-900">
            <Link :href="route('tournaments.news.index', tournament)" class="text-white font-bold text-xl hover:underline">
                {{ title }}
            </Link>
        </div>
    </div>
</template>