<script setup>
    import DemoResultEntry from '@/Components/Rounds/DemoResultEntry.vue';
    import ResultTabs from '@/Components/Rounds/ResultTabs.vue';

    defineProps({
        round: Object,
        tournament: Object
    })
</script>

<template>
    <h1 class="font-black text-3xl dark:text-white mb-3">Results</h1>

    <div class="tech-line-overview my-4"></div>

    <ResultTabs url="tournaments.rounds.index" :args="[tournament.id, round.id]" active="single" :tournament="tournament" />

    <div class="tech-line-overview my-4"></div>

    <div class="grid sm:grid-cols-2 gap-4 mt-10">
        <div class="m-1 w-full p-2 pt-0 mr-6">
            <!-- Heading -->
            <div class="w-full flex items-center rounded-md bg-blue-900 dark:bg-opacity-25 bg-opacity-15 p-2 shadow-md">
                <div class="uppercase font-black text-2xl dark:text-blue-200 text-center w-full">vq3</div>
            </div>


            <DemoResultEntry v-for="demo in round.vq3_results" :demo="demo" :key="demo.id" />

            <div v-if="round.vq3_results?.length == 0">
                <div class="text-xl text-white mt-5 text-center">No Demos Submitted</div>
            </div>
        </div>

        <div class="m-1 w-full p-2 pt-0">
            <div class="w-full flex items-center rounded-md bg-green-900 dark:bg-opacity-25 bg-opacity-15 p-2 shadow-md">
                <div class="uppercase font-black text-2xl dark:text-green-200 text-center w-full">CPM</div>
            </div>

            <DemoResultEntry v-for="demo in round.cpm_results" :demo="demo" :key="demo.id" />

            <div v-if="round.cpm_results?.length == 0">
                <div class="text-xl text-white mt-5 text-center">No Demos Submitted</div>
            </div>
        </div>
    </div>

</template>

<style scoped>
    .tech-line-overview {
        background: linear-gradient(90deg,#ffffff42,#2f90ff,#ffffff42);
        box-shadow: 0 0 50px 4px #0a7cffb2;
        height: 1px;
        max-width: 100%;
    }
</style>