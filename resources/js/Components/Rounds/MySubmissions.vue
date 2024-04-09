<script setup>
    import MyDemoEntry from '@/Components/Rounds/MyDemoEntry.vue';
    import { ref } from 'vue';

    const props = defineProps({
        round: Object,
        tournament: Object
    })

    const show = ref(props.round.results ? false : true);
</script>

<template>
    <div class="flex w-full justify-between items-center mt-5 cursor-pointer" @click="show = !show" >
        <h1 class="font-black text-3xl text-white mb-3">My Times</h1>

        <div class="rounded-md h-10 w-10 flex items-center justify-center bg-blackop-30 text-white cursor-pointer hover:bg-blackop-20">
            <svg v-if="show" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 15.75 7.5-7.5 7.5 7.5" />
            </svg>
            
            <svg v-else xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
            </svg>
        </div>
    </div>

    <div class="tech-line-overview my-4"></div>

    <div class="grid sm:grid-cols-2 gap-4 mt-10" v-if="show">
        <div class="m-1 w-full p-2 pt-0 mr-6">
            <!-- Heading -->
            <div class="w-full flex items-center rounded-md bg-blue-900 bg-opacity-25 bg-opacity-15 p-2">
                <div class="uppercase font-black text-2xl text-blue-200 text-center w-full">vq3</div>
            </div>


            <MyDemoEntry v-for="demo in round.vq3_demos" :demo="demo" :key="demo.id" :tournament="tournament" />

            <div v-if="round.vq3_demos?.length == 0">
                <div class="text-xl text-white mt-5 text-center">No Demos Submitted</div>
            </div>
        </div>

        <div class="m-1 w-full p-2 pt-0">
            <div class="w-full flex items-center rounded-md bg-green-900 bg-opacity-25 bg-opacity-15 p-2">
                <div class="uppercase font-black text-2xl text-green-200 text-center w-full">CPM</div>
            </div>

            <MyDemoEntry v-for="demo in round.cpm_demos" :demo="demo" :key="demo.id" :tournament="tournament" />

            <div v-if="round.cpm_demos?.length == 0">
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