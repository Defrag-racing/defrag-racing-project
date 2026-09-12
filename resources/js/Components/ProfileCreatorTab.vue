<script setup>
    import { ref, onMounted, computed, watch } from 'vue';
    import { Link } from '@inertiajs/vue3';
    import LockedOverlay from '@/Components/LockedOverlay.vue';

    const props = defineProps({
        userId: [Number, String],
        // Guest or unverified viewer: the stats endpoint sends only the
        // header counts, the other endpoints answer 403. The tab shows the
        // maps grid and dotted stand-ins under a veil for the rest.
        locked: { type: Boolean, default: false },
    });

    const D = '···';
    const lockedStats = {
        total_records: D, world_records: D, avg_records_per_map: D, unique_players: D,
        oldest_map: null, newest_map: null,
        gametype_distribution: { run: D, ctf: D },
        physics_breakdown: { vq3: { maps: D, records: D, players: D }, cpm: { maps: D, records: D, players: D } },
        weapon_breakdown: {},
    };

    // Data refs
    const stats = ref(null);
    const maps = ref({ data: [], total: 0, current_page: 1, last_page: 1 });
    const topPlayersData = ref({ players: [], completionists_by_physics: {}, total_maps: 0 });
    const recentActivityData = ref({ records: [] });
    const heatmapData = ref({ heatmap: {}, yearly: {} });
    const highlightedMapData = ref({ vq3: null, cpm: null });

    // Loading states
    const loadingStats = ref(true);
    const loadingMaps = ref(false);
    const loadingTopPlayers = ref(false);
    const loadingRecentActivity = ref(false);

    // Filters
    const mapSort = ref('newest');
    const mapPhysics = ref('all');
    const mapGametype = ref('all');
    const mapWeapon = ref('all');
    const mapSearch = ref('');
    const mapPage = ref(1);

    // Active section for lazy loading
    const sectionsLoaded = ref({
        stats: false,
        maps: false,
        topPlayers: false,
        recentActivity: false,
        heatmap: false,
        highlightedMap: false,
    });

    const fetchStats = async () => {
        loadingStats.value = true;
        try {
            const res = await fetch(`/api/profile/${props.userId}/mapper/stats`);
            const data = await res.json();
            stats.value = data?.locked ? { ...data, ...lockedStats } : data;
            sectionsLoaded.value.stats = true;
        } catch (e) {
            console.error('Error loading mapper stats:', e);
        } finally {
            loadingStats.value = false;
        }
    };

    const fetchMaps = async () => {
        loadingMaps.value = true;
        try {
            const params = new URLSearchParams({
                sort: mapSort.value,
                physics: mapPhysics.value,
                gametype: mapGametype.value,
                weapon: mapWeapon.value,
                page: mapPage.value,
            });
            if (mapSearch.value) params.set('search', mapSearch.value);

            const res = await fetch(`/api/profile/${props.userId}/mapper/maps?${params}`);
            maps.value = await res.json();
            sectionsLoaded.value.maps = true;
        } catch (e) {
            console.error('Error loading mapper maps:', e);
        } finally {
            loadingMaps.value = false;
        }
    };

    const fetchTopPlayers = async () => {
        loadingTopPlayers.value = true;
        try {
            const res = await fetch(`/api/profile/${props.userId}/mapper/top-players`);
            topPlayersData.value = await res.json();
            sectionsLoaded.value.topPlayers = true;
        } catch (e) {
            console.error('Error loading top players:', e);
        } finally {
            loadingTopPlayers.value = false;
        }
    };

    const fetchRecentActivity = async () => {
        loadingRecentActivity.value = true;
        try {
            const res = await fetch(`/api/profile/${props.userId}/mapper/recent-activity`);
            recentActivityData.value = await res.json();
            sectionsLoaded.value.recentActivity = true;
        } catch (e) {
            console.error('Error loading recent activity:', e);
        } finally {
            loadingRecentActivity.value = false;
        }
    };

    const fetchHeatmap = async () => {
        try {
            const res = await fetch(`/api/profile/${props.userId}/mapper/heatmap`);
            heatmapData.value = await res.json();
            sectionsLoaded.value.heatmap = true;
        } catch (e) {
            console.error('Error loading heatmap:', e);
        }
    };

    const fetchHighlightedMap = async () => {
        try {
            const res = await fetch(`/api/profile/${props.userId}/mapper/highlighted-map`);
            highlightedMapData.value = await res.json();
            sectionsLoaded.value.highlightedMap = true;
        } catch (e) {
            console.error('Error loading highlighted map:', e);
        }
    };


    // Initial load
    onMounted(async () => {
        await fetchStats();
        // Load everything else in parallel after stats
        if (stats.value?.has_maps) {
            Promise.all(props.locked ? [fetchMaps()] : [
                fetchMaps(),
                fetchTopPlayers(),
                fetchRecentActivity(),
                fetchHeatmap(),
                fetchHighlightedMap(),
            ]);
        }
    });

    // Watchers for map filters
    watch([mapSort, mapPhysics, mapGametype, mapWeapon], () => {
        mapPage.value = 1;
        fetchMaps();
    });

    let searchTimeout = null;
    watch(mapSearch, () => {
        if (searchTimeout) clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            mapPage.value = 1;
            fetchMaps();
        }, 300);
    });

    const changePage = (page) => {
        mapPage.value = page;
        fetchMaps();
    };

    // Weapon icons mapping
    const weaponLabels = {
        strafe: { label: 'Strafe', src: '/images/svg/strafe.svg' },
        rl: { label: 'Rocket', src: '/images/weapons/iconw_rocket.svg' },
        gl: { label: 'Grenade', src: '/images/weapons/iconw_grenade.svg' },
        pg: { label: 'Plasma', src: '/images/weapons/iconw_plasma.svg' },
        bfg: { label: 'BFG', src: '/images/weapons/iconw_bfg.svg' },
    };

    // Format large numbers
    const formatNumber = (n) => {
        if (!n && n !== 0) return '0';
        return n.toLocaleString();
    };

    // Heatmap rendering
    const heatmapYears = computed(() => {
        return Object.keys(heatmapData.value.yearly || {})
            .filter(y => (heatmapData.value.yearly[y]?.maps || 0) > 0)
            .sort((a, b) => b - a);
    });

    const timelineMax = computed(() => {
        const yearly = heatmapData.value.yearly || {};
        let max = 1;
        for (const y of Object.values(yearly)) {
            const total = y.maps || 0;
            if (total > max) max = total;
        }
        return max;
    });
</script>

<template>
    <div>
        <!-- Loading State -->
        <div v-if="loadingStats" class="flex items-center justify-center py-20">
            <div class="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-green-500"></div>
        </div>

        <!-- No Maps State -->
        <div v-else-if="!stats?.has_maps" class="text-center py-20">
            <div class="text-6xl mb-4 opacity-50">&#x1f5fa;</div>
            <div class="text-xl font-bold text-gray-400">{{ $t('No maps found for this creator') }}</div>
            <div class="text-sm text-gray-500 mt-2">{{ $t('Claim your mapper names in Settings to display your maps here') }}</div>
        </div>

        <!-- Main Content -->
        <div v-else>

            <!-- Locked: stand-in for the two most popular maps -->
            <div v-if="locked" class="relative grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <LockedOverlay />
                <div v-for="k in 2" :key="k" class="bg-black/40 backdrop-blur-sm rounded-xl p-4 border border-white/5 h-28 flex items-center text-gray-500 text-sm">··········</div>
            </div>

            <!-- Most Popular Maps - VQ3 / CPM -->
            <div v-if="!locked && (highlightedMapData.vq3 || highlightedMapData.cpm)" class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <!-- VQ3 Most Popular -->
                <div v-if="highlightedMapData.vq3" class="bg-gradient-to-br from-blue-500/5 via-blue-500/10 to-blue-500/5 backdrop-blur-sm border border-blue-500/20 rounded-xl p-4 relative overflow-hidden">
                    <div class="absolute top-3 right-3">
                        <span class="text-xs font-black px-2 py-0.5 rounded bg-blue-500/20 border border-blue-500/30 text-blue-400">VQ3</span>
                    </div>
                    <div class="text-[10px] font-bold text-blue-400/60 uppercase tracking-widest mb-2">{{ $t('Most Popular') }}</div>
                    <Link :href="route('maps.map', highlightedMapData.vq3.name)">
                        <div class="relative rounded-lg overflow-hidden mb-3 group">
                            <img :src="highlightedMapData.vq3.thumbnail ? `/storage/${highlightedMapData.vq3.thumbnail}` : '/images/unknown_map.jpg'"
                                class="w-full h-32 object-cover group-hover:scale-105 transition duration-300"
                                :alt="highlightedMapData.vq3.name">
                            <div class="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent"></div>
                            <div class="absolute bottom-2 left-3">
                                <div class="text-lg font-black text-white drop-shadow-lg">{{ highlightedMapData.vq3.name }}</div>
                            </div>
                        </div>
                    </Link>
                    <div class="flex items-center gap-4 text-xs">
                        <span class="text-gray-400 [&_span]:text-white [&_span]:font-bold" v-html="$tc('<span>:count</span> player|<span>:count</span> players', highlightedMapData.vq3.player_count, { count: formatNumber(highlightedMapData.vq3.player_count) })"></span>
                        <span class="text-gray-400 [&_span]:text-white [&_span]:font-bold" v-html="$tc('<span>:count</span> record|<span>:count</span> records', highlightedMapData.vq3.record_count, { count: formatNumber(highlightedMapData.vq3.record_count) })"></span>
                    </div>
                    <div v-if="highlightedMapData.vq3.wr" class="mt-2 flex items-center gap-2 text-xs">
                        <svg class="w-3.5 h-3.5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor"><use href="/images/svg/icons.svg#icon-trophy"></use></svg>
                        <span class="text-yellow-400 font-bold">{{ $t('WR:') }}</span>
                        <span class="text-white font-bold" v-html="q3tohtml(highlightedMapData.vq3.wr.name)"></span>
                        <span class="text-blue-400 font-bold">{{ formatTime(highlightedMapData.vq3.wr.time) }}</span>
                    </div>
                </div>

                <!-- CPM Most Popular -->
                <div v-if="highlightedMapData.cpm" class="bg-gradient-to-br from-purple-500/5 via-purple-500/10 to-purple-500/5 backdrop-blur-sm border border-purple-500/20 rounded-xl p-4 relative overflow-hidden">
                    <div class="absolute top-3 right-3">
                        <span class="text-xs font-black px-2 py-0.5 rounded bg-purple-500/20 border border-purple-500/30 text-purple-400">CPM</span>
                    </div>
                    <div class="text-[10px] font-bold text-purple-400/60 uppercase tracking-widest mb-2">{{ $t('Most Popular') }}</div>
                    <Link :href="route('maps.map', highlightedMapData.cpm.name)">
                        <div class="relative rounded-lg overflow-hidden mb-3 group">
                            <img :src="highlightedMapData.cpm.thumbnail ? `/storage/${highlightedMapData.cpm.thumbnail}` : '/images/unknown_map.jpg'"
                                class="w-full h-32 object-cover group-hover:scale-105 transition duration-300"
                                :alt="highlightedMapData.cpm.name">
                            <div class="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent"></div>
                            <div class="absolute bottom-2 left-3">
                                <div class="text-lg font-black text-white drop-shadow-lg">{{ highlightedMapData.cpm.name }}</div>
                            </div>
                        </div>
                    </Link>
                    <div class="flex items-center gap-4 text-xs">
                        <span class="text-gray-400 [&_span]:text-white [&_span]:font-bold" v-html="$tc('<span>:count</span> player|<span>:count</span> players', highlightedMapData.cpm.player_count, { count: formatNumber(highlightedMapData.cpm.player_count) })"></span>
                        <span class="text-gray-400 [&_span]:text-white [&_span]:font-bold" v-html="$tc('<span>:count</span> record|<span>:count</span> records', highlightedMapData.cpm.record_count, { count: formatNumber(highlightedMapData.cpm.record_count) })"></span>
                    </div>
                    <div v-if="highlightedMapData.cpm.wr" class="mt-2 flex items-center gap-2 text-xs">
                        <svg class="w-3.5 h-3.5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor"><use href="/images/svg/icons.svg#icon-trophy"></use></svg>
                        <span class="text-yellow-400 font-bold">{{ $t('WR:') }}</span>
                        <span class="text-white font-bold" v-html="q3tohtml(highlightedMapData.cpm.wr.name)"></span>
                        <span class="text-purple-400 font-bold">{{ formatTime(highlightedMapData.cpm.wr.time) }}</span>
                    </div>
                </div>
            </div>

            <!-- Stats Overview (dotted stand-in under a veil when locked) -->
            <div class="bg-black/40 backdrop-blur-sm rounded-xl border border-white/5 mb-6 overflow-hidden relative">
                <LockedOverlay v-if="locked" />
                <!-- Top row (maps left, records right) -->
                <div class="grid grid-cols-2 md:grid-cols-4 divide-x divide-white/5 border-b border-white/5">
                    <div v-if="stats.oldest_map" class="p-4">
                        <div class="text-[10px] text-gray-500 uppercase tracking-wider font-bold">{{ $t('Oldest Map') }}</div>
                        <Link :href="route('maps.map', stats.oldest_map.name)" class="flex items-center gap-2 mt-1 group">
                            <img v-if="stats.oldest_map.thumbnail" :src="`/storage/${stats.oldest_map.thumbnail}`" class="w-14 h-9 object-cover rounded border border-white/10 flex-shrink-0">
                            <div class="min-w-0">
                                <div class="text-xs font-bold text-blue-400 group-hover:text-blue-300 transition truncate">{{ stats.oldest_map.name }}</div>
                                <div class="text-[10px] text-gray-600">{{ stats.oldest_map.date_added?.substring(0, 10) }}</div>
                            </div>
                        </Link>
                    </div>
                    <div v-if="stats.newest_map" class="p-4">
                        <div class="text-[10px] text-gray-500 uppercase tracking-wider font-bold">{{ $t('Newest Map') }}</div>
                        <Link :href="route('maps.map', stats.newest_map.name)" class="flex items-center gap-2 mt-1 group">
                            <img v-if="stats.newest_map.thumbnail" :src="`/storage/${stats.newest_map.thumbnail}`" class="w-14 h-9 object-cover rounded border border-white/10 flex-shrink-0">
                            <div class="min-w-0">
                                <div class="text-xs font-bold text-green-400 group-hover:text-green-300 transition truncate">{{ stats.newest_map.name }}</div>
                                <div class="text-[10px] text-gray-600">{{ stats.newest_map.date_added?.substring(0, 10) }}</div>
                            </div>
                        </Link>
                    </div>
                    <div class="p-4">
                        <div class="text-[10px] text-gray-500 uppercase tracking-wider font-bold">{{ $t('Total Records') }}</div>
                        <div class="text-2xl font-black text-emerald-400">{{ formatNumber(stats.total_records) }}</div>
                    </div>
                    <div class="p-4">
                        <div class="text-[10px] text-gray-500 uppercase tracking-wider font-bold">{{ $t('World Records') }}</div>
                        <div class="text-2xl font-black text-yellow-400">{{ formatNumber(stats.world_records) }}</div>
                    </div>
                </div>
                <!-- Details row: left = map info, right = physics -->
                <div class="grid grid-cols-2 divide-x divide-white/5">
                    <!-- Left side -->
                    <div class="flex divide-x divide-white/5">
                        <div class="p-3 flex-1">
                            <div class="text-[10px] text-gray-600 uppercase font-bold">{{ $t('Total Maps') }}</div>
                            <div class="text-sm font-black text-white">{{ formatNumber(stats.total_maps) }}</div>
                        </div>
                        <div class="p-3 flex-1">
                            <div class="text-[10px] text-gray-600 uppercase font-bold">{{ $t('Gametypes') }}</div>
                            <div class="flex items-center gap-1.5 mt-0.5">
                                <template v-for="(count, gt) in stats.gametype_distribution" :key="gt">
                                    <span class="text-[10px] font-bold px-1.5 py-0.5 rounded" :class="gt === 'run' ? 'bg-orange-500/20 text-orange-400' : 'bg-cyan-500/20 text-cyan-400'">
                                        {{ gt?.toUpperCase() }} {{ count }}
                                    </span>
                                </template>
                            </div>
                        </div>
                        <div class="p-3 flex-1">
                            <div class="text-[10px] text-gray-600 uppercase font-bold">{{ $t('Avg/Map') }}</div>
                            <div class="text-sm font-black text-gray-300">{{ formatNumber(stats.avg_records_per_map) }}</div>
                        </div>
                        <div class="p-3 flex-1">
                            <div class="text-[10px] text-gray-600 uppercase font-bold">{{ $t('Players') }}</div>
                            <div class="text-sm font-black text-blue-400">{{ formatNumber(stats.unique_players) }}</div>
                        </div>
                    </div>
                    <!-- Right side -->
                    <div class="flex divide-x divide-white/5">
                        <template v-for="(data, physics) in stats.physics_breakdown" :key="physics">
                            <div class="p-3 flex-1">
                                <div class="text-[10px] uppercase font-bold" :class="physics === 'vq3' ? 'text-blue-500' : 'text-purple-500'">{{ physics }}</div>
                                <div class="text-xs text-gray-400 mt-0.5 whitespace-nowrap [&_span]:text-white [&_span]:font-bold"
                                    v-html="$t('<span>:maps</span> maps - <span>:records</span> records - <span>:players</span> players', { maps: formatNumber(data.maps), records: formatNumber(data.records), players: formatNumber(data.unique_players) })"></div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            <!-- Maps Grid -->
            <div class="bg-black/40 backdrop-blur-sm rounded-xl p-4 border border-white/5 mb-6">
                <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-3 mb-4">
                    <h3 class="text-sm font-black text-white uppercase tracking-wider">{{ $t('Maps (:count)', { count: formatNumber(maps.total) }) }}</h3>

                    <!-- Search -->
                    <input v-model="mapSearch" type="text" :placeholder="$t('Search maps...')"
                        class="px-3 py-1.5 rounded-lg bg-black/40 border border-white/10 text-sm text-white placeholder-gray-500 focus:border-green-500/50 focus:outline-none w-40">
                </div>

                <!-- Filter pills -->
                <div class="flex flex-wrap items-center gap-x-4 gap-y-2 mb-4">
                    <!-- Sort -->
                    <div class="flex items-center gap-1">
                        <button v-for="opt in [{v:'newest',l:$t('Newest')},{v:'oldest',l:$t('Oldest')},{v:'most_records',l:$t('Most Records')},{v:'most_played',l:$t('Most Played')}]"
                            :key="opt.v" @click="mapSort = opt.v"
                            class="px-2.5 py-1 rounded text-xs font-bold transition-all"
                            :class="mapSort === opt.v ? 'bg-white/10 text-white border border-white/20' : 'text-gray-500 hover:text-gray-300'">
                            {{ opt.l }}
                        </button>
                    </div>

                    <div class="w-px h-4 bg-white/10 hidden md:block"></div>

                    <!-- Physics -->
                    <div class="flex items-center gap-1">
                        <button @click="mapPhysics = 'all'"
                            class="px-2.5 py-1 rounded text-xs font-bold transition-all"
                            :class="mapPhysics === 'all' ? 'bg-white/10 text-white border border-white/20' : 'text-gray-500 hover:text-gray-300'">
                            {{ $t('All') }}
                        </button>
                        <button @click="mapPhysics = 'vq3'"
                            class="px-2.5 py-1 rounded text-xs font-bold transition-all"
                            :class="mapPhysics === 'vq3' ? 'bg-blue-500/20 text-blue-400 border border-blue-500/30' : 'text-gray-500 hover:text-blue-400'">
                            VQ3
                        </button>
                        <button @click="mapPhysics = 'cpm'"
                            class="px-2.5 py-1 rounded text-xs font-bold transition-all"
                            :class="mapPhysics === 'cpm' ? 'bg-purple-500/20 text-purple-400 border border-purple-500/30' : 'text-gray-500 hover:text-purple-400'">
                            CPM
                        </button>
                    </div>

                    <div class="w-px h-4 bg-white/10 hidden md:block"></div>

                    <!-- Weapons -->
                    <div class="flex items-center gap-1">
                        <button @click="mapWeapon = 'all'"
                            class="px-2.5 py-1 rounded text-xs font-bold transition-all"
                            :class="mapWeapon === 'all' ? 'bg-white/10 text-white border border-white/20' : 'text-gray-500 hover:text-gray-300'">
                            {{ $t('All') }}
                        </button>
                        <button v-for="w in [{v:'strafe',l:'Strafe'},{v:'rl',l:'Rocket'},{v:'gl',l:'Grenade'},{v:'pg',l:'Plasma'},{v:'bfg',l:'BFG'}]"
                            :key="w.v" @click="mapWeapon = w.v"
                            class="px-2.5 py-1 rounded text-xs font-bold transition-all flex items-center gap-1"
                            :class="mapWeapon === w.v ? 'bg-green-500/20 text-green-400 border border-green-500/30' : 'text-gray-500 hover:text-gray-300'">
                            <img :src="weaponLabels[w.v]?.src" class="w-3.5 h-3.5" :alt="w.l">
                            {{ w.l }}
                        </button>
                    </div>
                </div>

                <!-- Loading -->
                <div v-if="loadingMaps" class="flex items-center justify-center py-12">
                    <div class="animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-green-500"></div>
                </div>

                <!-- Grid -->
                <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                    <div v-for="map in maps.data" :key="map.id"
                        class="bg-black/30 rounded-lg border border-white/5 hover:border-green-500/30 transition overflow-hidden group">
                        <Link :href="route('maps.map', map.name)">
                            <div class="relative">
                                <img :src="map.thumbnail ? `/storage/${map.thumbnail}` : '/images/unknown_map.jpg'"
                                    class="w-full h-32 object-cover group-hover:scale-105 transition duration-300"
                                    :alt="map.name"
                                    loading="lazy">
                                <!-- Physics badge -->
                                <div class="absolute top-2 right-2 flex gap-1">
                                    <span v-if="map.physics === 'all' || map.physics === 'vq3'" class="px-1.5 py-0.5 rounded text-[10px] font-black bg-blue-500/80 text-white">VQ3</span>
                                    <span v-if="map.physics === 'all' || map.physics === 'cpm'" class="px-1.5 py-0.5 rounded text-[10px] font-black bg-purple-500/80 text-white">CPM</span>
                                </div>
                                <!-- Gametype badge -->
                                <div class="absolute top-2 left-2">
                                    <span class="px-1.5 py-0.5 rounded text-[10px] font-black uppercase"
                                        :class="map.gametype === 'run' ? 'bg-orange-500/80 text-white' : 'bg-cyan-500/80 text-white'">
                                        {{ map.gametype }}
                                    </span>
                                </div>
                            </div>
                        </Link>
                        <div class="p-3">
                            <div class="flex items-center justify-between gap-2">
                                <Link :href="route('maps.map', map.name)" class="text-sm font-bold text-white hover:text-green-400 transition truncate">
                                    {{ map.name }}
                                </Link>
                                <span class="text-[10px] text-gray-600 shrink-0">{{ map.date_added?.substring(0, 10) }}</span>
                            </div>
                            <div class="flex items-center justify-between mt-1 text-xs text-gray-500">
                                <span>{{ $tc(':count record|:count records', map.record_count, { count: formatNumber(map.record_count) }) }}</span>
                                <span>{{ $tc(':count player|:count players', map.player_count, { count: formatNumber(map.player_count) }) }}</span>
                            </div>
                            <!-- Weapon icons -->
                            <div v-if="map.weapons" class="flex flex-wrap justify-center gap-0.5 mt-1.5">
                                <template v-for="w in (map.weapons || '').split(',').filter(Boolean)" :key="w">
                                    <div :class="`sprite-items sprite-${w.trim()} w-4 h-4`" :title="w.trim()"></div>
                                </template>
                            </div>
                            <!-- WR info - fixed two columns -->
                            <div class="grid grid-cols-2 gap-x-3 mt-1.5">
                                <div class="text-xs">
                                    <span class="text-blue-400 font-bold">VQ3: </span>
                                    <template v-if="map.world_records?.find(wr => wr.physics === 'vq3')">
                                        <span class="text-gray-400" v-html="q3tohtml(map.world_records.find(wr => wr.physics === 'vq3').name)"></span>
                                        <span class="text-gray-500">{{ formatTime(map.world_records.find(wr => wr.physics === 'vq3').time) }}</span>
                                    </template>
                                    <span v-else class="text-gray-600 italic">{{ $t('no records') }}</span>
                                </div>
                                <div class="text-xs">
                                    <span class="text-purple-400 font-bold">CPM: </span>
                                    <template v-if="map.world_records?.find(wr => wr.physics === 'cpm')">
                                        <span class="text-gray-400" v-html="q3tohtml(map.world_records.find(wr => wr.physics === 'cpm').name)"></span>
                                        <span class="text-gray-500">{{ formatTime(map.world_records.find(wr => wr.physics === 'cpm').time) }}</span>
                                    </template>
                                    <span v-else class="text-gray-600 italic">{{ $t('no records') }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- No results -->
                <div v-if="!loadingMaps && maps.data?.length === 0" class="text-center py-8 text-gray-500">
                    {{ $t('No maps found matching filters') }}
                </div>

                <!-- Pagination -->
                <div v-if="maps.last_page > 1" class="flex items-center justify-center gap-2 mt-4">
                    <button v-for="p in maps.last_page" :key="p"
                        @click="changePage(p)"
                        class="px-3 py-1 rounded text-sm font-bold transition"
                        :class="p === maps.current_page ? 'bg-green-600 text-white' : 'bg-black/30 text-gray-400 hover:text-white hover:bg-black/50'">
                        {{ p }}
                    </button>
                </div>
            </div>

            <template v-if="!locked">
            <!-- Hall of Fame + Closest to 100% - VQ3 / CPM -->
            <div v-if="topPlayersData.completionists_by_physics && Object.keys(topPlayersData.completionists_by_physics).length"
                class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <template v-for="physics in ['vq3', 'cpm']" :key="physics">
                    <div v-if="topPlayersData.completionists_by_physics[physics]"
                        class="rounded-xl border overflow-hidden backdrop-blur-sm"
                        :class="physics === 'vq3' ? 'bg-gradient-to-br from-blue-500/5 to-blue-500/10 border-blue-500/20' : 'bg-gradient-to-br from-purple-500/5 to-purple-500/10 border-purple-500/20'">
                        <!-- Hall of Fame (100%) -->
                        <div v-if="topPlayersData.completionists_by_physics[physics]?.completionists?.length" class="p-4 border-b border-white/5">
                            <div class="flex items-center gap-2 mb-2">
                                <svg class="w-4 h-4 text-yellow-400" viewBox="0 0 20 20" fill="currentColor"><use href="/images/svg/icons.svg#icon-trophy"></use></svg>
                                <h3 class="text-xs font-black uppercase tracking-wider" :class="physics === 'vq3' ? 'text-blue-400' : 'text-purple-400'">
                                    {{ $t(':physics Hall of Fame', { physics: physics.toUpperCase() }) }}
                                </h3>
                            </div>
                            <div class="space-y-1.5">
                                <div v-for="player in topPlayersData.completionists_by_physics[physics].completionists" :key="player.mdd_id"
                                    class="flex items-center gap-2 bg-black/20 rounded-lg px-3 py-1.5">
                                    <img v-if="player.country" :src="`/images/flags/${player.country}.png`" onerror="this.src='/images/flags/_404.png'" class="w-5 h-3.5">
                                    <Link :href="route('profile.mdd', player.mdd_id)" class="font-bold text-white hover:text-green-400 transition text-xs truncate" v-html="q3tohtml(player.name)"></Link>
                                    <span class="text-[10px] text-gray-500 ml-auto flex-shrink-0">{{ $t(':count rec', { count: formatNumber(player.total_records) }) }}</span>
                                </div>
                            </div>
                        </div>
                        <div v-else class="p-4 border-b border-white/5">
                            <div class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-gray-600" viewBox="0 0 20 20" fill="currentColor"><use href="/images/svg/icons.svg#icon-trophy"></use></svg>
                                <h3 class="text-xs font-black uppercase tracking-wider text-gray-600">{{ $t(':physics Hall of Fame', { physics: physics.toUpperCase() }) }}</h3>
                                <span class="text-[10px] text-gray-600 ml-auto">{{ $t('No 100% completionists yet') }}</span>
                            </div>
                        </div>
                        <!-- Closest to 100% -->
                        <div v-if="topPlayersData.completionists_by_physics[physics]?.near_completionists?.length" class="p-4">
                            <div class="flex items-center gap-2 mb-2">
                                <h3 class="text-[10px] font-black uppercase tracking-wider text-gray-500">{{ $t(':physics Closest to 100%', { physics: physics.toUpperCase() }) }}</h3>
                            </div>
                            <div class="space-y-1.5">
                                <div v-for="player in topPlayersData.completionists_by_physics[physics].near_completionists" :key="player.mdd_id"
                                    class="flex items-center gap-2">
                                    <img v-if="player.country" :src="`/images/flags/${player.country}.png`" onerror="this.src='/images/flags/_404.png'" class="w-4 h-3 flex-shrink-0">
                                    <Link :href="route('profile.mdd', player.mdd_id)" class="font-bold text-white hover:text-green-400 transition text-[11px] truncate w-24 flex-shrink-0" v-html="q3tohtml(player.name)"></Link>
                                    <div class="flex-1 bg-gray-800/30 rounded-full h-3.5 overflow-hidden">
                                        <div class="h-full rounded-full transition-all"
                                            :class="physics === 'vq3' ? 'bg-gradient-to-r from-blue-600 to-blue-400' : 'bg-gradient-to-r from-purple-600 to-purple-400'"
                                            :style="{ width: `${player.coverage}%` }">
                                        </div>
                                    </div>
                                    <span class="text-[10px] font-bold flex-shrink-0" :class="player.coverage >= 90 ? 'text-green-400' : 'text-gray-500'">{{ player.coverage }}%</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            <!-- Top Players -->
            <div class="bg-black/40 backdrop-blur-sm rounded-xl p-4 border border-white/5 mb-6">
                <h3 class="text-sm font-black text-white uppercase tracking-wider mb-4">{{ $t('Top Players on These Maps') }}</h3>

                <div v-if="loadingTopPlayers" class="flex items-center justify-center py-8">
                    <div class="animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-green-500"></div>
                </div>

                <div v-else>
                    <!-- Table -->
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-xs text-gray-500 uppercase tracking-wider border-b border-white/5">
                                    <th class="text-left py-2 px-2 w-8">#</th>
                                    <th class="text-left py-2 px-2">{{ $t('Player') }}</th>
                                    <th class="text-right py-2 px-2">{{ $t('Records') }}</th>
                                    <th class="text-right py-2 px-2">{{ $t('WRs') }}</th>
                                    <th class="text-right py-2 px-2">{{ $t('Coverage') }}</th>
                                    <th class="text-right py-2 px-2">{{ $t('Avg Rank') }}</th>
                                    <th class="text-left py-2 px-2 hidden md:table-cell">{{ $t('Favorite Map') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(player, idx) in topPlayersData.players" :key="player.mdd_id"
                                    class="border-b border-white/5 hover:bg-white/5 transition">
                                    <td class="py-2 px-2 text-gray-500 font-bold">{{ idx + 1 }}</td>
                                    <td class="py-2 px-2">
                                        <div class="flex items-center gap-2">
                                            <img v-if="player.country" :src="`/images/flags/${player.country}.png`"
                                                onerror="this.src='/images/flags/_404.png'" class="w-5 h-3.5 flex-shrink-0" :alt="player.country">
                                            <Link :href="route('profile.mdd', player.mdd_id)" class="font-bold text-white hover:text-green-400 transition" v-html="q3tohtml(player.name)"></Link>
                                        </div>
                                    </td>
                                    <td class="py-2 px-2 text-right font-bold text-emerald-400">{{ formatNumber(player.record_count) }}</td>
                                    <td class="py-2 px-2 text-right font-bold text-yellow-400">{{ player.wr_count }}</td>
                                    <td class="py-2 px-2 text-right">
                                        <span class="font-bold" :class="player.map_coverage >= 100 ? 'text-green-400' : player.map_coverage >= 75 ? 'text-blue-400' : 'text-gray-400'">
                                            {{ player.map_coverage }}%
                                        </span>
                                    </td>
                                    <td class="py-2 px-2 text-right text-gray-400">{{ player.avg_rank }}</td>
                                    <td class="py-2 px-2 hidden md:table-cell">
                                        <Link v-if="player.favorite_map" :href="route('maps.map', player.favorite_map)" class="text-xs text-blue-400 hover:text-blue-300 transition">
                                            {{ player.favorite_map }}
                                        </Link>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Weapon Breakdown + Timeline side by side -->
            <div class="grid grid-cols-1 lg:grid-cols-5 gap-4 mb-6">
                <!-- Weapon Breakdown (3/5) -->
                <div class="lg:col-span-3 bg-black/40 backdrop-blur-sm rounded-xl border border-white/5 overflow-hidden">
                    <h3 class="text-sm font-black text-white uppercase tracking-wider px-4 pt-3 pb-2">{{ $t('Weapon Breakdown') }}</h3>
                    <table class="w-full text-xs">
                        <thead>
                            <tr class="border-t border-white/5 text-[10px] text-gray-600 uppercase tracking-wider">
                                <th class="text-left py-1 px-3 w-24"></th>
                                <th class="text-left py-1 px-1.5 border-l border-white/5"><span class="text-blue-500">VQ3</span></th>
                                <th class="text-right py-1 px-1.5 w-10"></th>
                                <th class="text-left py-1 px-1.5 border-l border-white/5"><span class="text-purple-500">CPM</span></th>
                                <th class="text-right py-1 px-1.5 w-10"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <template v-for="(data, weapon) in stats.weapon_breakdown" :key="weapon">
                                <tr v-for="(_, rowIdx) in 3" :key="rowIdx"
                                    class="border-t" :class="rowIdx === 0 ? 'border-white/10' : 'border-white/[0.03]'">
                                    <td v-if="rowIdx === 0" class="py-0.5 px-3 align-top" rowspan="3">
                                        <div class="flex items-center gap-1.5 pt-0.5">
                                            <img v-if="weaponLabels[weapon]?.src" :src="weaponLabels[weapon].src" class="w-3.5 h-3.5" :alt="weapon">
                                            <span class="text-xs font-bold text-white">{{ weaponLabels[weapon]?.label || weapon }}</span>
                                            <span class="text-[10px] text-gray-600">{{ data.maps }}</span>
                                        </div>
                                    </td>
                                    <td class="py-0.5 px-1.5 border-l border-white/5">
                                        <div v-if="data.physics?.vq3?.top_players?.[rowIdx]" class="flex items-center gap-1">
                                            <span class="w-2.5 text-right font-bold flex-shrink-0 text-[10px]" :class="rowIdx === 0 ? 'text-yellow-400' : rowIdx === 1 ? 'text-gray-400' : 'text-orange-400'">{{ rowIdx + 1 }}</span>
                                            <Link :href="route('profile.mdd', data.physics.vq3.top_players[rowIdx].mdd_id)" class="font-bold text-white hover:text-green-400 transition truncate text-[11px]" v-html="q3tohtml(data.physics.vq3.top_players[rowIdx].name)"></Link>
                                        </div>
                                    </td>
                                    <td class="py-0.5 px-1.5 text-right text-gray-600 text-[10px]">
                                        <span v-if="data.physics?.vq3?.top_players?.[rowIdx]">{{ data.physics.vq3.top_players[rowIdx].record_count }}</span>
                                    </td>
                                    <td class="py-0.5 px-1.5 border-l border-white/5">
                                        <div v-if="data.physics?.cpm?.top_players?.[rowIdx]" class="flex items-center gap-1">
                                            <span class="w-2.5 text-right font-bold flex-shrink-0 text-[10px]" :class="rowIdx === 0 ? 'text-yellow-400' : rowIdx === 1 ? 'text-gray-400' : 'text-orange-400'">{{ rowIdx + 1 }}</span>
                                            <Link :href="route('profile.mdd', data.physics.cpm.top_players[rowIdx].mdd_id)" class="font-bold text-white hover:text-green-400 transition truncate text-[11px]" v-html="q3tohtml(data.physics.cpm.top_players[rowIdx].name)"></Link>
                                        </div>
                                    </td>
                                    <td class="py-0.5 px-1.5 text-right text-gray-600 text-[10px]">
                                        <span v-if="data.physics?.cpm?.top_players?.[rowIdx]">{{ data.physics.cpm.top_players[rowIdx].record_count }}</span>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <!-- Creation Timeline (2/5) -->
                <div class="lg:col-span-2 bg-black/40 backdrop-blur-sm rounded-xl p-4 border border-white/5">
                    <h3 class="text-sm font-black text-white uppercase tracking-wider mb-3">{{ $t('Maps Timeline') }}</h3>
                    <div v-if="heatmapYears.length > 0" class="space-y-1.5">
                        <div v-for="year in heatmapYears" :key="year" class="flex items-center gap-2">
                            <span class="text-[10px] font-bold text-gray-500 w-8 flex-shrink-0 text-right">{{ year }}</span>
                            <div class="flex-1 bg-gray-800/30 rounded-full h-5 overflow-hidden flex">
                                <div v-if="heatmapData.yearly[year]?.maps"
                                    class="bg-gradient-to-r from-green-600 to-green-500 h-full flex items-center justify-center transition-all duration-500"
                                    :style="{ width: `${Math.max(8, (heatmapData.yearly[year].maps / timelineMax) * 100)}%` }">
                                    <span class="text-[9px] font-black text-white whitespace-nowrap px-1">{{ heatmapData.yearly[year].maps }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-xs text-gray-600 text-center py-4">{{ $t('No timeline data') }}</div>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="bg-black/40 backdrop-blur-sm rounded-xl p-4 border border-white/5 mb-6">
                <h3 class="text-sm font-black text-white uppercase tracking-wider mb-4">{{ $t('Recent Activity on These Maps') }}</h3>

                <div v-if="loadingRecentActivity" class="flex items-center justify-center py-8">
                    <div class="animate-spin rounded-full h-8 w-8 border-t-2 border-b-2 border-green-500"></div>
                </div>

                <div v-else-if="recentActivityData.records?.length" class="space-y-1">
                    <div v-for="record in recentActivityData.records" :key="record.id"
                        class="flex items-center gap-3 py-1.5 px-2 rounded hover:bg-white/5 transition text-sm">
                        <img v-if="record.country" :src="`/images/flags/${record.country}.png`"
                            onerror="this.src='/images/flags/_404.png'" class="w-5 h-3.5 flex-shrink-0">
                        <Link :href="route('profile.mdd', record.mdd_id)" class="font-bold text-white hover:text-green-400 transition truncate max-w-[150px]" v-html="q3tohtml(record.name)"></Link>
                        <span class="text-gray-500">{{ $t('on') }}</span>
                        <Link :href="route('maps.map', record.mapname)" class="font-bold text-blue-400 hover:text-blue-300 transition truncate max-w-[200px]">{{ record.mapname }}</Link>
                        <span class="text-yellow-400 font-bold ml-auto flex-shrink-0">{{ formatTime(record.time) }}</span>
                        <span class="text-xs font-bold flex-shrink-0" :class="record.rank === 1 ? 'text-yellow-400' : record.rank <= 3 ? 'text-orange-400' : 'text-gray-500'">
                            #{{ record.rank }}
                        </span>
                        <span :class="record.physics === 'vq3' ? 'text-blue-400' : 'text-purple-400'" class="text-xs font-black flex-shrink-0">{{ record.physics.toUpperCase() }}</span>
                        <span class="text-xs text-gray-600 flex-shrink-0 hidden md:inline">{{ record.date_set?.substring(0, 10) }}</span>
                    </div>
                </div>

                <div v-else class="text-center py-6 text-gray-500 text-sm">{{ $t('No recent records') }}</div>
            </div>
            </template>
            <template v-else>
            <div class="bg-black/40 backdrop-blur-sm rounded-xl p-4 border border-white/5 mb-6 relative">
                <LockedOverlay />
                <h3 class="text-sm font-black text-white uppercase tracking-wider mb-4">{{ $t('Top Players on These Maps') }}</h3>
                <div class="space-y-2">
                    <div v-for="k in 5" :key="k" class="flex items-center justify-between text-sm text-gray-400">
                        <span>{{ '·'.repeat(8 + (k % 3) * 3) }}</span><span>···</span>
                    </div>
                </div>
            </div>
            <div class="bg-black/40 backdrop-blur-sm rounded-xl p-4 border border-white/5 mb-6 relative">
                <LockedOverlay />
                <h3 class="text-sm font-black text-white uppercase tracking-wider mb-4">{{ $t('Recent Activity on These Maps') }}</h3>
                <div class="space-y-2">
                    <div v-for="k in 5" :key="k" class="flex items-center justify-between text-sm text-gray-400">
                        <span>{{ '·'.repeat(8 + (k % 3) * 3) }}</span><span>···</span>
                    </div>
                </div>
            </div>
            </template>
        </div>
    </div>
</template>
