<script setup>
    import { Head } from '@inertiajs/vue3';
    import ServerCard from '@/Components/ServerCard.vue';
    import Dropdown from '@/Components/Laravel/Dropdown.vue';

    import { router } from '@inertiajs/vue3';
    import { onMounted, ref } from 'vue';

    const props = defineProps({
        servers: Array,
    });

    const players = ref(0);
    const interval = ref(null);
    const isRotating = ref(false);
    const sorting = ref('popularity');
    const listedServers = ref(props.servers);

    const startInterval = () => {
        interval.value = setInterval(updatePage, 30000);
    }

    const stopInterval = () => {
        clearInterval(interval.value);
    }

    const updatePage = () => {
        if (isRotating.value) {
            return;
        }

        isRotating.value = true;

        router.reload()
        sort()

        setTimeout(() => {
            isRotating.value = false;
        }, 1500);
    }

    const countPlayers = () => {
        players.value = 0
        props.servers.forEach(element => {
            players.value += element.online_players.length
        });
    }

    onMounted(() => {
        countPlayers()

        startInterval()
    })

    const getServerName = (name) => {
        const colorRegex = /\^\d|\^x[\da-fA-F]{2}|\^[\da-fA-F]{6}/g;

        const cleanedString = name.replace(colorRegex, '');

        return cleanedString;
    }

    const sort = () => {
        if (sorting.value == 'popularity') {
            sortByPopularity();
            return;
        }

        if (sorting.value == 'alphabetically') {
            sortByAlphabets();
            return;
        }

    }

    const sortByPopularity = () => {
        listedServers.value = props.servers.sort((a, b) => {
            const playersA = a.online_players.length;
            const playersB = b.online_players.length;

            if (playersA < playersB) {
                return 1;
            }

            if (playersA > playersB) {
                return -1;
            }

            return 0;
        });

        sorting.value = 'popularity'
    }

    const sortByAlphabets = () => {
        listedServers.value = props.servers.sort((a, b) => {
            const playersA = getServerName(a.name).toLowerCase();
            const playersB = getServerName(b.name).toLowerCase();

            if (playersA < playersB) {
                return -1;
            }

            if (playersA > playersB) {
                return 1;
            }

            return 0;
        });

        sorting.value = 'alphabetically'
    }
</script>

<template>
    <div>
        <Head title="Servers" />

        <div class="max-w-8xl mx-auto pt-6 px-4 md:px-6 lg:px-8">
            <div class="flex justify-between items-center flex-wrap">
                <h2 class="font-semibold text-3xl text-gray-800 dark:text-gray-200 leading-tight">
                    Servers
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

                    <Dropdown align="center" width="48">
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
                            <div @click="sortByPopularity" class="cursor-pointer block px-4 py-2 text-sm leading-5 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-grayop-800 focus:outline-none focus:bg-gray-100 dark:focus:bg-grayop-800 transition duration-150 ease-in-out">
                                Popularity
                            </div>

                            <div @click="sortByAlphabets" class="cursor-pointer block px-4 py-2 text-sm leading-5 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-grayop-800 focus:outline-none focus:bg-gray-100 dark:focus:bg-grayop-800 transition duration-150 ease-in-out">
                                Alphabetically
                            </div>
                        </template>
                    </Dropdown>
                </div>
            </div>

            <div class="text-sm text-blue-400 flex items-center mt-3">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                </svg>

                <div class="ml-2 mr-5">{{ players }} Online Players</div>

                <div class="flex items-center text-gray-400 ml-5">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 14.25h13.5m-13.5 0a3 3 0 0 1-3-3m3 3a3 3 0 1 0 0 6h13.5a3 3 0 1 0 0-6m-16.5-3a3 3 0 0 1 3-3h13.5a3 3 0 0 1 3 3m-19.5 0a4.5 4.5 0 0 1 .9-2.7L5.737 5.1a3.375 3.375 0 0 1 2.7-1.35h7.126c1.062 0 2.062.5 2.7 1.35l2.587 3.45a4.5 4.5 0 0 1 .9 2.7m0 0a3 3 0 0 1-3 3m0 3h.008v.008h-.008v-.008Zm0-6h.008v.008h-.008v-.008Zm-3 6h.008v.008h-.008v-.008Zm0-6h.008v.008h-.008v-.008Z" />
                    </svg>
    
                    <div class="ml-2">{{ servers.length }} Online Servers</div>
                </div>
            </div>
        </div>

        <div class="py-12">
            <div class="max-w-8xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-grayop-800 overflow-hidden shadow-xl sm:rounded-lg p-4 flex justify-center flex-wrap">
                    <ServerCard v-for="server in listedServers" :server="server" :key="server.id" />
                </div>
            </div>
        </div>
    </div>
</template>
