<script setup>
    // "LIVE" in Twitch purple beside a server's name, with the streamer's
    // nick, so a stream can be spotted from the server list without opening
    // the player list. Renders nothing when nobody is streaming, so it can be
    // dropped into every header unconditionally.
    import { twitchChannel, twitchUrl } from '@/utils/twitch';

    defineProps({
        streamers: { type: Array, default: () => [] },
        size: { type: String, default: 'sm' },
    });
</script>

<template>
    <a v-for="s in streamers" :key="s.id"
       :href="twitchUrl(twitchChannel(s.profile))"
       target="_blank" rel="noopener noreferrer" @click.stop
       :title="$t('Live on Twitch')"
       class="inline-flex items-center gap-1.5 rounded-md border border-purple-400/50 bg-purple-600/30 font-black uppercase tracking-wider text-white shadow-[0_0_12px_-2px_rgba(168,85,247,0.7)] hover:bg-purple-600/50 transition-colors whitespace-nowrap"
       :class="size === 'xs' ? 'px-1.5 py-0.5 text-[9px]' : 'px-2 py-0.5 text-[10px]'">
        <span class="relative inline-flex w-1.5 h-1.5">
            <span class="live-dot-pulse absolute inline-flex w-full h-full rounded-full bg-red-500 opacity-75"></span>
            <span class="relative inline-flex w-1.5 h-1.5 rounded-full bg-red-500"></span>
        </span>
        {{ $t('LIVE') }}
        <span class="normal-case tracking-normal font-bold max-w-[7rem] truncate" v-html="q3tohtml(s.name)"></span>
    </a>
</template>

<style scoped>
    .live-dot-pulse { animation: live-dot-pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite; }
    @keyframes live-dot-pulse {
        0%, 100% { transform: scale(1); opacity: .75; }
        50% { transform: scale(2.2); opacity: 0; }
    }
    @media (prefers-reduced-motion: reduce) { .live-dot-pulse { animation: none; } }
</style>
