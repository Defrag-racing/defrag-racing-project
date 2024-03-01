<script setup>
    import { Link } from '@inertiajs/vue3';
    import Tournament from '@/Pages/Tournaments/Tournament.vue';
    import Donation from '@/Components/Donation.vue';

    const props = defineProps({
        tournament: Object
    });
</script>

<template>
    <Tournament :tournament="tournament" tab="ManageTournament">
        <div class="flex justify-center">
            <Link :href="route('tournaments.donations.create', tournament.id)" class="text-gray-300 font-bold bg-grayop-700 cursor-pointer hover:bg-grayop-600 text-center rounded-lg p-3 mr-4 mb-3">
                Add New Donation
            </Link>
        </div>

        <Donation v-for="donation in tournament.donations" management :key="donation.id" :donation="donation" />

        <div class="flex justify-center mt-5 text-md text-gray-500" v-if="tournament.donations.length === 0">
            There are no donations yet.
        </div>
    </Tournament>
</template>
