<script setup>
    import Tournament from '@/Pages/Tournaments/Tournament.vue';
    import ActiveRound from '@/Components/Rounds/ActiveRound.vue';

    const props = defineProps({
        tournament: Object,
        rounds: Array,
        teams: Array
    });

    const status = (round) => {
        const date = new Date();
        const start = new Date(round.start_date);
        const end = new Date(round.end_date);

        if (date < start) {
            return 'Upcoming';
        }

        if (date > end) {
            return 'Completed';
        }

        return 'Active';
    }

</script>

<template>
    <Tournament :tournament="tournament" tab="Rounds">
        <div v-for="round in rounds" :key="round.id">
            <ActiveRound
                :tournament="tournament"
                :active="status(round) === 'Active'"
                :round="round"
                type="teams"
                :teams="teams"
            />
        </div>
    </Tournament>
</template>