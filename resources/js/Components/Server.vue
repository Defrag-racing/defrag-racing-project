<script setup>
    import { computed } from 'vue';

    const props = defineProps({
        server: Object,
    });

    const copyServer = () => {
        const textarea = document.createElement('textarea');
        textarea.value = props.server.ip + ':' + props.server.port;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
    };

    const copyMap = () => {
        const textarea = document.createElement('textarea');
        textarea.value = props.server.mapdata?.name ?? props.server.map;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
    };

    let weaponsList = props.server.mapdata?.weapons?.split(',') ?? [];

    if (weaponsList.length > 0) {
        if (weaponsList[0].length == 0) {
            weaponsList.splice(0, 1);
        }
    }

    let itemsList = props.server.mapdata?.items?.split(',') ?? [];

    if (itemsList.length > 0) {
        if (itemsList[0].length == 0) {
            itemsList.splice(0, 1);
        }
    }

    let functionsList = props.server.mapdata?.functions?.split(',') ?? [];

    if (functionsList.length > 0) {
        if (functionsList[0].length == 0) {
            functionsList.splice(0, 1);
        }
    }

    const mapName = computed(() => {
        return props.server.mapdata?.name ?? props.server.map;
    });

    console.log(props.server)

</script>

<template>
    <div class="mt-5 mb-10 mx-4 group bg-gray-700 text-gray-300 rounded-md shadow-sm" style="width: 400px; max-width: 100%;">
        <div class="p-3">
            <!-- Top Bar -->
            <div class="rounded-md p-2 bg-blackop-30">
                <div class="flex justify-between">
                    <div class="flex">
                        <div class="flex items-center">
                            <img :src="`/images/flags/${server.location}.png`" class="w-8 pt-1" title="CZ">
                            <div class="text-lg font-bold ml-4" v-html="q3tohtml(server.name)"></div>
                        </div>
                    </div>
    
                    <div class="flex items-center">
                        <a :href="`defrag://${server.ip}:${server.port}`" class="mr-2">
                            <div class="rounded-md bg-blackop-30 hover:bg-blackop-20 p-2 cursor-pointer">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 0 1 0 1.972l-11.54 6.347a1.125 1.125 0 0 1-1.667-.986V5.653Z" />
                                </svg>                      
                            </div>
                        </a>
    
                        <!-- <div class="text-green-500 p-2">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.288 15.038a5.25 5.25 0 0 1 7.424 0M5.106 11.856c3.807-3.808 9.98-3.808 13.788 0M1.924 8.674c5.565-5.565 14.587-5.565 20.152 0M12.53 18.22l-.53.53-.53-.53a.75.75 0 0 1 1.06 0Z" />
                            </svg>                      
                        </div> -->

                        <div :class="server.defrag.includes('cpm') ? `rounded-md bg-blackop-30 hover:bg-blackop-20 p-2 cursor-pointer map-container-cpm uppercase` : `rounded-md bg-blackop-30 hover:bg-blackop-20 p-2 cursor-pointer map-container-vq3 uppercase`">
                            {{ server.defrag }}
                        </div>
                    </div>
                </div>

                <!-- Server Ip -->
                <div class="flex ml-2 text-gray-400">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 14.25h13.5m-13.5 0a3 3 0 0 1-3-3m3 3a3 3 0 1 0 0 6h13.5a3 3 0 1 0 0-6m-16.5-3a3 3 0 0 1 3-3h13.5a3 3 0 0 1 3 3m-19.5 0a4.5 4.5 0 0 1 .9-2.7L5.737 5.1a3.375 3.375 0 0 1 2.7-1.35h7.126c1.062 0 2.062.5 2.7 1.35l2.587 3.45a4.5 4.5 0 0 1 .9 2.7m0 0a3 3 0 0 1-3 3m0 3h.008v.008h-.008v-.008Zm0-6h.008v.008h-.008v-.008Zm-3 6h.008v.008h-.008v-.008Zm0-6h.008v.008h-.008v-.008Z" />
                    </svg>

                    <div class="flex items-center text-sm ml-5">
                        <div>
                            {{  server.ip }}:{{ server.port }}
                        </div>
                        
                        <div class="transition-all duration-500 ease-in-out opacity-0 group-hover:opacity-100 cursor-pointer text-gray-400 hover:text-green-500 ml-3" @click="copyServer">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 8.25V6a2.25 2.25 0 0 0-2.25-2.25H6A2.25 2.25 0 0 0 3.75 6v8.25A2.25 2.25 0 0 0 6 16.5h2.25m8.25-8.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-7.5A2.25 2.25 0 0 1 8.25 18v-1.5m8.25-8.25h-6a2.25 2.25 0 0 0-2.25 2.25v6" />
                            </svg>     
                        </div>
                    </div>
                </div>
            </div>

            <!-- Map Details -->
            <div class="flex mt-5">
                <div class="bg-gray-600 p-0.5 rounded-md">
                    <img onerror="this.src='/images/unknown.jpg'" :src="`https://defrag.racing/uploads/${server.mapdata?.thumbnail}`" class="rounded-md" style="width: 200px;">
                </div>

                <div class="ml-4">
                    <!-- Mapname -->
                    <div class="flex items-center">
                        <a :class="(mapName.length > 13) ? 'text-sm text-blue-400 hover:text-blue-300 font-bold' : 'text-md text-blue-400 hover:text-blue-300 font-bold'" href="#"> {{ server.mapdata?.name ?? server.map }} </a>
                    
                        <div class="transition-all duration-500 ease-in-out opacity-0 group-hover:opacity-100 cursor-pointer text-gray-400 hover:text-green-500 ml-2" @click="copyMap">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 8.25V6a2.25 2.25 0 0 0-2.25-2.25H6A2.25 2.25 0 0 0 3.75 6v8.25A2.25 2.25 0 0 0 6 16.5h2.25m8.25-8.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-7.5A2.25 2.25 0 0 1 8.25 18v-1.5m8.25-8.25h-6a2.25 2.25 0 0 0-2.25 2.25v6" />
                            </svg>                      
                        </div>
                    </div>

                    <!-- Author -->
                    <div class="flex items-center text-gray-400 mb-3">
                        By  <a class="ml-2 hover:text-gray-300" href="#">{{ server.mapdata?.author ?? 'Unknown' }}</a>
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

            <!-- Best Record -->
            <div class="text-lg p-2 bg-blackop-30 rounded-md mt-5">
                <div class="text-xs capitalize text-gray-400 font-bold">BEST TIME </div>

                <div class="flex justify-between">
                    <a class="hover:underline text-gray-500" href="#" v-html="q3tohtml('^9Jan^8Hejazi')"></a>
                    <div class="font-bold">
                        {{  formatTime('117032') }}
                    </div>
                </div>
            </div>

            <hr class="mt-4 text-gray-600 border-gray-600 bg-gray-600">
        </div>
    </div>
</template>
