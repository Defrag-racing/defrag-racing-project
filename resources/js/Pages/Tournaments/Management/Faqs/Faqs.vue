<script setup>
    import { Link } from '@inertiajs/vue3';
    import Tournament from '@/Pages/Tournaments/Tournament.vue';
    import FAQ from '@/Components/FAQ.vue';

    const props = defineProps({
        tournament: Object
    });
</script>

<template>
    <Tournament :tournament="tournament" tab="ManageTournament">
        <div class="flex justify-center">
            <Link :href="route('tournaments.faqs.create', tournament.id)" class="text-gray-300 font-bold bg-grayop-700 cursor-pointer hover:bg-grayop-600 text-center rounded-lg p-3 mr-4 mb-3">
                Add New faq
            </Link>
        </div>

        <FAQ v-for="faq in tournament.faqs" management :key="faq.id" :faq="faq" />

        <div class="flex justify-center mt-5 text-md text-gray-500" v-if="tournament.faqs.length === 0">
            There are no faqs yet.
        </div>
    </Tournament>
</template>
