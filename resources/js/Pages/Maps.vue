<script setup>
    import { Head } from '@inertiajs/vue3';
    import Pagination from '@/Components/Basic/Pagination.vue';
    import MapCard from '@/Components/MapCard.vue';
    import Dropdown from '@/Components/Laravel/Dropdown.vue';

    const props = defineProps({
        maps: Object,
    });
</script>

<template>
    <div>
        <Head title="Maps" />

        <div class="max-w-8xl mx-auto pt-6 px-4 md:px-6 lg:px-8">
            <div class="flex justify-between flex-wrap">
                <h2 class="font-semibold text-3xl text-gray-200 leading-tight">
                    Maps
                </h2>

                <Dropdown align="right" width="48">
                    <template #trigger>
                        <button class="flex items-center text-white bg-grayop-700 py-2 px-4 rounded-md font-bold cursor-pointer bg-grayop-700 hover:bg-gray-600 mr-3">
                            <div class="w-8 h-8 mr-2">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75" />
                                </svg>
                            </div>
        
                            <div>
                                <div class="text-left">
                                    Filters
                                </div>
        
                                <div class="text-xs text-gray-500 text-center">Currently: <span class="text-gray-400">Popularity</span></div>
                            </div>
                        </button>
                    </template>

                    <template #content>
                        <div class="cursor-pointer block px-4 py-2 text-sm leading-5 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-grayop-800 focus:outline-none focus:bg-gray-100 dark:focus:bg-grayop-800 transition duration-150 ease-in-out">
                            Popularity
                        </div>
                    </template>
                </Dropdown>
            </div>

            <div class="text-sm text-blue-400 flex items-center mt-3">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 6.75V15m6-6v8.25m.503 3.498 4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934c-.317.159-.69.159-1.006 0L9.503 3.252a1.125 1.125 0 0 0-1.006 0L3.622 5.689C3.24 5.88 3 6.27 3 6.695V19.18c0 .836.88 1.38 1.628 1.006l3.869-1.934c.317-.159.69-.159 1.006 0l4.994 2.497c.317.158.69.158 1.006 0Z" />
                </svg>

                <div class="ml-2 mr-5">{{ maps.total }} Maps Found</div>
            </div>
        </div>

        <div class="py-12">
            <div class="max-w-8xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-grayop-800 overflow-hidden shadow-xl sm:rounded-lg p-4">
                    <div class="flex flex-wrap justify-center">
                        <MapCard v-for="map in maps.data" :map="map" :mapname="map.name" :key="map.id" />
                    </div>
                    
                    <div class="flex justify-center">
                        <Pagination :current_page="maps.current_page" :last_page="maps.last_page" :link="maps.first_page_url" />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
