<script setup>
    import Tournament from '@/Pages/Tournaments/Tournament.vue';
    import New from '@/Components/Tournament/New.vue';

    const props = defineProps({
        tournament: Object,
        news: Array
    });
</script>

<template>
    <Tournament :tournament="tournament" tab="News">
        <div v-for="item in news" :key="item.id">
            <New :item="item" :tournament="tournament" />
        </div>
    </Tournament>
</template>