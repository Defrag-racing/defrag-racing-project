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

    let weaponsList = props.map.weapons?.split(',') ?? [];

    if (weaponsList.length > 0) {
        if (weaponsList[0].length == 0) {
            weaponsList.splice(0, 1);
        }
    }

    let itemsList = props.map.items?.split(',') ?? [];

    if (itemsList.length > 0) {
        if (itemsList[0].length == 0) {
            itemsList.splice(0, 1);
        }
    }

    let functionsList = props.map.functions?.split(',') ?? [];

    if (functionsList.length > 0) {
        if (functionsList[0].length == 0) {
            functionsList.splice(0, 1);
        }
    }

    const background = computed(() => {
        const physics = props.map.physics.toLowerCase()
        const bgs = {
            cpm: 'cpm-badge',
            vq3: 'vq3-badge',
            all: 'bg-blackop-80'
        }

        if (physics in bgs) {
            return bgs[physics]
        }

        return bgs['all']
    });

    const date = computed(() => {
        const time = props.map.created_at.split('T')[1].split('.')[0];

        const result = props.map.date_added.split(' ')[0] + ' ' + time;

        return moment(result).fromNow();
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
</script>

<template>
    <div>
        <div class="rounded mx-2 mb-4 group" :class="{'bg-gray-700': !transparent, 'bg-grayop-700': transparent}">
            <Link class="text-lg text-blue-400 hover:text-blue-300 font-bold" :href="route('maps.map', getRouteData)">
                <div class="rounded-t-md w-full h-48 bg-fit flex flex-col items-end justify-between mx-auto" :style="`width: 440px; max-width: 90vw; height: 280px; background-image: url('/storage/${map.thumbnail}')`" onerror="this.style.backgroundImage='url(\'/images/unknown.jpg\')'" >
                    <div :class="`rounded-md ${background} p-2 uppercase text-white font-bold mr-3 mt-2`">
                        {{ map.physics }}
                    </div>
        
                    <div>
                        <!-- Weapons -->
                        <div class="flex flex-wrap justify-end bg-blackop-80 rounded-md py-1 px-2 mr-3 mb-1" v-if="weaponsList.length > 0">
                            <div v-for="weapon in weaponsList" :title="weapon" :class="`sprite-items sprite-${weapon} w-4 h-4 flex-shrink-0 mx-1`"></div>
                        </div>
        
                        <!-- Items -->
                        <div class="flex flex-wrap justify-end bg-blackop-80 rounded-md py-1 px-2 mr-3 mb-1" v-if="itemsList.length > 0">
                            <div v-for="item in itemsList" :title="item" :class="`sprite-items sprite-${item} w-4 h-4 flex-shrink-0 mx-1`"></div>
                        </div>
        
                        <!-- Functions -->
                        <div class="flex flex-wrap justify-end bg-blackop-80 rounded-md py-1 px-2 mr-3 mb-2" v-if="functionsList.length > 0">
                            <div v-for="func in functionsList" :title="func" :class="`sprite-items sprite-${func} w-4 h-4 flex-shrink-0 mx-1`"></div>
                        </div>
                    </div>
                </div>
            </Link>
    
            <div class="flex justify-between items-center rounded-md py-2 px-2">
                <div class="flex items-center">
                    <Link class="text-lg text-blue-400 hover:text-blue-300 font-bold" :href="route('maps.map', getRouteData)"> {{ map.name }} </Link>

                    <Popper :closeDelay="300" hover style="z-index: 100;" >
                        <div class="transition-all duration-500 ease-in-out opacity-0 group-hover:opacity-100 cursor-pointer text-gray-400 hover:text-green-500 ml-2" @click="copy(map.name)">
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
    
                <a target="_blank" :href="'https://dl.defrag.racing/downloads/maps/' + map?.pk3?.split('/').pop()">
                    <div class="text-gray-400 bg-blackop-50 hover:bg-blackop-80 py-1 px-2 rounded-md cursor-pointer" title="Download">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 3.75H6.912a2.25 2.25 0 0 0-2.15 1.588L2.35 13.177a2.25 2.25 0 0 0-.1.661V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18v-4.162c0-.224-.034-.447-.1-.661L19.24 5.338a2.25 2.25 0 0 0-2.15-1.588H15M2.25 13.5h3.86a2.25 2.25 0 0 1 2.012 1.244l.256.512a2.25 2.25 0 0 0 2.013 1.244h3.218a2.25 2.25 0 0 0 2.013-1.244l.256-.512a2.25 2.25 0 0 1 2.013-1.244h3.859M12 3v8.25m0 0-3-3m3 3 3-3" />
                        </svg>
                    </div>
                </a>
            </div>
    
            <div class="flex justify-between items-center rounded-md pb-2 px-2">
                <div class="text-gray-400 overflow-hidden truncate text-left" style="width: 200px;">
                    By <Link class="text-gray-400 font-bold hover:underline cursor-pointer truncate" style="width: 250px;" :href="route('maps.filters', {author: map?.author ?? 'unknown'})"> {{ map.author }} </Link>
                </div>
    
                <div class="text-gray-400">
                    Added {{ date }}
                </div>
            </div>
        </div>
    </div>
</template>