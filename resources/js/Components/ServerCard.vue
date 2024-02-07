<script setup>
    import { Link } from '@inertiajs/vue3';
    import OnlinePlayer from '@/Components/OnlinePlayer.vue';
    import MapDetails from './MapDetails.vue';
    import { computed } from 'vue';
    import Popper from "vue3-popper";

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

    const bestrecordCountry = computed(() => {
        let country = props.server.besttime_country;

        return (country == 'XX' || country == '') ? '_404' : country;
    });
</script>

<template>
    <div>
        <div class="mt-5 mb-10 mx-4 group bg-grayop-700 text-gray-300 rounded-md shadow-sm" style="width: 400px; max-width: 100%;">
            <div class="p-3 rounded-md" >
                <div class="rounded-md" :class="{'cpm-gradiant-2': server.defrag.includes('cpm'), 'vq3-gradiant-2': !server.defrag.includes('cpm')}">
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
                                <div class="flex items-center">
                                    <a :href="`defrag://${server.ip}:${server.port}`" class="transition-all mr-1">
                                        <div class="flex rounded-md text-xs px-2 py-1 uppercase font-bold border-2 border-gray-400 text-white">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 0 1 0 1.972l-11.54 6.347a1.125 1.125 0 0 1-1.667-.986V5.653Z" />
                                            </svg>
                                            <span class="ml-1">Play</span>
                                        </div>
                                    </a>

                                    <Popper arrow style="z-index: 1000;">
                                        <div class="text-gray-300 cursor-pointer">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 5.25h.008v.008H12v-.008Z" />
                                            </svg>
                                        </div>
                            
                                        <template #content>
                                            <div class="px-5 py-2">
                                                <div>In order to use the play button, you need to download the Defrag Launcher.</div>
                                                <div><a class="text-blue-500 hover:text-blue-400 underline" target="_blank" href="https://defrag.racing/bundles/7/defrag-launcher">Follow this link</a> to download it.</div>
                                            </div>
                                        </template>
                                    </Popper>
                                </div>
                            </div>
                        </div>

                        <!-- Server Ip -->
                        <div class="flex justify-between mt-2 items-center">
                            <div class="flex ml-2 text-gray-400 mt-1">
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

                            <div class="flex">
                                <div class="bg-gray-700 rounded-full text-xs px-2 py-0.5 uppercase font-bold">
                                    <div>{{ server.type }}</div>
                                </div>
                                <div class="text-white rounded-full text-xs px-2 py-0.5 uppercase ml-2 font-bold" :class="{'bg-green-700': server.defrag.includes('cpm'), 'bg-blue-600': !server.defrag.includes('cpm')}">
                                    <div>{{ server.defrag }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Map Details -->
                <div class="flex mt-3">
                    <MapDetails :map="server.mapdata" :mapname="server.map" />
                </div>

                <!-- Best Record -->
                <div class="bg-blackop-30 rounded-md shadow-xl" :class="{'best-record-gradiant-cpm': server.defrag.includes('cpm'), 'best-record-gradiant-vq3': !server.defrag.includes('cpm')}">
                    <div class="text-lg p-2 rounded-md mt-3" v-if="server.besttime_name">
                        <div class="text-xs capitalize text-gray-200 font-bold">BEST TIME </div>
    
                        <div class="flex justify-between items-center">
                            <div>
                                <img :src="`/images/flags/${bestrecordCountry}.png`" class="w-5 inline mr-2">
                                <Link class="hover:underline font-bold " :href="server.besttime_url ? route('profile.index', server.besttime_url) : '#'" v-html="q3tohtml(server.besttime_name)"></Link>
                            </div>
                            <div class="font-bold">
                                {{  formatTime(server.besttime_time) }}
                            </div>
                        </div>
                    </div>
                </div>

                <hr class="mt-4 text-gray-600 border-gray-600 bg-gray-600">

                <!-- Online Players -->
                <div class="mt-4 defrag-scrollbar">
                    <OnlinePlayer v-for="player in server.online_players" :player="player" :key="player.id" />
                </div>
            </div>
        </div>
    </div>
</template>
