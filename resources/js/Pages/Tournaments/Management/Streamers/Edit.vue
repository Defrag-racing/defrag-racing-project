<script setup>
    import Tournament from '@/Pages/Tournaments/Tournament.vue';
    import Form from './Form.vue';
    
    const props = defineProps({
        tournament: Object,
        streamer: Object,
        users: Array
    });

</script>

<template>
    <Tournament :tournament="tournament" tab="ManageTournament">
        <Form :users="users" :item="streamer" url="tournaments.streamers.update" :tournament="tournament" />
    </Tournament>
</template>
