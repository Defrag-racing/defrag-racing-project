<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed, onMounted, onUnmounted, getCurrentInstance, watch } from 'vue';
import { formatPrize } from '@/utils/currency';

const props = defineProps({
    contest: Object,
    leaderboard: Array,
    totalTickets: Number,
    myEntry: Object,
    nowWatching: Object,
    pastWinners: Array,
    hallOfFame: Array,
    allTimeWatchers: Array,
    exclusions: Array,
    leaderboardTotal: Number,
});

const { proxy } = getCurrentInstance();
const q3tohtml = proxy.q3tohtml;

const TWITCH_CHANNEL = 'defraglive';

// Live countdown to the end of the period.
const now = ref(Date.now());
const receivedAt = ref(Date.now());
let timer = null;
let refreshTimer = null;
onMounted(() => {
    timer = setInterval(() => { now.value = Date.now(); }, 1000);
    refreshTimer = setInterval(() => {
        router.reload({
            only: ['leaderboard', 'totalTickets', 'myEntry', 'nowWatching'],
            // Keep the expanded list expanded across auto-refreshes.
            data: { leaderboard_limit: boardLimit.value },
            preserveScroll: true,
            preserveState: true,
        });
    }, 30000);
});
onUnmounted(() => {
    if (timer) clearInterval(timer);
    if (refreshTimer) clearInterval(refreshTimer);
});
watch(() => props.nowWatching, () => { receivedAt.value = Date.now(); });

const timeLeft = computed(() => {
    if (!props.contest?.ends_at) return null;
    let ms = new Date(props.contest.ends_at).getTime() - now.value;
    if (ms <= 0) return { d: 0, h: 0, m: 0, s: 0, ended: true };
    const s = Math.floor(ms / 1000);
    return {
        d: Math.floor(s / 86400),
        h: Math.floor((s % 86400) / 3600),
        m: Math.floor((s % 3600) / 60),
        s: s % 60,
        ended: false,
    };
});

const fmtWatch = (seconds) => {
    const s = Math.max(0, Math.floor(seconds || 0));
    const h = Math.floor(s / 3600);
    const m = Math.floor((s % 3600) / 60);
    if (h > 0) return `${h}h ${m}m`;
    if (m > 0) return `${m}m ${s % 60}s`;
    return `${s}s`;
};

const liveDelta = computed(() => Math.max(0, Math.floor((now.value - receivedAt.value) / 1000)));
const entrySeconds = (entry) => Math.max(0, Number(entry?.seconds || 0)) + (entry?.is_current ? liveDelta.value : 0);
const currentWatchSeconds = computed(() => props.nowWatching ? entrySeconds({
    seconds: props.nowWatching.seconds,
    is_current: true,
}) : 0);
const maxSeconds = computed(() => Math.max(1, ...(props.leaderboard || []).map(entrySeconds)));
const odds = (tickets) => props.totalTickets > 0 ? (tickets / props.totalTickets * 100) : 0;
const photo = (u) => u?.profile_photo_path ? '/storage/' + u.profile_photo_path : '/images/null.jpg';
const rankColor = (i) => i === 0 ? 'text-yellow-400' : i === 1 ? 'text-gray-300' : i === 2 ? 'text-amber-600' : 'text-gray-500';

// Period leaderboard: top 10 by default, "Show all" grows it 40 at a time by
// scrolling (same growing-limit pattern as the all-time stats below), so
// everyone competing can find themselves.
const BOARD_PAGE = 40;
const boardLimit = ref(10);
const boardExpanded = ref(false);
const boardLoading = ref(false);
const boardDone = computed(() =>
    boardLimit.value >= 400 || (props.leaderboard?.length ?? 0) < boardLimit.value);

const fetchBoard = (limit) => {
    boardLoading.value = true;
    router.reload({
        only: ['leaderboard'],
        data: { leaderboard_limit: limit },
        preserveScroll: true,
        preserveState: true,
        onFinish: () => { boardLoading.value = false; boardLimit.value = limit; },
    });
};
const toggleBoard = () => {
    if (boardExpanded.value) {
        boardExpanded.value = false;
        boardLimit.value = 10;
        fetchBoard(10);
        return;
    }
    boardExpanded.value = true;
    fetchBoard(BOARD_PAGE);
};
const onBoardScroll = (e) => {
    const el = e.target;
    if (!boardExpanded.value || boardLoading.value || boardDone.value) return;
    if (el.scrollTop + el.clientHeight >= el.scrollHeight - 80) {
        fetchBoard(boardLimit.value + BOARD_PAGE);
    }
};

// All-time watch stats: lazy Inertia prop, fetched when the view is expanded.
// Loads 40 rows at a time; scrolling near the bottom of the list fetches the
// next 40 (the server just re-slices with a bigger limit).
const WATCHERS_PAGE = 40;
const showAllTime = ref(false);
const allTimeLoading = ref(false);
const watchersLimit = ref(0); // last limit actually requested
const watchersDone = computed(() =>
    watchersLimit.value > 0 && (props.allTimeWatchers?.length ?? 0) < watchersLimit.value);

const fetchWatchers = (limit, onDone) => {
    allTimeLoading.value = true;
    router.reload({
        only: ['allTimeWatchers'],
        data: { watchers_limit: limit },
        preserveScroll: true,
        preserveState: true,
        onFinish: () => { allTimeLoading.value = false; watchersLimit.value = limit; onDone?.(); },
    });
};
const toggleAllTime = () => {
    if (showAllTime.value) { showAllTime.value = false; return; }
    if (props.allTimeWatchers) { showAllTime.value = true; return; }
    fetchWatchers(WATCHERS_PAGE, () => { showAllTime.value = true; });
};
const onWatchersScroll = (e) => {
    const el = e.target;
    if (allTimeLoading.value || watchersDone.value) return;
    if (el.scrollTop + el.clientHeight >= el.scrollHeight - 80) {
        fetchWatchers(watchersLimit.value + WATCHERS_PAGE);
    }
};

// Long durations read better with days: "3d 7h", then "5h 12m", then "42m".
const fmtLong = (seconds) => {
    const s = Math.max(0, Math.floor(seconds || 0));
    const d = Math.floor(s / 86400), h = Math.floor((s % 86400) / 3600), m = Math.floor((s % 3600) / 60);
    if (d) return `${d}d ${h}h`;
    if (h) return `${h}h ${m}m`;
    return `${m}m`;
};

// How a past contest's prize was settled (see DefragliveContest statuses).
const statusLabel = (s) => ({
    paid: 'paid',
    donated: 'donated to the site',
    forwarded: 'forwarded to next winner',
}[s] || 'payout pending');
const statusColor = (s) => ({
    paid: 'text-emerald-500',
    donated: 'text-sky-400',
    forwarded: 'text-indigo-400',
}[s] || 'text-amber-500');
</script>

<template>
    <Head :title="$t('DefragLive Watch Contest')" />

    <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8 py-8">
        <!-- Evergreen intro: what this is + live stream. Independent of any
             single contest, so the explainer is never duplicated per period. -->
        <div class="rounded-2xl border border-purple-500/20 bg-black/40 backdrop-blur-sm p-6 md:p-8 mb-6 shadow-2xl">
            <div class="grid lg:grid-cols-4 gap-6 lg:gap-8 items-start">
                <div class="lg:col-span-3">
                    <!-- Label and channel share a line: two short strings do
                         not each deserve one, and stacking them pushed the
                         actual heading down to a third row. -->
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1 mb-1">
                        <span class="text-xs uppercase tracking-widest text-purple-300/80 font-semibold">{{ $t('DefragLive Watch Contest') }}</span>
                        <a :href="`https://twitch.tv/${TWITCH_CHANNEL}`" target="_blank" rel="noopener"
                            class="inline-flex items-center gap-1.5 text-sm font-semibold text-[#a970ff] hover:text-[#bf94ff] hover:underline">
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M11.64 5.93h1.43v4.28h-1.43m3.93-4.28H17v4.28h-1.43M7 2L3.43 5.57v12.86h4.28V22l3.58-3.57h2.85L20.57 12V2m-1.43 9.29l-2.85 2.85h-2.86l-2.5 2.5v-2.5H7.71V3.43h11.43z"/></svg>
                            twitch.tv/{{ TWITCH_CHANNEL }}
                        </a>
                    </div>
                    <h1 class="text-2xl md:text-3xl font-black text-white">{{ $t('Get watched, get rewarded') }}</h1>
                    <!-- Kept to what this is and who it is for. How the winner
                         is chosen has its own section below; saying any of it
                         twice is what made this column a wall. -->
                    <div class="text-gray-400 mt-3 text-sm space-y-2 leading-relaxed">
                        <p>
                            <span class="text-gray-200 font-semibold">{{ $t('DefragLive') }}</span> {{ $t('streams defrag to Twitch around the clock, so anyone can watch from anywhere with no install and no setup. The players it spectates are the ones putting on that show, and this contest is') }} <span class="text-gray-200 font-semibold">{{ $t('their reward for it') }}</span>.
                        </p>
                        <p>
                            {{ $t('Let the bot watch you and you are in. There is nothing to sign up for and nothing to play differently.') }}
                        </p>
                        <p class="text-xs text-gray-500">
                            <Link href="/defraglive/maps" class="text-[#a970ff] hover:text-[#bf94ff] font-semibold hover:underline">{{ $t('Full map log') }}</Link>
                            {{ $t('- every map streamed, when, and who was spectated.') }}
                        </p>
                    </div>

                    <div class="mt-3 flex items-start gap-2 text-sm text-amber-100 bg-amber-500/20 border border-amber-400/40 rounded-lg px-3.5 py-2.5">
                        <svg class="w-5 h-5 shrink-0 mt-px text-amber-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <!-- One sentence, not five fragments. Split like that
                             it could not be translated: word order moves the
                             emphasised part somewhere else in most languages
                             and the pieces no longer line up. -->
                        <span v-html="$t('This prize is <strong>paid out from the admin\'s own funds</strong>. It is <strong>not</strong> taken from the donations you send to support the site: it is a personal gift to the community.')"></span>
                    </div>

                    <!-- Heading stays in this column so it follows the prize
                         note directly. Put below the grid it would sit under
                         the stream card, which is taller than this column, and
                         leave a hole the height of the difference. -->
                    <div class="mt-4 flex flex-wrap items-baseline gap-x-3 gap-y-1">
                        <h2 class="text-lg font-bold text-white">{{ $t('How the winner is picked') }}</h2>
                        <p class="text-sm text-gray-500">{{ $t('Watch time buys tickets, three tickets are drawn, and the drawn person who watched the most wins.') }}</p>
                    </div>
                </div>

                <!-- Watch-live card - the whole thing links to Twitch. The
                     interactive DefragLive overlay (player list, controls) only
                     renders on twitch.tv itself (Twitch never shows extensions
                     inside an embedded player), so we show the current map +
                     who's being spectated from our own data and send people to
                     Twitch for the full experience. -->
                <a :href="`https://twitch.tv/${TWITCH_CHANNEL}`" target="_blank" rel="noopener"
                    class="group block rounded-xl border border-[#9147ff]/30 hover:border-[#9147ff]/70 bg-black/40 backdrop-blur-sm shadow-2xl overflow-hidden transition-colors">
                    <!-- Map thumbnail backdrop with the spectated player on top -->
                    <div class="relative aspect-video bg-gray-950">
                        <img :src="nowWatching?.map_thumbnail ? `/storage/${nowWatching.map_thumbnail}` : '/images/unknown.jpg'"
                            class="absolute inset-0 w-full h-full object-cover opacity-80 group-hover:opacity-100 group-hover:scale-105 transition duration-300"
                            onerror="this.onerror=null;this.src='/images/unknown.jpg'" alt="">
                        <div class="absolute inset-0 bg-gradient-to-t from-black via-black/40 to-transparent"></div>

                        <div class="absolute top-3 left-3 inline-flex items-center gap-2 text-xs font-bold uppercase tracking-widest text-red-400 bg-black/60 rounded px-2 py-1">
                            <span class="relative flex h-2.5 w-2.5">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-500 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-red-500"></span>
                            </span>
                            {{ $t('Live now') }}
                        </div>

                        <!-- Play affordance on hover -->
                        <div class="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 transition">
                            <div class="w-16 h-16 rounded-full bg-[#9147ff]/90 flex items-center justify-center shadow-2xl">
                                <svg class="w-7 h-7 text-white ml-1" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
                            </div>
                        </div>

                        <div class="absolute bottom-0 inset-x-0 p-4">
                            <template v-if="nowWatching">
                                <div class="text-[11px] uppercase tracking-wide text-gray-300">{{ $t('Now spectating') }}</div>
                                <div class="text-2xl md:text-3xl font-black leading-tight" v-html="q3tohtml(nowWatching.name)"></div>
                                <div v-if="nowWatching.mapname" class="text-sm text-gray-300 mt-0.5">{{ $t('on') }} <span class="font-semibold text-white">{{ nowWatching.mapname }}</span></div>
                                <div class="text-xs text-purple-200 mt-1">{{ $t('Watched for :time', { time: fmtWatch(currentWatchSeconds) }) }}</div>
                            </template>
                            <template v-else>
                                <div class="text-xl font-black text-white">{{ $t('DefragLive') }}</div>
                                <div class="text-sm text-gray-300">{{ $t('streaming defrag 24/7') }}</div>
                            </template>
                        </div>
                    </div>

                    <div class="flex flex-col items-center text-center gap-2 p-4">
                        <span class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg bg-[#9147ff] group-hover:bg-[#772ce8] text-white font-bold transition-colors w-full justify-center">
                            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor"><path d="M11.64 5.93h1.43v4.28h-1.43m3.93-4.28H17v4.28h-1.43M7 2L3.43 5.57v12.86h4.28V22l3.58-3.57h2.85L20.57 12V2m-1.43 9.29l-2.85 2.85h-2.86l-2.5 2.5v-2.5H7.71V3.43h11.43z"/></svg>
                            {{ $t('Watch on Twitch') }}
                        </span>
                        <div class="text-[11px] text-gray-500">{{ $t('The interactive overlay (player list, controls) works on Twitch.') }}</div>
                    </div>
                </a>
            </div>

            <!-- Under the grid rather than beside it, so it spans the stream
                 card as well and gets the whole width to lay out across. This
                 is the part that keeps being misread - people top the ticket
                 list, do not win, and write in asking why - and inside the
                 text column it was a wall nobody would read. -->
            <div class="mt-3">
            <div class="grid gap-3 md:grid-cols-3">
                <div v-for="(step, i) in [
                    { head: $t('1 minute watched = 1 ticket'), body: $t('Whole minutes, and nothing else earns any. Under a minute across the entire period is no ticket and no entry.') },
                    { head: $t('Three tickets are drawn at random'), body: $t('At the end of the period, from every ticket in the pool. Three different numbers, so up to three different people make the final.') },
                    { head: $t('Of those three, the most watch time wins'), body: $t('The prize goes to whichever drawn person watched the most. A big watcher is likely to be among the three and then wins it; a small one needs to be drawn alone.') },
                ]" :key="i" class="rounded-xl border border-white/10 bg-black/30 p-4">
                    <div class="flex items-center gap-2.5 mb-1.5">
                        <span class="shrink-0 w-6 h-6 rounded-full bg-purple-500/25 border border-purple-400/40 text-purple-200 text-xs font-black flex items-center justify-center">{{ i + 1 }}</span>
                        <span class="text-sm font-bold text-white">{{ step.head }}</span>
                    </div>
                    <p class="text-sm text-gray-400 leading-relaxed">{{ step.body }}</p>
                </div>
            </div>

            <!-- Wraps: collapsed, the details sits beside the note at
                 320px; open, it takes the whole row and drops under the
                 note, so the text is not squeezed into a narrow column
                 with the note floating vertically centered beside it. -->
            <div class="mt-3 flex flex-col lg:flex-row lg:flex-wrap lg:items-start gap-3">
                <p class="flex-1 text-sm text-amber-200 bg-amber-500/10 border border-amber-400/30 rounded-lg px-4 py-2.5">
                    <!-- One sentence, not three fragments. Split around the
                         word "not" it could not survive translation: the
                         negation attaches to the verb in most languages, so
                         the pieces produced "niže ne neznamena" in Czech and
                         "no no significa" in Spanish. -->
                    <span v-html="$t('Being first on the list below does <strong>not</strong> mean you win. It means the draw is most likely to go your way: about a third of the periods for someone holding a tenth of the pool. The rest of the time somebody with less watch time gets drawn without you and takes it.')"></span>
                </p>

                <!-- "It is random" is exactly the claim somebody who has just
                     lost has no reason to take on trust, so the method is
                     written out and the drawn number is stored, not promised. -->
                <details class="lg:w-80 open:lg:w-full shrink-0 rounded-lg border border-white/10 bg-black/30 px-4 py-2.5">
                    <summary class="cursor-pointer select-none text-sm text-purple-300 hover:text-purple-200 transition">
                        {{ $t('How the draw works, exactly') }}
                    </summary>
                    <div class="mt-2.5 pt-2.5 border-t border-white/10 text-sm text-gray-400 space-y-2 md:space-y-0 md:grid md:grid-cols-2 md:gap-x-6 leading-relaxed">
                        <p>
                            {{ $t("Everyone with at least one ticket goes in. The tickets are laid end to end and numbered from 1 to the size of the pool, so 300 tickets is 300 consecutive numbers. Three different numbers are drawn with the operating system's cryptographic random generator. Of the people holding them, the one with the most watch time wins; a tie goes to the number drawn first. It is the textbook weighted raffle, run three times: no seed anyone can guess, nothing that favours a name.") }}
                        </p>
                        <p>
                            {{ $t("The three drawn numbers, who held each, the winner's tickets and the size of the pool are recorded at the moment of the draw and shown under past winners, so a result can be checked instead of taken on trust.") }}
                        </p>
                    </div>
                </details>
            </div>
            </div>
        </div>

        <!-- Current contest strip: the only part that rotates each period. -->
        <div class="rounded-2xl border border-white/10 bg-black/40 backdrop-blur-sm p-5 md:p-6 mb-6 shadow-2xl">
            <div v-if="contest" class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <div class="text-xs uppercase tracking-wide text-gray-500 mb-1">{{ $t('Current contest') }}</div>
                    <h2 class="text-xl md:text-2xl font-black text-white">{{ contest.title }}</h2>
                    <div v-if="nowWatching" class="mt-2 flex items-center gap-2 text-sm">
                        <span class="relative flex h-2.5 w-2.5">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-500 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-red-500"></span>
                        </span>
                        <span class="text-gray-400">{{ $t('Now spectating') }}</span>
                        <span class="font-bold" v-html="q3tohtml(nowWatching.name)"></span>
                        <span v-if="nowWatching.mapname" class="text-gray-500">{{ $t('on :map', { map: nowWatching.mapname }) }}</span>
                        <span class="text-purple-300 font-semibold">{{ $t('for :time', { time: fmtWatch(currentWatchSeconds) }) }}</span>
                    </div>
                </div>

                <div class="flex items-center gap-6 shrink-0">
                    <div class="text-right">
                        <div class="text-3xl md:text-4xl font-black text-emerald-400">
                            {{ formatPrize(contest.prize_amount, contest.prize_currency) }}
                        </div>
                        <div class="text-sm font-bold uppercase tracking-widest text-gray-300">{{ $t('prize') }}</div>
                        <!-- Pool transparency: base prize vs what previous winners forwarded in -->
                        <div v-if="contest.carried_over_amount > 0" class="text-[11px] text-purple-200/90 mt-1 leading-snug">
                            {{ $t(':base base + :carried carried over from previous winners', { base: formatPrize(contest.prize_amount - contest.carried_over_amount, contest.prize_currency), carried: formatPrize(contest.carried_over_amount, contest.prize_currency) }) }}
                        </div>
                    </div>
                    <div v-if="timeLeft && !timeLeft.ended" class="flex gap-2.5 font-mono">
                        <div v-for="part in [['d', timeLeft.d], ['h', timeLeft.h], ['m', timeLeft.m], ['s', timeLeft.s]]" :key="part[0]"
                            class="bg-black/40 rounded-xl px-3.5 py-2 text-center min-w-[60px]">
                            <div class="text-3xl md:text-4xl font-black text-white tabular-nums leading-none">{{ String(part[1]).padStart(2, '0') }}</div>
                            <div class="text-sm font-bold uppercase tracking-widest text-gray-300 mt-1">{{ part[0] }}</div>
                        </div>
                    </div>
                    <div v-else-if="timeLeft" class="text-base text-amber-400 font-semibold">{{ $t('Period ended') }}<br>{{ $t('awaiting draw') }}</div>
                </div>
            </div>

            <div v-else class="text-center py-2 text-gray-400">
                <span class="font-semibold text-gray-300">{{ $t('No contest running right now.') }}</span>
                {{ $t("The next one starts soon - keep getting spectated and you'll be in it.") }}
            </div>
        </div>

        <!-- My odds -->
        <div v-if="myEntry" class="rounded-xl border border-emerald-500/25 bg-black/40 backdrop-blur-sm p-4 mb-6 flex flex-wrap items-center gap-x-8 gap-y-2">
            <div>
                <div class="text-xs uppercase text-gray-500">{{ $t('Your rank') }}</div>
                <div class="text-2xl font-black text-white">#{{ myEntry.rank }}</div>
            </div>
            <div>
                <div class="text-xs uppercase text-gray-500">{{ $t('Watched') }}</div>
                <div class="text-2xl font-black text-white">{{ fmtWatch(entrySeconds(myEntry)) }}</div>
            </div>
            <div>
                <div class="text-xs uppercase text-gray-500">{{ $t('Tickets') }}</div>
                <div class="text-2xl font-black text-white">{{ myEntry.tickets }}</div>
            </div>
            <div>
                <div class="text-xs uppercase text-gray-500">{{ $t('Chance in the draw') }}</div>
                <div class="text-2xl font-black text-emerald-400">{{ myEntry.odds }}%</div>
                <div class="text-[11px] text-gray-500">{{ $t('odds, not a place') }}</div>
            </div>
        </div>

        <!-- Leaderboard -->
        <div class="rounded-2xl border border-white/10 bg-black/40 backdrop-blur-sm overflow-hidden mb-8 shadow-2xl">
            <div class="px-4 py-3 border-b border-white/10 flex items-center justify-between">
                <div>
                    <h2 class="font-bold text-gray-200">{{ $t('Most watched this period') }}</h2>
                    <p class="text-xs text-gray-500 mt-0.5">{{ $t('Watch time and odds, not a ranking of who wins - the winner is drawn.') }}</p>
                </div>
                <span class="text-xs text-gray-500">{{ $tc(':count ticket in the pool|:count tickets in the pool', totalTickets) }}</span>
            </div>

            <div v-if="leaderboard.length" class="divide-y divide-white/5"
                :class="boardExpanded ? 'max-h-[32rem] overflow-y-auto' : ''"
                @scroll="onBoardScroll">
                <div v-for="(e, i) in leaderboard" :key="i" class="flex items-center gap-3 px-4 py-3 hover:bg-white/5">
                    <div class="w-7 text-center font-black text-lg" :class="rankColor(i)">{{ i + 1 }}</div>

                    <img :src="photo(e.user)" class="w-8 h-8 rounded-full object-cover shrink-0" alt="">
                    <img v-if="e.user?.country" :src="`/images/flags/${e.user.country}.png`" class="w-5 h-3.5 shrink-0" onerror="this.style.display='none'">

                    <div class="min-w-0 flex-1">
                        <component :is="e.user ? Link : 'span'" :href="e.user ? `/profile/${e.user.id}` : undefined"
                            class="font-semibold truncate block" :class="e.user ? 'hover:underline' : ''">
                            <span v-html="q3tohtml(e.name)"></span>
                            <span v-if="e.is_current" class="ml-2 text-[10px] uppercase tracking-wider text-red-400">{{ $t('Live') }}</span>
                        </component>
                        <div class="mt-1 h-1.5 rounded-full bg-gray-800 overflow-hidden">
                            <div class="h-full rounded-full bg-gradient-to-r from-purple-500 to-emerald-400"
                                :style="{ width: Math.max(3, (entrySeconds(e) / maxSeconds) * 100) + '%' }"></div>
                        </div>
                    </div>

                    <div class="text-right shrink-0">
                        <div class="font-bold text-white tabular-nums">{{ fmtWatch(entrySeconds(e)) }}</div>
                        <div class="text-xs text-gray-500">{{ $tc(':count ticket|:count tickets', e.tickets) }} &middot; {{ odds(e.tickets).toFixed(1) }}%</div>
                    </div>
                </div>
            </div>

            <div v-else class="px-4 py-12 text-center text-gray-500">
                {{ $t('No watch time recorded yet this period. Hop on a server the bot is spectating!') }}
            </div>

            <!-- Everyone competing, not just the top 10 - fairness: anyone can
                 find themselves. Expands in place, grows 40 rows per scroll. -->
            <div v-if="leaderboardTotal > 10" class="px-4 py-2.5 border-t border-white/10 text-center">
                <button @click="toggleBoard"
                    class="text-xs font-semibold text-purple-300 hover:text-purple-200 disabled:opacity-50"
                    :disabled="boardLoading">
                    <span v-if="boardLoading">{{ $t('Loading...') }}</span>
                    <span v-else-if="!boardExpanded">{{ $t('Show everyone (:count)', { count: leaderboardTotal }) }}</span>
                    <span v-else>{{ $t('Show top 10 only') }}</span>
                </button>
            </div>

            <!-- Bans issued during this contest: a small factual note for
                 transparency (farmed time was voided) and deterrence. No
                 hours or avatars on purpose - no trophy for cheating. -->
            <div v-if="exclusions?.length" class="px-4 py-3 border-t border-white/10">
                <div class="text-[10px] uppercase tracking-wider text-gray-500 mb-1.5">{{ $t('Removed from this contest') }}</div>
                <div v-for="(x, i) in exclusions" :key="i" class="text-xs text-gray-500">
                    <span class="font-semibold text-gray-400">{{ x.name }}</span>
                    <span class="mx-1">-</span>{{ x.reason }}
                    <span class="mx-1">-</span>{{ x.date }}
                </div>
            </div>
        </div>

        <!-- Past winners - vertical timeline: center line, cards alternating
             left/right, newest draw at the top. Collapses to a single left-lined
             column on mobile. -->
        <div v-if="pastWinners.length">
            <h2 class="font-bold text-gray-300 mb-4">{{ $t('Past winners') }}</h2>
            <div class="relative">
                <div class="absolute top-1 bottom-1 left-4 sm:left-1/2 w-px bg-gradient-to-b from-purple-400/70 via-white/20 to-transparent"></div>
                <div v-for="(w, i) in pastWinners" :key="i"
                    class="relative pl-10 pb-6 sm:pl-0 sm:pb-8 sm:w-[calc(50%-1.5rem)]"
                    :class="[i % 2 === 0 ? 'sm:mr-auto' : 'sm:ml-auto', i > 0 ? 'sm:-mt-[4.5rem]' : '']">
                    <!-- node on the line -->
                    <div class="absolute top-5 left-4 -translate-x-1/2 w-2.5 h-2.5 rounded-full bg-purple-400 ring-4 ring-purple-400/20"
                        :class="i % 2 === 0 ? 'sm:left-auto sm:-right-6 sm:translate-x-1/2' : 'sm:-left-6 sm:-translate-x-1/2'"></div>
                    <!-- connector from card to the line -->
                    <div class="hidden sm:block absolute top-[1.35rem] w-6 h-px bg-white/15"
                        :class="i % 2 === 0 ? '-right-6' : '-left-6'"></div>
                    <div class="text-[11px] text-gray-500 mb-1" :class="i % 2 === 0 ? 'sm:text-right' : ''">{{ w.drawn_at }}</div>
                    <div class="flex items-center justify-between gap-3 rounded-xl border border-white/10 bg-black/40 backdrop-blur-sm px-4 py-3">
                        <div class="min-w-0">
                            <component :is="w.winner_user_id ? Link : 'span'" :href="w.winner_user_id ? `/profile/${w.winner_user_id}` : undefined"
                                class="font-semibold truncate block">
                                <span v-html="q3tohtml(w.winner_name)"></span>
                            </component>
                            <div class="text-xs text-gray-500 truncate">{{ w.title }}</div>
                            <div v-if="w.winner_seconds" class="text-xs text-purple-300/80 truncate">
                                {{ $t('Watched for :time', { time: fmtWatch(w.winner_seconds) }) }}
                                <template v-if="w.total_tickets"> · {{ w.winner_tickets }}/{{ w.total_tickets }} {{ $t('tickets') }} ({{ (w.winner_tickets / w.total_tickets * 100).toFixed(1) }}%)</template>
                            </div>
                            <!-- Best-of-three draws (since Sept 2026): the three
                                 picks in draw order, the winner marked. Older
                                 draws have no picks and show the line above only. -->
                            <div v-if="w.picks" class="mt-1 flex flex-wrap items-baseline gap-x-2 text-xs text-gray-300" :title="$t('Three tickets drawn, most watch time wins')">
                                <!-- Flex with gaps, not spaces: the template
                                     compiler condenses whitespace between tags
                                     and the words ran together. Coloured nicks,
                                     no ticket numbers (they mean nothing to a
                                     reader). -->
                                <span class="text-gray-400">{{ $t('Drawn:') }}</span>
                                <template v-for="(p, pi) in w.picks" :key="pi">
                                    <span v-if="pi > 0" class="text-gray-500">·</span>
                                    <span class="inline-flex items-baseline gap-x-1.5">
                                        <span v-html="q3tohtml(p.name)"></span>
                                        <span>{{ $tc(':count ticket|:count tickets', p.tickets) }}</span>
                                        <span v-if="p.winner" class="text-emerald-400 font-semibold">{{ $t('Winner') }}</span>
                                    </span>
                                </template>
                            </div>
                        </div>
                        <div class="text-right shrink-0">
                            <div class="font-bold text-emerald-400">{{ formatPrize(w.prize_amount, w.prize_currency) }}</div>
                            <div v-if="w.carried_over_amount > 0" class="text-[10px] text-purple-300/80 leading-tight">
                                incl. {{ formatPrize(w.carried_over_amount, w.prize_currency) }} carried over
                            </div>
                            <div class="text-[11px] uppercase" :class="statusColor(w.status)">{{ statusLabel(w.status) }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Hall of Fame - all-time winners -->
        <div v-if="hallOfFame.length" class="mt-8">
            <h2 class="font-bold text-gray-300 mb-3 flex items-center gap-2">
                <span>🏆</span> {{ $t('Hall of Fame') }} <span class="text-xs font-normal text-gray-500">{{ $t('all-time winners') }}</span>
            </h2>
            <div class="rounded-2xl border border-white/10 bg-black/40 backdrop-blur-sm overflow-hidden shadow-2xl divide-y divide-white/5">
                <div v-for="(h, i) in hallOfFame" :key="i" class="flex items-center gap-3 px-4 py-3 hover:bg-white/5">
                    <div class="w-7 text-center font-black text-lg" :class="rankColor(i)">{{ i + 1 }}</div>
                    <img :src="photo(h.user)" class="w-8 h-8 rounded-full object-cover shrink-0" alt="">
                    <img v-if="h.user?.country" :src="`/images/flags/${h.user.country}.png`" class="w-5 h-3.5 shrink-0" onerror="this.style.display='none'">
                    <component :is="h.user ? Link : 'span'" :href="h.user ? `/profile/${h.user.id}` : undefined"
                        class="min-w-0 flex-1 font-semibold truncate" :class="h.user ? 'hover:underline' : ''">
                        <span v-html="q3tohtml(h.name)"></span>
                    </component>
                    <div class="text-right shrink-0">
                        <div class="font-bold text-white">{{ h.wins }} {{ h.wins === 1 ? 'win' : 'wins' }}</div>
                        <div class="text-xs text-emerald-400">{{ formatPrize(h.total, h.currency) }} won</div>
                        <div v-if="h.seconds" class="text-[11px] text-purple-300/80">{{ fmtWatch(h.seconds) }} watched</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- All-time watch stats - every watcher ever, loaded on demand -->
        <div class="mt-8">
            <button @click="toggleAllTime"
                class="w-full flex items-center justify-center gap-2 rounded-xl border border-white/10 bg-black/40 backdrop-blur-sm px-4 py-3 text-sm font-semibold text-gray-300 hover:bg-white/5 transition-colors">
                <span>📊</span>
                <span>{{ showAllTime ? 'Hide all-time watch stats' : 'Show all-time watch stats' }}</span>
                <svg class="w-4 h-4 transition-transform" :class="showAllTime ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                <svg v-if="allTimeLoading" class="w-4 h-4 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10" class="opacity-25"/><path d="M22 12a10 10 0 0 1-10 10" class="opacity-75"/></svg>
            </button>
            <div v-if="showAllTime && allTimeWatchers?.length" class="mt-3 rounded-2xl border border-white/10 bg-black/40 backdrop-blur-sm overflow-hidden shadow-2xl">
                <div class="px-4 py-2.5 text-xs text-gray-500 border-b border-white/5">
                    {{ $t('Everyone who has ever been watched on the DefragLive stream - total spectated time across all contests.') }}
                </div>
                <div class="max-h-96 overflow-y-auto divide-y divide-white/5" @scroll.passive="onWatchersScroll">
                    <div v-for="(t, i) in allTimeWatchers" :key="i" class="flex items-center gap-3 px-4 py-2.5 hover:bg-white/5">
                        <div class="w-7 text-center font-black" :class="rankColor(i)">{{ i + 1 }}</div>
                        <img :src="photo(t.user)" class="w-7 h-7 rounded-full object-cover shrink-0" alt="">
                        <img v-if="t.user?.country" :src="`/images/flags/${t.user.country}.png`" class="w-5 h-3.5 shrink-0" onerror="this.style.display='none'">
                        <component :is="t.user ? Link : 'span'" :href="t.user ? `/profile/${t.user.id}` : undefined"
                            class="min-w-0 flex-1 font-semibold truncate text-sm" :class="t.user ? 'hover:underline' : ''">
                            <span v-html="q3tohtml(t.name)"></span>
                        </component>
                        <div class="text-right shrink-0">
                            <div class="font-bold text-purple-300 text-sm">{{ fmtLong(t.seconds) }}</div>
                            <div class="text-[11px] text-gray-500">{{ t.sessions }} {{ t.sessions === 1 ? 'session' : 'sessions' }}</div>
                        </div>
                    </div>
                    <div v-if="allTimeLoading" class="py-3 text-center text-xs text-gray-500">{{ $t('Loading more…') }}</div>
                    <div v-else-if="watchersDone" class="py-3 text-center text-xs text-gray-600">{{ $t("That's everyone!") }}</div>
                </div>
            </div>
            <div v-else-if="showAllTime" class="mt-3 text-center text-sm text-gray-500 py-4">{{ $t('No watch time recorded yet.') }}</div>
        </div>
    </div>
</template>
