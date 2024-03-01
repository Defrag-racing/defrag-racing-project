<script setup>
    import Tournament from '@/Pages/Tournaments/Tournament.vue';
    import FaqForm from '@/Pages/Tournaments/Management/Faqs/FaqForm.vue';
    
    const props = defineProps({
        tournament: Object,
        faq: Object
    });

</script>

<template>
    <Tournament :tournament="tournament" tab="ManageTournament">
        <FaqForm :faq="faq" url="tournaments.faqs.update" :tournament="tournament" />
    </Tournament>
</template>
