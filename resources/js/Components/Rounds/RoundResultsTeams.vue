<script setup>
    import DemoClanResultEntry from '@/Components/Rounds/DemoClanResultEntry.vue';
    import ResultTabs from '@/Components/Rounds/ResultTabs.vue';
    import { Link } from '@inertiajs/vue3';

    const props = defineProps({
        round: Object,
        tournament: Object,
        teams: Array
    })
</script>

<template>
    <h1 class="font-black text-3xl dark:text-white mb-3">Team Results</h1>

    <div class="tech-line-overview my-4"></div>

    <ResultTabs active="teams" :tournament="tournament" :round="round" />

    <div class="tech-line-overview my-4"></div>

    <div class="grid sm:grid-cols-3 mt-10">
        <div class="w-full">
            <!-- Heading -->
            <div class="w-full flex items-center bg-gray-700 rounded-tl-md dark:bg-opacity-25 bg-opacity-15 p-2 shadow-md">
                <div class="uppercase font-black text-2xl dark:text-gray-200 text-center w-full">Team</div>
            </div>
        </div>

        <div class="w-full">
            <!-- Heading -->
            <div class="w-full flex items-center bg-blue-900 dark:bg-opacity-25 bg-opacity-15 p-2 shadow-md">
                <div class="uppercase font-black text-2xl dark:text-blue-200 text-center w-full">vq3</div>
            </div>
        </div>

        <div class="w-full">
            <div class="w-full flex items-center rounded-tr-md bg-green-900 dark:bg-opacity-25 bg-opacity-15 p-2 shadow-md">
                <div class="uppercase font-black text-2xl dark:text-green-200 text-center w-full">CPM</div>
            </div>
        </div>
    </div>

    <div class="grid sm:grid-cols-3" v-for="team in teams">
        <div class="w-full">
            <!-- Heading -->
            <div class="w-full flex items-center bg-gray-700 dark:bg-opacity-10 bg-opacity-15 p-1 shadow-md">
                <div class="font-black dark:text-gray-200 text-center w-full" v-html="q3tohtml(team.name)">
                    
                </div>
            </div>
        </div>

        <div class="w-full">
            <!-- Heading -->
            <div class="w-full flex items-center bg-blue-900 dark:bg-opacity-10 bg-opacity-15 p-1 shadow-md">
                <Link :href="route('profile.index', team.vq3_player.id)" class="font-black dark:text-blue-200 text-center w-full" v-html="q3tohtml(team.vq3_player.name)"></Link>
            </div>
        </div>

        <div class="w-full">
            <div class="w-full flex items-center bg-green-900 dark:bg-opacity-10 bg-opacity-15 p-1 shadow-md">
                <Link :href="route('profile.index', team.cpm_player.id)" class="font-black dark:text-green-200 text-center w-full" v-html="q3tohtml(team.cpm_player.name)"></Link>
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