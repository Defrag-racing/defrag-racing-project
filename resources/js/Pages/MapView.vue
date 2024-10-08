<script setup>
    import { Head, router, usePage } from '@inertiajs/vue3';
    import MapCardLine from '@/Components/MapCardLine.vue';
    import MapRecord from '@/Components/MapRecord.vue';
    import MapRecordSmall from '@/Components/MapRecordSmall.vue';
    import MapCardLineSmall from '@/Components/MapCardLineSmall.vue';
    import Pagination from '@/Components/Basic/Pagination.vue';
    import ToggleButton from '@/Components/Basic/ToggleButton.vue';
    import Dropdown from '@/Components/Laravel/Dropdown.vue';
    import { watchEffect, ref, onMounted, onUnmounted, computed } from 'vue';

    const props = defineProps({
        map: Object,
        cpmRecords: Object,
        vq3Records: Object,
        cpmOldRecords: Object,
        vq3OldRecords: Object,
        my_cpm_record: Object,
        my_vq3_record: Object,
        showOldtop: {
            type: Boolean,
            default: false
        }
    });

    const page = usePage();

    const order = ref('ASC');
    const column = ref('time');
    const gametype = ref('run');

    const gametypes = [
        'run',
        'ctf1',
        'ctf2',
        'ctf3',
        'ctf4',
        'ctf5',
        'ctf6',
        'ctf7'
    ];

    const screenWidth = ref(window.innerWidth);

    const sortByDate = () => {
        if (column.value === 'date_set') {
            order.value = (order.value == 'ASC') ? 'DESC' : 'ASC';
        }

        column.value = 'date_set';

        router.reload({
            data: {
                sort: column.value,
                order: order.value
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
                order: order.value
            }
        })
    }

    const sortByGametype = (gt) => {
        gametype.value = gt;

        router.reload({
            data: {
                gametype: gt,
                sort: 'time',
                order: 'ASC'
            }
        })
    }

    watchEffect(() => {
        order.value = route().params['order'] ?? 'ASC';
        column.value = route().params['sort'] ?? 'time';
        gametype.value = route().params['gametype'] ?? 'run';
    });

    const resizeScreen = () => {
        screenWidth.value = window.innerWidth
    };

    const onChangeOldtop = (value) => {
        router.reload({
            data: {
                showOldtop: value
            }
        })
    }

    const getVq3Records = computed(() => {
        if (! props.showOldtop ) {
            return props.vq3Records
        }

        let oldtop_data = props.vq3OldRecords.data.map((item) => {
            item['oldtop'] = true

            return item
        });

        let result = {
            total: Math.max(props.vq3Records.total, props.vq3OldRecords.total),
            data: [...props.vq3Records.data, ...oldtop_data],
            first_page_url: props.vq3Records.first_page_url,
            current_page: props.vq3Records.current_page,
            last_page: (props.vq3Records.total > props.vq3OldRecords.total) ? props.vq3Records.last_page : props.vq3OldRecords.last_page,
            per_page: props.vq3Records.per_page
        }

        result.data.sort((a, b) => a.time - b.time)

        return result
    })

    const getCpmRecords = computed(() => {
        if (! props.showOldtop ) {
            return props.cpmRecords
        }

        let oldtop_data = props.cpmOldRecords.data.map((item) => {
            item['oldtop'] = true

            return item
        });

        let result = {
            total: Math.max(props.cpmRecords.total, props.cpmOldRecords.total),
            data: [...props.cpmRecords.data, ...oldtop_data],
            first_page_url: props.cpmRecords.first_page_url,
            current_page: props.cpmRecords.current_page,
            last_page: (props.cpmRecords.total > props.cpmOldRecords.total) ? props.cpmRecords.last_page : props.cpmOldRecords.last_page,
            per_page: props.cpmRecords.per_page
        }

        result.data.sort((a, b) => a.time - b.time)

        return result
    })

    onMounted(() => {
        window.addEventListener("resize", resizeScreen);
    });

    onUnmounted(() => {
        window.removeEventListener("resize", resizeScreen);
    });
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
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.59 14.37a6 6 0 0 1-5.84 7.38v-4.8m5.84-2.58a14.98 14.98 0 0 0 6.16-12.12A14.98 14.98 0 0 0 9.631 8.41m5.96 5.96a14.926 14.926 0 0 1-5.841 2.58m-.119-8.54a6 6 0 0 0-7.381 5.84h4.8m2.581-5.84a14.927 14.927 0 0 0-2.58 5.84m2.699 2.7c-.103.021-.207.041-.311.06a15.09 15.09 0 0 1-2.448-2.448 14.9 14.9 0 0 1 .06-.312m-2.24 2.39a4.493 4.493 0 0 0-1.757 4.306 4.493 4.493 0 0 0 4.306-1.758M16.5 9a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Z" />
                                    </svg>
                                </div>

                                <div>
                                    <div class="text-left">
                                        Gametype
                                    </div>

                                    <div class="text-xs text-gray-500 text-center flex">
                                        <span>Currently:</span>
                                        <span class="text-gray-400 capitalize ml-1"> {{ gametype }} </span>
                                    </div>
                                </div>
                            </button>
                        </template>

                        <template #content>
                            <div v-for="gt in gametypes" @click="sortByGametype(gt)" class="flex justify-between cursor-pointer block px-4 py-2 text-sm leading-5 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-grayop-800 focus:outline-none focus:bg-gray-100 dark:focus:bg-grayop-800 transition duration-150 ease-in-out">
                                <span class="uppercase"> {{ gt }} </span>
                            </div>
                        </template>
                    </Dropdown>
                </div>
            </div>


            <div class="flex mt-3">
                <div class="text-sm text-blue-400 flex items-center mr-2">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0ZM3.75 12h.007v.008H3.75V12Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm-.375 5.25h.007v.008H3.75v-.008Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                    </svg>

                    <div class="ml-2 mr-5">{{ vq3Records.total }} VQ3 Records</div>
                </div>

                <div class="text-sm text-blue-400 flex items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0ZM3.75 12h.007v.008H3.75V12Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm-.375 5.25h.007v.008H3.75v-.008Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                    </svg>

                    <div class="ml-2 mr-5">{{ cpmRecords.total }} CPM Records</div>
                </div>
            </div>
        </div>

        <div class="max-w-8xl mx-auto py-10 sm:px-6 lg:px-8">
            <div v-if="screenWidth > 640">
                <MapCardLine :map="map">
                    <div class="text-white mr-3">Old Top: </div>
                    <ToggleButton :isActive="showOldtop"  @setIsActive="onChangeOldtop" />
                    <div class="mr-5"></div>
                </MapCardLine>
            </div>

            <div v-else>
                <MapCardLineSmall :map="map" />
            </div>


            <div class="md:flex justify-center mb-5">
                <div class="rounded-md p-3 flex-1 bg-grayop-700 flex flex-col mr-1 justify-center">
                    <div v-if="my_vq3_record">
                        <div class="flex-grow" v-if="screenWidth > 640">
                            <MapRecord physics="VQ3" :record="my_vq3_record" />
                        </div>

                        <div class="flex-grow" v-else>
                            <MapRecordSmall :record="my_vq3_record" />
                        </div>
                    </div>

                    <div v-else class="flex items-center justify-center text-gray-500">
                        <div v-if="page.props?.auth?.user">You have no VQ3 Record In this map</div>
                        <div v-else>You need to be logged in to see your records</div>
                    </div>
                </div>

                <div class="rounded-md p-3 flex-1 bg-grayop-700 flex flex-col ml-1 mt-5 md:mt-0 justify-center">
                    <div v-if="my_cpm_record">
                        <div class="flex-grow" v-if="screenWidth > 640">
                            <MapRecord physics="CPM" :record="my_cpm_record" />
                        </div>

                        <div class="flex-grow" v-else>
                            <MapRecordSmall :record="my_cpm_record" />
                        </div>
                    </div>

                    <div v-else class="flex items-center justify-center text-gray-500 items-center">
                        <div v-if="page.props?.auth?.user">You have no CPM Record In this map</div>
                        <div v-else>You need to be logged in to see your records</div>
                    </div>
                </div>
            </div>

            <div class="md:flex justify-center">
                <div class="rounded-md p-3 flex-1 bg-grayop-700 flex flex-col mr-1">
                    <div v-if="getVq3Records.total > 0">
                        <div class="flex-grow" v-if="screenWidth > 640">
                            <MapRecord v-for="record in getVq3Records.data" physics="VQ3" :oldtop="record.oldtop" :key="record.id" :record="record" />
                        </div>

                        <div class="flex-grow" v-else>
                            <MapRecordSmall v-for="record in getVq3Records.data" :key="record.id" :record="record" />
                        </div>
                    </div>

                    <div v-else class="flex items-center justify-center mt-20 text-gray-500 text-lg">
                        <div>
                            <div class="flex items-center justify-center mb-4">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-10 h-10">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                </svg>
                            </div>
                            <div>There are no VQ3 Records</div>
                        </div>
                    </div>

                    <div class="flex justify-center" v-if="getVq3Records.total > getVq3Records.per_page">
                        <Pagination pageName="vq3Page" :last_page="getVq3Records.last_page" :current_page="getVq3Records.current_page" :link="getVq3Records.first_page_url" />
                    </div>
                </div>

                <div class="rounded-md p-3 flex-1 bg-grayop-700 flex flex-col ml-1 mt-5 md:mt-0">
                    <div v-if="getCpmRecords.total > 0">
                        <div class="flex-grow" v-if="screenWidth > 640">
                            <MapRecord v-for="record in getCpmRecords.data" physics="CPM" :key="record.id" :record="record" />
                        </div>

                        <div class="flex-grow" v-else>
                            <MapRecordSmall v-for="record in getCpmRecords.data" :key="record.id" :record="record" />
                        </div>
                    </div>

                    <div v-else class="flex items-center justify-center mt-20 text-gray-500 text-lg">
                        <div>
                            <div class="flex items-center justify-center mb-4">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-10 h-10">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                </svg>
                            </div>
                            <div>There are no CPM Records</div>
                        </div>
                    </div>

                    <div class="flex justify-center" v-if="getCpmRecords.total > getCpmRecords.per_page">
                        <Pagination pageName="cpmPage" :last_page="getCpmRecords.last_page" :current_page="getCpmRecords.current_page" :link="getCpmRecords.first_page_url" />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
