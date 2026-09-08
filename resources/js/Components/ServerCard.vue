<script setup>
    import { Link } from '@inertiajs/vue3';
    import OnlinePlayer from '@/Components/OnlinePlayer.vue';
    import MapDetails from './MapDetails.vue';
    import { computed, ref } from 'vue';
    import Popper from "vue3-popper";
    import CopyButton from '@/Components/Basic/CopyButton.vue';

    const props = defineProps({
        server: Object,
    });

    const bestrecordCountry = computed(() => {
        let country = props.server.besttime_country;

        return (country == 'XX' || country == '') ? '_404' : country;
    });
</script>

<template>
    <div>
        <div class="group text-gray-300 rounded-md server-card-wrapper">
            <div class="p-4 rounded-md" >
                <div class="server-background">
                    <img onerror="this.src='/images/unknown.jpg'" :src="`/storage/${server.mapdata?.thumbnail}`" alt="">
                    <div class="server-background-overlay"></div>
                    <div class="server-background-hover-overlay"></div>
                </div>
                <div class="rounded-md relative-3">
                    <!-- Top Bar -->
                    <div>
                        <div class="flex justify-between relative wi-100">
                            <div class="flex wi-100">
                                <div class="flex items-center wi-100">
                                    <img :src="`/images/flags/${server.location}.png`" class="w-4" :title="server.location">
                                    <div :class="(server.name.length > 35) ? 'text-sm font-bold ml-2' : 'text-lg font-bold ml-2'">{{ server.plain_name }}</div>
                                    <div class="text-white rounded-full text-xs px-2 py-0.5 uppercase ml-2 font-bold physics-badge" :class="{'bg-green-700': server.defrag.includes('cpm'), 'bg-blue-600': !server.defrag.includes('cpm')}">
                                        <div>{{ server.defrag }}</div>
                                    </div>
                                </div>
                            </div>
            
                            <div class="flex items-center hidemobile">
                                <div class="flex items-center">
                                    <Popper arrow style="z-index: 1000;">
                                        <div class="cursor-pointer question-icon">
                                            <span>?</span>
                                        </div>
                            
                                        <template #content>
                                            <div class="px-5 py-2">
                                                <div>{{ $t('In order to use the play button, you need to download the Defrag Launcher.') }}</div>
                                                <div class="[&_a]:text-blue-500 [&_a:hover]:text-blue-400 [&_a]:underline" v-html="$t('<a href=https://defrag.racing/bundles/7/defrag-launcher target=_blank>Follow this link</a> to download it.')"></div>
                                            </div>
                                        </template>
                                    </Popper>

                                    <a :href="`defrag://${server.ip}:${server.port}`" class="transition-all ml-1">
                                        <div class="flex rounded-md text-xs px-2 py-1 uppercase font-bold border-2 border-gray-400 text-white">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 0 1 0 1.972l-11.54 6.347a1.125 1.125 0 0 1-1.667-.986V5.653Z" />
                                            </svg>
                                            <span class="ml-1">{{ $t('Play') }}</span>
                                        </div>
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Server Ip -->
                        <div class="flex justify-between items-center relative">
                            <div class="flex">
                                <div class="flex items-center server-ip-text">
                                    <div>
                                        {{  server.ip }}:{{ server.port }}
                                    </div>
                                    <div class="transition-all duration-300 opacity-0 group-hover:opacity-100 ml-2">
                                        <CopyButton :text="server.ip + ':' + server.port" size="xs" :label="$t('Copy IP')" />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Map Details -->
                <div class="flex mt-3 relative mh-190">
                    <MapDetails :map="server.mapdata" :mapname="server.map" />
                </div>

                <!-- Best Record -->
                <div class="relative mh-25 mt-3">
                    <div class="text-md" v-if="server.besttime_name">
                        <div class="flex justify-between items-center">
                            <div>
                                <Link class="hover:underline font-bold " :href="server.besttime_profile_url || '#'" v-html="q3tohtml(server.besttime_name)"></Link>
                            </div>
                            <div class="font-bold">
                                {{  formatTime(server.besttime_time) }}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="hr relative"></div>

                <!-- Online Players -->
                <div class="mt-4 relative">
                    <OnlinePlayer v-for="player in server.online_players" :player="player" :key="player.id" :siblings="server.online_players" />
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
hr{
    position: relative;
}
.server-card-wrapper{
    width:100%;
    /* Card body is fully transparent - the thumbnail behind it
     * (covered + alpha-ramped via .server-background-overlay)
     * provides the entire visual surface. No second dark slab
     * stacking on top of the gradient = no hard line under the
     * thumbnail. */
    background: transparent;
    border: 1px solid rgba(255, 255, 255, 0.08);
    position: relative;
    overflow: hidden;
}
.server-background{
    position: absolute;
    top:0;
    left:0;
    width:100%;
    height:100%;            /* span the whole card so the overlay
                               gradient can fade thumbnail → glass
                               continuously instead of stopping at
                               the bottom of the image */
    display: block;
    z-index: 0;
}
.server-background img{
    width:100%;
    display:block;
    transition: all .15s linear;
}
.server-background .server-background-overlay{
    position: absolute;
    top:0;
    left:0;
    width:100%;
    height: 100%;
    /* No dark slab anywhere. Just a short gradient at the bottom
     * edge of the thumbnail itself fading the image into the
     * transparent card body - so text below it sits directly on
     * the page background, not on any dark veil. */
    background: linear-gradient(180deg,
        rgba(0,0,0,0) 0%,
        rgba(0,0,0,0) 60%,
        rgba(11,16,32,0.4) 90%,
        rgba(11,16,32,0) 100%);
    z-index: 2;
    box-sizing: content-box;
    transition: all .15s linear;
}
.server-background .server-background-hover-overlay{
    position: absolute;
    top:0;
    left:0;
    width:100%;
    height: 100%;
    background: rgba(22,29,43,0.5);
    z-index: 3;
    box-sizing: content-box;
    transition: all .15s linear;
    opacity: 0;
}
.server-ip-text{
    font-size: 10px;
    font-weight: 600;
    color: rgba(255, 255, 255, .3);
}
.relative{
    position: relative;
    z-index: 2;
}
.relative-3{
    position: relative;
    z-index: 3;
}
.hr{
    width:100%;
    height:2px;
    background:#283349;
    border-radius: 100px;
    margin-top:12px;
}
.server-card-wrapper:hover .server-background img{
 transform: scale(1.1);
}

.server-card-wrapper:hover .server-background .server-background-hover-overlay{
    opacity: .7;
}

.question-icon{
 margin-right: 4px;
 padding: 4px;
 opacity: .6;
 transition: all .3s linear;
}
.question-icon:hover{
 opacity: 1;
}
.copy-text{
    font-size: 14px;
    font-weight: 400;
    z-index: 99;
}
.question-icon span{
    color:white;
    font-size: 12px;
    font-weight: 800;
}
.mh-190{
    min-height: 190px;
}
.mh-25{
    min-height: 27px;
}

@media screen and (max-width:1279px) {
    .server-background img{
        max-height: 300px;
        object-fit: cover;
        object-position: center;
    }
    .server-background .server-background-overlay{
        max-height: 300px;
    }
    .server-background .server-background-hover-overlay{
        max-height: 300px;
    }
}
@media screen and (max-width:800px) {
    .hidemobile{
        display: none;
    }
    .physics-badge{
        margin-left:auto;
    }
    .wi-100{
        width:100%;
    }
}

</style>