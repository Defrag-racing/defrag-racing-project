<script setup>
    import { Link } from '@inertiajs/vue3';
    import Tournament from '@/Pages/Tournaments/Tournament.vue';

    const props = defineProps({
        tournament: Object
    });
</script>

<template>
    <Tournament :tournament="tournament" tab="ManageTournament">
        <div class="grid grid-cols-1 md:grid-cols-3 xl:grid-cols-4 justify-between">
            <Link :href="route('tournaments.edit', tournament.id)" class="text-gray-300 font-bold bg-grayop-700 cursor-pointer hover:bg-grayop-600 text-center rounded-lg p-3 mr-4 mb-3">
                Edit Tournament
            </Link>

            <Link :href="route('tournaments.donations.manage', tournament.id)" class="text-gray-300 font-bold bg-grayop-700 cursor-pointer hover:bg-grayop-600 text-center rounded-lg p-3 mr-4 mb-3">
                Manage Donations
            </Link>

            <Link :href="route('tournaments.faqs.manage', tournament.id)" class="text-gray-300 font-bold bg-grayop-700 cursor-pointer hover:bg-grayop-600 text-center rounded-lg p-3 mr-4 mb-3">
                Manage Faqs
            </Link>

            <Link :href="route('tournaments.streamers.manage', tournament.id)" class="text-gray-300 font-bold bg-grayop-700 cursor-pointer hover:bg-grayop-600 text-center rounded-lg p-3 mr-4 mb-3">
                Manage Streamers
            </Link>

            <Link :href="route('tournaments.related.manage', tournament.id)" class="text-gray-300 font-bold bg-grayop-700 cursor-pointer hover:bg-grayop-600 text-center rounded-lg p-3 mr-4 mb-3">
                Manage Related Tournaments
            </Link>

            <Link :href="route('tournaments.suggestions.index', tournament.id)" class="text-gray-300 font-bold bg-grayop-700 cursor-pointer hover:bg-grayop-600 text-center rounded-lg p-3 mr-4 mb-3">
                View Suggestions
            </Link>

            <a :href="route('tournaments.rounds.manage', tournament.id)" class="text-gray-300 font-bold bg-grayop-700 cursor-pointer hover:bg-grayop-600 text-center rounded-lg p-3 mr-4 mb-3">
                Manage Rounds
            </a>
        </div>
    </Tournament>
</template>
