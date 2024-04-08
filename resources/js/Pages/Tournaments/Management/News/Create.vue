<script setup>
    import Tournament from '@/Pages/Tournaments/Tournament.vue';
    import Form from './Form.vue';
    
    const props = defineProps({
        tournament: Object
    });

</script>

<template>
    <Tournament :tournament="tournament" tab="ManageTournament">
        <Form :item="{}" url="tournaments.news.store" :tournament="tournament" />
    </Tournament>
</template>
