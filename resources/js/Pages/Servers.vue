<script setup>
import { Head } from '@inertiajs/vue3';
import ServerCard from '@/Components/ServerCard.vue';
import Dropdown from '@/Components/Laravel/Dropdown.vue';

import { router } from '@inertiajs/vue3';
import { onMounted, onUnmounted, ref } from 'vue';

const props = defineProps({
    servers: Array,
});

const players = ref(0);
const interval = ref(null);
const isRotating = ref(false);
const sorting = ref('popularity');
const sortingOrderPopularity = ref(0);
const sortingOrderAZ = ref(0);
const filterSelected = ref(0);
const listedServers = ref(props.servers);

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

onUnmounted(() => {
    stopInterval()
})

const getServerName = (name) => {
    const colorRegex = /\^\d|\^x[\da-fA-F]{2}|\^[\da-fA-F]{6}/g;

    const cleanedString = name.replace(colorRegex, '');

    return cleanedString;
}

const sort = () => {
    if (sorting.value == 'popularity') {
        sortByPopularity();
        filterByType();
        return;
    }

    if (sorting.value == 'alphabetically') {
        sortByAlphabets();
        filterByType();
        return;
    }

}

const changefilter = (id) => {
    filterSelected.value = id
    sort()
}

const changeSort = (sortType) => {
    if(sortType == 'popularity'){
        if(sorting.value == 'popularity'){
            sortingOrderPopularity.value = sortingOrderPopularity.value == 0 ? 1 : 0;
        }
    }else{
        if(sorting.value == 'alphabetically'){
            sortingOrderAZ.value = sortingOrderAZ.value == 0 ? 1 : 0;
        }
    }
    sorting.value = sortType
    sort()
}

const filterByType = () => {
    if(filterSelected.value == 1){
        listedServers.value = listedServers.value.filter(server=>{
            return server.defrag.includes('cpm');
        })
    }else if(filterSelected.value == 2){
        listedServers.value = props.servers.filter(server=>{
            return !server.defrag.includes('cpm');
        })
    }
}

const sortByPopularity = () => {
    listedServers.value = props.servers.sort((a, b) => {
        const playersA = a.online_players.length;
        const playersB = b.online_players.length;
        if (playersA < playersB) {
            return sortingOrderPopularity.value == 0 ? 1 : -1;
        }

        if (playersA > playersB) {
            return sortingOrderPopularity.value == 0 ? -1 : 1;
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
            return sortingOrderAZ.value == 0 ? -1 : 1;
        }

        if (playersA > playersB) {
            return sortingOrderAZ.value == 0 ? 1 : -1;
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
                <h2 class="sub-header text-3xl text-gray-200 leading-tight">
                    Servers
                </h2>
                <div class="sm:flex">
                    <div class="text-sm text-blue-400 flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                            stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                        </svg>

                        <div class="ml-2 mr-5">{{ players }} Online Players</div>

                        <div class="flex items-center text-gray-400 ml-5">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                stroke="currentColor" class="w-5 h-5">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M5.25 14.25h13.5m-13.5 0a3 3 0 0 1-3-3m3 3a3 3 0 1 0 0 6h13.5a3 3 0 1 0 0-6m-16.5-3a3 3 0 0 1 3-3h13.5a3 3 0 0 1 3 3m-19.5 0a4.5 4.5 0 0 1 .9-2.7L5.737 5.1a3.375 3.375 0 0 1 2.7-1.35h7.126c1.062 0 2.062.5 2.7 1.35l2.587 3.45a4.5 4.5 0 0 1 .9 2.7m0 0a3 3 0 0 1-3 3m0 3h.008v.008h-.008v-.008Zm0-6h.008v.008h-.008v-.008Zm-3 6h.008v.008h-.008v-.008Zm0-6h.008v.008h-.008v-.008Z" />
                            </svg>

                            <div class="ml-2">{{ servers.length }} Online Servers</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex justify-between align-center max-w-8xl mx-auto pt-6 px-4 md:px-6 lg:px-8">
            <div class="filters-wrapper hidemobile">
                <div class="single-filter-item" :class="{'active': filterSelected == 0}" @click="changefilter(0)">
                    <span>All</span>
                </div>
                <div class="single-filter-item" :class="{'active': filterSelected == 1}" @click="changefilter(1)">
                    <span>CPM</span>
                </div>
                <div class="single-filter-item" :class="{'active': filterSelected == 2}" @click="changefilter(2)">
                    <span>VQ3</span>
                </div>
            </div>
            <div class="sorting-wrapper">
                <div @click="changeSort('popularity')" class="single-sort-item" :class="{'active': sorting == 'popularity'}">
                    <span>Popularity</span>
                    <div class="sort-pointer" :class="{'rotated': sortingOrderPopularity == 1}"></div>
                </div>
                <div @click="changeSort('alphabetically')" class="single-sort-item" :class="{'active': sorting == 'alphabetically'}">
                    <span>A-Z</span>
                    <div class="sort-pointer" :class="{'rotated': sortingOrderAZ == 1}"></div>
                </div>
            </div>
        </div>

        <div class="py-6">
            <div class="max-w-8xl mx-auto sm:px-6 lg:px-8">
                <div class="overflow-hidden">
                    <div class="grid servers-wrapper">
                        <div v-for="server in listedServers" :key="server.id">
                            <ServerCard :server="server" />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>


<style scoped>
.servers-wrapper {
    grid-template-columns: 1fr 1fr 1fr;
    gap: 24px;
}
.filters-wrapper, .sorting-wrapper{
    display: flex;
}
.single-filter-item{
    padding: 4px 8px;
    border-radius: 100px;
    background: #161d2b;
    display: flex;
    align-items: center;
    justify-content: center;
    width:70px;
    margin-right: 8px;
    cursor: pointer;
    transition: all .3s linear;
}
.single-filter-item.active{
    background: #2563eb;
}
.single-filter-item span{
    font-size: 12px;
    color:white;
}
.sub-header{
    font-size: 48px;
    font-weight: 800;
    text-align: center;
}
.single-sort-item{
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2px 4px;
    margin-left: 14px;
    padding-right: 12px;
    position: relative;
    cursor: pointer;
    -webkit-user-select: none; /* Safari */
    -ms-user-select: none; /* IE 10 and IE 11 */
    user-select: none; /* Standard syntax */
}
.single-sort-item span{
    color:#5d697f;
    font-size: 12px;
    display: flex;
    margin-right: 2px;
    transition: all .15s linear;
}
.single-sort-item .sort-pointer{
    position: absolute;
    right:0;
    width: 0; 
    height: 0; 
    border-left: 5px solid transparent;
    border-right: 5px solid transparent;
  
    border-bottom: 5px solid #5d697f;
    border-radius: 2px;
    transform: rotateX(180deg);
    transition: all .15s linear;
}
.single-sort-item.active span{
    color:#9ca3af;
}
.single-sort-item.active .sort-pointer{
    border-bottom: 5px solid #9ca3af;
}
.single-sort-item .sort-pointer.rotated{
    transform: rotateX(0deg);
}

@media screen and (max-width:1279px) {
    .servers-wrapper {
        grid-template-columns: 1fr;
        padding-left: 1rem;
        padding-right: 1rem;
    }
}
@media screen and (max-width:800px) {
    .hidemobile{
        display: none;
    }
    .single-sort-item{
        margin-left:0px;
        margin-right:14px;
    }
}
</style>