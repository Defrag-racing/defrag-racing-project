<script setup>
    import Tournament from '@/Pages/Tournaments/Tournament.vue';
    import ActiveRound from '@/Components/Rounds/ActiveRound.vue';

    const props = defineProps({
        tournament: Object,
        rounds: Array
    });

</script>

<template>
    <Tournament :tournament="tournament" tab="Rounds">
        <div v-for="round in rounds" :key="round.id">
            <ActiveRound
                :tournament="tournament"
                :active="round.active"
                :round="round"
            />
        </div>
    </Tournament>
</template>