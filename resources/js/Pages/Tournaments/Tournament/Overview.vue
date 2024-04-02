<script setup>
    import { ref } from 'vue';
    import { Link } from '@inertiajs/vue3';
    import Tournament from '@/Pages/Tournaments/Tournament.vue';
    import SuggestionForm from '@/Components/SuggestionForm.vue';
    import OverviewNew from '@/Components/Tournament/OverviewNew.vue';

    const props = defineProps({
        tournament: Object,
        organizers: Object,
        latestNews: Array
    });

    const trailerOpened = ref(false);
    const suggestionFormOpened = ref(false);

    const getYoutubeUrl = (url) => {
        return url.replace('watch?v=', 'embed/');
    }
</script>

<template>
    <Tournament :tournament="tournament" tab="Overview">
        <div v-if="latestNews.length > 0">
            <div class="flex justify-between items-center">
                <h1 class="font-black text-3xl text-white mb-3">Latest News</h1>
            </div>

            <div class="">
                <OverviewNew :comments="false" v-for="item in latestNews" :key="item.id" :item="item" :tournament="tournament" />
            </div>

            <div class="tech-line-overview"></div>
        </div>

        <!-- Top Bar -->
        <div>
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

            <div class="tech-line-overview"></div>
        </div>

        <!-- Description -->
        <div>
            <div class="flex justify-between">
                <p class="text-sm text-gray-400 mb-5">{{ tournament.description }}</p>
            </div>
        </div>

        <!-- Live Streamers -->
        <div>
            <h1 class="font-black text-3xl text-white my-5">Live Streamers</h1>

            <div class="tech-line-overview"></div>

            <div class="text-gray-400 text-lg flex flex-wrap">
                <div v-for="streamer in tournament.streamers" :key="streamer.id" class="mr-10 text-gray-500 text-lg mb-10 ">
                    <Link :href="route('profile.index', streamer.user.id)" v-html="q3tohtml(streamer.user.name)" class="text-2xl text-gray-500 hover:underline" />

                    <a class="flex justify-center hover:underline text-sm text-center" :href="'https://www.twitch.tv/' + streamer.twitch_username">
                        @<div>{{ streamer.twitch_username }}</div>
                    </a>
                </div>
            </div>
        </div>

        <!-- Prize Pool -->
        <div v-if="tournament.prize_pool > 0">
            <h1 class="font-black text-3xl dark:text-white my-5">Prize Pool</h1>

            <div class="tech-line-overview"></div>

            <div class="text-gray-900 dark:text-gray-400 text-lg mb-10">
                Current Prize Pool: <span class="text-blue-400 text-xl">{{ tournament.prize_pool }}$</span>
            </div>
        </div>

        <!-- Organizers -->
        <div>
            <h1 class="font-black text-3xl dark:text-white my-5">Organizers</h1>

            <div class="tech-line-overview"></div>

            <div class="mt-5">
                <div class="w-full flex text-center">
                    <div class="w-60" v-for="(data, role) in organizers">
                        <h1 class="text-gray-900 dark:text-gray-400 mr-2 capitalize mb-2">{{ role }}s</h1>

                        <div v-for="organizer in data">
                            <Link :href="route('profile.index', organizer.user.id)" class="text-gray-500 my-4 hover:underline" v-html="q3tohtml(organizer.user.name)" />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Related Tournaments -->
        <h1 class="font-black text-3xl dark:text-white my-5">Related Tournaments</h1>

        <div class="tech-line-overview"></div>

        <div class="w-full flex flex-wrap">
            <div v-for="relatedTournament in tournament.related_tournaments" :key="relatedTournament.id" class="mr-5 mb-4">
                <Link :href="route('tournaments.show', relatedTournament.tournament.id)"  class="flex items-end mb-1 border-gray-800 border-4 rounded-lg" :style="'min-height: 200px; width: 300px; background-image: url(\'/storage/' + relatedTournament.tournament.image + '\'); background-size: cover;'">
                    <div class="w-full px-5 py-3 rounded-b-lg" style="background-color: #000000b9;">
                        <div class="text-md font-bold hover:underline text-white">{{ relatedTournament.tournament.name }}</div>
                    </div>
                </Link>
            </div>
        </div>
    </Tournament>
</template>

<style scoped>
    .tech-line-overview {
        background: linear-gradient(90deg,#ffffff42,#2f90ff,#ffffff42);
        box-shadow: 0 0 50px 4px #0a7cffb2;
        height: 1px;
        margin-top: 10px;
        margin-bottom: 10px;
        max-width: 100%;
    }
</style>