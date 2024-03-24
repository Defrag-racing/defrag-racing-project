<script setup>
    import { Link } from '@inertiajs/vue3';
    import Tournament from '@/Pages/Tournaments/Tournament.vue';
    import MapForm from "./MapForm.vue";
    import MapFormExisting from "./MapFormExisting.vue";

    const props = defineProps({
        tournament: Object,
        round: Object
    });
</script>

<template>
    <Tournament :tournament="tournament" tab="ManageTournament">
        <div class="text-gray-300 text-xl text-center">{{ round.name }} Maps</div>

        <div class="flex bg-blackop-30 p-3 rounded-md my-5">
            <div class="flex justify-center flex-grow">
                <MapForm :tournament="tournament" :round="round" />
            </div>

            <div class="flex justify-center flex-grow">
                <MapFormExisting :tournament="tournament" :round="round" />
            </div>
        </div>

        <div v-for="map in round.maps" :key="round.id">
            <div class="rounded-md bg-blackop-30 px-5 py-3 mx-2 my-2 flex">
                <div class="flex-1">
                    <div class="text-lg text-gray-500">Filename: 
                        <span class="text-gray-300">{{ map.name }}</span>
                    </div>
                    <div class="text-sm text-gray-500">BSP CRC: 
                        <span class="text-gray-500">{{ map.crc }}</span>
                    </div>
                </div>

                <div class="flex items-center">
                    <Link :href="route('tournaments.rounds.maps.destroy', {tournament: tournament.id, round: round.id, map: map.id})" class="text-red-500 hover:text-gray-300">
                        Delete
                    </Link>
                </div>
            </div>
        </div>

        <div class="flex justify-center mt-5 text-md text-gray-500" v-if="round.maps.length === 0">
            There are no maps yet.
        </div>
    </Tournament>
</template>
