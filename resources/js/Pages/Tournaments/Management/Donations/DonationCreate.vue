<script setup>
    import Tournament from '@/Pages/Tournaments/Tournament.vue';
    import DonationForm from '@/Pages/Tournaments/Management/Donations/DonationForm.vue';
    
    const props = defineProps({
        tournament: Object,
    });

</script>

<template>
    <Tournament :tournament="tournament" tab="ManageTournament">
        <DonationForm :donation="{}" url="tournaments.donations.store" :tournament="tournament" />
    </Tournament>
</template>
