<script setup>
    import OnlinePlayer from '@/Components/OnlinePlayer.vue';
    import MapDetails from './MapDetails.vue';

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
</script>

<template>
    <div>
        <div class="mt-5 mb-10 mx-4 group bg-gray-700 text-gray-300 rounded-md shadow-sm" style="width: 400px; max-width: 100%;">
            <div class="p-3">
                <!-- Top Bar -->
                <div class="rounded-md p-2 bg-blackop-30">
                    <div class="flex justify-between">
                        <div class="flex">
                            <div class="flex items-center">
                                <img :src="`/images/flags/${server.location}.png`" class="w-8 pt-1" :title="server.location">
                                <div :class="(server.name.length > 35) ? 'text-sm font-bold ml-4' : 'text-lg font-bold ml-4'" v-html="q3tohtml(server.name)"></div>
                            </div>
                        </div>
        
                        <div class="flex items-center">
                            <!-- <a :href="`defrag://${server.ip}:${server.port}`" class="mr-2">
                                <div class="rounded-md bg-blackop-30 hover:bg-blackop-20 p-2 cursor-pointer">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 0 1 0 1.972l-11.54 6.347a1.125 1.125 0 0 1-1.667-.986V5.653Z" />
                                    </svg>                      
                                </div>
                            </a> -->

                            <div class="flex">
                                <div class="rounded-l-md bg-blackop-30 p-2 cursor-pointer uppercase">
                                    <div>{{ server.type }}</div>
                                </div>
                                <div :class="server.defrag.includes('cpm') ? `rounded-r-md bg-blackop-30 p-2 cursor-pointer map-container-cpm uppercase` : `rounded-r-md bg-blackop-30 p-2 cursor-pointer map-container-vq3 uppercase`">
                                    <div>{{ server.defrag }}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Server Ip -->
                    <div class="flex justify-between mt-2 items-center">
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

                        <a :href="`defrag://${server.ip}:${server.port}`" class="mr-6 text-gray-400 hover:text-gray-100 transition-all">
                            <div class="flex text-sm rounded-md cursor-pointer items-center">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 0 1 0 1.972l-11.54 6.347a1.125 1.125 0 0 1-1.667-.986V5.653Z" />
                                </svg>
                                <span class="ml-1">Play</span>
                            </div>
                        </a>
                    </div>
                </div>

                <!-- Map Details -->
                <div class="flex mt-3">
                    <MapDetails :map="server.mapdata" :mapname="server.map" />
                </div>

                <!-- Best Record -->
                <div class="text-lg p-2 bg-blackop-30 rounded-md mt-3">
                    <div class="text-xs capitalize text-gray-400 font-bold">BEST TIME </div>

                    <div class="flex justify-between">
                        <a class="hover:underline text-gray-500" href="#" v-html="q3tohtml('^9Jan^8Hejazi')"></a>
                        <div class="font-bold">
                            {{  formatTime('117032') }}
                        </div>
                    </div>
                </div>

                <hr class="mt-4 text-gray-600 border-gray-600 bg-gray-600">

                <!-- Online Players -->
                <div class="mt-4 players-scrollbar">
                    <OnlinePlayer v-for="player in server.online_players" :player="player" />
                </div>
            </div>
        </div>
    </div>
</template>
