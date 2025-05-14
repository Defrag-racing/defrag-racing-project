<script setup>
    import moment from 'moment';
    import { computed, ref } from 'vue';
    import { Link } from '@inertiajs/vue3';
    import { useClipboard } from '@/Composables/useClipboard';
    
    const { copy, copyState } = useClipboard();

    const props = defineProps({
        map: Object,
        transparent: {
            type: Boolean,
            default: true
        }
    });

    let weaponsList = props.map?.weapons?.split(',') ?? [];

    if (weaponsList.length > 0) {
        if (weaponsList[0].length == 0) {
            weaponsList.splice(0, 1);
        }
    }

    let itemsList = props.map?.items?.split(',') ?? [];

    if (itemsList.length > 0) {
        if (itemsList[0].length == 0) {
            itemsList.splice(0, 1);
        }
    }

    let functionsList = props.map?.functions?.split(',') ?? [];

    if (functionsList.length > 0) {
        if (functionsList[0].length == 0) {
            functionsList.splice(0, 1);
        }
    }

    const mapName = computed(() => {
        return props.map?.name;
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
            mapname: props.map?.name
        }

        if (getGametype.value !== 'run') {
            data.gametype = 'ctf2';
        }

        return data;
    });

    const date = computed(() => {
        const time = props.map.created_at.split('T')[1].split('.')[0];

        const result = props.map.date_added.split(' ')[0] + ' ' + time;

        return moment(result);
    });
</script>

<template>
    <div class="sm:flex rounded p-2 mb-4 group" :class="{'bg-gray-700': !transparent, 'bg-grayop-700': transparent}">
        <div>
            <Popper placement="right" arrow hover style="z-index: 200;">
                <div class="bg-grayop-600 p-0.5 rounded-md relative cursor-zoom-in">
                    <img onerror="this.src='/images/unknown.jpg'" :src="`/storage/${map?.thumbnail}`" class="rounded-md" style="width: 120px; height: 80px;">
                    <div class="flex" style="position: absolute; top: 60%; left: 70%;">
                        <div class="text-white bg-blackop-30 rounded-md p-1">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607ZM10.5 7.5v6m3-3h-6" />
                            </svg>
                        </div>
                    </div>
                </div>
        
        
                <template #content>
                    <div>
                        <div class="bg-grayop-600 p-1 rounded-md">
                            <img onerror="this.src='/images/unknown.jpg'" :src="`/storage/${map?.thumbnail}`" class="rounded-md" style="width: 400px; max-width: 90vw; height: 280px;">
                        </div>  
                    </div>  
                </template>
            </Popper>
        </div>

        <div class="sm:ml-4 flex-grow">
            <div class="sm:flex justify-between items-center w-full">
                <!-- Mapname -->
                <div class="flex items-center">
                    <Link class="text-lg text-blue-400 hover:text-blue-300 font-bold" :href="route('maps.map', getRouteData)"> {{ mapName }} </Link>
                
                    <Popper :closeDelay="300" hover style="z-index: 100;" >
                        <div class="transition-all duration-500 ease-in-out opacity-0 group-hover:opacity-100 cursor-pointer text-gray-400 hover:text-green-500 ml-2" @click="copy(map?.name)">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 8.25V6a2.25 2.25 0 0 0-2.25-2.25H6A2.25 2.25 0 0 0 3.75 6v8.25A2.25 2.25 0 0 0 6 16.5h2.25m8.25-8.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-7.5A2.25 2.25 0 0 1 8.25 18v-1.5m8.25-8.25h-6a2.25 2.25 0 0 0-2.25 2.25v6" />
                            </svg>                      
                        </div>
                
                
                        <template #content>
                            <div class="px-4 py-2 rounded-md bg-blackop-80">
                                <div class="text-gray-400">
                                    <span v-if="!copyState">Copy</span>
                                    <span v-else class="text-green-400">Copied !</span>
                                </div>
                            </div>
                        </template>
                    </Popper>
                </div>

                <div class="flex flex-wrap items-center">
                    <slot />
                    <div class="text-white mr-3">Map Physics: </div>
                    <div class="text-white rounded-full text-xs px-2 py-0.5 uppercase font-bold" :class="{'bg-green-700': map.physics.includes('cpm'), 'bg-blue-600': map.physics.includes('vq3'), 'bg-gray-700': map.physics.includes('all')}">
                        <div>{{ map.physics }}</div>
                    </div>
                </div>
            </div>

            <div class="w-full mt-1 flex justify-between mb-1">
                <!-- Author -->
                <div class="flex items-center text-gray-400 text-sm">
                    By <Link class="ml-1 hover:text-gray-300 overflow-hidden truncate" :title="map?.author" :href="route('maps.filters', {author: map?.author ?? 'unknown'})">{{ map?.author ?? 'Unknown' }}</Link>
                </div>

                <div class="flex items-center text-gray-400 text-sm" :title="date.format('MMMM Do, YYYY')">
                    Added {{ date.fromNow() }}
                </div>
            </div>

            <div class="sm:flex justify-between items-center w-full">
                <div class="mt-2 flex flex-wrap">
                    <div v-for="weapon in weaponsList" :title="weapon" :class="`sprite-items sprite-${weapon} w-5 h-5 mr-1 flex-shrink-0 mb-1`"></div>
                    <div class="mx-2" v-if="weaponsList.length > 0"></div>
                    <div v-for="item in itemsList" :title="item" :class="`sprite-items sprite-${item} w-5 h-5 mr-1 flex-shrink-0 mb-1`"></div>
                    <div class="mx-2" v-if="itemsList.length > 0"></div>
                    <div v-for="func in functionsList" :title="func" :class="`sprite-items sprite-${func} w-5 h-5 mr-1 flex-shrink-0 mb-1`"></div>
                </div>
    
                <a target="_blank" :href="'https://dl.defrag.racing/downloads/maps/' + map?.pk3?.split('/').pop()">
                    <div class="flex justify-center text-gray-400 bg-blackop-50 hover:bg-blackop-80 py-1 px-2 rounded-md cursor-pointer" title="Download">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 3.75H6.912a2.25 2.25 0 0 0-2.15 1.588L2.35 13.177a2.25 2.25 0 0 0-.1.661V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18v-4.162c0-.224-.034-.447-.1-.661L19.24 5.338a2.25 2.25 0 0 0-2.15-1.588H15M2.25 13.5h3.86a2.25 2.25 0 0 1 2.012 1.244l.256.512a2.25 2.25 0 0 0 2.013 1.244h3.218a2.25 2.25 0 0 0 2.013-1.244l.256-.512a2.25 2.25 0 0 1 2.013-1.244h3.859M12 3v8.25m0 0-3-3m3 3 3-3" />
                        </svg>
                        <div class="ml-2">Download</div>
                    </div>
                </a>
            </div>
        </div>
    </div>
</template>