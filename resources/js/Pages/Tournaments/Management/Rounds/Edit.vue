<script setup>
    import Tournament from '@/Pages/Tournaments/Tournament.vue';
    import Form from './Form.vue';
    
    const props = defineProps({
        tournament: Object,
        round: Object
    });

</script>

<template>
    <Tournament :tournament="tournament" tab="ManageTournament">
        <Form :item="round" url="tournaments.rounds.update" :tournament="tournament" />
    </Tournament>
</template>
