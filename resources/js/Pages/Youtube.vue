<script setup>
    import { ref, computed, onMounted, onUnmounted } from 'vue';
    import { Head, Link, router } from '@inertiajs/vue3';
    import Pagination from '@/Components/Basic/Pagination.vue';
    import { t } from '@/utils/i18n';
    import { useDeferredProps } from '@/utils/deferredProps';

    const props = defineProps({
        stats: Object,
        videos: Object,
        currentlyRendering: Object,
        pendingQueue: Array,
        pendingTotal: Number,
        demomeOnline: Boolean,
    });

    const expandedVideo = ref(null);
    const searchMap = ref(new URL(window.location).searchParams.get('search_map') || '');
    const searchPlayer = ref(new URL(window.location).searchParams.get('search_player') || '');
    let searchTimeout = null;

    const doSearch = () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            const params = {};
            if (searchMap.value) params.search_map = searchMap.value;
            if (searchPlayer.value) params.search_player = searchPlayer.value;
            router.get('/rendered-demos', params, {
                preserveState: true,
                preserveScroll: true,
                only: ['videos'],
            });
        }, 300);
    };

    const videosLoaded = ref(!!props.videos);

    // The list arrives in a second request, and comes again whenever the
    // page is re-rendered without it - a language switch, say.
    useDeferredProps(() => !props.videos, (done) => {
        const start = Date.now();
        router.reload({
            only: ['videos', 'currentlyRendering', 'pendingQueue', 'pendingTotal'],
            onFinish: () => {
                done();
                const remaining = 400 - (Date.now() - start);
                if (remaining > 0) {
                    setTimeout(() => { videosLoaded.value = true; }, remaining);
                } else {
                    videosLoaded.value = true;
                }
            }
        });
    });

    // Auto-refresh live status every 15 seconds
    let refreshInterval = null;
    onMounted(() => {
        if (props.currentlyRendering || (props.pendingQueue && props.pendingQueue.length > 0)) {
            refreshInterval = setInterval(() => {
                router.reload({ only: ['currentlyRendering', 'pendingQueue', 'pendingTotal', 'demomeOnline', 'videos', 'stats'] });
            }, 15000);
        }
    });
    onUnmounted(() => {
        if (refreshInterval) clearInterval(refreshInterval);
    });

    const toggleVideo = (videoId) => {
        expandedVideo.value = expandedVideo.value === videoId ? null : videoId;
    };

    const formatTime = (timeMs) => {
        if (!timeMs) return '-';
        const minutes = Math.floor(timeMs / 60000);
        const seconds = Math.floor((timeMs % 60000) / 1000);
        const ms = timeMs % 1000;
        return `${minutes}:${String(seconds).padStart(2, '0')}.${String(ms).padStart(3, '0')}`;
    };

    const formatDuration = (seconds) => {
        if (!seconds) return '-';
        const h = Math.floor(seconds / 3600);
        const m = Math.floor((seconds % 3600) / 60);
        const s = seconds % 60;
        if (h > 0) return `${h}h ${m}m`;
        return `${m}m ${s}s`;
    };

    const sourceLabel = (source) => {
        return { discord: 'Discord', web: t('Web'), auto: t('Auto') }[source] || source;
    };

    const sourceColor = (source) => {
        return {
            discord: 'bg-indigo-500/20 text-indigo-300',
            web: 'bg-green-500/20 text-green-300',
            auto: 'bg-gray-500/20 text-gray-300',
        }[source] || 'bg-gray-500/20 text-gray-300';
    };

    const priorityLabel = (p) => {
        return { 1: 'WR', 2: t('Verified'), 3: t('Normal') }[p] || '';
    };

    const estimateRender = (timeMs) => {
        if (!timeMs) return null;
        const totalSec = Math.round(timeMs / 1000 * 3);
        if (totalSec < 60) return `~${totalSec}s`;
        const min = Math.round(totalSec / 60);
        return `~${min} min`;
    };

    const gridSize = ref(4);
    const gridClass = computed(() => {
        return {
            3: 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-3',
            4: 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4',
        }[gridSize.value];
    });

    const embedParams = 'autoplay=1&rel=0&iv_load_policy=3&playsinline=1&color=white&modestbranding=1';

    const timeSince = (dateStr) => {
        const now = new Date();
        const date = new Date(dateStr);
        const diffMs = now - date;
        const diffMin = Math.floor(diffMs / 60000);
        if (diffMin < 1) return t('just now');
        if (diffMin < 60) return t(':count m ago', { count: diffMin });
        const diffH = Math.floor(diffMin / 60);
        if (diffH < 24) return t(':count h ago', { count: diffH });
        return t(':count d ago', { count: Math.floor(diffH / 24) });
    };
</script>

<template>
    <div class="">
        <Head :title="$t('Rendered Demos')" />

        <!-- Header Section -->
        <div class="relative bg-gradient-to-b from-black/25 via-black/10 to-transparent pt-6 pb-8">
            <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8">
                <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
                    <div class="flex items-center gap-3">
                        <h1 class="text-2xl md:text-3xl font-black text-gray-300/90">{{ $t('Rendered Demos') }}</h1>
                        <span v-if="demomeOnline" class="flex items-center gap-1.5 px-2 py-1 rounded-full bg-green-500/10 border border-green-500/30">
                            <span class="w-2 h-2 rounded-full bg-green-400 animate-pulse"></span>
                            <span class="text-xs font-medium text-green-400">{{ $t('Renderer Online') }}</span>
                        </span>
                        <span v-else class="flex items-center gap-1.5 px-2 py-1 rounded-full bg-gray-500/10 border border-gray-500/30">
                            <span class="w-2 h-2 rounded-full bg-gray-500"></span>
                            <span class="text-xs font-medium text-gray-500">{{ $t('Renderer Offline') }}</span>
                        </span>
                    </div>

                    <!-- Stats (right side) -->
                    <div class="flex items-center gap-3">
                        <div class="bg-black/40 backdrop-blur-sm border border-white/10 rounded-lg px-4 py-2 text-center">
                            <div class="text-lg font-bold text-white leading-tight">{{ stats.total_renders }}</div>
                            <div class="text-[10px] text-gray-400">{{ $t('Videos') }}</div>
                        </div>
                        <div class="bg-black/40 backdrop-blur-sm border border-white/10 rounded-lg px-4 py-2 text-center">
                            <div class="text-lg font-bold text-white leading-tight">{{ stats.total_render_hours }}h</div>
                            <div class="text-[10px] text-gray-400">{{ $t('Render Time') }}</div>
                        </div>
                        <div class="bg-black/40 backdrop-blur-sm border border-white/10 rounded-lg px-4 py-2 text-center">
                            <div class="text-lg font-bold text-white leading-tight">{{ stats.total_maps }}</div>
                            <div class="text-[10px] text-gray-400">{{ $t('Maps') }}</div>
                        </div>
                    </div>
                </div>

                <!-- Search -->
                <div class="flex gap-2">
                    <div class="relative">
                        <input
                            v-model="searchMap"
                            @input="doSearch()"
                            type="text"
                            :placeholder="$t('Map name...')"
                            class="w-40 bg-black/40 backdrop-blur-sm border border-white/10 rounded-lg px-4 py-2 text-sm text-white placeholder-gray-500 focus:outline-none focus:border-white/30"
                        />
                        <button v-if="searchMap" @click="searchMap = ''; doSearch()" class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-white">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                        </button>
                        <svg v-else class="absolute right-2 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" /></svg>
                    </div>
                    <div class="relative">
                        <input
                            v-model="searchPlayer"
                            @input="doSearch()"
                            type="text"
                            :placeholder="$t('Player name...')"
                            class="w-40 bg-black/40 backdrop-blur-sm border border-white/10 rounded-lg px-4 py-2 text-sm text-white placeholder-gray-500 focus:outline-none focus:border-white/30"
                        />
                        <button v-if="searchPlayer" @click="searchPlayer = ''; doSearch()" class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-white">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                        </button>
                        <svg v-else class="absolute right-2 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" /></svg>
                    </div>
                </div>
            </div>
        </div>

        <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8 -mt-2 pb-12">

            <!-- Live Render Status -->
            <div v-if="videosLoaded && (currentlyRendering || (pendingQueue && pendingQueue.length > 0))" class="mb-8 grid grid-cols-1 lg:grid-cols-2 gap-4">
                <!-- Currently Rendering (left) -->
                <div v-if="currentlyRendering" class="bg-gradient-to-r from-red-500/10 to-orange-500/10 backdrop-blur-sm border border-red-500/30 rounded-xl p-5">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-3 h-3 rounded-full bg-red-500 animate-pulse"></div>
                        <span class="text-sm font-bold text-white uppercase tracking-wide">{{ $t('Currently Rendering') }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <div>
                            <Link :href="`/maps/${encodeURIComponent(currentlyRendering.map_name)}`" class="text-lg font-bold text-white hover:text-blue-400 transition-colors">
                                {{ currentlyRendering.map_name }}
                            </Link>
                            <div class="text-sm text-gray-400 mt-0.5">
                                <span v-html="q3tohtml(currentlyRendering.player_name)"></span>
                                <span class="uppercase ml-1">{{ currentlyRendering.physics }}</span>
                                <span class="font-mono ml-2">{{ formatTime(currentlyRendering.time_ms) }}</span>
                                <span v-if="currentlyRendering.record?.rank" class="ml-2 text-gray-500">#{{ currentlyRendering.record.rank }}</span>
                            </div>
                        </div>
                        <div class="text-right">
                            <span :class="sourceColor(currentlyRendering.source)" class="text-xs px-2 py-0.5 rounded font-medium">
                                {{ sourceLabel(currentlyRendering.source) }}
                            </span>
                            <div v-if="currentlyRendering.requested_by" class="text-xs text-gray-500 mt-1" v-html="$t('by :name', { name: q3tohtml(currentlyRendering.requested_by) })"></div>
                        </div>
                    </div>
                    <!-- Progress bar (indeterminate for now) -->
                    <div class="mt-4 h-1.5 bg-white/10 rounded-full overflow-hidden">
                        <div class="h-full bg-gradient-to-r from-red-500 to-orange-500 rounded-full animate-progress-indeterminate"></div>
                    </div>
                    <div class="text-xs text-gray-500 mt-1.5">
                        {{ $t('Started :time', { time: timeSince(currentlyRendering.updated_at) }) }}
                        <span v-if="estimateRender(currentlyRendering.time_ms)" class="ml-2">- {{ $t('Est. :time (if demo has prerun, this may be off)', { time: estimateRender(currentlyRendering.time_ms) }) }}</span>
                    </div>
                </div>

                <!-- Pending Queue (right) -->
                <div v-if="pendingQueue && pendingQueue.length > 0" class="bg-black/40 backdrop-blur-sm border border-white/10 rounded-xl overflow-hidden" :class="{ 'lg:col-start-2': !currentlyRendering }">
                    <div class="px-3 py-2 border-b border-white/5 flex items-center justify-between">
                        <span class="text-sm font-bold text-white">{{ $t('Queue') }}</span>
                        <span class="text-xs text-gray-500">{{ $t(':count pending', { count: pendingTotal }) }}</span>
                    </div>
                    <div class="divide-y divide-white/5 max-h-64 overflow-y-auto">
                        <div v-for="(item, i) in pendingQueue" :key="item.id" class="px-3 py-1.5 flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <span class="text-xs font-bold text-gray-500 w-5 text-right">{{ i + 1 }}</span>
                                <div>
                                    <span class="text-sm font-medium text-gray-200">{{ item.map_name }}</span>
                                    <span class="text-xs text-gray-500 ml-2" v-html="q3tohtml(item.player_name)"></span>
                                    <span class="text-xs text-gray-600 uppercase ml-1">{{ item.physics }}</span>
                                    <span class="text-xs text-gray-500 font-mono ml-1">{{ formatTime(item.time_ms) }}</span>
                                    <span v-if="item.record?.rank" class="text-xs text-gray-600 ml-1">#{{ item.record.rank }}</span>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <span v-if="item.priority < 3" class="text-xs px-1.5 py-0.5 rounded font-medium"
                                    :class="item.priority === 1 ? 'bg-yellow-500/20 text-yellow-400' : 'bg-blue-500/20 text-blue-400'">
                                    {{ priorityLabel(item.priority) }}
                                </span>
                                <span :class="sourceColor(item.source)" class="text-xs px-1.5 py-0.5 rounded font-medium">
                                    {{ sourceLabel(item.source) }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Loading skeleton -->
            <div v-if="!videosLoaded">
                <!-- Queue skeleton -->
                <div class="mb-8 grid grid-cols-1 lg:grid-cols-2 gap-4">
                    <div class="bg-black/40 backdrop-blur-sm border border-white/10 rounded-xl p-5 animate-pulse">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="w-3 h-3 rounded-full bg-white/10"></div>
                            <div class="h-3 bg-white/10 rounded w-32"></div>
                        </div>
                        <div class="h-5 bg-white/10 rounded w-48 mb-2"></div>
                        <div class="h-3 bg-white/5 rounded w-64"></div>
                        <div class="mt-4 h-1.5 bg-white/10 rounded-full"></div>
                    </div>
                    <div class="bg-black/40 backdrop-blur-sm border border-white/10 rounded-xl overflow-hidden animate-pulse">
                        <div class="px-3 py-2 border-b border-white/5 flex items-center justify-between">
                            <div class="h-3 bg-white/10 rounded w-12"></div>
                            <div class="h-3 bg-white/5 rounded w-16"></div>
                        </div>
                        <div class="divide-y divide-white/5">
                            <div v-for="i in 4" :key="i" class="px-3 py-2 flex items-center gap-3">
                                <div class="h-3 bg-white/5 rounded w-4"></div>
                                <div class="h-3 bg-white/10 rounded w-40"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Video grid skeleton -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                    <div v-for="i in 12" :key="i" class="bg-black/40 backdrop-blur-sm border border-white/10 rounded-lg overflow-hidden animate-pulse">
                        <div class="aspect-video bg-white/5"></div>
                        <div class="p-3 space-y-2">
                            <div class="h-4 bg-white/10 rounded w-3/4"></div>
                            <div class="h-3 bg-white/5 rounded w-1/2"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Video Grid -->
            <div v-else-if="videos && videos.data.length === 0 && !currentlyRendering && (!pendingQueue || pendingQueue.length === 0)" class="text-center py-20 text-gray-400">
                {{ $t('No rendered videos yet.') }}
            </div>

            <div v-else-if="videos && videos.data.length > 0">
                <div class="flex items-center justify-between mb-4">
                    <h2 v-if="currentlyRendering || (pendingQueue && pendingQueue.length > 0)" class="text-lg font-bold text-white">{{ $t('Completed') }}</h2>
                    <div class="flex items-center gap-1 bg-black/40 backdrop-blur-sm border border-white/10 rounded-lg p-1">
                        <button v-for="n in [3, 4]" :key="n" @click="gridSize = n"
                            class="px-2.5 py-1 text-xs font-medium rounded-md transition-all"
                            :class="gridSize === n ? 'bg-white/15 text-white' : 'text-gray-400 hover:text-white'">
                            {{ n }}
                        </button>
                    </div>
                </div>
                <div class="grid gap-4" :class="gridClass">
                    <div
                        v-for="video in videos.data"
                        :key="video.id"
                        :class="video.status === 'failed'
                            ? 'bg-black/40 border border-red-500/30 rounded-lg overflow-hidden opacity-50'
                            : 'bg-black/40 backdrop-blur-sm border border-white/10 rounded-lg overflow-hidden hover:border-white/20 transition-all'"
                    >
                        <!-- Failed overlay -->
                        <template v-if="video.status === 'failed'">
                            <div class="aspect-video bg-black/80 flex items-center justify-center">
                                <div class="text-center px-4">
                                    <svg class="w-8 h-8 text-red-500 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                    </svg>
                                    <div class="text-xs text-red-400 font-medium">{{ $t('Render failed') }}</div>
                                    <div class="text-xs text-gray-500 mt-1">{{ $t('Reported to admin') }}</div>
                                </div>
                            </div>
                        </template>

                        <!-- Thumbnail / Embedded Player -->
                        <template v-else>
                        <div class="relative aspect-video bg-black cursor-pointer" @click="toggleVideo(video.id)">
                            <template v-if="expandedVideo === video.id">
                                <iframe
                                    :src="`https://www.youtube.com/embed/${video.youtube_video_id}?${embedParams}`"
                                    class="absolute inset-0 w-full h-full"
                                    frameborder="0"
                                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                    allowfullscreen
                                ></iframe>
                            </template>
                            <template v-else>
                                <img
                                    :src="`https://img.youtube.com/vi/${video.youtube_video_id}/maxresdefault.jpg`"
                                    :alt="video.map_name"
                                    class="absolute inset-0 w-full h-full object-cover"
                                    loading="lazy"
                                    decoding="async"
                                    @error="(e) => e.target.src = `https://img.youtube.com/vi/${video.youtube_video_id}/mqdefault.jpg`"
                                />
                                <div class="absolute inset-0">
                                    <div class="absolute inset-x-0 top-0 h-12 bg-gradient-to-b from-black/70 to-transparent"></div>
                                    <div class="absolute inset-x-0 bottom-0 h-16 bg-gradient-to-t from-black/70 to-transparent"></div>
                                    <div class="absolute inset-0 flex items-center justify-center group">
                                        <svg class="w-[68px] h-[48px] transition-opacity group-hover:opacity-100 opacity-80" viewBox="0 0 68 48">
                                            <path d="M66.52 7.74c-.78-2.93-2.49-5.41-5.42-6.19C55.79.13 34 0 34 0S12.21.13 6.9 1.55C3.97 2.33 2.27 4.81 1.48 7.74.06 13.05 0 24 0 24s.06 10.95 1.48 16.26c.78 2.93 2.49 5.41 5.42 6.19C12.21 47.87 34 48 34 48s21.79-.13 27.1-1.55c2.93-.78 4.64-3.26 5.42-6.19C67.94 34.95 68 24 68 24s-.06-10.95-1.48-16.26z" fill="red"/>
                                            <path d="M45 24 27 14v20" fill="white"/>
                                        </svg>
                                    </div>
                                    <div class="absolute bottom-2 left-3 text-xs text-white/80 font-medium drop-shadow">{{ video.map_name }}</div>
                                    <div class="absolute bottom-2 right-3 text-xs text-white/60 font-mono drop-shadow">{{ formatTime(video.time_ms) }}</div>
                                </div>
                            </template>
                        </div>
                        </template>

                        <!-- Video Info -->
                        <div class="p-3">
                            <div class="flex items-center justify-between mb-1">
                                <Link
                                    :href="`/maps/${encodeURIComponent(video.map_name)}`"
                                    class="text-sm font-semibold text-white hover:text-blue-400 transition-colors truncate"
                                >
                                    {{ video.map_name }}
                                </Link>
                                <span :class="sourceColor(video.source)" class="text-xs px-1.5 py-0.5 rounded font-medium ml-2 shrink-0">
                                    {{ sourceLabel(video.source) }}
                                </span>
                            </div>

                            <div class="text-xs text-gray-400 space-y-0.5">
                                <div class="flex items-center justify-between">
                                    <span v-html="q3tohtml(video.player_name)"></span>
                                    <span class="font-mono">{{ formatTime(video.time_ms) }}</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="uppercase">{{ video.physics }}</span>
                                    <span v-if="video.render_duration_seconds" class="text-gray-500">
                                        {{ $t('Render time: :duration', { duration: formatDuration(video.render_duration_seconds) }) }}
                                    </span>
                                </div>
                                <div v-if="video.requested_by" class="text-gray-500 truncate" v-html="$t('by :name', { name: q3tohtml(video.requested_by) })"></div>
                            </div>

                            <div class="mt-2 flex items-center justify-between">
                                <a
                                    v-if="video.youtube_url"
                                    :href="video.youtube_url"
                                    target="_blank"
                                    rel="noopener"
                                    class="flex items-center gap-1.5 text-xs text-red-400 hover:text-red-300 transition-colors"
                                >
                                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814z"/>
                                        <path d="M9.545 15.568V8.432L15.818 12l-6.273 3.568z" fill="#fff"/>
                                    </svg>
                                    {{ $t('Watch on YouTube') }}
                                </a>
                                <a
                                    v-if="video.demo_id"
                                    :href="`/demos/${video.demo_id}/download`"
                                    class="flex items-center gap-1.5 text-xs text-blue-400 hover:text-blue-300 transition-colors"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                                    </svg>
                                    {{ $t('Download Demo') }}
                                </a>
                            </div>
                            <div v-if="!video.youtube_url && video.status === 'failed'" class="mt-2 text-xs text-red-400/60">
                                {{ $t('Waiting for admin fix') }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pagination -->
            <div v-if="videos && videos.last_page > 1" class="mt-8">
                <Pagination
                    :last_page="videos.last_page"
                    :current_page="videos.current_page"
                    link="/rendered-demos"
                />
            </div>
        </div>
    </div>
</template>

<style scoped>
@keyframes progress-indeterminate {
    0% { transform: translateX(-100%); width: 40%; }
    50% { transform: translateX(50%); width: 60%; }
    100% { transform: translateX(250%); width: 40%; }
}
.animate-progress-indeterminate {
    animation: progress-indeterminate 2s ease-in-out infinite;
}
</style>
