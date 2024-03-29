<script setup>
    import { Link } from '@inertiajs/vue3';
    import Tournament from '@/Pages/Tournaments/Tournament.vue';

    const props = defineProps({
        tournament: Object,
        rounds: Array,
    });
</script>

<template>
    <Tournament :tournament="tournament" tab="ManageTournament">
        <div v-for="round in rounds" :key="round.id" class="bg-blackop-30 w-full rounded-md mb-2 p-3 text-white">
            <Link :href="route('tournaments.validation.round', {tournament: tournament.id, round: round.id})" class="hover:underline text-xl">
                {{ round.name }}
            </Link>
        </div>

        <div class="flex justify-center mt-5 text-md text-gray-500" v-if="rounds.length === 0">
            There are no rounds yet.
        </div>
    </Tournament>
</template>
