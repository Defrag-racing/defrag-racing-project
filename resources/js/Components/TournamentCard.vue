<script setup>
    import { Link } from '@inertiajs/vue3';

    const props = defineProps({
        tournament: Object
    });
</script>

<template>
    <div class="w-full">
        <Link :href="route('tournaments.show', tournament.id)" class="flex flex-col justify-between mb-4 mx-auto border border-grayop-500 border-4 rounded-lg" :style="`height: 400px; width: 600px; max-width: 100%; background-size: cover; background-image: url(/storage/${tournament.image});`">
            <div class="flex justify-end">
                <div v-if="! tournament.published" :class="`rounded-md bg-blackop-30 p-2 uppercase text-white font-bold mr-3 mt-2`">
                    Not Published
                </div>
            </div>

            <div class="h-24 w-full px-5 py-5 rounded-b-lg" style="background-color: #000000b9;">
                <div class="text-2xl font-bold hover:underline text-white">{{ tournament.name }}</div>
    
                <div class="flex justify-between">
                    <div class="text-blue-400">{{ tournament.rounds.length }} Rounds</div>
                </div>
            </div>
        </Link>
    </div>
</template>