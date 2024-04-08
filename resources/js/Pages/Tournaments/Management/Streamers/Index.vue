<script setup>
    import { Link } from '@inertiajs/vue3';
    import Tournament from '@/Pages/Tournaments/Tournament.vue';

    const props = defineProps({
        tournament: Object
    });
</script>

<template>
    <Tournament :tournament="tournament" tab="ManageTournament">
        <div class="flex justify-center items-center">
            <Link :href="route('tournaments.streamers.create', tournament.id)" class="text-gray-300 font-bold bg-grayop-700 cursor-pointer hover:bg-grayop-600 text-center rounded-lg p-3 mr-4 mb-3">
                Add New Streamer
            </Link>
        </div>

        <div v-for="streamer in tournament.streamers" :key="streamer.id">
            <div class="rounded-md bg-blackop-30 px-5 py-3 mx-2 my-2">
                <div class="flex justify-between items-center">
                    <h2 class="text-lg text-gray-300 mb-2">
                        <span class="mr-3" v-html="q3tohtml(streamer.user.name)"></span>
                        <Link :href="'https://www.twitch.tv/' + streamer.twitch_username" class="text-blue-500 hover:text-blue-400">@{{ streamer.twitch_username}}</Link>
                    </h2>
        
                    <div class="flex">
                        <Link :href="route('tournaments.streamers.edit', {tournament: streamer.tournament_id, streamer: streamer.id})" class="text-gray-300 font-bold bg-grayop-700 cursor-pointer hover:bg-grayop-600 text-center rounded-lg p-3 mr-4 mb-3">
                            Edit
                        </Link>
        
                        <Link :href="route('tournaments.streamers.destroy', {tournament: streamer.tournament_id, streamer: streamer.id})" class="text-gray-300 font-bold bg-grayop-700 cursor-pointer hover:bg-grayop-600 text-center rounded-lg p-3 mr-4 mb-3">
                            Delete
                        </Link>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex justify-center mt-5 text-md text-gray-500" v-if="tournament.streamers.length === 0">
            There are no streamers yet.
        </div>
    </Tournament>
</template>
