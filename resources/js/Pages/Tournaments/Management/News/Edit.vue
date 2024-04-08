<script setup>
    import Tournament from '@/Pages/Tournaments/Tournament.vue';
    import Form from './Form.vue';
    
    const props = defineProps({
        tournament: Object,
        news: Object
    });

</script>

<template>
    <Tournament :tournament="tournament" tab="ManageTournament">
        <Form :item="news" url="tournaments.news.update" :tournament="tournament" />
    </Tournament>
</template>
