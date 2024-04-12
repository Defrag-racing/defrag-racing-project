<script setup>
    import Tournament from '@/Pages/Tournaments/Tournament.vue';
    import StandingsEntry from '@/Components/Rounds/StandingsEntry.vue';
    import ResultTabs from '@/Components/Rounds/ResultTabs.vue';

    const props = defineProps({
        tournament: Object,
        vq3_standings: Object,
        cpm_standings: Object
    });
</script>

<template>
    <Tournament :tournament="tournament" tab="Standings">
        <div class="flex justify-between items-center">
            <h1 class="font-black text-3xl text-white mb-3">Standings</h1>
        </div>
    
        <div class="tech-line-overview"></div>

        <ResultTabs active="single" :tournament="tournament" url="tournaments.standings.index" :args="[tournament.id]" />

        <div class="grid sm:grid-cols-2 gap-4 mt-10">
            <div class="m-1 w-full p-2 pt-0 mr-6">
                <!-- Heading -->
                <div class="w-full flex items-center rounded-md bg-blue-900 bg-opacity-25 bg-opacity-15 p-2 shadow-md">
                    <div class="uppercase font-black text-2xl text-blue-200 text-center w-full">vq3</div>
                </div>
    
    
                <StandingsEntry physics="vq3" v-for="(demo, index) in vq3_standings" :demo="demo" :key="demo.id" :rank="index+1" />
    
                <div v-if="vq3_standings?.length == 0">
                    <div class="text-xl text-white mt-5 text-center">No results yet.</div>
                </div>
            </div>
    
            <div class="m-1 w-full p-2 pt-0">
                <div class="w-full flex items-center rounded-md bg-green-900 bg-opacity-25 bg-opacity-15 p-2 shadow-md">
                    <div class="uppercase font-black text-2xl text-green-200 text-center w-full">CPM</div>
                </div>
    
                <StandingsEntry physics="cpm" v-for="(demo, index) in cpm_standings" :demo="demo" :key="demo.id" :rank="index+1" />
    
                <div v-if="cpm_standings?.length == 0">
                    <div class="text-xl text-white mt-5 text-center">No results yet.</div>
                </div>
            </div>
        </div>
    </Tournament>
</template>

<style scoped>
    .tech-line-overview {
        background: linear-gradient(90deg,#ffffff42,#2f90ff,#ffffff42);
        box-shadow: 0 0 50px 4px #0a7cffb2;
        height: 1px;
        margin-top: 10px;
        margin-bottom: 10px;
        max-width: 100%;
    }
</style>