<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { router } from '@inertiajs/vue3';
import { defineAsyncComponent, onMounted, onUnmounted, ref, computed, watch } from 'vue';
import OnlinePlayer from '@/Components/OnlinePlayer.vue';
import CopyButton from '@/Components/Basic/CopyButton.vue';
import LauncherBanner from '@/Components/LauncherBanner.vue';
import CheatsBanner from '@/Components/CheatsBanner.vue';
import PromoRail from '@/Components/PromoRail.vue';
import FavoriteStar from '@/Components/FavoriteStar.vue';
import { streamersOn, twitchChannel, twitchUrl, plainName } from '@/utils/twitch';
import { t } from '@/utils/i18n';
import { getWeaponIcon, getWeaponName, getItemIcon, getItemName, getFunctionIcon, getFunctionName } from '@/utils/gameItems';
const AddToMaplistModal = defineAsyncComponent(() => import('@/Components/Maplists/AddToMaplistModal.vue'));

const page = usePage();
const showMaplistModal = ref(false);
const selectedMapId = ref(null);

const props = defineProps({
    servers: {
        type: Array,
        default: () => []
    },
    // Ids the signed-in user starred. Sent once with the page, then kept
    // here - the list itself reloads every 30 seconds and would otherwise
    // undo a click whose request is still in flight.
    favoriteServers: {
        type: Array,
        default: () => []
    },
});

const favorites = ref(new Set(props.favoriteServers ?? []));
const isFavorite = (server) => favorites.value.has(server.id);

// Starring is per account, so it follows the person to every machine they
// play from. Signed out the star still shows - it is how you find out the
// feature exists - and asks for a login when clicked.
const toggleFavorite = async (server) => {
    if (! page.props.auth.user) {
        router.visit('/login');
        return;
    }

    const wasFavorite = favorites.value.has(server.id);

    // Optimistic: the star answers the click, not the round trip.
    const next = new Set(favorites.value);
    wasFavorite ? next.delete(server.id) : next.add(server.id);
    favorites.value = next;

    try {
        await (wasFavorite
            ? window.axios.delete(`/servers/${server.id}/favorite`)
            : window.axios.post(`/servers/${server.id}/favorite`));
    } catch (error) {
        // Put it back where it was, so the page never claims something
        // the account does not actually hold.
        const reverted = new Set(favorites.value);
        wasFavorite ? reverted.add(server.id) : reverted.delete(server.id);
        favorites.value = reverted;

        const message = error?.response?.data?.message;
        if (message) {
            alert(message);
        }
    }
};

const localServers = ref([]);
const serversLoading = ref(true);
const serversLoaded = computed(() => localServers.value.length > 0);

const hoveredMapServer = ref(null);

const players = ref(0);
const interval = ref(null);
const isRotating = ref(false);

// Colour for the estimated-ping badge: green good, amber okay, red far.
const pingClass = (ms) => ms < 50
    ? 'bg-green-500/25 border-green-400/50 text-green-300'
    : ms < 100
        ? 'bg-yellow-500/25 border-yellow-400/50 text-yellow-200'
        : 'bg-red-500/25 border-red-400/50 text-red-300';
const pingTitle = computed(() => t('Estimated ping from your location (approximate - excludes your local connection)'));

const formatRecordDate = (value) => {
    if (!value) return '';
    const d = new Date(value);
    if (isNaN(d.getTime())) return '';
    const dd = String(d.getDate()).padStart(2, '0');
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const yy = String(d.getFullYear()).slice(-2);
    const yyyy = String(d.getFullYear());
    const fmt = page.props.dateFormat;
    if (fmt === 'dmY') return `${dd}/${mm}/${yyyy}`;
    if (fmt === 'Ymd') return `${yyyy}/${mm}/${dd}`;
    if (fmt === 'dmy') return `${dd}/${mm}/${yy}`;
    return `${yy}/${mm}/${dd}`;
};

// Three ways to read this page, kept in a cookie for a year.
//
// `large` is called that in the cookie and Modern on screen: it was the only
// layout for a long time and renaming the stored value would have reset the
// choice for everyone still carrying the old one.
const LAYOUTS = ['large', 'compact', 'oldschool'];

const getLayoutFromCookie = () => {
    const cookies = document.cookie.split(';');
    const layoutCookie = cookies.find(c => c.trim().startsWith('servers_layout='));
    const stored = layoutCookie ? layoutCookie.split('=')[1] : null;

    return LAYOUTS.includes(stored) ? stored : 'large';
};

const layout = ref(getLayoutFromCookie());

// Filters - restore from localStorage
const savedFilters = JSON.parse(localStorage.getItem('servers_filters') || '{}');
const filters = ref({
    gametype: 'all', // all, run, ctf, freestyle, teamrun
    physics: 'all', // all, cpm, vq3
    hideEmpty: savedFilters.hideEmpty || false,
    showDetails: savedFilters.showDetails !== undefined ? savedFilters.showDetails : true,
    favoritesOnly: savedFilters.favoritesOnly || false,
});

// Persist filter changes to localStorage
watch(() => [filters.value.hideEmpty, filters.value.showDetails, filters.value.favoritesOnly], () => {
    localStorage.setItem('servers_filters', JSON.stringify({
        hideEmpty: filters.value.hideEmpty,
        showDetails: filters.value.showDetails,
        favoritesOnly: filters.value.favoritesOnly,
    }));
});

const sorting = ref('popularity');
const sortingOrder = ref('desc'); // 'asc' or 'desc'

const startInterval = () => {
    if (interval.value == null) {
        interval.value = setInterval(updatePage, 30000);
    }
}

const stopInterval = () => {
    clearInterval(interval.value);
    interval.value = null;
}

const updatePage = () => {
    if (isRotating.value) {
        return;
    }

    isRotating.value = true;
    fetchServers()

    setTimeout(() => {
        isRotating.value = false;
    }, 1500);
}

const countPlayers = () => {
    players.value = 0
    localServers.value.forEach(element => {
        players.value += element.online_players.length
    });
}

const fetchServers = async () => {
    serversLoading.value = true;
    try {
        const response = await fetch('/api/servers/live');
        if (response.ok) {
            localServers.value = await response.json();
            countPlayers();
        }
    } catch (error) {
        console.error('Failed to load servers:', error);
    } finally {
        serversLoading.value = false;
    }
}

onMounted(() => {
    fetchServers()
    startInterval()
})

onUnmounted(() => {
    stopInterval()
})

const getServerName = (name) => {
    const colorRegex = /\^\d|\^x[\da-fA-F]{2}|\^[\da-fA-F]{6}/g;
    return name.replace(colorRegex, '');
}

const setLayout = (value) => {
    layout.value = value;
    // Save to cookie (expires in 1 year)
    const expires = new Date();
    expires.setFullYear(expires.getFullYear() + 1);
    document.cookie = `servers_layout=${layout.value}; expires=${expires.toUTCString()}; path=/`;
}

const openAddToMaplist = (mapId) => {
    selectedMapId.value = mapId;
    showMaplistModal.value = true;
}

const toggleSort = (type) => {
    if (sorting.value === type) {
        sortingOrder.value = sortingOrder.value === 'desc' ? 'asc' : 'desc';
    } else {
        sorting.value = type;
        sortingOrder.value = 'desc';
    }
}

/**
 * Which tab a server belongs under. Pulled out of the filter so the cheats
 * warning can ask the same question - freestyle answers it differently and
 * there must not be two ideas of what freestyle means.
 */
const effectiveGametype = (server) => {
    const serverType = server.type?.toLowerCase() || 'run';
    const serverName = (server.name || '').replace(/\^\d|\^x[\da-fA-F]{2}|\^[\da-fA-F]{6}/g, '').toLowerCase();

    let effectiveType = serverType;
    if (effectiveType === 'teamruns') effectiveType = 'team';
    if (effectiveType === 'cpm' || effectiveType === 'vq3' || effectiveType === 'mixed') effectiveType = 'run';

    if (effectiveType === 'run') {
        if (serverName.includes('fastcap') || serverName.includes('ctf')) effectiveType = 'fastcaps';
        else if (serverName.includes('freestyle')) effectiveType = 'freestyle';
        else if (serverName.includes('teamrun') || serverName.includes('team run')) effectiveType = 'team';
    }

    return effectiveType;
};

/**
 * On a freestyle server sv_cheats is the point rather than a problem: nothing
 * there is a timed result, so there is nothing for it to invalidate. The
 * warning still shows, because it is true and someone should be able to see
 * it, but quietly - a full red bar there is crying wolf, and a warning that
 * fires where it does not matter is one people stop reading where it does.
 */
const cheatsAreExpected = (server) => effectiveGametype(server) === 'freestyle';

// Everybody streaming right now, whatever the filters hide. Feeds the bar
// above the list: a stream on a server you filtered out is still a stream.
const liveNow = computed(() => {
    const out = [];

    for (const server of localServers.value ?? []) {
        for (const player of streamersOn(server)) {
            out.push({ player, server });
        }
    }

    return out;
});

const filteredAndSortedServers = computed(() => {
    if (!localServers.value || localServers.value.length === 0) return [];
    let result = [...localServers.value];

    // Filter by gametype (using 'type' field + fallback to server name detection)
    if (filters.value.gametype !== 'all') {
        result = result.filter(server => {
            const serverType = server.type?.toLowerCase() || 'run';
            const serverName = (server.name || '').replace(/\^\d|\^x[\da-fA-F]{2}|\^[\da-fA-F]{6}/g, '').toLowerCase();

            // A mixed server advertises a run leaderboard alongside
            // whatever else it does, so it belongs in the run tab too.
            //
            // defrag_gametype used to be read as that signal, on the
            // basis that 5 means "run + teamrun at once". It is 5 on
            // every single server we know of - 76 of 76 in production -
            // so it said nothing, and being OR'd into both the run and
            // teamrun cases it made those two tabs return the entire
            // list. The name keyword and type='mixed' are the signals
            // admins actually set.
            const isMixed = serverType === 'mixed' || serverName.includes('mixed');

            // Detect effective type: DB type first, then name-based detection.
            // The type column is not a clean vocabulary - it carries
            // 'teamruns' as well as 'team', and physics values (cpm, vq3)
            // where a gametype belongs - so it is normalised first. Without
            // that, the twelve servers typed by physics matched no tab at
            // all and the two 'teamruns' ones were invisible to the team tab.
            const effectiveType = effectiveGametype(server);

            switch (filters.value.gametype) {
                case 'run':
                    return effectiveType === 'run' || isMixed;
                case 'ctf':
                    return effectiveType === 'ctf' || effectiveType === 'fastcaps';
                case 'freestyle':
                    return effectiveType === 'freestyle';
                // Not isMixed: a teamrun can be voted on any mixed server,
                // but someone filtering here wants the servers set up for
                // them, not the forty run servers where it is possible.
                case 'teamrun':
                    return effectiveType === 'teamrun' || effectiveType === 'team';
                default:
                    return true;
            }
        });
    }

    // Filter by physics
    if (filters.value.physics !== 'all') {
        result = result.filter(server => {
            if (filters.value.physics === 'cpm') {
                return server.defrag.toLowerCase().includes('cpm');
            } else if (filters.value.physics === 'vq3') {
                return !server.defrag.toLowerCase().includes('cpm');
            }
            return true;
        });
    }

    // Only the starred ones
    if (filters.value.favoritesOnly) {
        result = result.filter(server => isFavorite(server));
    }

    // Hide empty servers. A starred server stays: the empty one you are
    // about to join is the whole reason somebody starred it.
    if (filters.value.hideEmpty) {
        result = result.filter(server => server.online_players.length > 0 || isFavorite(server));
    }


    // Sort
    result.sort((a, b) => {
        // Starred servers sit above the rest whatever the sort is, and
        // sort among themselves by the same rule as everything else.
        const starred = (isFavorite(b) ? 1 : 0) - (isFavorite(a) ? 1 : 0);
        if (starred !== 0) {
            return starred;
        }

        // Then anything being streamed. A stream is the one thing on this
        // page you can join without launching the game.
        const streamed = (streamersOn(b).length ? 1 : 0) - (streamersOn(a).length ? 1 : 0);
        if (streamed !== 0) {
            return streamed;
        }

        let comparison = 0;

        if (sorting.value === 'popularity') {
            comparison = b.online_players.length - a.online_players.length;
        } else if (sorting.value === 'alphabetically') {
            const nameA = getServerName(a.name).toLowerCase();
            const nameB = getServerName(b.name).toLowerCase();
            comparison = nameA.localeCompare(nameB);
        }

        return sortingOrder.value === 'desc' ? comparison : -comparison;
    });

    return result;
});

const serverCount = computed(() => filteredAndSortedServers.value.length);

</script>

<template>
    <div class="">
        <Head :title="$t('Servers')" />

        <!-- Whatever is running for money right now. This page is where people
             are while deciding what to play, so it is the one place a weekly
             map or a watch contest reaches somebody who was not already going
             to look for it. -->
        <PromoRail />

        <!-- Header Section -->
        <div class="relative bg-gradient-to-b from-black/25 via-black/10 to-transparent pt-6 pb-96 pointer-events-none">
            <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8 pointer-events-auto">
                <div class="flex justify-between items-center flex-wrap gap-4">
                    <h1 class="text-2xl md:text-3xl font-black text-gray-300/90">
                        {{ $t('Live Servers') }}
                    </h1>

                    <Link :href="route('launcher')"
                          class="flex items-center gap-2 bg-gradient-to-r from-blue-600/30 to-blue-500/15 hover:from-blue-600/40 hover:to-blue-500/25 backdrop-blur-sm px-3 py-2 rounded-lg border border-blue-400/40 hover:border-blue-300/60 transition-colors text-sm">
                        <svg class="w-5 h-5 text-blue-300 flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                            <polyline points="7 10 12 15 17 10"/>
                            <line x1="12" y1="15" x2="12" y2="3"/>
                        </svg>
                        <span class="font-bold text-white whitespace-nowrap">{{ $t('Get the launcher') }}</span>
                        <span class="hidden lg:inline text-blue-200/80 font-semibold text-xs">{{ $t('connect in one click, and more') }}</span>
                    </Link>

                    <!-- The rules belong where people are about to join a
                         server, not only in the footer. Every finished run on
                         these servers is recorded and may be reviewed, and
                         somebody should not learn that after the fact. -->
                    <Link :href="route('rules')"
                          class="flex items-center gap-2 bg-gradient-to-r from-amber-600/25 to-amber-500/10 hover:from-amber-600/35 hover:to-amber-500/20 backdrop-blur-sm px-3 py-2 rounded-lg border border-amber-400/40 hover:border-amber-300/60 transition-colors text-sm">
                        <svg class="w-5 h-5 text-amber-300 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v17.25m0 0c-1.472 0-2.882.265-4.185.75M12 20.25c1.472 0 2.882.265 4.185.75M18.75 4.97A48.416 48.416 0 0 0 12 4.5c-2.291 0-4.545.16-6.75.47m13.5 0c1.01.143 2.01.317 3 .52m-3-.52 2.62 10.726c.122.499-.106 1.028-.589 1.202a5.988 5.988 0 0 1-2.031.352 5.988 5.988 0 0 1-2.031-.352c-.483-.174-.711-.703-.59-1.202L18.75 4.971Zm-16.5.52c.99-.203 1.99-.377 3-.52m0 0 2.62 10.726c.122.499-.106 1.028-.589 1.202a5.989 5.989 0 0 1-2.031.352 5.989 5.989 0 0 1-2.031-.352c-.483-.174-.711-.703-.59-1.202L5.25 4.971Z" />
                        </svg>
                        <span class="font-bold text-white whitespace-nowrap">{{ $t('Rules') }}</span>
                        <span class="hidden lg:inline text-amber-200/80 font-semibold text-xs">{{ $t('read before you play') }}</span>
                    </Link>

                    <!-- text-xs, matching the filter row below. At text-sm
                         these two pills alone were ~50px wider, which is what
                         pushed the Polish and Russian header onto a second
                         line while English still fit. -->
                    <div class="flex items-center gap-2 text-xs">
                        <div class="flex items-center gap-2 bg-black/40 backdrop-blur-sm px-3 py-2 rounded-lg border border-blue-400/30">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-4 h-4 text-blue-400">
                                <path d="M4.5 6.375a4.125 4.125 0 1 1 8.25 0 4.125 4.125 0 0 1-8.25 0ZM14.25 8.625a3.375 3.375 0 1 1 6.75 0 3.375 3.375 0 0 1-6.75 0ZM1.5 19.125a7.125 7.125 0 0 1 14.25 0v.003l-.001.119a.75.75 0 0 1-.363.63 13.067 13.067 0 0 1-6.761 1.873c-2.472 0-4.786-.684-6.76-1.873a.75.75 0 0 1-.364-.63l-.001-.122ZM17.25 19.128l-.001.144a2.25 2.25 0 0 1-.233.96 10.088 10.088 0 0 0 5.06-1.01.75.75 0 0 0 .42-.643 4.875 4.875 0 0 0-6.957-4.611 8.586 8.586 0 0 1 1.71 5.157v.003Z" />
                            </svg>
                            <span class="font-bold text-blue-300">{{ players }}</span>
                            <span class="text-gray-300 font-semibold">{{ $t('Players Online') }}</span>
                        </div>
                        <div class="flex items-center gap-2 bg-black/40 backdrop-blur-sm px-3 py-2 rounded-lg border border-white/10">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-4 h-4 text-gray-300">
                                <path d="M4.5 3.75a3 3 0 0 0-3 3v.75h21v-.75a3 3 0 0 0-3-3h-15Z" />
                                <path fill-rule="evenodd" d="M22.5 9.75h-21v7.5a3 3 0 0 0 3 3h15a3 3 0 0 0 3-3v-7.5Zm-18 3.75a.75.75 0 0 1 .75-.75h6a.75.75 0 0 1 0 1.5h-6a.75.75 0 0 1-.75-.75Zm.75 2.25a.75.75 0 0 0 0 1.5h3a.75.75 0 0 0 0-1.5h-3Z" clip-rule="evenodd" />
                            </svg>
                            <span class="font-bold text-white">{{ serverCount }}</span>
                            <span class="text-gray-300 font-semibold">{{ $t('Active Servers') }}</span>
                        </div>

                    </div>
                </div>

                <!-- Filters & Controls -->
                <div class="mt-6">
                    <div class="bg-black/40 backdrop-blur-sm rounded-2xl border border-white/5 p-4 shadow-2xl">
                <!-- Four segmented pills rather than sixteen loose ones.
                     The old row spent roughly 150px on gaps and per-button
                     borders, which English could afford and Polish and
                     Russian could not - their labels are a third longer and
                     the row broke onto a second line. Each group is now one
                     bordered pill with its label as a leading chip, and the
                     groups are told apart by hue instead of by whitespace. -->
                <!-- Two halves, not one run of four: what filters the list
                     sits on the left, what only changes how it is shown sits
                     against the right edge. It also gives a long language
                     somewhere to grow - the gap in the middle absorbs it
                     before anything has to wrap. -->
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <!-- Gametype Filter -->
                        <div class="flex flex-wrap items-stretch rounded-lg border border-sky-400/25 bg-sky-500/[0.07] overflow-hidden">
                            <span class="flex items-center px-2.5 py-1.5 bg-sky-500/10 text-[11px] font-bold text-sky-300/80 uppercase whitespace-nowrap">{{ $t('Gametype:') }}</span>
                            <button @click="filters.gametype = 'all'" :class="filters.gametype === 'all' ? 'bg-sky-500/35 text-white' : 'text-gray-400 hover:bg-white/5'" class="px-2 py-1.5 border-l border-sky-400/20 text-xs font-bold transition-colors whitespace-nowrap">
                                {{ $t('All') }}
                            </button>
                            <button @click="filters.gametype = 'run'" :class="filters.gametype === 'run' ? 'bg-sky-500/35 text-white' : 'text-gray-400 hover:bg-white/5'" class="px-2 py-1.5 border-l border-sky-400/20 text-xs font-bold transition-colors whitespace-nowrap">
                                Run
                            </button>
                            <button @click="filters.gametype = 'ctf'" :class="filters.gametype === 'ctf' ? 'bg-sky-500/35 text-white' : 'text-gray-400 hover:bg-white/5'" class="px-2 py-1.5 border-l border-sky-400/20 text-xs font-bold transition-colors whitespace-nowrap">
                                CTF
                            </button>
                            <button @click="filters.gametype = 'freestyle'" :class="filters.gametype === 'freestyle' ? 'bg-sky-500/35 text-white' : 'text-gray-400 hover:bg-white/5'" class="px-2 py-1.5 border-l border-sky-400/20 text-xs font-bold transition-colors whitespace-nowrap">
                                Freestyle
                            </button>
                            <button @click="filters.gametype = 'teamrun'" :class="filters.gametype === 'teamrun' ? 'bg-sky-500/35 text-white' : 'text-gray-400 hover:bg-white/5'" class="px-2 py-1.5 border-l border-sky-400/20 text-xs font-bold transition-colors whitespace-nowrap">
                                Teamrun
                            </button>
                        </div>

                        <!-- Physics Filter -->
                        <div class="flex flex-wrap items-stretch rounded-lg border border-violet-400/25 bg-violet-500/[0.07] overflow-hidden">
                            <span class="flex items-center px-2.5 py-1.5 bg-violet-500/10 text-[11px] font-bold text-violet-300/80 uppercase whitespace-nowrap">{{ $t('Physics:') }}</span>
                            <button @click="filters.physics = 'all'" :class="filters.physics === 'all' ? 'bg-violet-500/35 text-white' : 'text-gray-400 hover:bg-white/5'" class="px-2 py-1.5 border-l border-violet-400/20 text-xs font-bold transition-colors whitespace-nowrap">
                                {{ $t('All') }}
                            </button>
                            <button @click="filters.physics = 'cpm'" :class="filters.physics === 'cpm' ? 'bg-violet-500/35 text-white' : 'text-gray-400 hover:bg-white/5'" class="px-2 py-1.5 border-l border-violet-400/20 text-xs font-bold transition-colors whitespace-nowrap">
                                CPM
                            </button>
                            <button @click="filters.physics = 'vq3'" :class="filters.physics === 'vq3' ? 'bg-violet-500/35 text-white' : 'text-gray-400 hover:bg-white/5'" class="px-2 py-1.5 border-l border-violet-400/20 text-xs font-bold transition-colors whitespace-nowrap">
                                VQ3
                            </button>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <!-- Starred servers. Its own switch rather than a
                             third button under "Hide", because it is the one
                             filter somebody flips several times in a sitting
                             and it names what it shows, not what it drops. -->
                        <button
                            @click="filters.favoritesOnly = !filters.favoritesOnly"
                            :class="filters.favoritesOnly ? 'bg-amber-500/40 text-white border-amber-400/50' : 'bg-amber-500/[0.07] text-gray-400 hover:bg-white/5 border-amber-400/25'"
                            class="flex items-center gap-1.5 rounded-lg border px-2.5 py-1.5 text-xs font-bold transition-colors whitespace-nowrap"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" :fill="filters.favoritesOnly ? 'currentColor' : 'none'" stroke="currentColor" stroke-width="2" class="w-4 h-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.5a.56.56 0 0 1 1.04 0l2.13 5.11c.09.2.28.35.5.36l5.52.44c.5.04.7.67.32 1l-4.2 3.6a.56.56 0 0 0-.19.59l1.28 5.39c.12.49-.41.88-.84.62l-4.73-2.89a.56.56 0 0 0-.58 0l-4.73 2.89c-.43.26-.96-.13-.84-.62l1.28-5.39a.56.56 0 0 0-.19-.59l-4.2-3.6c-.38-.33-.18-.96.32-1l5.52-.44c.22-.01.41-.16.5-.36l2.13-5.11Z" />
                            </svg>
                            {{ $t('Favorites') }}
                            <span v-if="favorites.size" class="px-1.5 rounded bg-black/30 text-amber-200">{{ favorites.size }}</span>
                        </button>

                        <!-- Sort Options -->
                        <div class="flex flex-wrap items-stretch rounded-lg border border-emerald-400/25 bg-emerald-500/[0.07] overflow-hidden">
                            <span class="flex items-center px-2.5 py-1.5 bg-emerald-500/10 text-[11px] font-bold text-emerald-300/80 uppercase whitespace-nowrap">{{ $t('Sort:') }}</span>
                            <button @click="toggleSort('popularity')" :class="sorting === 'popularity' ? 'bg-emerald-500/35 text-white' : 'text-gray-400 hover:bg-white/5'" class="px-2 py-1.5 border-l border-emerald-400/20 text-xs font-bold transition-colors whitespace-nowrap flex items-center gap-1">
                                {{ $t('Popularity') }}
                                <svg v-if="sorting === 'popularity'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" :class="sortingOrder === 'desc' ? 'rotate-0' : 'rotate-180'" class="w-3 h-3 transition-transform">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                </svg>
                            </button>
                            <button @click="toggleSort('alphabetically')" :class="sorting === 'alphabetically' ? 'bg-emerald-500/35 text-white' : 'text-gray-400 hover:bg-white/5'" class="px-2 py-1.5 border-l border-emerald-400/20 text-xs font-bold transition-colors whitespace-nowrap flex items-center gap-1">
                                A-Z
                                <svg v-if="sorting === 'alphabetically'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" :class="sortingOrder === 'desc' ? 'rotate-0' : 'rotate-180'" class="w-3 h-3 transition-transform">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                </svg>
                            </button>
                        </div>

                        <!-- What to leave out. The word "Hide" was on both
                             buttons and is now said once, by the chip that
                             governs them - the buttons name the thing, not the
                             verb. These are toggles rather than a single
                             choice, so the active fill is solid and not a
                             tint like the groups that pick one of several. -->
                        <div class="flex flex-wrap items-stretch rounded-lg border border-amber-400/25 bg-amber-500/[0.07] overflow-hidden">
                            <span class="flex items-center px-2.5 py-1.5 bg-amber-500/10 text-[11px] font-bold text-amber-300/80 uppercase whitespace-nowrap">{{ $t('Hide:') }}</span>
                            <button @click="filters.hideEmpty = !filters.hideEmpty" :class="filters.hideEmpty ? 'bg-amber-500/40 text-white' : 'text-gray-400 hover:bg-white/5'" class="px-2 py-1.5 border-l border-amber-400/20 text-xs font-bold transition-colors whitespace-nowrap">
                                {{ $t('Empty') }}
                            </button>
                            <button @click="filters.showDetails = !filters.showDetails" :class="!filters.showDetails ? 'bg-amber-500/40 text-white' : 'text-gray-400 hover:bg-white/5'" class="px-2 py-1.5 border-l border-amber-400/20 text-xs font-bold transition-colors whitespace-nowrap">
                                {{ $t('Details') }}
                            </button>
                        </div>

                        <!-- Layout is a choice of one, not a switch, so it is
                             its own group rather than a third toggle sitting
                             among things it has nothing to do with. -->
                        <div class="flex flex-wrap items-stretch rounded-lg border border-rose-400/25 bg-rose-500/[0.07] overflow-hidden">
                            <span class="flex items-center px-2.5 py-1.5 bg-rose-500/10 text-[11px] font-bold text-rose-300/80 uppercase whitespace-nowrap">{{ $t('View:') }}</span>
                            <button @click="setLayout('large')" :class="layout === 'large' ? 'bg-rose-500/35 text-white' : 'text-gray-400 hover:bg-white/5'" class="px-2 py-1.5 border-l border-rose-400/20 text-xs font-bold transition-colors whitespace-nowrap">
                                {{ $t('Modern') }}
                            </button>
                            <button @click="setLayout('compact')" :class="layout === 'compact' ? 'bg-rose-500/35 text-white' : 'text-gray-400 hover:bg-white/5'" class="px-2 py-1.5 border-l border-rose-400/20 text-xs font-bold transition-colors whitespace-nowrap">
                                {{ $t('Compact') }}
                            </button>
                            <button @click="setLayout('oldschool')" :class="layout === 'oldschool' ? 'bg-rose-500/35 text-white' : 'text-gray-400 hover:bg-white/5'" class="px-2 py-1.5 border-l border-rose-400/20 text-xs font-bold transition-colors whitespace-nowrap">
                                {{ $t('Oldschool') }}
                            </button>
                        </div>
                    </div>
                </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Servers Grid/List -->
        <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8 pb-12" style="margin-top: -23rem;">
            <LauncherBanner variant="servers" />

            <!-- Who is streaming, one line each, above everything. Only
                 while somebody is: an empty "nobody live" strip would be
                 the page apologising for itself. -->
            <div v-if="liveNow.length" class="mb-6 space-y-2">
                <a v-for="{ player, server } in liveNow" :key="player.id"
                   :href="twitchUrl(twitchChannel(player.profile))" target="_blank" rel="noopener noreferrer"
                   class="flex flex-wrap items-center gap-x-3 gap-y-1 rounded-xl border border-purple-400/40 bg-gradient-to-r from-purple-600/30 via-purple-600/15 to-black/40 backdrop-blur-sm px-4 py-2.5 shadow-[0_0_30px_-10px_rgba(168,85,247,0.6)] hover:border-purple-300/60 hover:from-purple-600/40 transition-colors">
                    <span class="inline-flex items-center gap-1.5 rounded-md bg-red-600 px-2 py-0.5 text-[10px] font-black uppercase tracking-wider text-white">
                        <span class="relative inline-flex w-1.5 h-1.5">
                            <span class="live-dot-pulse absolute inline-flex w-full h-full rounded-full bg-white opacity-75"></span>
                            <span class="relative inline-flex w-1.5 h-1.5 rounded-full bg-white"></span>
                        </span>
                        {{ $t('LIVE') }}
                    </span>
                    <span class="text-sm text-purple-100">
                        {{ $t(':name is streaming on :server', { name: plainName(player.name), server: getServerName(server.name) }) }}
                    </span>
                    <span v-if="server.map" class="text-xs text-purple-200/60">{{ server.map }}</span>
                    <span class="ml-auto inline-flex items-center gap-1.5 text-xs font-bold text-white">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M11.571 4.714h1.715v5.143H11.57zm4.715 0H18v5.143h-1.714zM6 0L1.714 4.286v15.428h5.143V24l4.286-4.286h3.428L22.286 12V0zm14.571 11.143l-3.428 3.428h-3.429l-3 3v-3H6.857V1.714h13.714z" /></svg>
                        {{ $t('Watch on Twitch') }}
                    </span>
                </a>
            </div>

            <!-- Loading skeleton while deferred data loads -->
            <div v-if="!serversLoaded" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div v-for="i in 6" :key="i" class="bg-black/40 backdrop-blur-sm rounded-2xl border border-white/10 overflow-hidden animate-pulse">
                    <div class="h-48 bg-white/5"></div>
                    <div class="p-4 space-y-3">
                        <div class="h-5 bg-white/10 rounded w-3/4"></div>
                        <div class="h-4 bg-white/5 rounded w-1/2"></div>
                        <div class="h-4 bg-white/5 rounded w-2/3"></div>
                    </div>
                </div>
            </div>

            <!-- Large Card Layout -->
            <div v-else-if="layout === 'large'" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div v-for="server in filteredAndSortedServers" :key="server.id" :class="['group relative cursor-default bg-black/40 backdrop-blur-sm rounded-2xl border transition-all duration-300 hover:shadow-2xl overflow-hidden player-list-hover-group', server.cheats && !cheatsAreExpected(server) ? 'border-red-500/25 hover:border-red-400/45 hover:shadow-red-500/10' : 'border-white/10 hover:border-white/20 hover:shadow-blue-500/20', isFavorite(server) ? 'ring-1 ring-amber-400/50' : '']">
                    <CheatsBanner :cheats="server.cheats" :subdued="cheatsAreExpected(server)" />
                    <!-- Background Image - FIXED SIZE, never changes, keeps aspect ratio -->
                    <div class="absolute top-0 left-0 right-0 h-[450px] rounded-t-2xl pointer-events-none">
                        <div class="relative inline-block w-full">
                            <!-- Soft mask on the image itself: bottom
                                 ~30% of the thumbnail fades to
                                 transparent so the image dissolves
                                 directly into the glass card body.
                                 No separate overlay slab needed. -->
                            <a v-if="server.map" :href="`/maps/${encodeURIComponent(server.map)}`" class="block">
                                <img :src="`/storage/${server.mapdata?.thumbnail}`" @error="$event.target.src='/images/unknown.jpg'" class="w-full object-contain object-top pointer-events-auto cursor-pointer" style="max-height: 450px; -webkit-mask-image: linear-gradient(to bottom, black 65%, transparent 100%); mask-image: linear-gradient(to bottom, black 65%, transparent 100%);" />
                            </a>
                            <img v-else :src="`/storage/${server.mapdata?.thumbnail}`" @error="$event.target.src='/images/unknown.jpg'" class="w-full object-contain object-top" style="max-height: 450px; -webkit-mask-image: linear-gradient(to bottom, black 65%, transparent 100%); mask-image: linear-gradient(to bottom, black 65%, transparent 100%);" />
                            <!-- Hover state: shrunken bottom fade kept
                                 because the hover overlay below the
                                 image needs a visible seam. -->
                            <div :class="['absolute inset-x-0 bottom-0 bg-gradient-to-t to-transparent transition-all', hoveredMapServer === server.id ? 'h-6 from-gray-950/90 opacity-100' : 'h-0 opacity-0']"></div>
                            <!-- Solid dark below the image (only when
                                 the user hovers the map row to reveal
                                 the metadata overlay) -->
                            <div :class="['absolute inset-x-0 top-full w-full bg-gray-950/90', hoveredMapServer === server.id ? 'h-[300px]' : 'h-0']" style="transition: height 0.3s ease;"></div>
                            <!-- Below the image: no `from-black`
                                 gradient. Card body's own glass (the
                                 outer wrapper has bg-black/40
                                 backdrop-blur-sm) handles readability;
                                 the previous solid-black-into-glass
                                 ramp produced a visible dark stripe
                                 between the thumbnail and the player
                                 list. Keep this div for the hover
                                 transition but make it transparent
                                 in the default state. -->
                            <div :class="['absolute inset-x-0 top-full w-full bg-gradient-to-b from-black via-black/60 to-transparent', hoveredMapServer === server.id ? 'h-0 opacity-0' : 'h-0 opacity-0']"></div>
                        </div>
                    </div>

                    <div class="relative p-6 flex flex-col h-full">
                        <!-- Server Info Box -->
                        <div :class="['mb-4 p-4 rounded-lg border transition-all duration-300', hoveredMapServer === server.id ? 'bg-transparent border-transparent' : 'bg-black/40 border-white/20']">
                            <!-- Server Name with Flag and Copy IP - Left Aligned -->
                            <div :class="['flex items-center gap-2 mb-3 map-hover-fade', hoveredMapServer === server.id ? 'opacity-0 pointer-events-none' : 'opacity-100']">
                                <img :src="`/images/flags/${server.location}.png`" class="w-5 h-3.5 rounded" style="filter: drop-shadow(0 2px 4px rgba(0,0,0,1)) drop-shadow(0 0 8px rgba(0,0,0,0.8));" :title="server.location" @error="$event.target.style.display='none'">
                                <h3 class="text-xl font-bold text-white flex-1" style="text-shadow: 0 2px 8px rgba(0,0,0,1), 0 0 6px rgba(0,0,0,1), 0 0 12px rgba(0,0,0,0.8);" v-html="q3tohtml(server.name)"></h3>
                                <CopyButton :text="server.ip + ':' + server.port" size="sm" :label="$t('Copy IP')" />
                                <FavoriteStar :active="isFavorite(server)" @toggle="toggleFavorite(server)" />
                            </div>

                            <!-- Map Info with hover group -->
                            <div class="space-y-1.5">
                                <div v-if="server.map" :class="['bg-white/5 rounded-lg px-3 py-2 border border-white/10 transition-all relative map-features-hover-group', filters.showDetails ? 'map-features-expanded' : '']">
                                    <div class="flex items-center justify-between gap-2">
                                        <div class="flex items-center gap-2">
                                            <!-- No "Map:" in front of it. On a card
                                                 whose whole top half is the map's
                                                 own screenshot the word says
                                                 nothing, and it cost a third of
                                                 this row - which is the row that
                                                 has to hold a long map name, the
                                                 copy button and the save button
                                                 side by side. -->
                                            <div class="flex items-center gap-2 min-w-0" @mouseenter="hoveredMapServer = server.id" @mouseleave="hoveredMapServer = null">
                                                <!-- px-3 -mx-3 is not spacing: truncate
                                                     is overflow:hidden, which clips at the
                                                     box edge and took the text-shadow with
                                                     it. The padding gives the 12px glow room
                                                     to land inside the box and the negative
                                                     margin puts the text back where it was. -->
                                                <a :href="`/maps/${encodeURIComponent(server.map)}`" class="font-bold text-white text-lg hover:text-blue-400 transition-colors map-name-highlight truncate px-3 -mx-3" style="text-shadow: 0 2px 8px rgba(0,0,0,1), 0 0 6px rgba(0,0,0,1), 0 0 12px rgba(0,0,0,0.8);">{{ server.map }}</a>
                                            </div>
                                            <!-- Copy map name -->
                                            <CopyButton :text="server.map" size="xs" :label="$t('Copy map')" />
                                        </div>
                                        <div class="flex items-center gap-2 ml-auto">
                                            <!-- Expand Indicator - only show if map has features -->
                                            <div v-if="(server.mapdata?.weapons && server.mapdata.weapons.length > 0) || (server.mapdata?.items && server.mapdata.items.length > 0) || (server.mapdata?.functions && server.mapdata.functions.length > 0)" class="map-expand-indicator">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-4 h-4">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                                </svg>
                                            </div>
                                            <!-- Add to Maplist button (only if logged in and map has ID) -->
                                            <button
                                                v-if="page.props.auth.user && server.mapdata?.id"
                                                @click.stop="openAddToMaplist(server.mapdata.id)"
                                                class="save-maplist-btn"
                                                :title="$t('Save to Maplist')"
                                            >
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3.5 h-3.5">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M17.593 3.322c1.1.128 1.907 1.077 1.907 2.185V21L12 17.25 4.5 21V5.507c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0 1 11.186 0Z" />
                                                </svg>
                                                <span class="text-[10px] font-semibold">{{ $t('Save') }}</span>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Map Features: Weapons, Items, Functions - Expandable on hover -->
                                    <div v-if="(server.mapdata?.weapons && server.mapdata.weapons.length > 0) || (server.mapdata?.items && server.mapdata.items.length > 0) || (server.mapdata?.functions && server.mapdata.functions.length > 0)" class="map-features-container">
                                        <div class="flex flex-wrap gap-2 pt-2 border-t border-white/10 mt-2">
                                            <!-- Weapons -->
                                            <div v-if="server.mapdata.weapons && server.mapdata.weapons.length > 0" class="flex items-center gap-1.5">
                                                <span class="text-gray-200 font-bold text-[11px] uppercase tracking-wide drop-shadow-[0_2px_6px_rgba(0,0,0,1)] drop-shadow-[0_0_8px_rgba(0,0,0,0.8)]">{{ $t('Weapons:') }}</span>
                                                <div class="flex gap-1">
                                                    <img v-for="weapon in server.mapdata.weapons.split(',')" :key="weapon"
                                                         :src="getWeaponIcon(weapon)"
                                                         :alt="getWeaponName(weapon)"
                                                         :title="getWeaponName(weapon)"
                                                         class="w-4 h-4 opacity-95 hover:opacity-100 transition-opacity map-feature-icon" />
                                                </div>
                                            </div>

                                            <!-- Items -->
                                            <div v-if="server.mapdata.items && server.mapdata.items.length > 0" class="flex items-center gap-1.5">
                                                <span class="text-gray-200 font-bold text-[11px] uppercase tracking-wide drop-shadow-[0_2px_6px_rgba(0,0,0,1)] drop-shadow-[0_0_8px_rgba(0,0,0,0.8)]">{{ $t('Items:') }}</span>
                                                <div class="flex gap-1">
                                                    <img v-for="item in server.mapdata.items.split(',')" :key="item"
                                                         :src="getItemIcon(item)"
                                                         :alt="getItemName(item)"
                                                         :title="getItemName(item)"
                                                         class="w-4 h-4 opacity-95 hover:opacity-100 transition-opacity map-feature-icon" />
                                                </div>
                                            </div>

                                            <!-- Functions -->
                                            <div v-if="server.mapdata.functions && server.mapdata.functions.length > 0" class="flex items-center gap-1.5">
                                                <span class="text-gray-200 font-bold text-[11px] uppercase tracking-wide drop-shadow-[0_2px_6px_rgba(0,0,0,1)] drop-shadow-[0_0_8px_rgba(0,0,0,0.8)]">{{ $t('Functions:') }}</span>
                                                <div class="flex gap-1">
                                                    <img v-for="func in server.mapdata.functions.split(',')" :key="func"
                                                         :src="getFunctionIcon(func)"
                                                         :alt="getFunctionName(func)"
                                                         :title="getFunctionName(func)"
                                                         class="w-4 h-4 opacity-95 hover:opacity-100 transition-opacity map-feature-icon" />
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <a v-if="server.besttime_time && server.besttime_time > 0" :href="server.besttime_url ? `/profile/${server.besttime_url}` : '#'" @click.stop :class="['flex items-center gap-2 text-sm hover:bg-white/5 rounded px-1 -mx-1 py-0.5 mt-1.5 transition-colors relative z-10 map-hover-fade', hoveredMapServer === server.id ? 'opacity-0 pointer-events-none' : 'opacity-100']">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-4 h-4 text-yellow-500 flex-shrink-0" style="filter: drop-shadow(0 2px 4px rgba(0,0,0,1)) drop-shadow(0 0 6px rgba(0,0,0,0.8));">
                                        <path fill-rule="evenodd" d="M5.166 2.621v.858c-1.035.148-2.059.33-3.071.543a.75.75 0 0 0-.584.859 6.753 6.753 0 0 0 6.138 5.6 6.73 6.73 0 0 0 2.743 1.346A6.707 6.707 0 0 1 9.279 15H8.54c-1.036 0-1.875.84-1.875 1.875V19.5h-.75a2.25 2.25 0 0 0-2.25 2.25c0 .414.336.75.75.75h15a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-2.25-2.25h-.75v-2.625c0-1.036-.84-1.875-1.875-1.875h-.739a6.706 6.706 0 0 1-1.112-3.173 6.73 6.73 0 0 0 2.743-1.347 6.753 6.753 0 0 0 6.139-5.6.75.75 0 0 0-.585-.858 47.077 47.077 0 0 0-3.07-.543V2.62a.75.75 0 0 0-.658-.744 49.22 49.22 0 0 0-6.093-.377c-2.063 0-4.096.128-6.093.377a.75.75 0 0 0-.657.744Zm0 2.629c0 1.196.312 2.32.857 3.294A5.266 5.266 0 0 1 3.16 5.337a45.6 45.6 0 0 1 2.006-.343v.256Zm13.5 0v-.256c.674.1 1.343.214 2.006.343a5.265 5.265 0 0 1-2.863 3.207 6.72 6.72 0 0 0 .857-3.294Z" clip-rule="evenodd" />
                                    </svg>
                                    <img v-if="server.besttime_country" :src="`/images/flags/${server.besttime_country}.png`" class="w-4 h-3 rounded" style="filter: drop-shadow(0 2px 4px rgba(0,0,0,1)) drop-shadow(0 0 6px rgba(0,0,0,0.8));" @error="$event.target.style.display='none'">
                                    <span class="font-bold flex-1" style="text-shadow: 0 2px 8px rgba(0,0,0,1), 0 0 6px rgba(0,0,0,1), 0 0 12px rgba(0,0,0,0.8);" v-html="q3tohtml(server.besttime_name)"></span>
                                    <span v-if="server.besttime_date" class="text-sm text-gray-400 font-mono leading-none" style="text-shadow: 0 2px 8px rgba(0,0,0,1), 0 0 6px rgba(0,0,0,1);">{{ formatRecordDate(server.besttime_date) }}</span>
                                    <span class="font-bold text-yellow-400 font-mono leading-none" style="text-shadow: 0 2px 8px rgba(0,0,0,1), 0 0 6px rgba(0,0,0,1), 0 0 12px rgba(0,0,0,0.8);">{{ formatTime(server.besttime_time) }}</span>
                                </a>
                                <div v-if="server.mytime_time && server.mytime_time > 0" :class="['flex items-center gap-2 text-sm map-hover-fade', hoveredMapServer === server.id ? 'opacity-0 pointer-events-none' : 'opacity-100']">
                                    <span v-if="server.myrank_position && server.myrank_total" class="text-sm text-gray-400 font-bold flex-shrink-0" style="text-shadow: 0 2px 8px rgba(0,0,0,1), 0 0 6px rgba(0,0,0,1), 0 0 12px rgba(0,0,0,0.8);">{{ server.myrank_position }}/{{ server.myrank_total }}</span>
                                    <span class="font-bold flex-1 text-white text-sm" style="text-shadow: 0 2px 8px rgba(0,0,0,1), 0 0 6px rgba(0,0,0,1), 0 0 12px rgba(0,0,0,0.8);">{{ $t('My Time') }}</span>
                                    <span v-if="server.mytime_date" class="text-sm text-gray-400 font-mono leading-none" style="text-shadow: 0 2px 8px rgba(0,0,0,1), 0 0 6px rgba(0,0,0,1);">{{ formatRecordDate(server.mytime_date) }}</span>
                                    <span :class="server.defrag.toLowerCase().includes('cpm') ? 'text-purple-400' : 'text-blue-400'" class="font-bold font-mono text-sm leading-none" style="text-shadow: 0 2px 8px rgba(0,0,0,1), 0 0 6px rgba(0,0,0,1), 0 0 12px rgba(0,0,0,0.8);">{{ formatTime(server.mytime_time) }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Players List - Always expanded.
                             Background uses variant S2 from
                             /q3-bg-demo.html: slate-700 (#334155) at
                             88% alpha with a 4px backdrop blur. Solid
                             enough to read like a panel, but with a
                             slight bleed of the map thumbnail under
                             it. Every Q3 colour code stays legible -
                             black ^0 as a dark silhouette, white ^7
                             bright, brights (yellow/cyan/magenta)
                             punchy. -->
                        <div v-if="server.online_players.length > 0" :class="['mb-4 mt-2 map-hover-fade relative z-20', hoveredMapServer === server.id ? 'opacity-0 pointer-events-none' : 'opacity-100']">
                            <div class="rounded-lg p-2 border border-white/10 backdrop-blur-[4px]" style="background: rgba(71,85,105,0.55);">
                                <div class="space-y-1.5">
                                    <OnlinePlayer v-for="player in server.online_players" :key="player.id" :player="player" :siblings="server.online_players" />
                                </div>
                            </div>
                        </div>
                        <div v-else :class="['mb-4 mt-2 map-hover-fade', hoveredMapServer === server.id ? 'opacity-0 pointer-events-none' : 'opacity-100']">
                            <div class="p-3 rounded-lg border border-white/10 text-center backdrop-blur-[4px]" style="background: rgba(71,85,105,0.55);">
                                <span class="text-sm text-gray-300 drop-shadow-[0_2px_4px_rgba(0,0,0,0.8)]">{{ $t('No players online') }}</span>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div :class="['mt-auto map-hover-fade', hoveredMapServer === server.id ? 'opacity-0 pointer-events-none' : 'opacity-100']">
                            <a :href="`defrag://${server.ip}:${server.port}`" :class="server.defrag.toLowerCase().includes('cpm') ? 'connect-button-cpm' : 'connect-button-vq3'" class="connect-button w-full flex items-center justify-between px-4 py-3 rounded-lg text-white font-bold text-sm transition-all hover:scale-[1.02]">
                                <div class="flex items-center gap-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 0 1 0 1.972l-11.54 6.347a1.125 1.125 0 0 1-1.667-.986V5.653Z" />
                                    </svg>
                                    {{ $t('Connect') }}
                                </div>
                                <div class="flex items-center gap-2">
                                    <span v-if="server.estimated_ping != null" :class="pingClass(server.estimated_ping)" :title="pingTitle"
                                        class="inline-flex items-center gap-1 px-2 py-0.5 text-xs font-bold rounded border tabular-nums">
                                        <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2 20h.01M7 20v-4M12 20v-8M17 20V8M22 4v16"/></svg>
                                        ~{{ server.estimated_ping }} ms
                                    </span>
                                    <span :class="server.defrag.toLowerCase().includes('cpm') ? 'bg-purple-500/30 border-purple-400/50 text-purple-300' : 'bg-blue-500/30 border-blue-400/50 text-blue-300'" class="px-2.5 py-0.5 text-xs font-black uppercase tracking-wider rounded border">
                                        {{ server.defrag.toLowerCase().includes('cpm') ? 'CPM' : 'VQ3' }}
                                    </span>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Compact List Layout - Split by Physics -->
            <div v-else-if="layout === 'compact'" class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- VQ3 Servers (Left) -->
                <div class="space-y-3">
                    <div class="flex items-center gap-3 mb-4 px-4">
                        <div class="w-10 h-10 bg-gradient-to-br from-blue-600 to-blue-500 rounded-xl flex items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-6 h-6 text-white">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-xl font-black text-white">{{ $t('VQ3 Servers') }}</h3>
                            <p class="text-sm text-gray-500">{{ $tc(':count server|:count servers', filteredAndSortedServers.filter(s => !s.defrag.toLowerCase().includes('cpm')).length) }}</p>
                        </div>
                    </div>

                    <div v-for="server in filteredAndSortedServers.filter(s => !s.defrag.toLowerCase().includes('cpm'))" :key="server.id" :class="['group relative overflow-hidden rounded-xl border transition-all duration-300', server.cheats && !cheatsAreExpected(server) ? 'border-red-500/25 hover:border-red-400/45 pt-4' : ['border-white/10 hover:border-blue-500/50', server.cheats ? 'pt-4' : ''], isFavorite(server) ? 'ring-1 ring-amber-400/50' : '']">
                        <CheatsBanner :cheats="server.cheats" compact :subdued="cheatsAreExpected(server)" />
                        <!-- Background Map Thumbnail -->
                        <div v-if="server.mapdata?.thumbnail" class="absolute inset-0 transition-all duration-500">
                            <img
                                :src="`/storage/${server.mapdata.thumbnail}`"
                                @error="$event.target.src='/images/unknown.jpg'"
                                loading="lazy"
                                decoding="async"
                                class="w-full h-full object-cover scale-105 group-hover:scale-110 opacity-100 transition-all duration-500"
                                :alt="server.map"
                            />
                            <div class="absolute inset-0 bg-gradient-to-r from-black/60 via-black/40 to-black/60 group-hover:from-black/50 group-hover:via-black/30 group-hover:to-black/50 transition-all duration-500"></div>
                        </div>

                        <div class="relative p-3">
                            <!-- Main compact row -->
                            <div class="flex items-center justify-between gap-2">
                                <!-- Left: Flag and Server Info -->
                                <div class="flex items-center gap-2 flex-1 min-w-0">
                                    <FavoriteStar :active="isFavorite(server)" size="xs" @toggle="toggleFavorite(server)" />
                                    <img :src="`/images/flags/${server.location}.png`" class="w-5 h-3.5 rounded shadow-md flex-shrink-0" :title="server.location" @error="$event.target.style.display='none'">

                                    <div class="inline-flex flex-col bg-black/40  px-2 py-1 rounded border border-white/20">
                                        <h3 class="text-base font-bold text-white transition-colors flex items-center gap-2 flex-wrap" style="text-shadow: 0 2px 8px rgba(0,0,0,1), 0 0 6px rgba(0,0,0,1), 0 0 12px rgba(0,0,0,0.8);">
                                            <span v-html="q3tohtml(server.name)"></span>
                                        </h3>
                                        <div class="flex items-center gap-2 text-xs text-gray-300 transition-colors">
                                            <a v-if="server.map" :href="`/maps/${encodeURIComponent(server.map)}`" class="hover:text-blue-400 transition-colors" style="text-shadow: 0 2px 8px rgba(0,0,0,0.9), 0 0 4px rgba(0,0,0,0.8);">{{ server.map }}</a>
                                        </div>
                                    </div>
                                </div>

                                <!-- Middle: WR, My Time & Players -->
                                <div class="flex items-center gap-2 flex-shrink-0">
                                    <div class="flex flex-col gap-1">
                                        <!-- World Record -->
                                        <div v-if="server.besttime_time && server.besttime_time > 0" class="flex items-center justify-between gap-1.5 bg-white/10  px-2 py-0.5 rounded border border-white/20 min-w-[140px]">
                                            <div class="flex items-center gap-1.5">
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-3 h-3 text-yellow-500 flex-shrink-0">
                                                    <path fill-rule="evenodd" d="M5.166 2.621v.858c-1.035.148-2.059.33-3.071.543a.75.75 0 0 0-.584.859 6.753 6.753 0 0 0 6.138 5.6 6.73 6.73 0 0 0 2.743 1.346A6.707 6.707 0 0 1 9.279 15H8.54c-1.036 0-1.875.84-1.875 1.875V19.5h-.75a2.25 2.25 0 0 0-2.25 2.25c0 .414.336.75.75.75h15a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-2.25-2.25h-.75v-2.625c0-1.036-.84-1.875-1.875-1.875h-.739a6.706 6.706 0 0 1-1.112-3.173 6.73 6.73 0 0 0 2.743-1.347 6.753 6.753 0 0 0 6.139-5.6.75.75 0 0 0-.585-.858 47.077 47.077 0 0 0-3.07-.543V2.62a.75.75 0 0 0-.658-.744 49.22 49.22 0 0 0-6.093-.377c-2.063 0-4.096.128-6.093.377a.75.75 0 0 0-.657.744Zm0 2.629c0 1.196.312 2.32.857 3.294A5.266 5.266 0 0 1 3.16 5.337a45.6 45.6 0 0 1 2.006-.343v.256Zm13.5 0v-.256c.674.1 1.343.214 2.006.343a5.265 5.265 0 0 1-2.863 3.207 6.72 6.72 0 0 0 .857-3.294Z" clip-rule="evenodd" />
                                                </svg>
                                                <a :href="`/profile/${server.besttime_url}`" @click.stop class="text-xs font-bold text-white hover:text-yellow-300 transition-colors truncate max-w-[60px] relative z-10" style="text-shadow: 0 2px 8px rgba(0,0,0,0.9), 0 0 4px rgba(0,0,0,0.8);" v-html="q3tohtml(server.besttime_name)"></a>
                                            </div>
                                            <span class="text-xs font-bold text-yellow-400 font-mono" style="text-shadow: 0 2px 8px rgba(0,0,0,0.9), 0 0 4px rgba(0,0,0,0.8);">{{ formatTime(server.besttime_time) }}</span>
                                        </div>

                                        <!-- My Time -->
                                        <div v-if="server.mytime_time && server.mytime_time > 0" class="flex items-center justify-between gap-1.5 bg-white/10  px-2 py-0.5 rounded border border-white/20 min-w-[140px]">
                                            <div class="flex items-center gap-1.5">
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-3 h-3 text-blue-400 flex-shrink-0">
                                                    <path fill-rule="evenodd" d="M7.5 6a4.5 4.5 0 1 1 9 0 4.5 4.5 0 0 1-9 0ZM3.751 20.105a8.25 8.25 0 0 1 16.498 0 .75.75 0 0 1-.437.695A18.683 18.683 0 0 1 12 22.5c-2.786 0-5.433-.608-7.812-1.7a.75.75 0 0 1-.437-.695Z" clip-rule="evenodd" />
                                                </svg>
                                                <span v-if="server.myrank_position && server.myrank_total" class="text-xs font-bold text-white" style="text-shadow: 0 2px 8px rgba(0,0,0,0.9), 0 0 4px rgba(0,0,0,0.8);">{{ server.myrank_position }}/{{ server.myrank_total }}</span>
                                                <span v-else class="text-xs font-bold text-white" style="text-shadow: 0 2px 8px rgba(0,0,0,0.9), 0 0 4px rgba(0,0,0,0.8);">{{ $t('Me') }}</span>
                                            </div>
                                            <span class="text-xs font-bold text-blue-400 font-mono" style="text-shadow: 0 2px 8px rgba(0,0,0,0.9), 0 0 4px rgba(0,0,0,0.8);">{{ formatTime(server.mytime_time) }}</span>
                                        </div>
                                    </div>

                                    <!-- Player Count -->
                                    <div v-if="server.online_players.length > 0" class="flex items-center gap-1 bg-white/10  px-2 py-1 rounded border border-white/20">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-3 h-3 text-blue-400">
                                            <path d="M4.5 6.375a4.125 4.125 0 1 1 8.25 0 4.125 4.125 0 0 1-8.25 0ZM14.25 8.625a3.375 3.375 0 1 1 6.75 0 3.375 3.375 0 0 1-6.75 0ZM1.5 19.125a7.125 7.125 0 0 1 14.25 0v.003l-.001.119a.75.75 0 0 1-.363.63 13.067 13.067 0 0 1-6.761 1.873c-2.472 0-4.786-.684-6.76-1.873a.75.75 0 0 1-.364-.63l-.001-.122ZM17.25 19.128l-.001.144a2.25 2.25 0 0 1-.233.96 10.088 10.088 0 0 0 5.06-1.01.75.75 0 0 0 .42-.643 4.875 4.875 0 0 0-6.957-4.611 8.586 8.586 0 0 1 1.71 5.157v.003Z" />
                                        </svg>
                                        <span class="text-xs font-bold text-white" style="text-shadow: 0 2px 8px rgba(0,0,0,0.9), 0 0 4px rgba(0,0,0,0.8);">{{ server.online_players.length }}</span>
                                    </div>

                                    <!-- Estimated ping -->
                                    <div v-if="server.estimated_ping != null" :class="pingClass(server.estimated_ping)" :title="pingTitle" class="flex items-center gap-1 px-2 py-1 rounded border tabular-nums">
                                        <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2 20h.01M7 20v-4M12 20v-8M17 20V8M22 4v16"/></svg>
                                        <span class="text-xs font-bold">~{{ server.estimated_ping }}ms</span>
                                    </div>
                                </div>

                                <!-- Right: Play & Copy IP Button -->
                                <div class="flex items-center gap-1.5 flex-shrink-0">
                                    <a :href="`defrag://${server.ip}:${server.port}`" class="flex items-center justify-center gap-1 px-2 py-1 bg-white/10 border border-blue-500/60 hover:border-blue-400 hover:bg-blue-500/20 rounded-lg text-white transition-all ">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-3.5 h-3.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 0 1 0 1.972l-11.54 6.347a1.125 1.125 0 0 1-1.667-.986V5.653Z" />
                                        </svg>
                                        <span class="text-xs font-bold" style="text-shadow: 0 2px 8px rgba(0,0,0,0.9), 0 0 4px rgba(0,0,0,0.8);">{{ $t('Play') }}</span>
                                    </a>
                                    <CopyButton :text="server.ip + ':' + server.port" size="sm" :label="$t('Copy IP')" />
                                </div>
                            </div>

                            <!-- Players List - Expands on hover -->
                            <div v-if="server.online_players.length > 0" class="mt-0 max-h-0 group-hover:max-h-96 overflow-hidden transition-all duration-300">
                                <div class="pt-2 space-y-1">
                                    <OnlinePlayer v-for="player in server.online_players" :key="player.id" :player="player" :siblings="server.online_players" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-if="filteredAndSortedServers.filter(s => !s.defrag.toLowerCase().includes('cpm')).length === 0" class="text-center py-8  bg-white/5 rounded-xl border border-white/10">
                        <p class="text-gray-500 text-sm">{{ $t('No VQ3 servers found') }}</p>
                    </div>
                </div>

                <!-- CPM Servers (Right) -->
                <div class="space-y-3">
                    <div class="flex items-center gap-3 mb-4 px-4">
                        <div class="w-10 h-10 bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl flex items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-6 h-6 text-white">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.59 14.37a6 6 0 0 1-5.84 7.38v-4.8m5.84-2.58a14.98 14.98 0 0 0 6.16-12.12A14.98 14.98 0 0 0 9.631 8.41m5.96 5.96a14.926 14.926 0 0 1-5.841 2.58m-.119-8.54a6 6 0 0 0-7.381 5.84h4.8m2.581-5.84a14.927 14.927 0 0 0-2.58 5.84m2.699 2.7c-.103.021-.207.041-.311.06a15.09 15.09 0 0 1-2.448-2.448 14.9 14.9 0 0 1 .06-.312m-2.24 2.39a4.493 4.493 0 0 0-1.757 4.306 4.493 4.493 0 0 0 4.306-1.758M16.5 9a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-xl font-black text-white">{{ $t('CPM Servers') }}</h3>
                            <p class="text-sm text-gray-500">{{ $tc(':count server|:count servers', filteredAndSortedServers.filter(s => s.defrag.toLowerCase().includes('cpm')).length) }}</p>
                        </div>
                    </div>

                    <div v-for="server in filteredAndSortedServers.filter(s => s.defrag.toLowerCase().includes('cpm'))" :key="server.id" :class="['group relative overflow-hidden rounded-xl border transition-all duration-300', server.cheats && !cheatsAreExpected(server) ? 'border-red-500/25 hover:border-red-400/45 pt-4' : ['border-white/10 hover:border-purple-500/50', server.cheats ? 'pt-4' : ''], isFavorite(server) ? 'ring-1 ring-amber-400/50' : '']">
                        <CheatsBanner :cheats="server.cheats" compact :subdued="cheatsAreExpected(server)" />
                        <!-- Background Map Thumbnail -->
                        <div v-if="server.mapdata?.thumbnail" class="absolute inset-0 transition-all duration-500">
                            <img
                                :src="`/storage/${server.mapdata.thumbnail}`"
                                @error="$event.target.src='/images/unknown.jpg'"
                                loading="lazy"
                                decoding="async"
                                class="w-full h-full object-cover scale-105 group-hover:scale-110 opacity-100 transition-all duration-500"
                                :alt="server.map"
                            />
                            <div class="absolute inset-0 bg-gradient-to-r from-black/60 via-black/40 to-black/60 group-hover:from-black/50 group-hover:via-black/30 group-hover:to-black/50 transition-all duration-500"></div>
                        </div>

                        <div class="relative p-3">
                            <div class="flex items-center justify-between gap-2">
                                <!-- Left: Flag and Server Info -->
                                <div class="flex items-center gap-2 flex-1 min-w-0">
                                    <FavoriteStar :active="isFavorite(server)" size="xs" @toggle="toggleFavorite(server)" />
                                    <img :src="`/images/flags/${server.location}.png`" class="w-5 h-3.5 rounded shadow-md flex-shrink-0" :title="server.location" @error="$event.target.style.display='none'">

                                    <div class="inline-flex flex-col bg-black/40  px-2 py-1 rounded border border-white/20">
                                        <h3 class="text-base font-bold text-white transition-colors flex items-center gap-2 flex-wrap" style="text-shadow: 0 2px 8px rgba(0,0,0,1), 0 0 6px rgba(0,0,0,1), 0 0 12px rgba(0,0,0,0.8);">
                                            <span v-html="q3tohtml(server.name)"></span>
                                        </h3>
                                        <div class="flex items-center gap-2 text-xs text-gray-300 transition-colors">
                                            <a v-if="server.map" :href="`/maps/${encodeURIComponent(server.map)}`" class="hover:text-purple-400 transition-colors" style="text-shadow: 0 2px 8px rgba(0,0,0,0.9), 0 0 4px rgba(0,0,0,0.8);">{{ server.map }}</a>
                                        </div>
                                    </div>
                                </div>

                                <!-- Middle: WR, My Time & Players -->
                                <div class="flex items-center gap-2 flex-shrink-0">
                                    <div class="flex flex-col gap-1">
                                        <!-- World Record -->
                                        <div v-if="server.besttime_time && server.besttime_time > 0" class="flex items-center justify-between gap-1.5 bg-white/10  px-2 py-0.5 rounded border border-white/20 min-w-[140px]">
                                            <div class="flex items-center gap-1.5">
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-3 h-3 text-yellow-500 flex-shrink-0">
                                                    <path fill-rule="evenodd" d="M5.166 2.621v.858c-1.035.148-2.059.33-3.071.543a.75.75 0 0 0-.584.859 6.753 6.753 0 0 0 6.138 5.6 6.73 6.73 0 0 0 2.743 1.346A6.707 6.707 0 0 1 9.279 15H8.54c-1.036 0-1.875.84-1.875 1.875V19.5h-.75a2.25 2.25 0 0 0-2.25 2.25c0 .414.336.75.75.75h15a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-2.25-2.25h-.75v-2.625c0-1.036-.84-1.875-1.875-1.875h-.739a6.706 6.706 0 0 1-1.112-3.173 6.73 6.73 0 0 0 2.743-1.347 6.753 6.753 0 0 0 6.139-5.6.75.75 0 0 0-.585-.858 47.077 47.077 0 0 0-3.07-.543V2.62a.75.75 0 0 0-.658-.744 49.22 49.22 0 0 0-6.093-.377c-2.063 0-4.096.128-6.093.377a.75.75 0 0 0-.657.744Zm0 2.629c0 1.196.312 2.32.857 3.294A5.266 5.266 0 0 1 3.16 5.337a45.6 45.6 0 0 1 2.006-.343v.256Zm13.5 0v-.256c.674.1 1.343.214 2.006.343a5.265 5.265 0 0 1-2.863 3.207 6.72 6.72 0 0 0 .857-3.294Z" clip-rule="evenodd" />
                                                </svg>
                                                <a :href="`/profile/${server.besttime_url}`" @click.stop class="text-xs font-bold text-white hover:text-yellow-300 transition-colors truncate max-w-[60px] relative z-10" style="text-shadow: 0 2px 8px rgba(0,0,0,0.9), 0 0 4px rgba(0,0,0,0.8);" v-html="q3tohtml(server.besttime_name)"></a>
                                            </div>
                                            <span class="text-xs font-bold text-yellow-400 font-mono" style="text-shadow: 0 2px 8px rgba(0,0,0,0.9), 0 0 4px rgba(0,0,0,0.8);">{{ formatTime(server.besttime_time) }}</span>
                                        </div>

                                        <!-- My Time -->
                                        <div v-if="server.mytime_time && server.mytime_time > 0" class="flex items-center justify-between gap-1.5 bg-white/10  px-2 py-0.5 rounded border border-white/20 min-w-[140px]">
                                            <div class="flex items-center gap-1.5">
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-3 h-3 text-purple-400 flex-shrink-0">
                                                    <path fill-rule="evenodd" d="M7.5 6a4.5 4.5 0 1 1 9 0 4.5 4.5 0 0 1-9 0ZM3.751 20.105a8.25 8.25 0 0 1 16.498 0 .75.75 0 0 1-.437.695A18.683 18.683 0 0 1 12 22.5c-2.786 0-5.433-.608-7.812-1.7a.75.75 0 0 1-.437-.695Z" clip-rule="evenodd" />
                                                </svg>
                                                <span v-if="server.myrank_position && server.myrank_total" class="text-xs font-bold text-white" style="text-shadow: 0 2px 8px rgba(0,0,0,0.9), 0 0 4px rgba(0,0,0,0.8);">{{ server.myrank_position }}/{{ server.myrank_total }}</span>
                                                <span v-else class="text-xs font-bold text-white" style="text-shadow: 0 2px 8px rgba(0,0,0,0.9), 0 0 4px rgba(0,0,0,0.8);">{{ $t('Me') }}</span>
                                            </div>
                                            <span class="text-xs font-bold text-purple-400 font-mono" style="text-shadow: 0 2px 8px rgba(0,0,0,0.9), 0 0 4px rgba(0,0,0,0.8);">{{ formatTime(server.mytime_time) }}</span>
                                        </div>
                                    </div>

                                    <!-- Player Count -->
                                    <div v-if="server.online_players.length > 0" class="flex items-center gap-1 bg-white/10  px-2 py-1 rounded border border-white/20">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-3 h-3 text-purple-400">
                                            <path d="M4.5 6.375a4.125 4.125 0 1 1 8.25 0 4.125 4.125 0 0 1-8.25 0ZM14.25 8.625a3.375 3.375 0 1 1 6.75 0 3.375 3.375 0 0 1-6.75 0ZM1.5 19.125a7.125 7.125 0 0 1 14.25 0v.003l-.001.119a.75.75 0 0 1-.363.63 13.067 13.067 0 0 1-6.761 1.873c-2.472 0-4.786-.684-6.76-1.873a.75.75 0 0 1-.364-.63l-.001-.122ZM17.25 19.128l-.001.144a2.25 2.25 0 0 1-.233.96 10.088 10.088 0 0 0 5.06-1.01.75.75 0 0 0 .42-.643 4.875 4.875 0 0 0-6.957-4.611 8.586 8.586 0 0 1 1.71 5.157v.003Z" />
                                        </svg>
                                        <span class="text-xs font-bold text-white" style="text-shadow: 0 2px 8px rgba(0,0,0,0.9), 0 0 4px rgba(0,0,0,0.8);">{{ server.online_players.length }}</span>
                                    </div>

                                    <!-- Estimated ping -->
                                    <div v-if="server.estimated_ping != null" :class="pingClass(server.estimated_ping)" :title="pingTitle" class="flex items-center gap-1 px-2 py-1 rounded border tabular-nums">
                                        <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2 20h.01M7 20v-4M12 20v-8M17 20V8M22 4v16"/></svg>
                                        <span class="text-xs font-bold">~{{ server.estimated_ping }}ms</span>
                                    </div>
                                </div>

                                <!-- Right: Play & Copy IP Button -->
                                <div class="flex items-center gap-1.5 flex-shrink-0">
                                    <a :href="`defrag://${server.ip}:${server.port}`" class="flex items-center justify-center gap-1 px-2 py-1 bg-white/10 border border-purple-500/60 hover:border-purple-400 hover:bg-purple-500/20 rounded-lg text-white transition-all ">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-3.5 h-3.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 0 1 0 1.972l-11.54 6.347a1.125 1.125 0 0 1-1.667-.986V5.653Z" />
                                        </svg>
                                        <span class="text-xs font-bold" style="text-shadow: 0 2px 8px rgba(0,0,0,0.9), 0 0 4px rgba(0,0,0,0.8);">{{ $t('Play') }}</span>
                                    </a>
                                    <CopyButton :text="server.ip + ':' + server.port" size="sm" :label="$t('Copy IP')" />
                                </div>
                            </div>

                            <!-- Players List - Expands on hover -->
                            <div v-if="server.online_players.length > 0" class="mt-0 max-h-0 group-hover:max-h-96 overflow-hidden transition-all duration-300">
                                <div class="pt-2 space-y-1">
                                    <OnlinePlayer v-for="player in server.online_players" :key="player.id" :player="player" :siblings="server.online_players" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-if="filteredAndSortedServers.filter(s => s.defrag.toLowerCase().includes('cpm')).length === 0" class="text-center py-8  bg-white/5 rounded-xl border border-white/10">
                        <p class="text-gray-500 text-sm">{{ $t('No CPM servers found') }}</p>
                    </div>
                </div>
            </div>

            <!-- Oldschool Layout: the shape of the q3df.org serverlist, three
                 boxes to a row, each one a thumbnail beside the address and a
                 plain table of who is on. Everything is stated rather than
                 revealed on hover, which is the whole appeal of it.

                 The shape only. Its grey-on-black and its table borders stay
                 there; this reads as the rest of the site, and the map, the
                 player and the record all link where they link everywhere
                 else here, which on the original they do not. -->
            <!-- Four to a row and not five. The card has to carry a full
                 ip:port without shortening it, since that is the thing people
                 are here to copy, and at five the text column comes up about
                 40px short however small the thumbnail is made. At four it has
                 room to spare. -->
            <div v-else-if="layout === 'oldschool'" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
                <!-- `relative` and the extra top padding are what the other
                     three card layouts already have and this one did not, so
                     the banner - which is absolutely positioned at top-0 -
                     had nothing to anchor to and lay across the server name.
                     Padding only when there is a banner to make room for, and
                     keyed on `cheats` alone: a subdued banner is still drawn. -->
                <div v-for="server in filteredAndSortedServers" :key="server.id"
                     :class="['group relative rounded-xl border bg-black/40 backdrop-blur-sm p-3',
                              server.cheats && !cheatsAreExpected(server) ? 'border-red-500/25' : 'border-white/10',
                              server.cheats ? 'pt-6' : '',
                              isFavorite(server) ? 'ring-1 ring-amber-400/50' : '']">

                    <!-- compact, like the two column layouts. The full-height
                         banner is for the big cards, where it sits over a
                         450px thumbnail rather than over the card's first row. -->
                    <CheatsBanner :cheats="server.cheats" compact :subdued="cheatsAreExpected(server)" />

                    <div class="flex items-center gap-2 mb-3">
                        <img v-if="server.location" :src="`/images/flags/${server.location}.png`" :title="server.location"
                             class="w-5 h-3.5 rounded shrink-0" @error="$event.target.style.display='none'" />
                        <h3 class="font-bold text-sm truncate flex-1" v-html="q3tohtml(server.name)"></h3>
                        <FavoriteStar :active="isFavorite(server)" size="xs" @toggle="toggleFavorite(server)" />
                    </div>

                    <div class="flex gap-3">
                        <a :href="server.map ? `/maps/${encodeURIComponent(server.map)}` : '#'" class="shrink-0">
                            <img :src="`/storage/${server.mapdata?.thumbnail}`"
                                 @error="$event.target.src='/images/unknown.jpg'"
                                 class="w-24 h-16 rounded-lg object-cover bg-gray-900 border border-white/10" />
                        </a>

                        <div class="min-w-0 flex-1 text-xs space-y-1">
                            <!-- Where the address used to be printed. On the
                                 original that address is itself the way in -
                                 it says so at the top of the page - so a
                                 button that connects belongs in its place
                                 rather than beside it. The address stays
                                 reachable: it is the button's tooltip, and
                                 the copy icon next to it still hands it over
                                 for anyone typing it somewhere else. -->
                            <!-- Wraps: at four to a row the column is about
                                 200px, and Connect plus a labelled Copy IP is
                                 wider than that in Russian, where the word for
                                 Connect is longer than an IP address. It drops
                                 to a second line there rather than squashing
                                 the button. -->
                            <div class="flex flex-wrap items-center gap-1.5">
                                <a :href="`defrag://${server.ip}:${server.port}`"
                                   :title="`${server.ip}:${server.port}`"
                                   :class="server.defrag?.toLowerCase().includes('cpm') ? 'connect-button-cpm' : 'connect-button-vq3'"
                                   class="connect-button flex items-center gap-1 px-2 py-1 rounded text-white font-bold transition-all hover:scale-[1.03]">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3.5 h-3.5 shrink-0">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 0 1 0 1.972l-11.54 6.347a1.125 1.125 0 0 1-1.667-.986V5.653Z" />
                                    </svg>
                                    {{ $t('Connect') }}
                                </a>
                                <CopyButton :text="server.ip + ':' + server.port" size="xs" :label="$t('Copy IP')" />
                            </div>
                            <div v-if="server.map" class="flex items-center gap-1 min-w-0">
                                <a :href="`/maps/${encodeURIComponent(server.map)}`"
                                   class="font-bold text-white hover:text-blue-400 transition-colors truncate">{{ server.map }}</a>
                                <CopyButton :text="server.map" size="xs" :label="$t('Copy map')" />
                            </div>
                            <div class="uppercase" :class="server.defrag?.toLowerCase().includes('cpm') ? 'text-purple-300' : 'text-blue-300'">
                                {{ server.defrag }}
                            </div>
                        </div>
                    </div>

                    <!-- The map record, exactly where the original put it. -->
                    <div v-if="server.besttime_time && server.besttime_time > 0"
                         class="mt-3 pt-2 border-t border-white/10 text-xs">
                        <span class="text-gray-500 font-bold uppercase tracking-wide">{{ $t('Best Time') }}</span>
                        <a :href="server.besttime_url ? `/profile/${server.besttime_url}` : '#'"
                           class="flex items-center gap-1.5 mt-1 hover:bg-white/5 rounded px-1 -mx-1 py-0.5 transition-colors">
                            <img v-if="server.besttime_country" :src="`/images/flags/${server.besttime_country}.png`"
                                 class="w-4 h-3 rounded shrink-0" @error="$event.target.style.display='none'" />
                            <span class="truncate" v-html="q3tohtml(server.besttime_name || '')"></span>
                            <span class="ml-auto font-mono font-bold text-yellow-400 shrink-0">{{ formatTime(server.besttime_time) }}</span>
                        </a>
                    </div>

                    <!-- Players, then spectators, as two plain lists. Uses the
                         same row component as everywhere else, so the flag,
                         the profile link, the padlock and the Twitch dot all
                         come along rather than being rebuilt here. -->
                    <div v-if="server.online_players.length > 0" class="mt-3">
                        <div class="grid grid-cols-[1fr_auto] gap-x-3 text-[10px] font-bold uppercase tracking-wide text-gray-500 border-b border-white/10 pb-1 mb-1">
                            <span>{{ $t('Player') }}</span>
                            <span>{{ $t('Time') }}</span>
                        </div>
                        <OnlinePlayer v-for="player in server.online_players" :key="player.id" :player="player" :siblings="server.online_players" />
                    </div>

                    <div v-else class="mt-3 text-xs text-gray-600 italic">
                        {{ $t('No players online') }}
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div v-if="filteredAndSortedServers.length === 0" class="text-center py-16">
                <div class=" bg-white/5 rounded-2xl border border-white/10 p-12 max-w-md mx-auto">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-16 h-16 text-gray-500 mx-auto mb-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                    </svg>
                    <h3 class="text-xl font-bold text-white mb-2">{{ $t('No servers found') }}</h3>
                    <p v-if="filters.favoritesOnly && favorites.size === 0" class="text-gray-400">{{ $t('Click the star on a server to keep it at the top of this page.') }}</p>
                    <p v-else class="text-gray-400">{{ $t('Try adjusting your filters') }}</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Add to Maplist Modal -->
    <AddToMaplistModal
        :show="showMaplistModal"
        :map-id="selectedMapId"
        @close="showMaplistModal = false"
    />
</template>

<style scoped>
.live-dot-pulse { animation: live-dot-pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite; }
@keyframes live-dot-pulse {
    0%, 100% { transform: scale(1); opacity: .75; }
    50% { transform: scale(2.2); opacity: 0; }
}
@media (prefers-reduced-motion: reduce) { .live-dot-pulse { animation: none; } }
/* Save to Maplist button - same bubble style as CopyButton */
.save-maplist-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.125rem 0.375rem;
    border-radius: 0.25rem;
    color: #fff;
    background: rgba(255, 255, 255, 0.25);
    border: 1px solid rgba(255, 255, 255, 0.4);
    flex-shrink: 0;
    transition: all 0.2s ease;
    cursor: pointer;
    white-space: nowrap;
}
.save-maplist-btn:hover {
    color: #c084fc;
    background: rgba(255, 255, 255, 0.15);
    border-color: rgba(192, 132, 252, 0.5);
    transform: scale(1.1);
}
.save-maplist-btn:active {
    transform: scale(0.9);
}

/* Map features hover box styling */
.map-features-hover-group {
    cursor: default;
}

.map-features-hover-group:hover {
    background: rgba(255, 255, 255, 0.1) !important;
    border-color: rgba(96, 165, 250, 0.3) !important;
}

/* Map name highlight on hover */
.map-name-highlight {
    position: relative;
    display: inline-block;
}

.map-features-hover-group:hover .map-name-highlight {
    color: rgb(96, 165, 250) !important;
    text-shadow: 0 0 10px rgba(96, 165, 250, 0.5), 0 2px 8px rgba(0,0,0,0.9), 0 0 4px rgba(0,0,0,0.8);
}

/* Expand indicator - static, animates only on hover */
.map-expand-indicator {
    position: relative;
    padding: 4px;
    background: rgba(0, 0, 0, 0.25);
    border-radius: 50%;
    color: rgba(255, 255, 255, 0.75);
    transition: transform 0.3s ease, background 0.3s ease;
    filter: drop-shadow(0 1px 3px rgba(0, 0, 0, 0.7));
}

.map-features-hover-group:hover .map-expand-indicator {
    transform: rotate(180deg);
    background: rgba(96, 165, 250, 0.25);
    color: rgb(255, 255, 255);
    filter: drop-shadow(0 0 5px rgba(96, 165, 250, 0.35)) drop-shadow(0 2px 4px rgba(0, 0, 0, 0.8));
}


/* Map features expand animation */
.map-features-container {
    max-height: 0;
    overflow: hidden;
    opacity: 0;
    /* The overflow is here to animate the height, but it clips sideways too,
       and it was cutting the glow off the Functions label and the icons in a
       straight vertical line. Padding gives the glow room to land inside the
       box; the negative margin of the same size puts the row back where it
       was. Only horizontal - the vertical clip is what does the animation. */
    padding-left: 12px;
    padding-right: 12px;
    margin-left: -12px;
    margin-right: -12px;
    /* Collapsed the padding has to go, or box-sizing keeps the box 12px tall
       when max-height says 0 and the row never fully closes. */
    padding-bottom: 0;
    transition: max-height 0.4s cubic-bezier(0.4, 0, 0.2, 1), opacity 0.3s ease;
}

.map-features-hover-group:hover .map-features-container,
.map-features-expanded .map-features-container {
    max-height: 200px;
    opacity: 1;
    /* The icons sit on the bottom edge of this box, so their drop-shadow was
       being sliced off flat. This is the room it needs; the negative margin
       gives the space straight back so nothing below moves. */
    padding-bottom: 12px;
    margin-bottom: -12px;
    transition: max-height 0.5s cubic-bezier(0.4, 0, 0.2, 1), opacity 0.4s ease;
}

.map-features-expanded .map-expand-indicator {
    display: none;
}

/* Map name hover - fade out fast (300ms), fade back in slow (1000ms) */
.map-hover-fade {
    transition: opacity 1000ms ease;
}
.map-hover-fade.opacity-0 {
    transition: opacity 300ms ease;
}

/* Map feature icon shadows */
.map-feature-icon {
    filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 1)) drop-shadow(0 0 6px rgba(0, 0, 0, 0.8));
}

/* Player list hover-to-expand animation */
.player-list-container {
    max-height: 70px;
    overflow: hidden;
    position: relative;
    opacity: 1;
    transition: max-height 0.4s cubic-bezier(0.4, 0, 0.2, 1), opacity 0.3s ease;
}

.player-list-hover-group:hover .player-list-container {
    max-height: 1200px;
    opacity: 1;
    transition: max-height 0.5s cubic-bezier(0.4, 0, 0.2, 1), opacity 0.4s ease;
}

/* Show More Indicator at bottom of player list - Enhanced */
.show-more-indicator {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 60px;
    background: linear-gradient(to bottom, transparent, rgba(0, 0, 0, 0.95));
    display: flex;
    align-items: flex-end;
    justify-content: center;
    padding-bottom: 8px;
    pointer-events: none;
    opacity: 1;
    transition: opacity 0.3s ease;
}

.show-more-content {
    position: relative;
    padding: 4px;
    background: rgba(0, 0, 0, 0.25);
    border-radius: 50%;
    color: rgba(255, 255, 255, 0.75);
    filter: drop-shadow(0 1px 3px rgba(0, 0, 0, 0.7));
}


.player-list-hover-group:hover .show-more-indicator {
    opacity: 0;
}

/* Connect to Server Button - Fixed Glass Texture */
.connect-button {
    position: relative;
    overflow: hidden;
    /* backdrop-filter removed for performance */
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.4), inset 0 1px 0 rgba(255, 255, 255, 0.1);
}

.connect-button::before {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(135deg,
        rgba(255, 255, 255, 0.15) 0%,
        rgba(255, 255, 255, 0.05) 25%,
        transparent 50%,
        rgba(0, 0, 0, 0.05) 75%,
        rgba(0, 0, 0, 0.1) 100%
    );
    pointer-events: none;
}

.connect-button::after {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
    transition: left 0.5s ease;
}

.connect-button:hover::after {
    left: 100%;
}

.connect-button-vq3 {
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.25), rgba(37, 99, 235, 0.15));
    border: 2px solid rgba(96, 165, 250, 0.4);
}

.connect-button-vq3:hover {
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.35), rgba(37, 99, 235, 0.25));
    border-color: rgba(96, 165, 250, 0.6);
    box-shadow: 0 6px 20px rgba(59, 130, 246, 0.3), inset 0 1px 0 rgba(255, 255, 255, 0.2);
}

.connect-button-cpm {
    background: linear-gradient(135deg, rgba(168, 85, 247, 0.25), rgba(147, 51, 234, 0.15));
    border: 2px solid rgba(192, 132, 252, 0.4);
}

.connect-button-cpm:hover {
    background: linear-gradient(135deg, rgba(168, 85, 247, 0.35), rgba(147, 51, 234, 0.25));
    border-color: rgba(192, 132, 252, 0.6);
    box-shadow: 0 6px 20px rgba(168, 85, 247, 0.3), inset 0 1px 0 rgba(255, 255, 255, 0.2);
}

/* Fade gradient at bottom when collapsed */
.player-list-container::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 40px;
    background: linear-gradient(to bottom, transparent, rgba(0, 0, 0, 0.9));
    pointer-events: none;
    opacity: 1;
    transition: opacity 0.3s ease;
}

.player-list-hover-group:hover .player-list-container::after {
    opacity: 0;
}

/* Scrollbar styling */
.scrollbar-thin::-webkit-scrollbar {
    width: 4px;
}

.scrollbar-thin::-webkit-scrollbar-track {
    background: transparent;
}

.scrollbar-thin::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 2px;
}

.scrollbar-thin::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.2);
}
</style>
