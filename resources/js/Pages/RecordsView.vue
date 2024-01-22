<script setup>
    import { Head, router } from '@inertiajs/vue3';
    import Record from '@/Components/Record.vue';
    import RecordSmallScreen from '@/Components/RecordSmallScreen.vue';
    import Pagination from '@/Components/Basic/Pagination.vue';
    import Dropdown from '@/Components/Laravel/Dropdown.vue';
    import { watchEffect, ref, onMounted, onUnmounted } from 'vue';

    const props = defineProps({
        records: Object
    });

    const physics = ref('all');
    const screenWidth = ref(window.innerWidth);
    const isRotating = ref(false);
    const interval = ref(null);

    const startInterval = () => {
        if (interval.value == null) {
            interval.value = setInterval(updatePage, 30000);
        }
    }

    const stopInterval = () => {
        clearInterval(interval.value);
        interval.value = null;
    }

    const updatePage = () => {
        if (isRotating.value) {
            return;
        }

        isRotating.value = true;

        router.reload()

        setTimeout(() => {
            isRotating.value = false;
        }, 1500);
    }

    const sortByPhysics = (newPhysics) => {
        physics.value = newPhysics

        router.reload({
            data: {
                physics: physics.value,
                page: 1
            }
        })
    }

    const resizeScreen = () => {
        screenWidth.value = window.innerWidth
    }

    watchEffect(() => {
        physics.value = route().params['physics'] ?? 'all';
    });

    onMounted(() => {
        window.addEventListener("resize", resizeScreen);

        startInterval();
    });

    onUnmounted(() => {
        window.removeEventListener("resize", resizeScreen);
        stopInterval();
    });
</script>

<template>
    <div>
        <Head title="Records" />

        <div class="max-w-8xl mx-auto pt-6 px-4 md:px-6 lg:px-8">
            <div class="flex justify-between items-center flex-wrap">
                <h2 class="font-semibold text-xl text-gray-200 leading-tight">
                    Records
                </h2>

                <div class="flex">
                    <div @click="updatePage" title="Auto Update Servers every 30 seconds" class="flex items-center text-white bg-grayop-700 py-2 px-4 rounded-md font-bold cursor-pointer bg-grayop-700 hover:bg-gray-600 mr-3">
                        <div class="w-8 h-8 mr-2">
                            <svg :class="{ 'rotate-animation': isRotating }" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                            </svg>
                        </div>
    
                        <div>
                            <div>
                                <span class="text-center">Auto Update</span>
                            </div>
        
                            <div class="text-xs text-gray-500 text-center">Every 30 seconds</div>
                        </div>
                    </div>

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
                <div class="rounded-md p-3 flex-grow bg-grayop-700 flex flex-col">
                    <div class="flex-grow" v-if="screenWidth > 640">
                        <Record v-for="record in records.data" :key="record.id" :record="record" />
                    </div>

                    <div class="flex-grow" v-else>
                        <RecordSmallScreen v-for="record in records.data" :key="record.id" :record="record" />
                    </div>

                    <div class="flex justify-center">
                        <Pagination :last_page="records.last_page" :current_page="records.current_page" :link="records.first_page_url" />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
