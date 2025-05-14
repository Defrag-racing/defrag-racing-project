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
        return moment(props.map.date_added).fromNow()
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
        <div class="rounded p-2 mx-2 mb-4 group" :class="{'bg-gray-700': !transparent, 'bg-grayop-700': transparent}">
            <Link class="text-lg text-blue-400 hover:text-blue-300 font-bold" :href="route('maps.map', getRouteData)">
                <div class="rounded-md w-full h-48 bg-fit flex flex-col items-end justify-between mx-auto" :style="`width: 150px; max-width: 90vw; height: 100px; background-image: url('/storage/${map.thumbnail}')`" onerror="this.style.backgroundImage='url(\'/images/unknown.jpg\')'" >
                </div>
            </Link>
    
            <div class="flex justify-between items-center bg-blackop-50 rounded-md mt-2 py-2 px-2">
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
            </div>
        </div>
    </div>
</template>