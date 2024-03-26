<script setup>
    import Tournament from '@/Pages/Tournaments/Tournament.vue';
    import ActiveRound from '@/Components/Rounds/ActiveRound.vue';

    const props = defineProps({
        tournament: Object,
        listings: Array
    });

    const getRoundStatus = (round) => {
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

    const status = (listing) => {
        if (listing.type === 'tournament') {
            return 'Tournament';
        }

        if (listing.type === 'round') {
            return getRoundStatus(listing.round);
        }
    }

</script>

<template>
    <Tournament :tournament="tournament" tab="Rounds">
        <div v-for="listing in listings" :key="listing.id">
            <ActiveRound
                v-if="status(listing) === 'Active' || status(listing) === 'Completed'"
                :tournament="tournament"
                :title="listing.title"
                :active="status(listing) === 'Active'"
                :listing="listing"
            />
        </div>
    </Tournament>
</template>