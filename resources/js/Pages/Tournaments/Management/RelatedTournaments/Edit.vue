<script setup>
    import Tournament from '@/Pages/Tournaments/Tournament.vue';
    import Form from './Form.vue';
    
    const props = defineProps({
        tournament: Object,
        relatedTournament: Object,
        tournaments: Array
    });

</script>

<template>
    <Tournament :tournament="tournament" tab="ManageTournament">
        <Form :tournaments="tournaments" :item="relatedTournament" url="tournaments.related.update" :tournament="tournament" />
    </Tournament>
</template>
