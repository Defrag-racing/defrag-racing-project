<script setup>
    import { computed, ref, watchEffect } from 'vue';
    import { Link } from '@inertiajs/vue3';

    const props = defineProps({
        map: Object,
        mapname: String
    });

    const copyText = ref(false);

    const copyMap = () => {
        const textarea = document.createElement('textarea');
        textarea.value = props.map?.name ?? props.mapname;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);

        copyText.value = true;

        setTimeout(() => {
            copyText.value = false;
        }, 1000);
    };

    let weaponsList = ref([]);
    let itemsList = ref([]);
    let functionsList = ref([]);

    const getItemsData = () => {
        let weaponsListTemp = props.map?.weapons?.split(',') ?? [];

        if (weaponsListTemp.length > 0) {
            if (weaponsListTemp[0].length == 0) {
                weaponsListTemp.splice(0, 1);
            }
        }

        weaponsList.value = weaponsListTemp;

        let itemsListTemp = props.map?.items?.split(',') ?? [];

        if (itemsListTemp.length > 0) {
            if (itemsListTemp[0].length == 0) {
                itemsListTemp.splice(0, 1);
            }
        }

        itemsList.value = itemsListTemp;

        let functionsListTemp = props.map?.functions?.split(',') ?? [];

        if (functionsListTemp.length > 0) {
            if (functionsListTemp[0].length == 0) {
                functionsListTemp.splice(0, 1);
            }
        }

        functionsList.value = functionsListTemp;
    };

    getItemsData();

    const mapName = computed(() => {
        return props.map?.name ?? props.mapname;
    });

    const getGametype = computed(() => {
        let gametype = props.map?.gametype;

        if (!gametype) {
            return 'run'
        }

        return (gametype == 'fastcaps') ? 'ctf2' : 'run';
    });

    const getRouteData = computed(() => {
        let data ={
            mapname: mapName.value
        }

        if (getGametype.value !== 'run') {
            data.gametype = 'ctf2';
        }

        return data;
    });

    watchEffect(() => {
        getItemsData();
    });
</script>

<template>
    <div class="flex">
        <div class="flex-none">
            <div class="bg-gray-600 p-0.5 rounded-md">
                <img onerror="this.src='/images/unknown.jpg'" :src="`/storage/${map?.thumbnail}`" class="rounded-md" style="width: 170px; height: 130px;">
            </div>
        </div>

        <div class="ml-4 flex-1">
            <!-- Mapname -->
            <div class="flex items-center">
                <Link :class="(mapName.length > 13) ? 'text-sm text-blue-400 hover:text-blue-300 font-bold' : 'text-md text-blue-400 hover:text-blue-300 font-bold'" :href="route('maps.map', getRouteData)"> {{ mapName }} </Link>
            
                <Popper :closeDelay="300" hover style="z-index: 100;" >
                    <div class="transition-all duration-500 ease-in-out opacity-0 group-hover:opacity-100 cursor-pointer text-gray-400 hover:text-green-500 ml-2" @click="copyMap">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 8.25V6a2.25 2.25 0 0 0-2.25-2.25H6A2.25 2.25 0 0 0 3.75 6v8.25A2.25 2.25 0 0 0 6 16.5h2.25m8.25-8.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-7.5A2.25 2.25 0 0 1 8.25 18v-1.5m8.25-8.25h-6a2.25 2.25 0 0 0-2.25 2.25v6" />
                        </svg>                      
                    </div>
            
            
                    <template #content>
                        <div class="px-4 py-2 rounded-md bg-blackop-80">
                            <div class="text-gray-400">
                                <span v-if="!copyText">Copy</span>
                                <span v-else class="text-green-400">Copied !</span>
                            </div>
                        </div>
                    </template>
                </Popper>
            </div>

            <!-- Author -->
            <div class="flex items-center text-gray-400 mb-3 text-sm">
                By <Link class="ml-1 hover:text-gray-300 overflow-hidden truncate" style="max-width: 120px;" :title="map?.author" :href="route('maps.filters', {author: map?.author ?? 'unknown'})">{{ map?.author ?? 'Unknown' }}</Link>
            </div>

            <!-- Weapons -->
            <div class="mt-2 flex flex-wrap" v-if="weaponsList.length > 0">
                <div v-for="weapon in weaponsList" :title="weapon" :class="`sprite-items sprite-${weapon} w-4 h-4 mr-1 flex-shrink-0 mb-1`"></div>
            </div>

            <!-- Items -->
            <div class="mt-2 flex flex-wrap" v-if="itemsList.length > 0">
                <div v-for="item in itemsList" :title="item" :class="`sprite-items sprite-${item} w-4 h-4 mr-1 flex-shrink-0 mb-1`"></div>
            </div>

            <!-- Functions -->
            <div class="mt-2 flex flex-wrap" v-if="functionsList.length > 0">
                <div v-for="func in functionsList" :title="func" :class="`sprite-items sprite-${func} w-4 h-4 mr-1 flex-shrink-0 mb-1`"></div>
            </div>
        </div>
    </div>
</template>