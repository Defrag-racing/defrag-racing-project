<script setup>
    import { Link } from '@inertiajs/vue3';
    import Tournament from '@/Pages/Tournaments/Tournament.vue';

    const props = defineProps({
        tournament: Object,
        organizers: Array
    });
</script>

<template>
    <Tournament :tournament="tournament" tab="ManageTournament">
        <div class="flex justify-center items-center">
            <Link :href="route('tournaments.organizers.create', tournament.id)" class="text-gray-300 font-bold bg-grayop-700 cursor-pointer hover:bg-grayop-600 text-center rounded-lg p-3 mr-4 mb-3">
                Add New Organizer
            </Link>
        </div>

        <div v-for="organizer in organizers" :key="organizer.id">
            <div class="rounded-md bg-blackop-30 px-5 py-3 mx-2 my-2">
                <div class="flex justify-between items-center">
                    <h2 class="text-lg text-gray-300 mb-2">
                        <span class="mr-3" v-html="q3tohtml(organizer.user.name)"></span>
                        <div class="text-blue-500 capitalize">{{ organizer.role}}</div>
                    </h2>
        
                    <div class="flex">
                        <Link :href="route('tournaments.organizers.edit', {tournament: organizer.tournament_id, organizer: organizer.id})" class="text-gray-300 font-bold bg-grayop-700 cursor-pointer hover:bg-grayop-600 text-center rounded-lg p-3 mr-4 mb-3">
                            Edit
                        </Link>
        
                        <Link :href="route('tournaments.organizers.destroy', {tournament: organizer.tournament_id, organizer: organizer.id})" class="text-gray-300 font-bold bg-grayop-700 cursor-pointer hover:bg-grayop-600 text-center rounded-lg p-3 mr-4 mb-3">
                            Delete
                        </Link>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex justify-center mt-5 text-md text-gray-500" v-if="organizers.length === 0">
            There are no organizers yet.
        </div>
    </Tournament>
</template>
