<script setup>
    import { Link } from '@inertiajs/vue3';
    import Tournament from '@/Pages/Tournaments/Tournament.vue';
    import DemoValidationEntry from '@/Components/Rounds/DemoValidationEntry.vue';

    const props = defineProps({
        tournament: Object,
        round: Object,
        unvalidated_vq3: Number,
        unvalidated_cpm: Number
    });
</script>

<template>
    <Tournament :tournament="tournament" tab="ManageTournament">
        <h1 class="font-black text-3xl dark:text-white mb-3">Demo Validation for {{ round.name }}</h1>

        <div class="tech-line-overview my-4"></div>

        <div class="grid sm:grid-cols-2 gap-4 mt-10">
            <div class="m-1 w-full p-2 pt-0 mr-6">
                <!-- Heading -->
                <div class="w-full text-center rounded-md bg-blue-900 dark:bg-opacity-25 bg-opacity-15 p-2">
                    <div class="uppercase font-black text-2xl dark:text-blue-200 text-center w-full">VQ3</div>
                    <div class="text-white">
                        ({{ unvalidated_vq3 }} Unvalidated)
                    </div>
                </div>


                <DemoValidationEntry v-for="demo in round.vq3_demos" :demo="demo" :round="round" :tournament="tournament" :key="demo.id" />

                <div v-if="round.vq3_demos?.length == 0">
                    <div class="text-xl text-white mt-5 text-center">No Demos Submitted</div>
                </div>
            </div>

            <div class="m-1 w-full p-2 pt-0">
                <div class="w-full text-center rounded-md bg-green-900 dark:bg-opacity-25 bg-opacity-15 p-2">
                    <div class="uppercase font-black text-2xl dark:text-green-200 text-center w-full">CPM</div>
                    <div class="text-white">
                        ({{ unvalidated_cpm }} Unvalidated)
                    </div>
                </div>

                <DemoValidationEntry v-for="demo in round.cpm_demos" :demo="demo" :round="round" :tournament="tournament" :key="demo.id" />

                <div v-if="round.cpm_demos?.length == 0">
                    <div class="text-xl text-white mt-5 text-center">No Demos Submitted</div>
                </div>
            </div>
        </div>
    </Tournament>
</template>
