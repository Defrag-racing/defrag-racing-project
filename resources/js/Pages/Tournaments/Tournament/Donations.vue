<script setup>
    import Tournament from '@/Pages/Tournaments/Tournament.vue';
    import Donation from '@/Components/Donation.vue';
    import { computed } from 'vue';


    const props = defineProps({
        tournament: Object
    });

    const donations = computed(() => {
        return props.tournament.donations.reduce((accumulator, currentValue) => {
            return accumulator + currentValue.amount;
        }, 0);
    });
</script>

<template>
    <Tournament :tournament="tournament" tab="Donations">
        <Donation :tournament="tournament" v-for="donation in tournament.donations" :donation="donation" />

        <div class="text-2xl font-bold text-gray-100 text-center my-4">
            Total donations: ${{ donations }}
        </div>

        <div class="text-center text-gray-400 text-xl" v-if="tournament.donations.length === 0">
            There are no donations yet.
        </div>
    </Tournament>
</template>