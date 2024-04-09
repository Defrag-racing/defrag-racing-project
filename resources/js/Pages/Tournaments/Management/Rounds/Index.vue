<script setup>
    import { Link } from '@inertiajs/vue3';
    import Tournament from '@/Pages/Tournaments/Tournament.vue';
    import DeleteRoundModal from './DeleteRoundModal.vue';
    import { useForm } from '@inertiajs/vue3';

    import { ref } from 'vue';

    const props = defineProps({
        tournament: Object
    });

    const form = useForm({
        _method: 'POST'
    });

    const showDeleteRound = ref(false);
    const selectedRound = ref(0);

    const deleteRound = (round) => {
        selectedRound.value = round;
        showDeleteRound.value = true;
    }

    const publishRound = (round) => {
        form.post(route('tournaments.rounds.publish', {tournament: props.tournament.id, round: round.id}));
    }
</script>

<template>
    <Tournament :tournament="tournament" tab="ManageTournament">
        <div class="flex justify-center items-center">
            <Link :href="route('tournaments.rounds.create', tournament.id)" class="text-gray-300 font-bold bg-grayop-700 cursor-pointer hover:bg-grayop-600 text-center rounded-lg p-3 mr-4 mb-3">
                Add New Round
            </Link>
        </div>

        <div v-for="round in tournament.rounds" :key="round.id">
            <div class="rounded-md bg-blackop-30 px-5 py-3 mx-2 my-2 flex">
                <div class="flex flex-col items-center">
                    <img :src="'/storage/' + round.image" style="width: 200px;" class="border-2 rounded-md border-grayop-500">
                    
                    <div v-if="round.published" class="text-lg text-green-500 mt-3">Published</div>
                    <div v-else class="text-lg text-yellow-500 mt-3">Unpublished</div>
                </div>

                <div class="ml-5">
                    <div class="text-white text-xl">{{ round.name }}</div>
                    <div class="text-gray-400 mt-2">Starts at: {{ round.start_date }} </div>
                    <div class="text-gray-400 mt-2">Ends at: {{ round.end_date }} </div>
                </div>

                <div class="flex flex-col items-end flex-grow justify-center">
                    <Link :href="route('tournaments.rounds.edit', {tournament: tournament.id, round: round.id})" class="text-gray-300 font-bold bg-grayop-700 cursor-pointer hover:bg-grayop-600 text-center rounded-lg p-3 mr-4 mb-3">
                        Edit Round
                    </Link>

                    <Link :href="route('tournaments.rounds.maps.index', {tournament: tournament.id, round: round.id})" class="text-gray-300 font-bold bg-grayop-700 cursor-pointer hover:bg-grayop-600 text-center rounded-lg p-3 mr-4 mb-3">
                        Edit Maps
                    </Link>

                    <div @click="publishRound(round)" class="font-bold bg-grayop-700 cursor-pointer hover:bg-grayop-600 text-center rounded-lg p-3 mr-4 mb-3" :class="{'text-yellow-500': round.published, 'text-green-500': ! round.published,}">
                        {{ round.published ? 'Unpublish Round' : 'Publish Round' }}
                    </div>

                    <div @click="deleteRound(round)" class="text-red-500 font-bold bg-grayop-700 cursor-pointer hover:bg-grayop-600 text-center rounded-lg p-3 mr-4 mb-3">
                        Delete Round
                    </div>
                </div>
            </div>
        </div>

        <DeleteRoundModal v-if="selectedRound" :show="showDeleteRound" :close="() => showDeleteRound = false" :round="selectedRound" :tournament="tournament" />

        <div class="flex justify-center mt-5 text-md text-gray-500" v-if="tournament.rounds.length === 0">
            There are no rounds yet.
        </div>
    </Tournament>
</template>
