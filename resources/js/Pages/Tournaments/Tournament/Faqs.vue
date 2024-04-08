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

        <div class="text-center text-gray-400 text-xl" v-if="tournament.faqs.length === 0">
            There are no FAQs.
        </div>
    </Tournament>
</template>