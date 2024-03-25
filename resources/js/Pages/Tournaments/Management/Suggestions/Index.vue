<script setup>
    import { Link } from '@inertiajs/vue3';
    import Tournament from '@/Pages/Tournaments/Tournament.vue';
    import Suggestion from '@/Components/Suggestion.vue';

    const props = defineProps({
        tournament: Object,
        suggestions: Array,
        archivedSuggestions: Array
    });
</script>

<template>
    <Tournament :tournament="tournament" tab="ManageTournament">
        <div class="text-xl text-gray-300 font-bold mb-5 text-center">
            Suggestions
        </div>

        <div v-for="suggestion in suggestions">
            <Suggestion :key="suggestion.id" :suggestion="suggestion" />
        </div>

        <div class="flex justify-center mt-5 text-md text-gray-500" v-if="suggestions.length === 0">
            There are no suggestions yet.
        </div>

        <div class="text-xl text-gray-300 font-bold mb-5 text-center mt-10" v-if="archivedSuggestions.length > 0">
            Archived Suggestions
        </div>

        <div v-for="suggestion in archivedSuggestions" v-if="archivedSuggestions.length > 0">
            <Suggestion :key="suggestion.id" :suggestion="suggestion" />
        </div>
    </Tournament>
</template>
