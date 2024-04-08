<script setup>
    import Tournament from '@/Pages/Tournaments/Tournament.vue';
    import Form from './Form.vue';
    
    const props = defineProps({
        tournament: Object,
        users: Array
    });

</script>

<template>
    <Tournament :tournament="tournament" tab="ManageTournament">
        <Form :users="users" :item="{}" url="tournaments.streamers.store" :tournament="tournament" />
    </Tournament>
</template>
