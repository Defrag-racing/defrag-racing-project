<script setup>
    import { Link } from '@inertiajs/vue3';

    const props = defineProps({
        tournament: Object
    });
</script>

<template>
    <div class="w-full">
        <Link :href="route('tournaments.show', tournament.id)" class="w-full flex items-end mb-4 mx-auto border border-grayop-500 border-4 rounded-lg" :style="`min-height: 400px; width: 600px; background-size: cover; background-image: url(/storage/${tournament.image});`">
            <div class="h-24 w-full px-5 py-5 rounded-b-lg" style="background-color: #000000b9;">
                <div class="text-2xl font-bold hover:underline text-white">{{ tournament.name }}</div>
    
                <div class="flex justify-between">
                    <div class="text-blue-400">7 Rounds</div>
                    <div class="text-blue-400">Started {{ timeSince(tournament.start_date) }} ago</div>
                </div>
            </div>
        </Link>
    </div>
</template>