<script setup>
    import Tournament from '@/Pages/Tournaments/Tournament.vue';
    import Form from './Form.vue';
    
    const props = defineProps({
        tournament: Object,
        organizer: Object,
        users: Array
    });

</script>

<template>
    <Tournament :tournament="tournament" tab="ManageTournament">
        <Form :users="users" :item="organizer" url="tournaments.organizers.update" :tournament="tournament" />
    </Tournament>
</template>
