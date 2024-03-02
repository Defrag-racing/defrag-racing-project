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
    </Tournament>
</template>