<script setup>
    import { Head, router, Link, usePage } from '@inertiajs/vue3';
    import Record from '@/Components/Record.vue';
    import AmnestyBanner from '@/Components/AmnestyBanner.vue';
    import Pagination from '@/Components/Basic/Pagination.vue';
    import Dropdown from '@/Components/Laravel/Dropdown.vue';
    import { watchEffect, ref, computed, onMounted } from 'vue';

    const page = usePage();
    const cpmFirst = computed(() => page.props.physicsOrder === 'cpm_first');

    const dateColWidth = computed(() => {
        if (windowWidth.value < 500) return 'w-[50px]';
        const fmt = page.props.dateFormat;
        return (fmt === 'Ymd' || fmt === 'dmY') ? 'w-[72px]' : 'w-[50px]';
    });

    // Resolution debug indicator
    const windowWidth = ref(typeof window !== 'undefined' ? window.innerWidth : 0);
    const breakpointLabel = computed(() => {
        const w = windowWidth.value;
        if (w >= 1536) return `2xl (${w}px)`;
        if (w >= 1280) return `xl (${w}px)`;
        if (w >= 1024) return `lg (${w}px)`;
        if (w >= 768) return `md (${w}px)`;
        if (w >= 640) return `sm (${w}px)`;
        return `xs (${w}px)`;
    });
    if (typeof window !== 'undefined') {
        window.addEventListener('resize', () => { windowWidth.value = window.innerWidth; });
    }

    const scoreTooltip = ref(null);
    const scoreTooltipStyle = computed(() => {
        if (!scoreTooltip.value?.el) return {};
        const rect = scoreTooltip.value.el.getBoundingClientRect();
        return {
            position: 'fixed',
            left: rect.left + rect.width / 2 + 'px',
            top: rect.top - 8 + 'px',
            transform: 'translate(-50%, -100%)',
            zIndex: 99999,
        };
    });

    const props = defineProps({
        vq3Records: Object,
        cpmRecords: Object
    });

    const recordsLoaded = ref(!!props.vq3Records || !!props.cpmRecords);

    // The lists arrive in a second request, and come again whenever the page
    // is re-rendered without them - a language switch, say.
    useDeferredProps(() => !props.vq3Records && !props.cpmRecords, (done) => {
        const start = Date.now();
        recordsLoaded.value = false;

        router.reload({
            only: ['vq3Records', 'cpmRecords'],
            onFinish: () => {
                done();
                const remaining = 400 - (Date.now() - start);
                if (remaining > 0) {
                    setTimeout(() => { recordsLoaded.value = true; }, remaining);
                } else {
                    recordsLoaded.value = true;
                }
            }
        });
    });

    const physics = ref('all');
    const mode = ref('all');

    const sortByPhysics = (newPhysics) => {
        physics.value = newPhysics

        router.reload({
            data: {
                physics: physics.value,
                mode: mode.value
            }
        })
    }

    const sortByMode = (newMode) => {
        mode.value = newMode

        router.reload({
            only: ['vq3Records', 'cpmRecords'],
            data: {
                physics: physics.value,
                mode: mode.value
            }
        })
    }

    watchEffect(() => {
        const url = new URL(window.location.href);
        physics.value = url.searchParams.get('physics') ?? 'all';
        mode.value = url.searchParams.get('mode') ?? 'all';
    });
</script>

<template>
    <div class="pb-4">
        <Head :title="$t('Records')" />

        <!-- Resolution Debug Indicator -->
        <div class="fixed bottom-2 right-2 z-[99999] bg-black/80 border border-white/20 rounded-lg px-3 py-1.5 text-xs font-mono text-green-400 pointer-events-none">
            {{ breakpointLabel }}
        </div>

        <!-- Header Section -->
        <div class="relative bg-gradient-to-b from-black/25 via-black/10 to-transparent pt-6 pb-96">
            <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8">
                <div class="flex justify-between items-center flex-wrap gap-4">
                    <!-- Title -->
                    <div>
                        <h1 class="text-2xl md:text-3xl font-black text-gray-300/90 mb-2">{{ $t('Latest Records') }}</h1>
                        <div class="flex items-center gap-2 text-gray-400">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 0 1 0 3.75H5.625a1.875 1.875 0 0 1 0-3.75Z" />
                            </svg>
                            <span class="text-sm font-semibold">{{ $tc(':count total record|:count total records', (vq3Records?.total || 0) + (cpmRecords?.total || 0)) }}</span>
                        </div>
                    </div>

                    <AmnestyBanner />

                    <!-- Gamemode Filter -->
                    <div class="flex flex-wrap items-center gap-2 bg-black/40 backdrop-blur-sm rounded-xl p-2.5">
                        <!-- All Modes -->
                        <div class="flex items-center bg-white/5 border border-white/10 rounded-xl p-2">
                            <button
                                @click="sortByMode('all')"
                                :class="[
                                    'px-3 py-1.5 rounded-lg text-xs font-semibold transition-all border',
                                    mode === 'all'
                                        ? 'bg-white/30 border-white/50 text-white'
                                        : 'bg-white/5 border-white/10 text-gray-400 hover:bg-white/10'
                                ]">
                                {{ $t('ALL MODES') }}
                            </button>
                        </div>

                        <!-- Run Mode -->
                        <div class="flex items-center bg-white/5 border border-white/10 rounded-xl p-2">
                            <button
                                @click="sortByMode('run')"
                                :class="[
                                    'px-3 py-1.5 rounded-lg text-xs font-semibold transition-all border',
                                    mode === 'run'
                                        ? 'bg-green-500/30 border-green-400/50 text-white'
                                        : 'bg-white/5 border-white/10 text-gray-400 hover:bg-white/10'
                                ]">
                                {{ $t('RUN') }}
                            </button>
                        </div>

                        <!-- CTF Modes -->
                        <div class="flex items-center gap-1 bg-white/5 border border-white/10 rounded-xl p-2">
                            <button
                                @click="sortByMode('ctf')"
                                :class="[
                                    'px-3 py-1.5 rounded-lg text-xs font-semibold transition-all border',
                                    mode === 'ctf'
                                        ? 'bg-red-500/30 border-red-400/50 text-white'
                                        : 'bg-white/5 border-white/10 text-gray-400 hover:bg-white/10'
                                ]">
                                {{ $t('ALL CTF') }}
                            </button>
                            <button
                                v-for="i in 7" :key="'ctf' + i"
                                @click="sortByMode('ctf' + i)"
                                :class="[
                                    'px-2.5 py-1.5 rounded-lg text-xs font-semibold transition-all border',
                                    mode === 'ctf' + i
                                        ? 'bg-red-500/30 border-red-400/50 text-white'
                                        : 'bg-white/5 border-white/10 text-gray-400 hover:bg-white/10'
                                ]">
                                {{ i }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Records List - Two Tables -->
        <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8" style="margin-top: -22rem;">
            <div class="grid grid-cols-1 xl:grid-cols-2 gap-4 min-h-[800px]">
                <!-- Loading skeleton -->
                <template v-if="!vq3Records && !cpmRecords">
                    <div v-for="i in 2" :key="i" class="bg-black/40 backdrop-blur-sm rounded-xl overflow-hidden shadow-2xl border border-white/10">
                        <div class="bg-gradient-to-r from-white/5 to-white/3 border-b border-white/10 px-4 py-3">
                            <div class="h-6 bg-white/10 rounded w-32 animate-pulse"></div>
                        </div>
                        <div class="px-4 py-2">
                            <div v-for="j in 15" :key="j" class="flex items-center gap-3 py-2 border-b border-white/[0.02]">
                                <div class="w-8 h-4 bg-white/10 rounded animate-pulse"></div>
                                <div class="h-4 bg-white/10 rounded animate-pulse" :style="{ width: (60 + Math.random() * 100) + 'px' }"></div>
                                <div class="w-6 h-6 bg-white/10 rounded-full animate-pulse"></div>
                                <div class="h-4 bg-white/10 rounded animate-pulse" :style="{ width: (50 + Math.random() * 60) + 'px' }"></div>
                                <div class="h-4 bg-white/10 rounded w-16 animate-pulse ml-auto"></div>
                                <div class="h-3 bg-white/5 rounded w-24 animate-pulse"></div>
                            </div>
                        </div>
                    </div>
                </template>

                <template v-else>
                <!-- VQ3 Records -->
                <div :style="{ order: cpmFirst ? 2 : 1 }" class="bg-gradient-to-br from-white/10 to-white/5 backdrop-blur-sm rounded-xl overflow-hidden shadow-2xl border border-white/10 hover:border-white/20 transition-all duration-300">
                    <div class="bg-gradient-to-r from-blue-600/20 to-blue-500/10 border-b border-blue-500/30 px-4 pt-1 pb-1">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <h2 class="text-lg font-bold text-blue-400">{{ $t('VQ3 Records') }} <span class="text-sm font-normal text-gray-400">({{ vq3Records.total }})</span></h2>
                            </div>
                            <Link v-if="page.props.auth?.user" href="/user/settings?tab=global-customize" class="text-xs text-gray-500 hover:text-blue-400 transition-colors underline decoration-dotted underline-offset-2">
                                {{ $t('Swap VQ3/CPM sides') }}
                            </Link>
                        </div>
                    </div>
                    <div class="px-4 py-2">
                        <!-- Column Headers -->
                        <div class="flex items-center gap-2 sm:gap-3 mb-1 pb-1 border-b border-white/15">
                            <div class="w-5 sm:w-8 flex-shrink-0 text-center pl-0.5 text-[10px] text-gray-400 uppercase tracking-wider font-semibold -ml-3">#</div>
                            <div class="flex-1 text-[10px] text-gray-400 uppercase tracking-wider font-semibold">{{ $t('Player') }}</div>
                            <div class="flex items-center gap-2 sm:gap-3 flex-shrink-0">
                                <div class="w-16 min-[500px]:w-20 sm:w-40 flex-shrink-0 text-[10px] text-gray-400 uppercase tracking-wider font-semibold text-left">{{ $t('Map') }}</div>
                                <div class="flex items-center gap-0.5 ml-auto -mr-3">
                                    <div class="w-[60px] min-[500px]:w-[80px] text-[10px] text-gray-400 uppercase tracking-wider font-semibold text-right">{{ $t('Time') }}</div>
                                    <div class="hidden sm:block w-8 sm:w-10 flex-shrink-0 text-center text-[10px] text-gray-400 uppercase tracking-wider font-semibold" style="padding-left: 5px">{{ $t('Score') }}</div>
                                    <div v-if="windowWidth >= 400" :class="[dateColWidth, 'flex-shrink-0 text-[10px] text-gray-400 uppercase tracking-wider font-semibold text-right']">{{ $t('Date') }}</div>
                                </div>
                            </div>
                        </div>
                        <Record v-for="record in vq3Records.data" :key="record.id" :record="record" @scoreHover="scoreTooltip = $event" />
                    </div>
                    <!-- VQ3 Pagination -->
                    <div v-if="vq3Records.total > vq3Records.per_page" class="border-t border-blue-500/20 bg-transparent p-4">
                        <Pagination :last_page="vq3Records.last_page" :current_page="vq3Records.current_page" :link="vq3Records.first_page_url" pageName="vq3_page" :only="['vq3Records', 'cpmRecords']" />
                    </div>
                </div>

                <!-- CPM Records -->
                <div :style="{ order: cpmFirst ? 1 : 2 }" class="bg-gradient-to-br from-white/10 to-white/5 backdrop-blur-sm rounded-xl overflow-hidden shadow-2xl border border-white/10 hover:border-white/20 transition-all duration-300">
                    <div class="bg-gradient-to-r from-purple-600/20 to-purple-500/10 border-b border-purple-500/30 px-4 pt-1 pb-1">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <h2 class="text-lg font-bold text-purple-400">{{ $t('CPM Records') }} <span class="text-sm font-normal text-gray-400">({{ cpmRecords.total }})</span></h2>
                            </div>
                            <Link v-if="page.props.auth?.user" href="/user/settings?tab=global-customize" class="text-xs text-gray-500 hover:text-purple-400 transition-colors underline decoration-dotted underline-offset-2">
                                {{ $t('Swap VQ3/CPM sides') }}
                            </Link>
                        </div>
                    </div>
                    <div class="px-4 py-2">
                        <!-- Column Headers -->
                        <div class="flex items-center gap-2 sm:gap-3 mb-1 pb-1 border-b border-white/15">
                            <div class="w-5 sm:w-8 flex-shrink-0 text-center pl-0.5 text-[10px] text-gray-400 uppercase tracking-wider font-semibold -ml-3">#</div>
                            <div class="flex-1 text-[10px] text-gray-400 uppercase tracking-wider font-semibold">{{ $t('Player') }}</div>
                            <div class="flex items-center gap-2 sm:gap-3 flex-shrink-0">
                                <div class="w-16 min-[500px]:w-20 sm:w-40 flex-shrink-0 text-[10px] text-gray-400 uppercase tracking-wider font-semibold text-left">{{ $t('Map') }}</div>
                                <div class="flex items-center gap-0.5 ml-auto -mr-3">
                                    <div class="w-[60px] min-[500px]:w-[80px] text-[10px] text-gray-400 uppercase tracking-wider font-semibold text-right">{{ $t('Time') }}</div>
                                    <div class="hidden sm:block w-8 sm:w-10 flex-shrink-0 text-center text-[10px] text-gray-400 uppercase tracking-wider font-semibold" style="padding-left: 5px">{{ $t('Score') }}</div>
                                    <div v-if="windowWidth >= 400" :class="[dateColWidth, 'flex-shrink-0 text-[10px] text-gray-400 uppercase tracking-wider font-semibold text-right']">{{ $t('Date') }}</div>
                                </div>
                            </div>
                        </div>
                        <Record v-for="record in cpmRecords.data" :key="record.id" :record="record" @scoreHover="scoreTooltip = $event" />
                    </div>
                    <!-- CPM Pagination -->
                    <div v-if="cpmRecords.total > cpmRecords.per_page" class="border-t border-purple-500/20 bg-transparent p-4">
                        <Pagination :last_page="cpmRecords.last_page" :current_page="cpmRecords.current_page" :link="cpmRecords.first_page_url" pageName="cpm_page" :only="['vq3Records', 'cpmRecords']" />
                    </div>
                </div>
                </template>
            </div>
        </div>

        <div class="h-4"></div>
    </div>

    <!-- Score tooltip (teleported to body) -->
    <Teleport to="body">
        <div v-if="scoreTooltip" :style="scoreTooltipStyle"
            class="px-3 py-2 rounded-lg bg-gray-900 border border-white/15 text-[10px] text-gray-300 whitespace-nowrap shadow-2xl pointer-events-none">
            <div>{{ $t('Reltime:') }} <span class="text-blue-400">{{ scoreTooltip.reltime }}</span></div>
            <div v-if="scoreTooltip.base_score != null">{{ $t('Base score:') }} <span class="text-gray-100">{{ scoreTooltip.base_score }}</span></div>
            <div v-if="scoreTooltip.rank_multiplier != null">{{ $t('Rank mult:') }} <span class="text-purple-400">× {{ scoreTooltip.rank_multiplier }}</span></div>
            <div v-if="scoreTooltip.multiplier != null">{{ $t('Map mult:') }} <span class="text-green-400">× {{ scoreTooltip.multiplier }}</span></div>
            <div class="border-t border-white/10 mt-1 pt-1">{{ $t('Score:') }} <span class="text-yellow-400 font-bold">{{ scoreTooltip.score }}</span></div>
        </div>
    </Teleport>
</template>
