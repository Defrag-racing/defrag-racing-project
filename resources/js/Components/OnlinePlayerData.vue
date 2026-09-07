<script setup>
    import { Link } from '@inertiajs/vue3';
    import { computed } from 'vue';
    import { isStreaming, twitchChannel as channelOf } from '@/utils/twitch';
    import Popper from "vue3-popper";
    // FORCE REBUILD v2
    const props = defineProps({
        player: Object,
        spectator: Boolean
    });

    const getProfile = computed(() => {
        if (! props.player.mdd_id) {
            return '#';
        }

        if (props.player.profile) {
            return route('profile.index', props.player.profile.id);
        }

        return route('profile.mdd', props.player.mdd_id);
    })

    // Whether this player is on Twitch right now. The flag is written every
    // two minutes by twitch:check-live-status; the name is what they typed
    // into their settings, which is sometimes a whole address, so the channel
    // link is built from the last part of it.
    const isLive = computed(() => isStreaming(props.player));

    const twitchChannel = computed(() => channelOf(props.player.profile));

</script>

<template>
    <div>
        <Popper arrow hover :disabled="player.profile == null" style="z-index: 100;">
            <span class="inline-flex items-center gap-1.5">
            <Link :href="getProfile" v-if="player.mdd_id" class="inline-flex items-center gap-1.5">
                <img v-if="player.country && player.country !== 'XX'" :src="`/images/flags/${player.country}.png`" class="w-4 h-3 rounded shadow-md flex-shrink-0" :title="player.country" onerror="this.src='/images/flags/_404.png'" />
                <div class="font-bold inline online-player-name-text" v-html="q3tohtml(player.name)"></div>

                <svg v-if="player.profile" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="mb-0.5 ml-1 text-green-500 w-4 h-4 inline">
                    <path fill-rule="evenodd" d="M12 1.5a5.25 5.25 0 0 0-5.25 5.25v3a3 3 0 0 0-3 3v6.75a3 3 0 0 0 3 3h10.5a3 3 0 0 0 3-3v-6.75a3 3 0 0 0-3-3v-3c0-2.9-2.35-5.25-5.25-5.25Zm3.75 8.25v-3a3.75 3.75 0 1 0-7.5 0v3h7.5Z" clip-rule="evenodd" />
                </svg>
            </Link>

            <div v-else class="inline-flex items-center gap-1.5">
                <img v-if="player.country && player.country !== 'XX'" :src="`/images/flags/${player.country}.png`" class="w-4 h-3 rounded shadow-md flex-shrink-0" :title="player.country" onerror="this.src='/images/flags/_404.png'" />
                <div class="font-bold inline online-player-name-text" v-html="q3tohtml(player.name)"></div>

                <svg v-if="player.profile" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="mb-0.5 ml-1 text-green-500 w-4 h-4 inline">
                    <path fill-rule="evenodd" d="M12 1.5a5.25 5.25 0 0 0-5.25 5.25v3a3 3 0 0 0-3 3v6.75a3 3 0 0 0 3 3h10.5a3 3 0 0 0 3-3v-6.75a3 3 0 0 0-3-3v-3c0-2.9-2.35-5.25-5.25-5.25Zm3.75 8.25v-3a3.75 3.75 0 1 0-7.5 0v3h7.5Z" clip-rule="evenodd" />
                </svg>
            </div>

            <!-- Streaming right now. Sits outside the profile Link rather
                 than inside it, because it goes somewhere else and an anchor
                 inside an anchor is not a thing. Used to be a 10px red dot;
                 in a list of thirty names it was the thing nobody saw. -->
            <a v-if="isLive" :href="`https://www.twitch.tv/${twitchChannel}`"
               target="_blank" rel="noopener noreferrer"
               class="inline-flex shrink-0 items-center gap-1 rounded-md border border-red-400/50 bg-red-600/80 px-1.5 py-px text-[9px] font-black uppercase tracking-wider text-white shadow-[0_0_10px_-1px_rgba(239,68,68,0.8)] hover:bg-red-500 transition-colors"
               :title="$t('Live on Twitch')" @click.stop>
                <span class="relative inline-flex w-1.5 h-1.5">
                    <span class="live-dot-pulse absolute inline-flex w-full h-full rounded-full bg-white opacity-75"></span>
                    <span class="relative inline-flex w-1.5 h-1.5 rounded-full bg-white"></span>
                </span>
                {{ $t('LIVE') }}
            </a>
            </span>

            <template #content>
                <div class="flex items-center px-5 py-2" v-if="player.profile">
                    <div class="mr-4">
                        <img class="h-10 w-10 rounded-full object-cover" :src="player.profile.profile_photo_path ? '/storage/' + player.profile.profile_photo_path : '/images/null.jpg'" :alt="player.profile.name">
                    </div>
    
                    <div>
                        <div class="text-gray-500">
                            {{ $t('Logged in as') }}
                        </div>
    
                        <img onerror="this.src='/images/flags/_404.png'" :src="`/images/flags/${player.profile.country}.png`" class="w-6 inline mr-2 mb-0.5">
                        <div class="inline text-lg" v-html="q3tohtml(player.profile.name)"></div>
                    </div>
                </div>

                <div style="height: 1px; width: 100%;" class="bg-gray-700"></div>

                <div class="flex justify-center px-5 py-2" v-if="player.profile?.clan">
                    <Link :href="route('clans.show', player.profile.clan.id)" class="flex items-center text-lg font-medium text-gray-300">
                        <img class="h-8 w-8 rounded-full object-cover mr-4" :src="`/storage/${player.profile.clan.image}`">

                        <span class="text-md" v-html="q3tohtml(player.profile.clan.name)"></span>
                    </Link>
                </div>
            </template>
        </Popper>
    </div>
</template>

<style>
    :root {
        --popper-theme-background-color: #272e3bf8;
        --popper-theme-background-color-hover: #272e3bee;
        --popper-theme-text-color: #ffffff;
        --popper-theme-border-width: 0px;
        --popper-theme-border-style: solid;
        --popper-theme-border-radius: 6px;
        --popper-theme-padding: 10px 100px 10px 10px;
        --popper-theme-box-shadow: 0 6px 30px -6px rgba(0, 0, 0, 0.25);
    }
</style>

<style scoped>
    .online-player-name-text{
        font-size: 14px;
    }

    /* Slow enough to read as "on air" rather than as something demanding a
       click. A full server list can carry a dozen of these at once. */
    .live-dot-pulse {
        animation: live-dot-pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
    }

    @keyframes live-dot-pulse {
        0%, 100% { transform: scale(1); opacity: .75; }
        50% { transform: scale(1.9); opacity: 0; }
    }

    @media (prefers-reduced-motion: reduce) {
        .live-dot-pulse { animation: none; }
    }

    .player-name-stroke {
        text-shadow:
            -1px -1px 0 #000,
            1px -1px 0 #000,
            -1px 1px 0 #000,
            1px 1px 0 #000,
            -2px -2px 0 #000,
            2px -2px 0 #000,
            -2px 2px 0 #000,
            2px 2px 0 #000,
            0 0 4px rgba(0,0,0,0.9),
            0 2px 6px rgba(0,0,0,0.8);
    }
</style>
