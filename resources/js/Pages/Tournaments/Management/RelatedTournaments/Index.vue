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
            <Link :href="route('tournaments.related.create', tournament.id)" class="text-gray-300 font-bold bg-grayop-700 cursor-pointer hover:bg-grayop-600 text-center rounded-lg p-3 mr-4 mb-3">
                Add New Related Tournament
            </Link>
        </div>

        <div class="w-full flex flex-wrap">
            <div v-for="relatedTournament in tournament.related_tournaments" :key="relatedTournament.id" class="mr-5 mb-3">
                <Link :href="route('tournaments.show', relatedTournament.tournament.id)"  class="flex items-end mb-1 border-gray-800 border-4 rounded-lg" :style="'min-height: 200px; width: 300px; background-image: url(\'/storage/' + relatedTournament.tournament.image + '\'); background-size: cover;'">
                    <div class="w-full px-5 py-3 rounded-b-lg" style="background-color: #000000b9;">
                        <div class="text-md font-bold hover:underline text-white">{{ relatedTournament.tournament.name }}</div>
                    </div>
                </Link>

                <div class="flex justify-between bg-grayop-900 rounded-md p-2">
                    <Link :href="route('tournaments.related.edit', {tournament: tournament.id, relatedTournament: relatedTournament.id})" class="text-gray-300 font-bold bg-grayop-700 cursor-pointer hover:bg-grayop-600 text-center rounded-lg px-3 py-2">
                        Edit
                    </Link>
    
                    <Link :href="route('tournaments.related.destroy', {tournament: tournament.id, relatedTournament: relatedTournament.id})" class="text-gray-300 font-bold bg-grayop-700 cursor-pointer hover:bg-grayop-600 text-center rounded-lg px-3 py-2">
                        Delete
                    </Link>
                </div>
            </div>
        </div>

        <div class="flex justify-center mt-5 text-md text-gray-500" v-if="tournament.related_tournaments.length === 0">
            There are no related tournaments yet.
        </div>
    </Tournament>
</template>
