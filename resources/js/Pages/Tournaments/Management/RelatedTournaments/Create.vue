<script setup>
    import Tournament from '@/Pages/Tournaments/Tournament.vue';
    import Form from './Form.vue';
    
    const props = defineProps({
        tournament: Object,
        tournaments: Array
    });

</script>

<template>
    <Tournament :tournament="tournament" tab="ManageTournament">
        <Form :tournaments="tournaments" :item="{}" url="tournaments.related.store" :tournament="tournament" />
    </Tournament>
</template>
