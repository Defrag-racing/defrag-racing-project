<script setup>
    import { Link } from '@inertiajs/vue3';
    import Tournament from '@/Pages/Tournaments/Tournament.vue';

    const props = defineProps({
        tournament: Object,
        donations: Number,
        suggestions: Number
    });
</script>

<template>
    <Tournament :tournament="tournament" tab="ManageTournament">
        <div class="grid grid-cols-1 md:grid-cols-3 xl:grid-cols-4 justify-between">
            <Link :href="route('tournaments.edit', tournament.id)" class="text-gray-300 font-bold bg-grayop-700 cursor-pointer hover:bg-grayop-600 text-center rounded-lg p-3 mr-4 mb-3">
                Edit Tournament
            </Link>

            <Link :href="route('tournaments.donations.manage', tournament.id)" class="text-gray-300 font-bold bg-grayop-700 cursor-pointer hover:bg-grayop-600 text-center rounded-lg p-3 mr-4 mb-3">
                Manage Donations ({{ donations }})
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

            <Link :href="route('tournaments.suggestions.index', tournament.id)" class="relative text-gray-300 font-bold bg-grayop-700 cursor-pointer hover:bg-grayop-600 text-center rounded-lg p-3 mr-4 mb-3">
                <div v-if="suggestions > 0" class="absolute top-0 right-0 transform translate-x-1/2 -translate-y-1/2 bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center">
                    {{ suggestions }}
                </div>
                View Suggestions
            </Link>

            <Link :href="route('tournaments.rounds.manage', tournament.id)" class="text-gray-300 font-bold bg-grayop-700 cursor-pointer hover:bg-grayop-600 text-center rounded-lg p-3 mr-4 mb-3">
                Manage Rounds
            </Link>

            <Link :href="route('tournaments.news.manage', tournament.id)" class="text-gray-300 font-bold bg-grayop-700 cursor-pointer hover:bg-grayop-600 text-center rounded-lg p-3 mr-4 mb-3">
                Manage News
            </Link>
        </div>
    </Tournament>
</template>
