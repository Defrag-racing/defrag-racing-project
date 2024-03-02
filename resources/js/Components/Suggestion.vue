<script setup>
    import { Link } from '@inertiajs/vue3';

    const props = defineProps({
        suggestion: Object
    });
</script>

<template>
    <div class="mb-5">
        <div class="rounded-md bg-blackop-30 px-5 py-2 mx-2">
            <div class="flex justify-between">
                <h2 class="text-lg text-gray-300">
                    {{ suggestion.title }}
                </h2>
    
                <div class="flex">
                    <Link :href="route('tournaments.suggestions.destroy', {tournament: suggestion.tournament_id, suggestion: suggestion.id})" class="text-gray-300 font-bold bg-grayop-700 cursor-pointer hover:bg-grayop-600 text-center rounded-lg p-3 mr-4 mb-3">
                        Delete
                    </Link>
                </div>
            </div>
        </div>
        <div class="rounded-md bg-blackop-20 px-5 py-3 mx-2 my-2 text-sm text-gray-500">
            {{ suggestion.message }}
        </div>
    </div>
</template>