<script setup>
    import { computed, ref } from 'vue';
    import CommentSection from '@/Components/Tournament/CommentSection.vue';

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
            <div class="text-white font-bold text-xl">
                {{ title }}
            </div>
        </div>
    </div>
</template>