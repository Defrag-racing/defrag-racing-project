<script>
    import HomeLayout from '@/Layouts/HomeLayout.vue'
    import { Link } from '@inertiajs/vue3';

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
                    <Link :href="route('announcements')" class="inline-flex justify-between items-center py-1 px-1 pe-4 mb-3 text-sm  rounded-full bg-blue-900 text-blue-300 hover:bg-blue-800">
                        <span class="text-xs bg-blue-600 rounded-full text-white px-4 py-1.5 me-3">
                            <span class="font-bold">Latest News - </span>
                            <span class="text-gray-300">{{ timeSince(announcement.created_at) }} ago</span>
                        </span>
                        <span class="text-sm font-medium">
                            {{ announcement.title }}
                        </span> 
                        <svg class="w-2.5 h-2.5 ms-2 rtl:rotate-180" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 6 10">
                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 9 4-4-4-4"/>
                        </svg>
                    </Link>

                    <div class="flex justify-center w-full">
                        <Link :href="route('changelog')" class="flex gap-2 items-center rounded-full text-gray-400 hover:text-white py-1.5 me-3 cursor-pointer" style="width: 100px;">
                            <span>Changelog</span>
                            <span>
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M17.25 8.25 21 12m0 0-3.75 3.75M21 12H3" />
                                </svg>
                            </span>
                        </Link> 
                    </div>

                    <h1 class="mb-4 text-4xl font-extrabold tracking-tight leading-none md:text-5xl lg:text-6xl text-white">Welcome to Defrag<span class="text-6xl text-blue-500">.</span>Racing</h1>
                    <p class="mb-8 text-lg font-normal lg:text-xl sm:px-16 lg:px-48 text-gray-200">
                        Defrag is a speed running game where you compete on reaching the finish line in the shortest time possible.
                    </p>

                    <!-- <div class="flex justify-center">
                        <Link :href="route('getting.started')" class="inline-flex justify-center items-center py-2.5 px-5 text-base font-medium text-center text-white rounded-lg bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 dark:focus:ring-blue-900">
                            Getting Started
                            <svg class="w-3.5 h-3.5 ms-2 rtl:rotate-180" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 14 10">
                                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M1 5h12m0 0L9 1m4 4L9 9"/>
                            </svg>
                        </Link>
                    </div> -->
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
