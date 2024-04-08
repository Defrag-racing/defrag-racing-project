<script setup>
    import Tournament from '@/Pages/Tournaments/Tournament.vue';
    import DonationForm from '@/Pages/Tournaments/Management/Donations/DonationForm.vue';
    
    const props = defineProps({
        tournament: Object,
        donation: Object
    });

</script>

<template>
    <Tournament :tournament="tournament" tab="ManageTournament">
        <DonationForm :donation="donation" url="tournaments.donations.update" :tournament="tournament" />
    </Tournament>
</template>
