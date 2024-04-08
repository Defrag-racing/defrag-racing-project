<script>
    import HomeLayout from '@/Layouts/HomeLayout.vue'

    export default {
        layout: HomeLayout,
    }
</script>

<script setup>
    import { Head } from '@inertiajs/vue3';
    import MapCardSmall from '@/Components/MapCardSmall.vue';
    import ServerCard from '@/Components/ServerCard.vue';

    const props = defineProps({
        maps: Array,
        servers: Array,
        announcement: Object
    });
</script>

<template>
    <div>
        <Head title="Home" />

        <div class="py-12">
            <div class="max-w-8xl mx-auto sm:px-6 lg:px-8">
                <div class="flex justify-center mt-10">
                    <img src="/images/logo.png">
                </div>

                <div class="px-4 mx-auto max-w-screen-xl text-center py-10">

                    <a href="#" class="inline-flex justify-between items-center py-1 px-1 pe-4 mb-7 text-sm text-blue-700 bg-blue-100 rounded-full dark:bg-blue-900 dark:text-blue-300 hover:bg-blue-200 dark:hover:bg-blue-800">
                        <span class="text-xs bg-blue-600 rounded-full text-white px-4 py-1.5 me-3">Latest News</span> <span class="text-sm font-medium">
                            {{ announcement.title }}
                        </span> 
                        <svg class="w-2.5 h-2.5 ms-2 rtl:rotate-180" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 6 10">
                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 9 4-4-4-4"/>
                        </svg>
                    </a>

                    <h1 class="mb-4 text-4xl font-extrabold tracking-tight leading-none text-gray-900 md:text-5xl lg:text-6xl dark:text-white">Welcome to Defrag<span class="text-6xl text-blue-500">.</span>Racing</h1>
                    <p class="mb-8 text-lg font-normal text-gray-500 lg:text-xl sm:px-16 lg:px-48 dark:text-gray-200">
                        Defrag is a speed running game where you compete on reaching the finish line in the shortest time possible.
                    </p>

                    <div class="flex justify-center">
                        <a href="#" class="inline-flex justify-center items-center py-2.5 px-5 text-base font-medium text-center text-white rounded-lg bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 dark:focus:ring-blue-900">
                            Getting Started
                            <svg class="w-3.5 h-3.5 ms-2 rtl:rotate-180" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 10">
                                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M1 5h12m0 0L9 1m4 4L9 9"/>
                            </svg>
                        </a>
                    </div>
                </div>

                <div class="py-2">
                    <div class="flex items-center mb-5">
                        <div style="height: 1px;" class="flex-grow bg-gray-700"></div>
                        <div class="text-white text-xl mx-2">Latest Servers</div>
                        <div style="height: 1px;" class="flex-grow bg-gray-700"></div>
                    </div>
                    
                    <div class="flex flex-wrap justify-center items-center gap-4">
                        <div v-for="server in servers" :key="server.id">
                            <ServerCard :server="server" />
                        </div>
                    </div>
                </div>

                <div class="py-2">
                    <div class="flex items-center mb-5">
                        <div style="height: 1px;" class="flex-grow bg-gray-700"></div>
                        <div class="text-white text-xl mx-2">Latest Maps</div>
                        <div style="height: 1px;" class="flex-grow bg-gray-700"></div>
                    </div>
                    
                    <div class="flex flex-wrap justify-center items-center gap-4">
                        <div v-for="map in maps">
                            <MapCardSmall :map="map" :mapname="map.name" :key="map.id" />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
