<script setup>
    import { ref } from 'vue';
    import { Link } from '@inertiajs/vue3';
    import Tournament from '@/Pages/Tournaments/Tournament.vue';
    import SuggestionForm from '@/Components/SuggestionForm.vue';

    const props = defineProps({
        tournament: Object
    });

    const trailerOpened = ref(false);
    const suggestionFormOpened = ref(false);

    const getYoutubeUrl = (url) => {
        return url.replace('watch?v=', 'embed/');
    }
</script>

<template>
    <Tournament :tournament="tournament" tab="Overview">
        <!-- Top Bar -->
        <div class="flex justify-between items-center">
            <h1 class="font-black text-3xl text-white">{{ tournament.name }}</h1>

            <div class="flex items-center">
                <div v-if="tournament.trailer">
                    <div class="flex justify-end text-gray-300">
                        <div @click="trailerOpened = true" class="font-bold bg-grayop-700 hover:bg-grayop-600 cursor-pointer flex justify-center items-center text-center rounded-lg p-3 w-40">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.348a1.125 1.125 0 010 1.971l-11.54 6.347a1.125 1.125 0 01-1.667-.985V5.653z" />
                            </svg>                              
                            
                            <span class="ml-2">Trailer</span>
                        </div>
                    </div>
    
                    <div v-if="trailerOpened" class="fixed top-0 left-0 w-screen h-screen" style="z-index: 100; background-color: rgba(0, 0, 0, 0.5);">
                        <div class="absolute top-10 right-10 cursor-pointer" @click="trailerOpened = false">
                            <svg width="44px" height="44px" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <g id="Menu / Close_SM">
                                <path id="Vector" d="M16 16L12 12M12 12L8 8M12 12L16 8M12 12L8 16" stroke="#cccccc" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </g>
                            </svg>
                        </div>
                        <div class="w-full h-full flex justify-center mt-20">
                            <iframe width="90%" height="90%" :src="getYoutubeUrl(tournament.trailer)" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end ml-2">
                    <div @click="suggestionFormOpened = true" class="text-gray-300 font-bold bg-grayop-700 hover:bg-grayop-600 cursor-pointer flex justify-center items-center text-center rounded-lg p-3 w-40">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 01.865-.501 48.172 48.172 0 003.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z" />
                        </svg>
                        
                        <span class="ml-2">Suggest</span>
                    </div>
    
                    <SuggestionForm v-if="suggestionFormOpened" :closeForm="() => suggestionFormOpened = false" :tournament="tournament" />
                </div>
            </div>
        </div>

        <div class="w-1/2 h-0.5 mb-5 mt-3" style="background-color: #4d78bf"></div>

        <!-- Description -->
        <div>
            <div class="flex justify-between">
                <p class="text-sm text-gray-900 dark:text-gray-400 mb-5">{{ tournament.description }}</p>
            </div>
        </div>

        <!-- Live Streamers -->
        <div>
            <h1 class="font-black text-3xl dark:text-white my-5">Live Streamers</h1>

            <div class="w-1/2 h-0.5 mb-5 mt-3" style="background-color: #4d78bf"></div>

            <div class="text-gray-900 dark:text-gray-400 text-lg mb-10 flex flex-wrap">
                <!-- <a v-for="streamer in tournament.streamers" :key="streamer.id" target="_blank" :href="'https://www.twitch.tv/' + streamer.twitch_username" class="mx-5">
                    <div class="w-full flex justify-center mb-2">
                        <div class="flex items-end rounded-lg" :style="'background-image: url(\'https://static-cdn.jtvnw.net/previews-ttv/live_user_' + streamer.twitch_username + '-350x250.jpg\'); width: 350px; height: 250px;'">
                            <div class="h-12 w-full px-5 pt-2 rounded-b-lg flex" style="background-color: #000000b9;">
                                <div class="text-2xl font-bold hover:underline">{{ tournament.name }}</div>
                                <div class="ml-3" style="width: 22px; height: 22px; background-color: rgb(207, 0, 0); border-radius: 50%; margin-top: 5px;"></div>
                            </div>
                        </div>
                    </div>
                </a> -->

                <div v-for="streamer in tournament.streamers" :key="streamer.id" class="mr-10 text-gray-500 text-lg mb-10 ">
                    <Link :href="route('profile.index', streamer.user.id)" v-html="q3tohtml(streamer.user.name)" class="text-2xl" />

                    <a class="flex justify-center hover:underline text-sm text-center" :href="'https://www.twitch.tv/' + streamer.twitch_username">
                        @<div>{{ streamer.twitch_username }}</div>
                    </a>
                </div>
            </div>
        </div>
    </Tournament>
</template>