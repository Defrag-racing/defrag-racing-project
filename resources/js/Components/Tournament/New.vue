<script setup>
    import { computed, ref } from 'vue';
    import CommentSection from '@/Components/Tournament/CommentSection.vue';

    defineProps({
        tournament: Object,
        item: Object
    });
</script>

<template>
    <div class="mb-5 p-5 rounded-md mx-auto bg-grayop-900">
        <div class="text-white font-bold text-xl">
            {{ item.title }}
        </div>

        <div class="my-3" style="width: 100%; height: 1px; background-color: #72727244"></div>

        <div class="flex justify-center p-5" v-if="item.image">
            <img :src="`/storage/${item.image}`" class="rounded-md border-2 border-gray-700">
        </div>

        <div class="text-gray-400">
            {{ item.content }}
        </div>
    
        <CommentSection class="mt-5" :comments="item.comments" :url="route('tournaments.news.comment', {tournament: tournament.id, new: item.id})" />
    </div>
</template>