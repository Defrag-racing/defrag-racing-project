<script setup>
    import Tournament from '@/Pages/Tournaments/Tournament.vue';
    import FaqForm from '@/Pages/Tournaments/Management/Faqs/FaqForm.vue';
    
    const props = defineProps({
        tournament: Object,
    });

</script>

<template>
    <Tournament :tournament="tournament" tab="ManageTournament">
        <FaqForm :faq="{}" url="tournaments.faqs.store" :tournament="tournament" />
    </Tournament>
</template>
