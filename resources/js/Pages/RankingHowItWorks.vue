<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { t } from '@/utils/i18n';

const props = defineProps({
    categoryStats: { type: Object, default: () => ({}) },
    categoryStatsAsOf: { type: String, default: null },
    ratingSettings: { type: Object, default: () => ({}) },
});

// Display order - matches the rust service and ranking UI.
const STATS_CATEGORIES = ['overall', 'strafe', 'lg', 'rocket', 'plasma', 'grenade', 'slick', 'tele', 'bfg'];
const CTF_MODES = ['ctf1', 'ctf2', 'ctf3', 'ctf4', 'ctf5', 'ctf6', 'ctf7'];

// Display label for a category code - the code itself stays the lookup key,
// this is only what the live table prints. Same wording as section 9 below.
// Written as literal t() calls rather than t(LABELS[cat]): lang:sync collects
// only the keys it can see, so a variable key would never reach cs.json.
function categoryLabel(cat) {
    const labels = {
        overall: t('Overall'),
        strafe: t('Strafe'),
        lg: 'LG',
        rocket: 'Rocket',
        plasma: t('Plasma'),
        grenade: 'Grenade',
        slick: t('Slick'),
        tele: t('Tele'),
        bfg: 'BFG',
    };

    return labels[cat] ?? cat;
}

// Categories that actually have data for the 'run' mode (skip empty rows).
const runCategoriesWithData = computed(() => {
    const run = props.categoryStats?.run ?? {};
    return STATS_CATEGORIES.filter(cat => run[cat] && (run[cat].vq3 || run[cat].cpm));
});

// CTF modes that actually have any 'overall' data in either physics.
const ctfModesWithData = computed(() => {
    return CTF_MODES.filter(mode => {
        const o = props.categoryStats?.[mode]?.overall;
        return o && (o.vq3 || o.cpm);
    });
});

const hasCategoryStats = computed(() =>
    runCategoriesWithData.value.length > 0 || ctfModesWithData.value.length > 0
);

function statCell(mode, category, physics) {
    return props.categoryStats?.[mode]?.[category]?.[physics] ?? null;
}

function formatAsOf(raw) {
    if (!raw) return null;
    const d = new Date(raw.includes('T') ? raw : raw.replace(' ', 'T') + 'Z');
    if (isNaN(d.getTime())) return raw;
    return d.toISOString().slice(0, 10);
}

// Live parameters from rating_settings DB (admin-editable via Filament).
// Defaults are fallbacks only - the prop is the source of truth at runtime.
const s = computed(() => {
    const r = props.ratingSettings ?? {};
    const num = (key, fallback) => {
        const v = r[key];
        return v === undefined || v === null || v === '' ? fallback : Number(v);
    };
    return {
        cfg_a: num('cfg_a', 1.2),
        cfg_b: num('cfg_b', 1.33),
        cfg_m: num('cfg_m', 0.3),
        cfg_v: num('cfg_v', 0.1),
        cfg_q: num('cfg_q', 0.5),
        cfg_d: num('cfg_d', 0.02),
        mult_l: num('mult_l', 1.0),
        mult_n: num('mult_n', 2.0),
        rank_k: num('rank_k', 0.80),
        rank_n: num('rank_n', 0.95),
        rank_p: num('rank_p', 0.05),
        min_map_players: num('min_map_players', 5),
        min_top1_time: num('min_top1_time', 500),
        max_tied_wr_players: num('max_tied_wr_players', 3),
        min_total_records: num('min_total_records', 10),
    };
});

// Trim trailing zeros so 1.20 → "1.2" and 0.3300 → "0.33".
function fmt(value, digits = 4) {
    if (value === undefined || value === null || Number.isNaN(value)) return '–';
    const fixed = Number(value).toFixed(digits);
    return fixed.replace(/\.?0+$/, '') || '0';
}

function calcRankMultiplier(totalPlayers, yourRank) {
    const cfg = s.value;
    if (totalPlayers <= 1 || yourRank === 0) return 1.0;
    const t = Math.min(Math.max((yourRank - 1) / (totalPlayers - 1), 0), 1);
    if (t === 0) return 1.0;
    const exponent = Math.pow(t, cfg.rank_n + cfg.rank_p * (1 - t));
    return Math.pow(cfg.rank_k, exponent);
}

function calcMapScore(reltime) {
    const c = s.value;
    return 1000 * (c.cfg_a + (-c.cfg_a / Math.pow(1 + c.cfg_q * Math.exp(-c.cfg_b * (reltime - c.cfg_m)), 1 / c.cfg_v)));
}

function calcMultiplier(players, median) {
    const cfg = s.value;
    const k = Math.max(median / 2, 1);
    const x = players;
    return (cfg.mult_l * Math.pow(x, cfg.mult_n)) / (Math.pow(k, cfg.mult_n) + Math.pow(x, cfg.mult_n));
}

function calcPlayerRating(scores) {
    const cfg = s.value;
    const sorted = [...scores].sort((a, b) => b - a);
    let weightedSum = 0;
    let weightSum = 0;
    for (let i = 0; i < sorted.length; i++) {
        const weight = Math.exp(-cfg.cfg_d * i);
        weightedSum += sorted[i] * weight;
        weightSum += weight;
    }
    let rating = weightedSum / weightSum;
    if (sorted.length < cfg.min_total_records) {
        rating *= sorted.length / cfg.min_total_records;
    }
    return rating;
}

// Median used in examples - prefer VQ3 Overall live data, fall back to 13.
const exampleMedian = computed(() => {
    const live = props.categoryStats?.run?.overall?.vq3?.median;
    return live && live > 0 ? Math.round(live) : 13;
});
const exampleK = computed(() => Math.max(exampleMedian.value / 2, 1));

const exampleWR = 15000;
const exampleTime = 18500;
const exampleReltime = exampleTime / exampleWR;
const exampleBaseScore = computed(() => calcMapScore(exampleReltime));
const exampleMultiplier = computed(() => calcMultiplier(25, exampleMedian.value));
const exampleFinalScore = computed(() => exampleBaseScore.value * exampleMultiplier.value);

const exampleScores = [850, 780, 720, 650, 600, 550, 500, 480, 420, 380, 350, 300];
const exampleRating = computed(() => calcPlayerRating(exampleScores));
const exampleFewScores = [850, 780, 720];
const exampleFewRating = computed(() => calcPlayerRating(exampleFewScores));

// Share of total weight carried by the top-N records in the limit of a
// very large record count: 1 - exp(-D*N). For a finite roster the share
// is even higher.
function topNWeightShare(n) {
    return (1 - Math.exp(-s.value.cfg_d * n)) * 100;
}
const top100Share = computed(() => topNWeightShare(100));
const top200Share = computed(() => topNWeightShare(200));
</script>

<template>
    <Head :title="$t('How Rankings Work')" />

    <div class="min-h-screen bg-gray-950 text-gray-300">
        <div class="max-w-4xl mx-auto px-4 md:px-6 lg:px-8 py-10">
            <!-- Back link -->
            <Link href="/ranking" class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-blue-400 transition-colors mb-6">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                </svg>
                {{ $t('Back to Rankings') }}
            </Link>

            <!-- WIP Banner -->
            <div class="bg-red-600 border-4 border-red-800 rounded-xl px-4 py-4 text-center mb-8">
                <div class="flex items-center justify-center gap-3 mb-1">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-7 h-7 text-white"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" /></svg>
                    <span class="text-white font-black text-lg sm:text-xl uppercase tracking-wide">{{ $t('Work in Progress') }}</span>
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-7 h-7 text-white"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" /></svg>
                </div>
                <p class="text-white/90 text-sm sm:text-base font-semibold">{{ $t('The ranking system is still under active development. Parameters are being tuned and formulas may change. Please give me time until the system is finalized.') }}</p>
            </div>

            <h1 class="text-2xl md:text-3xl font-black text-gray-200 mb-2">{{ $t('How Rankings Work') }}</h1>
            <p class="text-gray-500 text-sm mb-10">{{ $t('A complete breakdown of the ranking algorithm, step by step.') }}</p>

            <!-- Table of contents -->
            <div class="bg-gray-900/50 border border-gray-800 rounded-xl p-4 mb-10">
                <div class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">{{ $t('Contents') }}</div>
                <div class="grid grid-cols-2 gap-1 text-sm">
                    <a href="#overview" class="text-blue-400 hover:text-blue-300">{{ $t('1. Overview') }}</a>
                    <a href="#map-eligibility" class="text-blue-400 hover:text-blue-300">{{ $t('2. Map Eligibility') }}</a>
                    <a href="#reltime" class="text-blue-400 hover:text-blue-300">{{ $t('3. Relative Time (reltime)') }}</a>
                    <a href="#base-score" class="text-blue-400 hover:text-blue-300">{{ $t('4. Base Map Score') }}</a>
                    <a href="#multiplier" class="text-blue-400 hover:text-blue-300">{{ $t('5. Map Multiplier') }}</a>
                    <a href="#rank-multiplier" class="text-blue-400 hover:text-blue-300">{{ $t('6. Rank Multiplier') }}</a>
                    <a href="#final-score" class="text-blue-400 hover:text-blue-300">{{ $t('7. Final Map Score') }}</a>
                    <a href="#player-rating" class="text-blue-400 hover:text-blue-300">{{ $t('8. Player Rating') }}</a>
                    <a href="#categories" class="text-blue-400 hover:text-blue-300">{{ $t('9. Categories') }}</a>
                    <a href="#updates" class="text-blue-400 hover:text-blue-300">{{ $t('10. Real-time Updates') }}</a>
                    <a href="#full-example" class="text-blue-400 hover:text-blue-300">{{ $t('11. Full Example') }}</a>
                </div>
            </div>

            <!-- 1. Overview -->
            <section id="overview" class="mb-12">
                <h2 class="text-2xl font-bold text-gray-200 mb-3 flex items-center gap-2">
                    <span class="text-blue-400 text-lg font-mono">1.</span> {{ $t('Overview') }}
                </h2>
                <p class="mb-3">{{ $t('The ranking system measures how well a player performs across all maps they have records on. The process works in 5 stages:') }}</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="bg-gray-900/60 border border-gray-800 rounded-lg p-3">
                        <div class="text-sm font-bold text-green-400 mb-1">{{ $t('1. Relative Time') }}</div>
                        <div class="text-xs text-gray-400">{{ $t('Your time is compared to the fastest time by another player to get a ratio (reltime).') }}</div>
                    </div>
                    <div class="bg-gray-900/60 border border-gray-800 rounded-lg p-3">
                        <div class="text-sm font-bold text-yellow-400 mb-1">{{ $t('2. Base Score') }}</div>
                        <div class="text-xs text-gray-400">{{ $t('The reltime is fed into a logistic curve to produce a score (0-1000).') }}</div>
                    </div>
                    <div class="bg-gray-900/60 border border-gray-800 rounded-lg p-3">
                        <div class="text-sm font-bold text-purple-400 mb-1">{{ $t('3. Map Multiplier') }}</div>
                        <div class="text-xs text-gray-400">{{ $t('Score is scaled by how many players the map has (popular maps count more).') }}</div>
                    </div>
                    <div class="bg-gray-900/60 border border-gray-800 rounded-lg p-3">
                        <div class="text-sm font-bold text-pink-400 mb-1">{{ $t('4. Rank Multiplier') }}</div>
                        <div class="text-xs text-gray-400">{{ $t('Score is further scaled by your rank on the map (beating more players counts more).') }}</div>
                    </div>
                    <div class="bg-gray-900/60 border border-gray-800 rounded-lg p-3 sm:col-span-2">
                        <div class="text-sm font-bold text-red-400 mb-1">{{ $t('5. Player Rating') }}</div>
                        <div class="text-xs text-gray-400">{{ $t('All map scores are combined with exponential weighting into a single rating.') }}</div>
                    </div>
                </div>
            </section>

            <!-- 2. Map Eligibility -->
            <section id="map-eligibility" class="mb-12">
                <h2 class="text-2xl font-bold text-gray-200 mb-3 flex items-center gap-2">
                    <span class="text-blue-400 text-lg font-mono">2.</span> {{ $t('Map Eligibility') }}
                </h2>
                <p class="mb-3">{{ $t('Not all maps are included in rankings. A map must meet these criteria:') }}</p>
                <div class="bg-gray-900/60 border border-gray-800 rounded-xl p-4 space-y-2">
                    <div class="flex items-start gap-2">
                        <span class="text-green-400 mt-0.5 font-bold">&#x2713;</span>
                        <span>{{ $t('At least') }} <strong class="text-white">{{ $tc(':count unique player|:count unique players', s.min_map_players) }}</strong> {{ $t('must have a record on the map for the given physics (VQ3 and CPM are counted separately)') }}</span>
                    </div>
                    <div class="flex items-start gap-2">
                        <span class="text-green-400 mt-0.5 font-bold">&#x2713;</span>
                        <span>{{ $t('The fastest time must be at least') }} <strong class="text-white">{{ s.min_top1_time }}ms</strong> {{ $t('(:seconds seconds) - eliminates bugged/trivial maps', { seconds: (s.min_top1_time / 1000).toFixed(1) }) }}</span>
                    </div>
                    <div class="flex items-start gap-2">
                        <span class="text-green-400 mt-0.5 font-bold">&#x2713;</span>
                        <span>{{ $t('No more than') }} <strong class="text-white">{{ $tc(':count player|:count players', s.max_tied_wr_players) }}</strong> {{ $t('can share the exact same WR time - if :count+ players have identical best times, the map is considered "free WR" and is excluded', { count: s.max_tied_wr_players + 1 }) }}</span>
                    </div>
                </div>
                <div class="mt-3 bg-blue-500/10 border border-blue-500/20 rounded-lg p-3 text-xs text-blue-300">
                    <strong>{{ $t('Why?') }}</strong> {{ $t("Maps with very few players or trivially short times don't provide meaningful competitive data. Free WR maps (where many players easily reach the same ceiling) don't differentiate skill.") }}
                </div>
            </section>

            <!-- 3. Reltime -->
            <section id="reltime" class="mb-12">
                <h2 class="text-2xl font-bold text-gray-200 mb-3 flex items-center gap-2">
                    <span class="text-blue-400 text-lg font-mono">3.</span> {{ $t('Relative Time (reltime)') }}
                </h2>
                <p class="mb-3">{{ $t('Reltime is the ratio of your time to the') }} <strong class="text-white">{{ $t('fastest time set by someone else') }}</strong>:</p>
                <div class="bg-gray-900/60 border border-gray-800 rounded-xl p-4 font-mono text-center text-sm text-white mb-3">
                    reltime = your_time / fastest_other_time
                </div>
                <div class="mt-3 bg-blue-500/10 border border-blue-500/20 rounded-lg p-3 text-xs text-blue-300 mb-4">
                    <strong>{{ $t('Key detail:') }}</strong> {{ $t('You are always compared to the best time by a') }} <em>{{ $t('different player') }}</em>. {{ $t('If you hold the WR, your reltime is calculated against the 2nd place time - you are never compared to yourself.') }}
                </div>
                <div class="space-y-2 text-sm">
                    <div class="flex items-center gap-3">
                        <span class="bg-green-600/20 text-green-400 px-2 py-0.5 rounded font-mono text-xs w-24 text-center">&lt; 1.000</span>
                        <span>{{ $t('You are faster than the best time by anyone else (you hold WR)') }}</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="bg-green-600/20 text-green-400 px-2 py-0.5 rounded font-mono text-xs w-24 text-center">1.000</span>
                        <span>{{ $t('You matched the fastest other player exactly') }}</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="bg-yellow-600/20 text-yellow-400 px-2 py-0.5 rounded font-mono text-xs w-24 text-center">1.233</span>
                        <span>{{ $t('Your time is 23.3% slower than the fastest other time') }}</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="bg-red-600/20 text-red-400 px-2 py-0.5 rounded font-mono text-xs w-24 text-center">2.500</span>
                        <span>{{ $t('Your time is 2.5x the fastest other time') }}</span>
                    </div>
                </div>

                <div class="mt-4 bg-gray-900/60 border border-gray-800 rounded-xl p-4">
                    <div class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">{{ $t('Example (non-WR player)') }}</div>
                    <div class="text-sm space-y-1">
                        <div>{{ $t('WR time (other player):') }} <span class="text-white font-mono">{{ (exampleWR / 1000).toFixed(3) }}s</span></div>
                        <div>{{ $t('Your time:') }} <span class="text-white font-mono">{{ (exampleTime / 1000).toFixed(3) }}s</span></div>
                        <div>reltime = {{ (exampleTime / 1000).toFixed(3) }} / {{ (exampleWR / 1000).toFixed(3) }} = <span class="text-yellow-400 font-mono font-bold">{{ exampleReltime.toFixed(4) }}</span></div>
                    </div>
                </div>

                <div class="mt-3 bg-yellow-500/10 border border-yellow-500/20 rounded-lg p-3 text-xs text-yellow-300">
                    <strong>{{ $t('Note:') }}</strong> {{ $t('The lower the reltime, the better. A reltime below 1.0 means you are the WR holder and faster than everyone else.') }}
                </div>
            </section>

            <!-- 4. Base Score -->
            <section id="base-score" class="mb-12">
                <h2 class="text-2xl font-bold text-gray-200 mb-3 flex items-center gap-2">
                    <span class="text-blue-400 text-lg font-mono">4.</span> {{ $t('Base Map Score (Logistic Curve)') }}
                </h2>
                <p class="mb-3">{{ $t('The reltime is converted into a score using a generalized logistic function. This creates a smooth S-curve where:') }}</p>
                <div class="bg-gray-900/60 border border-gray-800 rounded-xl p-4 font-mono text-center text-sm text-white mb-3 overflow-x-auto">
                    score = 1000 * (A + (-A / (1 + Q * exp(-B * (reltime - M)))^(1/V)))
                </div>
                <div class="bg-gray-900/60 border border-gray-800 rounded-xl p-4 mb-3">
                    <div class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">{{ $t('Parameters') }}</div>
                    <div class="space-y-1 text-xs">
                        <div><span class="text-white font-mono">A = {{ fmt(s.cfg_a) }}</span> <span class="text-gray-500">- {{ $t('amplitude') }}</span></div>
                        <div><span class="text-white font-mono">B = {{ fmt(s.cfg_b) }}</span> <span class="text-gray-500">- {{ $t('steepness') }}</span></div>
                        <div><span class="text-white font-mono">M = {{ fmt(s.cfg_m) }}</span> <span class="text-gray-500">- {{ $t('midpoint shift') }}</span></div>
                        <div><span class="text-white font-mono">V = {{ fmt(s.cfg_v) }}</span> <span class="text-gray-500">- {{ $t('curve shape') }}</span></div>
                        <div><span class="text-white font-mono">Q = {{ fmt(s.cfg_q) }}</span> <span class="text-gray-500">- {{ $t('initial value') }}</span></div>
                    </div>
                </div>

                <p class="mb-3 text-sm">{{ $t('In practice, this means:') }}</p>
                <div class="bg-gray-900/60 border border-gray-800 rounded-xl p-4">
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between items-center">
                            <span>{{ $t('WR holder (reltime = 1.00)') }}</span>
                            <span class="font-mono text-green-400 font-bold">{{ calcMapScore(1.0).toFixed(1) }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span>{{ $t('10% slower (reltime = 1.10)') }}</span>
                            <span class="font-mono text-lime-400 font-bold">{{ calcMapScore(1.1).toFixed(1) }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span>{{ $t('25% slower (reltime = 1.25)') }}</span>
                            <span class="font-mono text-yellow-400 font-bold">{{ calcMapScore(1.25).toFixed(1) }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span>{{ $t('50% slower (reltime = 1.50)') }}</span>
                            <span class="font-mono text-orange-400 font-bold">{{ calcMapScore(1.5).toFixed(1) }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span>{{ $t('2x slower (reltime = 2.00)') }}</span>
                            <span class="font-mono text-red-400 font-bold">{{ calcMapScore(2.0).toFixed(1) }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span>{{ $t('5x slower (reltime = 5.00)') }}</span>
                            <span class="font-mono text-red-600 font-bold">{{ calcMapScore(5.0).toFixed(1) }}</span>
                        </div>
                    </div>
                </div>
                <div class="mt-3 bg-blue-500/10 border border-blue-500/20 rounded-lg p-3 text-xs text-blue-300">
                    <strong>{{ $t('Key insight:') }}</strong> {{ $t('The logistic curve rewards being close to WR exponentially. Going from 1.5x to 1.25x WR is a much bigger score jump than going from 3.0x to 2.75x.') }}
                </div>
            </section>

            <!-- 5. Map Multiplier -->
            <section id="multiplier" class="mb-12">
                <h2 class="text-2xl font-bold text-gray-200 mb-3 flex items-center gap-2">
                    <span class="text-blue-400 text-lg font-mono">5.</span> {{ $t('Map Multiplier (Hill Function)') }}
                </h2>
                <p class="mb-3">{{ $t('Not all maps carry the same weight. Maps with more active players are considered more competitive and get a higher multiplier. This uses a Hill/logistic function:') }}</p>
                <div class="bg-gray-900/60 border border-gray-800 rounded-xl p-4 font-mono text-center text-sm text-white mb-3">
                    multiplier = (L * x^n) / (k^n + x^n)
                </div>
                <div class="bg-gray-900/60 border border-gray-800 rounded-xl p-4 mb-3">
                    <div class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">{{ $t('Parameters') }}</div>
                    <div class="space-y-1 text-xs">
                        <div><span class="text-white font-mono">x</span> <span class="text-gray-500">- {{ $t('number of unique players on the map') }}</span></div>
                        <div><span class="text-white font-mono">k</span> <span class="text-gray-500">- {{ $t('median(players per map in category) / 2 (the halfway point)') }}</span></div>
                        <div><span class="text-white font-mono">L = {{ fmt(s.mult_l) }}</span> <span class="text-gray-500">- {{ $t('maximum multiplier (100%)') }}</span></div>
                        <div><span class="text-white font-mono">n = {{ fmt(s.mult_n) }}</span> <span class="text-gray-500">- {{ $t('steepness of the curve') }}</span></div>
                    </div>
                </div>

                <p class="mb-3 text-sm">{{ $t('For VQ3 Overall (median = :median players, so k = :k):', { median: exampleMedian, k: fmt(exampleK, 1) }) }}</p>
                <div class="bg-gray-900/60 border border-gray-800 rounded-xl p-4">
                    <div class="space-y-3">
                        <div>
                            <div class="flex justify-between items-center text-sm mb-1">
                                <span>{{ $t('4 players (small map)') }}</span>
                                <span class="font-mono text-red-400 font-bold">{{ (calcMultiplier(4, exampleMedian) * 100).toFixed(1) }}%</span>
                            </div>
                            <div class="w-full bg-gray-800 rounded-full h-2">
                                <div class="bg-red-500 h-2 rounded-full" :style="`width: ${calcMultiplier(4, exampleMedian) * 100}%`"></div>
                            </div>
                        </div>
                        <div>
                            <div class="flex justify-between items-center text-sm mb-1">
                                <span>{{ $tc(':count player|:count players', 7) }} (k ≈ {{ fmt(exampleK, 1) }})</span>
                                <span class="font-mono text-yellow-400 font-bold">{{ (calcMultiplier(7, exampleMedian) * 100).toFixed(1) }}%</span>
                            </div>
                            <div class="w-full bg-gray-800 rounded-full h-2">
                                <div class="bg-yellow-500 h-2 rounded-full" :style="`width: ${calcMultiplier(7, exampleMedian) * 100}%`"></div>
                            </div>
                        </div>
                        <div>
                            <div class="flex justify-between items-center text-sm mb-1">
                                <span>{{ $tc(':count player|:count players', exampleMedian) }} {{ $t('(median)') }}</span>
                                <span class="font-mono text-lime-400 font-bold">{{ (calcMultiplier(exampleMedian, exampleMedian) * 100).toFixed(1) }}%</span>
                            </div>
                            <div class="w-full bg-gray-800 rounded-full h-2">
                                <div class="bg-lime-500 h-2 rounded-full" :style="`width: ${calcMultiplier(exampleMedian, exampleMedian) * 100}%`"></div>
                            </div>
                        </div>
                        <div>
                            <div class="flex justify-between items-center text-sm mb-1">
                                <span>{{ $t('25 players') }}</span>
                                <span class="font-mono text-green-400 font-bold">{{ (calcMultiplier(25, exampleMedian) * 100).toFixed(1) }}%</span>
                            </div>
                            <div class="w-full bg-gray-800 rounded-full h-2">
                                <div class="bg-green-500 h-2 rounded-full" :style="`width: ${calcMultiplier(25, exampleMedian) * 100}%`"></div>
                            </div>
                        </div>
                        <div>
                            <div class="flex justify-between items-center text-sm mb-1">
                                <span>{{ $t('50 players (popular map)') }}</span>
                                <span class="font-mono text-green-400 font-bold">{{ (calcMultiplier(50, exampleMedian) * 100).toFixed(1) }}%</span>
                            </div>
                            <div class="w-full bg-gray-800 rounded-full h-2">
                                <div class="bg-green-500 h-2 rounded-full" :style="`width: ${calcMultiplier(50, exampleMedian) * 100}%`"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="mt-3 bg-purple-500/10 border border-purple-500/20 rounded-lg p-3 text-xs text-purple-300">
                    <strong>{{ $t('Why?') }}</strong> {{ $t('A WR on a map with 4 players is less impressive than a WR on a map with 50 active competitors. The multiplier ensures that popular, competitive maps carry more weight.') }}
                </div>

                <!-- Live category medians (drives k for each game type / physics) -->
                <div v-if="hasCategoryStats" class="mt-6 bg-gray-900/60 border border-gray-800 rounded-xl p-4">
                    <div class="flex items-center justify-between mb-3 flex-wrap gap-2">
                        <div class="text-xs font-bold text-gray-500 uppercase tracking-wider">{{ $t('Median players per ranked map (live)') }}</div>
                        <div v-if="categoryStatsAsOf" class="text-[10px] text-gray-600">{{ $t('as of :date', { date: formatAsOf(categoryStatsAsOf) }) }}</div>
                    </div>
                    <p class="text-xs text-gray-500 mb-3">{{ $t('Each cell shows') }} <span class="font-mono text-gray-400">median</span> {{ $t('(and') }} <span class="font-mono text-gray-400">k = median / 2</span>{{ $t(') for that game type / physics / category combo. Updated by the nightly full recalculation.') }}</p>

                    <!-- Run mode: matrix of categories × physics -->
                    <div v-if="runCategoriesWithData.length > 0" class="mb-5">
                        <div class="text-xs font-semibold text-gray-400 mb-2">Run</div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-xs">
                                <thead>
                                    <tr class="text-gray-500 border-b border-gray-800">
                                        <th class="text-left font-semibold py-2 pr-3">{{ $t('Category') }}</th>
                                        <th class="text-right font-semibold py-2 px-3">{{ $t('VQ3 median') }} <span class="text-gray-600 font-normal">(k)</span></th>
                                        <th class="text-right font-semibold py-2 pl-3">{{ $t('CPM median') }} <span class="text-gray-600 font-normal">(k)</span></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="cat in runCategoriesWithData" :key="cat" class="border-b border-gray-800/60 last:border-0">
                                        <td class="py-1.5 pr-3 text-gray-300 capitalize">{{ categoryLabel(cat) }}</td>
                                        <td class="py-1.5 px-3 text-right font-mono">
                                            <template v-if="statCell('run', cat, 'vq3')">
                                                <span class="text-white">{{ statCell('run', cat, 'vq3').median.toFixed(1) }}</span>
                                                <span class="text-gray-500 ml-1">({{ statCell('run', cat, 'vq3').k.toFixed(1) }})</span>
                                            </template>
                                            <span v-else class="text-gray-700">-</span>
                                        </td>
                                        <td class="py-1.5 pl-3 text-right font-mono">
                                            <template v-if="statCell('run', cat, 'cpm')">
                                                <span class="text-white">{{ statCell('run', cat, 'cpm').median.toFixed(1) }}</span>
                                                <span class="text-gray-500 ml-1">({{ statCell('run', cat, 'cpm').k.toFixed(1) }})</span>
                                            </template>
                                            <span v-else class="text-gray-700">-</span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- CTF modes: only overall is computed -->
                    <div v-if="ctfModesWithData.length > 0">
                        <div class="text-xs font-semibold text-gray-400 mb-2">{{ $t('CTF (overall only)') }}</div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-xs">
                                <thead>
                                    <tr class="text-gray-500 border-b border-gray-800">
                                        <th class="text-left font-semibold py-2 pr-3">{{ $t('Mode') }}</th>
                                        <th class="text-right font-semibold py-2 px-3">{{ $t('VQ3 median') }} <span class="text-gray-600 font-normal">(k)</span></th>
                                        <th class="text-right font-semibold py-2 pl-3">{{ $t('CPM median') }} <span class="text-gray-600 font-normal">(k)</span></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="mode in ctfModesWithData" :key="mode" class="border-b border-gray-800/60 last:border-0">
                                        <td class="py-1.5 pr-3 text-gray-300 uppercase">{{ mode }}</td>
                                        <td class="py-1.5 px-3 text-right font-mono">
                                            <template v-if="statCell(mode, 'overall', 'vq3')">
                                                <span class="text-white">{{ statCell(mode, 'overall', 'vq3').median.toFixed(1) }}</span>
                                                <span class="text-gray-500 ml-1">({{ statCell(mode, 'overall', 'vq3').k.toFixed(1) }})</span>
                                            </template>
                                            <span v-else class="text-gray-700">-</span>
                                        </td>
                                        <td class="py-1.5 pl-3 text-right font-mono">
                                            <template v-if="statCell(mode, 'overall', 'cpm')">
                                                <span class="text-white">{{ statCell(mode, 'overall', 'cpm').median.toFixed(1) }}</span>
                                                <span class="text-gray-500 ml-1">({{ statCell(mode, 'overall', 'cpm').k.toFixed(1) }})</span>
                                            </template>
                                            <span v-else class="text-gray-700">-</span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </section>

            <!-- 6. Rank Multiplier -->
            <section id="rank-multiplier" class="mb-12">
                <h2 class="text-2xl font-bold text-gray-200 mb-3 flex items-center gap-2">
                    <span class="text-blue-400 text-lg font-mono">6.</span> {{ $t('Rank Multiplier') }}
                </h2>
                <p class="mb-3">{{ $t('Your rank on each map further scales your score. Higher ranks earn a bigger multiplier:') }}</p>
                <div class="bg-gray-900/60 border border-gray-800 rounded-xl p-4 font-mono text-center text-sm text-white mb-3 overflow-x-auto">
                    t = (your_rank - 1) / (total_players - 1)<br>
                    rank_mult = k ^ (t ^ (n + p * (1 - t)))
                </div>
                <div class="text-xs text-gray-500 mb-3">
                    {{ $t('Generalized stretched exponential with a position-dependent exponent. WR (t = 0) always gets exactly 1.0; the worst rank on the map (t = 1) gets exactly') }} <span class="font-mono">k</span>.
                </div>
                <div class="bg-gray-900/60 border border-gray-800 rounded-xl p-4 mb-3">
                    <div class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">{{ $t('Parameters') }}</div>
                    <div class="space-y-1 text-xs">
                        <div><span class="text-white font-mono">total_players</span> <span class="text-gray-500">- {{ $t('number of unique players on the map') }}</span></div>
                        <div><span class="text-white font-mono">your_rank</span> <span class="text-gray-500">- {{ $t('your position on the map leaderboard (1 = WR)') }}</span></div>
                        <div><span class="text-white font-mono">k = {{ fmt(s.rank_k) }}</span> <span class="text-gray-500">- {{ $t('rank_mult value for the worst-ranked record on the map') }}</span></div>
                        <div><span class="text-white font-mono">n = {{ fmt(s.rank_n) }}</span> <span class="text-gray-500">- {{ $t('overall curve steepness') }}</span></div>
                        <div><span class="text-white font-mono">p = {{ fmt(s.rank_p) }}</span> <span class="text-gray-500">- {{ $t('position of the steep section along the curve') }}</span></div>
                    </div>
                </div>
                <div class="bg-gray-900/60 border border-gray-800 rounded-xl p-4">
                    <div class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">{{ $t('Example: 50 players on a map') }}</div>
                    <div class="space-y-2">
                        <div v-for="[rank, label] in [[1, $t('Rank #1 (WR)')], [5, $t('Rank #5')], [25, $t('Rank #25')], [50, $t('Rank #50 (last)')]]" :key="rank">
                            <div class="flex justify-between items-center text-sm mb-1">
                                <span>{{ label }}</span>
                                <span class="font-mono font-bold" :class="calcRankMultiplier(50, rank) > 0.8 ? 'text-green-400' : calcRankMultiplier(50, rank) > 0.5 ? 'text-yellow-400' : 'text-red-400'">{{ (calcRankMultiplier(50, rank) * 100).toFixed(1) }}%</span>
                            </div>
                            <div class="w-full bg-gray-800 rounded-full h-2">
                                <div class="h-2 rounded-full" :class="calcRankMultiplier(50, rank) > 0.8 ? 'bg-green-500' : calcRankMultiplier(50, rank) > 0.5 ? 'bg-yellow-500' : 'bg-red-500'" :style="`width: ${calcRankMultiplier(50, rank) * 100}%`"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="mt-3 bg-purple-500/10 border border-purple-500/20 rounded-lg p-3 text-xs text-purple-300">
                    <strong>{{ $t('Why?') }}</strong> {{ $t('Two players on the same map with similar times could have very different ranks. The rank multiplier ensures that actually beating more players gives a bigger reward, not just having a fast time.') }}
                </div>
            </section>

            <!-- 7. Final Score -->
            <section id="final-score" class="mb-12">
                <h2 class="text-2xl font-bold text-gray-200 mb-3 flex items-center gap-2">
                    <span class="text-blue-400 text-lg font-mono">7.</span> {{ $t('Final Map Score') }}
                </h2>
                <p class="mb-3">{{ $t('The final score for each record combines all multipliers:') }}</p>
                <div class="bg-gray-900/60 border border-gray-800 rounded-xl p-4 font-mono text-center text-sm text-white mb-3">
                    final_score = base_score * map_multiplier * rank_multiplier
                </div>
                <div class="bg-gray-900/60 border border-gray-800 rounded-xl p-4">
                    <div class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">{{ $t('Example (25 players, rank #3, VQ3 overall median = :median)', { median: exampleMedian }) }}</div>
                    <div class="text-sm space-y-1">
                        <div>{{ $t('Your reltime:') }} <span class="text-white font-mono">{{ exampleReltime.toFixed(4) }}</span></div>
                        <div>{{ $t('Base score:') }} <span class="text-white font-mono">{{ exampleBaseScore.toFixed(1) }}</span></div>
                        <div>{{ $t('Map multiplier (25 players):') }} <span class="text-white font-mono">{{ exampleMultiplier.toFixed(4) }}</span> ({{ (exampleMultiplier * 100).toFixed(1) }}%)</div>
                        <div>{{ $t('Rank multiplier (rank #3 of 25):') }} <span class="text-white font-mono">{{ calcRankMultiplier(25, 3).toFixed(4) }}</span> ({{ (calcRankMultiplier(25, 3) * 100).toFixed(1) }}%)</div>
                        <div class="pt-1 border-t border-gray-800">{{ $t('Final score:') }} {{ exampleBaseScore.toFixed(1) }} * {{ exampleMultiplier.toFixed(4) }} * {{ calcRankMultiplier(25, 3).toFixed(4) }} = <span class="text-green-400 font-mono font-bold">{{ (exampleBaseScore * exampleMultiplier * calcRankMultiplier(25, 3)).toFixed(1) }}</span></div>
                    </div>
                </div>
            </section>

            <!-- 8. Player Rating -->
            <section id="player-rating" class="mb-12">
                <h2 class="text-2xl font-bold text-gray-200 mb-3 flex items-center gap-2">
                    <span class="text-blue-400 text-lg font-mono">8.</span> {{ $t('Player Rating') }}
                </h2>
                <p class="mb-3">{{ $t("A player's overall rating is calculated from all their map scores using") }} <strong class="text-white">{{ $t('exponential weighting') }}</strong>:</p>
                <div class="bg-gray-900/60 border border-gray-800 rounded-xl p-4 font-mono text-center text-sm text-white mb-3 overflow-x-auto">
                    rating = sum(score_i * exp(-D * (i-1))) / sum(exp(-D * (i-1)))
                </div>

                <!-- Reassurance callout: bad records do NOT lower the rating -->
                <div class="bg-green-500/10 border border-green-500/30 rounded-xl p-4 mb-3">
                    <div class="flex items-start gap-3">
                        <svg class="w-5 h-5 text-green-400 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                        <div>
                            <div class="text-sm font-bold text-green-300 mb-1">{{ $t('Bad records do not lower your rating') }}</div>
                            <div class="text-xs text-gray-300">{{ $t("Weak runs simply contribute very little weight - they don't subtract anything. Just play the maps you enjoy; you can't hurt your rating by adding a slow time on some map. Practically only your best scores move the number.") }}</div>
                        </div>
                    </div>
                </div>

                <div class="bg-gray-900/60 border border-gray-800 rounded-xl p-4 mb-3">
                    <div class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">{{ $t('Parameters') }}</div>
                    <div class="space-y-1 text-xs">
                        <div><span class="text-white font-mono">i</span> <span class="text-gray-500">- {{ $t('rank position in your sorted map scores (1 = your best map)') }}</span></div>
                        <div><span class="text-white font-mono">score_i</span> <span class="text-gray-500">- {{ $t('the final map score (after multipliers) on your i-th best map') }}</span></div>
                        <div><span class="text-white font-mono">D = {{ fmt(s.cfg_d) }}</span> <span class="text-gray-500">- {{ $t('decay constant; smaller value means slower decay (more records contribute)') }}</span></div>
                    </div>
                </div>

                <div class="bg-gray-900/60 border border-gray-800 rounded-xl p-4 mb-3">
                    <div class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">{{ $t('How it works') }}</div>
                    <div class="text-sm space-y-2">
                        <p>{{ $t('1. All your map scores are') }} <strong class="text-white">{{ $t('sorted from highest to lowest') }}</strong></p>
                        <p>{{ $t('2. Each score gets a weight:') }} <span class="font-mono text-white">exp(-{{ fmt(s.cfg_d) }} * (i - 1))</span> {{ $t('where i starts at 1') }}</p>
                        <p>{{ $t('3. Your best map gets weight 1.0, your 10th best ~0.84, your 50th best ~0.38') }}</p>
                        <p>{{ $t('4. The final rating is the weighted average of all scores') }}</p>
                    </div>
                </div>

                <div class="bg-blue-500/10 border border-blue-500/20 rounded-xl p-4 mb-3">
                    <div class="flex items-start gap-3">
                        <svg class="w-5 h-5 text-blue-400 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" /></svg>
                        <div class="text-sm">
                            <div class="font-bold text-blue-300 mb-1">{{ $t('Which records actually matter?') }}</div>
                            <div class="text-xs text-gray-300">{{ $t('With') }} <span class="font-mono text-white">D = {{ fmt(s.cfg_d) }}</span>{{ $t(', your') }} <strong class="text-white">{{ $t('top 100 records carry ~:share%', { share: top100Share.toFixed(1) }) }}</strong> {{ $t('of the total weight in your rating, and your') }} <strong class="text-white">{{ $t('top 200 carry ~:share%', { share: top200Share.toFixed(1) }) }}</strong>{{ $t('. So pushing your best maps a little harder will move your number much more than adding a hundred more average runs.') }}</div>
                            <div class="text-[10px] text-gray-500 mt-1">{{ $t('(Computed dynamically from D - if the decay constant changes, this number changes too.)') }}</div>
                        </div>
                    </div>
                </div>

                <div class="bg-gray-900/60 border border-gray-800 rounded-xl p-4 mb-3">
                    <div class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">{{ $t('Example: Player with 12 maps') }}</div>
                    <div class="grid grid-cols-3 sm:grid-cols-4 gap-2 text-xs mb-3">
                        <div v-for="(score, i) in exampleScores" :key="i" class="bg-gray-800 rounded px-2 py-1 text-center">
                            <div class="text-gray-500">#{{ i + 1 }}</div>
                            <div class="font-mono" :class="i < 3 ? 'text-green-400' : i < 6 ? 'text-yellow-400' : 'text-gray-400'">{{ score }}</div>
                            <div class="text-gray-600 text-[9px]">w={{ Math.exp(-s.cfg_d * i).toFixed(3) }}</div>
                        </div>
                    </div>
                    <div class="text-sm">
                        {{ $t('Rating:') }} <span class="text-green-400 font-mono font-bold">{{ exampleRating.toFixed(1) }}</span>
                        <span class="text-gray-500 ml-2">{{ $t('(weighted heavily towards the best scores)') }}</span>
                    </div>
                </div>

                <!-- Penalty for few records -->
                <div class="bg-red-500/10 border border-red-500/20 rounded-xl p-4">
                    <div class="text-sm font-bold text-red-400 mb-2">{{ $t('Penalty for few records') }}</div>
                    <p class="text-sm mb-2">{{ $t('Players with fewer than') }} <strong class="text-white">{{ $tc(':count record|:count records', s.min_total_records) }}</strong> {{ $t('receive a proportional penalty:') }}</p>
                    <div class="bg-gray-900/60 rounded-lg p-3 font-mono text-center text-white text-sm mb-2">
                        penalized_rating = rating * (num_records / {{ s.min_total_records }})
                    </div>
                    <div class="text-sm">
                        {{ $t('Example: same top 3 scores (850, 780, 720) but only 3 records:') }}
                        <span class="text-red-400 font-mono font-bold ml-1">{{ exampleFewRating.toFixed(1) }}</span>
                        <span class="text-gray-500"> {{ $t('vs :rating with 12 records', { rating: exampleRating.toFixed(1) }) }}</span>
                    </div>
                    <div class="text-xs text-gray-500 mt-1">{{ $t('This prevents players from cherry-picking only a few easy maps to inflate their rating.') }}</div>
                </div>
            </section>

            <!-- 8. Categories -->
            <section id="categories" class="mb-12">
                <h2 class="text-2xl font-bold text-gray-200 mb-3 flex items-center gap-2">
                    <span class="text-blue-400 text-lg font-mono">9.</span> {{ $t('Categories') }}
                </h2>
                <p class="mb-3">{{ $t('Rankings are calculated separately for each category. A map belongs to a category based on its weapons and features:') }}</p>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                    <div class="bg-gray-900/60 border border-gray-800 rounded-lg p-3">
                        <div class="text-sm font-bold text-white">{{ $t('Overall') }}</div>
                        <div class="text-xs text-gray-500">{{ $t('All maps') }}</div>
                    </div>
                    <div class="bg-gray-900/60 border border-gray-800 rounded-lg p-3">
                        <div class="text-sm font-bold text-yellow-400">{{ $t('Strafe') }}</div>
                        <div class="text-xs text-gray-500">{{ $t('Maps without weapons (MG/SG/Gauntlet/Hook/RG allowed)') }}</div>
                    </div>
                    <div class="bg-gray-900/60 border border-gray-800 rounded-lg p-3">
                        <div class="text-sm font-bold text-cyan-400">{{ $t('Slick') }}</div>
                        <div class="text-xs text-gray-500">{{ $t('Maps with slick surfaces') }}</div>
                    </div>
                    <div class="bg-gray-900/60 border border-gray-800 rounded-lg p-3">
                        <div class="text-sm font-bold text-purple-400">{{ $t('Tele') }}</div>
                        <div class="text-xs text-gray-500">{{ $t('Maps with teleporters') }}</div>
                    </div>
                    <div class="bg-gray-900/60 border border-gray-800 rounded-lg p-3">
                        <div class="text-sm font-bold text-orange-400">Rocket</div>
                        <div class="text-xs text-gray-500">{{ $t('Maps with RL') }}</div>
                    </div>
                    <div class="bg-gray-900/60 border border-gray-800 rounded-lg p-3">
                        <div class="text-sm font-bold text-blue-400">{{ $t('Plasma') }}</div>
                        <div class="text-xs text-gray-500">{{ $t('Maps with PG') }}</div>
                    </div>
                    <div class="bg-gray-900/60 border border-gray-800 rounded-lg p-3">
                        <div class="text-sm font-bold text-green-400">Grenade</div>
                        <div class="text-xs text-gray-500">{{ $t('Maps with GL') }}</div>
                    </div>
                    <div class="bg-gray-900/60 border border-gray-800 rounded-lg p-3">
                        <div class="text-sm font-bold text-pink-400">LG</div>
                        <div class="text-xs text-gray-500">{{ $t('Maps with Lightning Gun') }}</div>
                    </div>
                    <div class="bg-gray-900/60 border border-gray-800 rounded-lg p-3">
                        <div class="text-sm font-bold text-red-400">BFG</div>
                        <div class="text-xs text-gray-500">{{ $t('Maps with BFG') }}</div>
                    </div>
                </div>
                <div class="mt-3 text-xs text-gray-500">
                    {{ $t('Each category has its own median for the map multiplier calculation - see the live table in') }} <a href="#multiplier" class="text-blue-400 hover:text-blue-300">{{ $t('section 5') }}</a>{{ $t('. Different categories sit at very different player counts, which is why') }} <span class="font-mono">k</span> {{ $t('shifts per category.') }}
                </div>
            </section>

            <!-- 9. Updates -->
            <section id="updates" class="mb-12">
                <h2 class="text-2xl font-bold text-gray-200 mb-3 flex items-center gap-2">
                    <span class="text-blue-400 text-lg font-mono">10.</span> {{ $t('Real-time Updates') }}
                </h2>
                <div class="bg-gray-900/60 border border-gray-800 rounded-xl p-4 space-y-3">
                    <div class="flex items-start gap-3">
                        <div class="w-8 h-8 rounded-full bg-green-600/20 flex items-center justify-center flex-shrink-0">
                            <svg class="w-4 h-4 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                        </div>
                        <div>
                            <div class="text-sm font-bold text-white">{{ $t('Incremental (real-time)') }}</div>
                            <div class="text-xs text-gray-400">{{ $t('Every time a new record is submitted to mDd, rankings for that specific map are instantly recalculated. This means your ranking updates within seconds of a new record.') }}</div>
                        </div>
                    </div>
                    <div class="flex items-start gap-3">
                        <div class="w-8 h-8 rounded-full bg-blue-600/20 flex items-center justify-center flex-shrink-0">
                            <svg class="w-4 h-4 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                            </svg>
                        </div>
                        <div>
                            <div class="text-sm font-bold text-white">{{ $t('Full recalculation (daily)') }}</div>
                            <div class="text-xs text-gray-400">{{ $t('Once per day, all rankings across all maps, physics, and categories are recalculated from scratch. This ensures consistency and catches any edge cases the incremental updates might miss.') }}</div>
                        </div>
                    </div>
                </div>
                <div class="mt-3 bg-yellow-500/10 border border-yellow-500/20 rounded-lg p-3 text-xs text-yellow-300">
                    <strong>{{ $t('Why both?') }}</strong> {{ $t("The incremental update is an optimization - it touches only the map a new record landed on, not every map that record might secondarily affect (e.g. category-wide medians used in the map multiplier shift slightly when any map's player count changes). Over a day these small approximations can drift from the exact value. The nightly full recalculation re-derives everything from scratch and brings the rankings back to the ground truth.") }}
                </div>
            </section>

            <!-- 10. Full Example -->
            <section id="full-example" class="mb-12">
                <h2 class="text-2xl font-bold text-gray-200 mb-3 flex items-center gap-2">
                    <span class="text-blue-400 text-lg font-mono">11.</span> {{ $t('Full Walkthrough Example') }}
                </h2>
                <p class="mb-3 text-sm">{{ $t('Let\'s follow a complete calculation for a player with a record on "run_example" (VQ3 Overall):') }}</p>

                <div class="space-y-4">
                    <!-- Step 1 -->
                    <div class="bg-gray-900/60 border border-gray-800 rounded-xl p-4">
                        <div class="text-sm font-bold text-green-400 mb-2">{{ $t('Step 1: Check map eligibility') }}</div>
                        <div class="text-xs space-y-1 text-gray-400">
                            <div>{{ $t('Map "run_example" has') }} <span class="text-white">{{ $t('25 unique players') }}</span> &#x2713; {{ $t('(min 5)') }}</div>
                            <div>{{ $t('WR time:') }} <span class="text-white">15.000s</span> &#x2713; {{ $t('(above 500ms)') }}</div>
                            <div>{{ $t('Only 1 player holds the WR') }} &#x2713; {{ $t('(max 3 tied)') }}</div>
                            <div class="text-green-400 font-bold mt-1">{{ $t('Map is ranked.') }}</div>
                        </div>
                    </div>

                    <!-- Step 2 -->
                    <div class="bg-gray-900/60 border border-gray-800 rounded-xl p-4">
                        <div class="text-sm font-bold text-yellow-400 mb-2">{{ $t('Step 2: Calculate reltime') }}</div>
                        <div class="text-xs text-gray-400">
                            <div>{{ $t('Your time:') }} <span class="text-white">18.500s</span></div>
                            <div>reltime = 18.500 / 15.000 = <span class="text-yellow-400 font-mono font-bold">{{ exampleReltime.toFixed(4) }}</span></div>
                        </div>
                    </div>

                    <!-- Step 3 -->
                    <div class="bg-gray-900/60 border border-gray-800 rounded-xl p-4">
                        <div class="text-sm font-bold text-purple-400 mb-2">{{ $t('Step 3: Calculate base score') }}</div>
                        <div class="text-xs text-gray-400">
                            <div>score = logistic({{ exampleReltime.toFixed(4) }}) = <span class="text-purple-400 font-mono font-bold">{{ exampleBaseScore.toFixed(1) }}</span></div>
                        </div>
                    </div>

                    <!-- Step 4 -->
                    <div class="bg-gray-900/60 border border-gray-800 rounded-xl p-4">
                        <div class="text-sm font-bold text-blue-400 mb-2">{{ $t('Step 4: Apply map multiplier') }}</div>
                        <div class="text-xs text-gray-400">
                            <div>{{ $t('Category median:') }} <span class="text-white">{{ $tc(':count player|:count players', exampleMedian) }}</span>, k = {{ fmt(exampleK, 1) }}</div>
                            <div>{{ $t('Map has') }} <span class="text-white">{{ $t('25 players') }}</span></div>
                            <div>map_multiplier = (25<sup>{{ fmt(s.mult_n) }}</sup>) / ({{ fmt(exampleK, 1) }}<sup>{{ fmt(s.mult_n) }}</sup> + 25<sup>{{ fmt(s.mult_n) }}</sup>) = <span class="text-blue-400 font-mono font-bold">{{ exampleMultiplier.toFixed(4) }}</span></div>
                            <div class="mt-1">scaled = {{ exampleBaseScore.toFixed(1) }} * {{ exampleMultiplier.toFixed(4) }} = <span class="text-blue-400 font-mono font-bold">{{ exampleFinalScore.toFixed(1) }}</span></div>
                        </div>
                    </div>

                    <!-- Step 5: Rank multiplier (NEW) -->
                    <div class="bg-gray-900/60 border border-gray-800 rounded-xl p-4">
                        <div class="text-sm font-bold text-pink-400 mb-2">{{ $t('Step 5: Apply rank multiplier') }}</div>
                        <div class="text-xs text-gray-400">
                            <div>{{ $t('Your rank on the map:') }} <span class="text-white">{{ $t('#3 of 25 players') }}</span></div>
                            <div>t = (3 - 1) / (25 - 1) = <span class="text-white font-mono">{{ (2/24).toFixed(4) }}</span></div>
                            <div>rank_mult = {{ fmt(s.rank_k) }}<sup>(t ^ ({{ fmt(s.rank_n) }} + {{ fmt(s.rank_p) }} * (1 - t)))</sup> = <span class="text-pink-400 font-mono font-bold">{{ calcRankMultiplier(25, 3).toFixed(4) }}</span></div>
                            <div class="mt-1">final_score = {{ exampleFinalScore.toFixed(1) }} * {{ calcRankMultiplier(25, 3).toFixed(4) }} = <span class="text-green-400 font-mono font-bold">{{ (exampleFinalScore * calcRankMultiplier(25, 3)).toFixed(1) }}</span></div>
                        </div>
                    </div>

                    <!-- Step 6: Player rating (renumbered) -->
                    <div class="bg-gray-900/60 border border-gray-800 rounded-xl p-4">
                        <div class="text-sm font-bold text-red-400 mb-2">{{ $t('Step 6: Combine into player rating') }}</div>
                        <div class="text-xs text-gray-400">
                            <div>{{ $t('This score (:score) joins all your other map scores from the given category.', { score: (exampleFinalScore * calcRankMultiplier(25, 3)).toFixed(1) }) }}</div>
                            <div>{{ $t('They are sorted, exponentially weighted, and averaged into your final rating.') }}</div>
                            <div>{{ $t('If you have fewer than 10 records, a penalty is applied.') }}</div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Back to rankings -->
            <div class="text-center pt-6 border-t border-gray-800">
                <Link href="/ranking" class="inline-flex items-center gap-2 px-6 py-3 bg-blue-600 hover:bg-blue-500 text-white rounded-xl font-bold text-sm transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                    </svg>
                    {{ $t('Back to Rankings') }}
                </Link>
            </div>
        </div>
    </div>
</template>
