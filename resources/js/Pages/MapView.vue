<script setup>
    import { Head, router } from '@inertiajs/vue3';
    import MapCard from '@/Components/MapCard.vue';
    import MapRecord from '@/Components/MapRecord.vue';
    import Pagination from '@/Components/Basic/Pagination.vue';
    import Dropdown from '@/Components/Laravel/Dropdown.vue';
    import { watchEffect, ref } from 'vue';

    const props = defineProps({
        map: Object,
        records: Object
    });

    const order = ref('DESC');
    const column = ref('date_set');
    const physics = ref('all');

    const sortByDate = () => {
        if (column.value === 'date_set') {
            order.value = (order.value == 'ASC') ? 'DESC' : 'ASC';
        }

        column.value = 'date_set';

        router.reload({
            data: {
                sort: column.value,
                order: order.value,
                physics: physics.value
            }
        })
    }

    const sortByTime = () => {
        if (column.value === 'time') {
            order.value = (order.value == 'ASC') ? 'DESC' : 'ASC';
        } else {
            order.value = 'ASC';
        }

        column.value = 'time';

        router.reload({
            data: {
                sort: column.value,
                order: order.value,
                physics: physics.value
            }
        })
    }

    const sortByPhysics = (newPhysics) => {
        if (newPhysics !== physics.value) {
            order.value = 'ASC';
            column.value = 'time';
        }

        physics.value = newPhysics

        router.reload({
            data: {
                sort: column.value,
                order: order.value,
                physics: physics.value,
                page: 1
            }
        })
    }

    watchEffect(() => {
        order.value = route().params['order'] ?? 'DESC';
        column.value = route().params['sort'] ?? 'date_set';
        physics.value = route().params['physics'] ?? 'all';
    })
</script>

<template>
    <div>
        <Head :title="map.name" />

        <div class="max-w-8xl mx-auto pt-6 px-4 md:px-6 lg:px-8">
            <div class="flex justify-between items-center flex-wrap">
                <h2 class="font-semibold text-xl text-gray-200 leading-tight">
                    Map details
                </h2>


                <div class="flex flex-wrap">
                    <Dropdown align="right" width="48" class="mt-2 sm:mt-0">
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
            
                                    <div class="text-xs text-gray-500 text-center flex">
                                        <span>Currently:</span>
                                        <span class="text-gray-400 capitalize ml-1"> {{ column.split('_')[0] }} </span>
    
                                        <span class="ml-0.5">
                                            <svg v-if="order === 'ASC'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                            </svg>
    
                                            <svg v-else xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 15.75 7.5-7.5 7.5 7.5" />
                                            </svg>
                                        </span>
                                    </div>
                                </div>
                            </button>
                        </template>
    
                        <template #content>
                            <div @click="sortByDate" class="flex justify-between cursor-pointer block px-4 py-2 text-sm leading-5 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-grayop-800 focus:outline-none focus:bg-gray-100 dark:focus:bg-grayop-800 transition duration-150 ease-in-out">
                                <span>Date Set</span>
                                <svg v-if="order === 'ASC'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                </svg>
    
                                <svg v-else xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 15.75 7.5-7.5 7.5 7.5" />
                                </svg>                             
                            </div>
    
                            <div @click="sortByTime" class="flex justify-between cursor-pointer block px-4 py-2 text-sm leading-5 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-grayop-800 focus:outline-none focus:bg-gray-100 dark:focus:bg-grayop-800 transition duration-150 ease-in-out">
                                <span>Time</span>
                                <svg v-if="order === 'ASC'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                </svg>
    
                                <svg v-else xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 15.75 7.5-7.5 7.5 7.5" />
                                </svg>                           
                            </div>
                        </template>
                    </Dropdown>
    
    
                    <Dropdown align="right" width="48" class="mt-2 sm:mt-0">
                        <template #trigger>
                            <button class="flex items-center text-white bg-grayop-700 py-2 px-4 rounded-md font-bold cursor-pointer bg-grayop-700 hover:bg-gray-600 mr-3">
                                <div class="w-8 h-8 mr-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75" />
                                    </svg>
                                </div>
            
                                <div>
                                    <div class="text-left">
                                        Physics
                                    </div>
            
                                    <div class="text-xs text-gray-500 text-center flex">
                                        <span>Currently:</span>
                                        <span class="text-gray-400 capitalize ml-1"> {{ physics }} </span>
                                    </div>
                                </div>
                            </button>
                        </template>
    
                        <template #content>
                            <div @click="sortByPhysics('all')" class="flex justify-between cursor-pointer block px-4 py-2 text-sm leading-5 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-grayop-800 focus:outline-none focus:bg-gray-100 dark:focus:bg-grayop-800 transition duration-150 ease-in-out">
                                <span>ALL</span>                         
                            </div>

                            <div @click="sortByPhysics('cpm')" class="flex justify-between cursor-pointer block px-4 py-2 text-sm leading-5 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-grayop-800 focus:outline-none focus:bg-gray-100 dark:focus:bg-grayop-800 transition duration-150 ease-in-out">
                                <span>CPM</span>                         
                            </div>

                            <div @click="sortByPhysics('vq3')" class="flex justify-between cursor-pointer block px-4 py-2 text-sm leading-5 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-grayop-800 focus:outline-none focus:bg-gray-100 dark:focus:bg-grayop-800 transition duration-150 ease-in-out">
                                <span>VQ3</span>                         
                            </div>
                        </template>
                    </Dropdown>
                </div>
            </div>


            <div class="text-sm text-blue-400 flex items-center mt-3">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0ZM3.75 12h.007v.008H3.75V12Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm-.375 5.25h.007v.008H3.75v-.008Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                </svg>

                <div class="ml-2 mr-5">{{ records.total }} Records</div>
            </div>
        </div>

        <div class="max-w-8xl mx-auto py-10 sm:px-6 lg:px-8">
            <div class="flex flex-wrap justify-center">
                <div>
                    <MapCard :map="map" />
                </div>

                <div class="rounded-md p-3 flex-grow bg-grayop-700 flex flex-col">
                    <div class="flex-grow">
                        <MapRecord v-for="record in records.data" :key="record.id" :record="record" />
                    </div>

                    <div class="flex justify-center">
                        <Pagination :last_page="records.last_page" :current_page="records.current_page" :link="records.first_page_url" />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
