<script setup>
    import Tournament from '@/Pages/Tournaments/Tournament.vue';
    import Donation from '@/Components/Donation.vue';

    const props = defineProps({
        tournament: Object
    });
</script>

<template>
    <Tournament :tournament="tournament" tab="Donations">
        <Donation :tournament="tournament" v-for="donation in tournament.donations" :donation="donation" />

        <div class="text-center text-gray-400 text-xl" v-if="tournament.donations.length === 0">
            There are no donations yet.
        </div>
    </Tournament>
</template>