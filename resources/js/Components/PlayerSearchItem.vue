<script setup>
    import { Link } from '@inertiajs/vue3';

    const props = defineProps({
        player: Object
    });
</script>

<template>
    <Link class="group flex items-center gap-4 cursor-pointer rounded-xl hover:bg-white/5 p-3 transition-all border border-transparent hover:border-purple-500/30 hover:shadow-lg hover:shadow-purple-500/5" :href="route(player.mdd ? 'profile.mdd' : 'profile.index', player.id)">
        <!-- Avatar -->
        <div class="shrink-0 relative">
            <img class="h-12 w-12 rounded-full object-cover ring-2 ring-white/10 group-hover:ring-purple-500/50 transition-all shadow-lg" :src="player.profile_photo_path ? '/storage/' + player.profile_photo_path : '/images/null.jpg'" :alt="player.name">
            <img :src="`/images/flags/${player.country}.png`" class="absolute -bottom-0.5 -right-0.5 w-5 h-4 rounded-sm ring-2 ring-black/50 shadow" onerror="this.src='/images/flags/_404.png'">
        </div>

        <!-- Player Info -->
        <div class="flex-1 min-w-0">
            <div class="text-base font-black text-white group-hover:text-purple-400 transition-colors truncate mb-0.5" v-html="q3tohtml(player.name)"></div>
            <div class="text-xs text-gray-400 font-semibold">
                <span v-if="player.country">{{ player.country }}</span>
                <span v-else>{{ $t('Player') }}</span>
                <span v-if="player.matched_alias" class="text-gray-500"> {{ $t('- alias:') }} <span v-html="q3tohtml(player.matched_alias)"></span></span>
                <span v-else-if="player.mdd_name" class="text-gray-500"> {{ $t('- mDd:') }} <span v-html="q3tohtml(player.mdd_name)"></span></span>
            </div>
        </div>

        <!-- Arrow -->
        <svg class="w-5 h-5 text-gray-600 group-hover:text-purple-400 group-hover:translate-x-1 transition-all shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
        </svg>
    </Link>
</template>