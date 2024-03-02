<script setup>
    import { Link } from '@inertiajs/vue3';
    import Tournament from '@/Pages/Tournaments/Tournament.vue';
    import Suggestion from '@/Components/Suggestion.vue';

    const props = defineProps({
        tournament: Object
    });
</script>

<template>
    <Tournament :tournament="tournament" tab="ManageTournament">
        <Suggestion v-for="suggestion in tournament.suggestions" :key="suggestion.id" :suggestion="suggestion" />

        <div class="flex justify-center mt-5 text-md text-gray-500" v-if="tournament.suggestions.length === 0">
            There are no suggestions yet.
        </div>
    </Tournament>
</template>
