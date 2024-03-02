<script setup>
    import Tournament from '@/Pages/Tournaments/Tournament.vue';
    import FAQ from '@/Components/FAQ.vue';

    const props = defineProps({
        tournament: Object
    });
</script>

<template>
    <Tournament :tournament="tournament" tab="FAQs">
        <FAQ :tournament="tournament" v-for="faq in tournament.faqs" :faq="faq" />
    </Tournament>
</template>