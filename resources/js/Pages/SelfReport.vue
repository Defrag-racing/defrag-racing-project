<script>
import MainLayout from '@/Layouts/MainLayout.vue';

export default {
    layout: MainLayout,
};
</script>

<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, getCurrentInstance, onMounted, onUnmounted, ref, watch } from 'vue';
import Pagination from '@/Components/Basic/Pagination.vue';
import { t, currentLocale } from '@/utils/i18n';

const props = defineProps({
    hasMddAccount: { type: Boolean, default: false },
    blocked: { type: Object, default: null },
    records: { type: Object, default: () => ({ data: [] }) },
    search: { type: String, default: '' },
    sort: { type: String, default: 'date' },
    totalRecords: { type: Number, default: 0 },
    reasons: { type: Object, default: () => ({}) },
    handlingOptions: { type: Object, default: () => ({}) },
    mine: { type: Object, default: () => ({ data: [] }) },
    mineCounts: { type: Object, default: () => ({}) },
    mineState: { type: String, default: 'all' },
});

// The rows of the page being looked at. Everything else on this page talks
// about the whole collection, so the two are kept apart by name.
const rows = computed(() => props.records?.data ?? []);

// Your own withdrawals: same states the admin sees, in the words a player
// would use for them.
const MINE_STATES = computed(() => ({
    all: t('All'),
    waiting: t('Waiting'),
    queued: t('Queued for the merge'),
    done: t('Off the board'),
    restored: t('Put back'),
}));

const mineRows = computed(() => props.mine?.data ?? []);

const setMineState = (state) => {
    router.get('/amnesty', { mine_state: state === 'all' ? undefined : state }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ['mine', 'mineState', 'mineCounts'],
    });
};

const { proxy } = getCurrentInstance();
const formatTime = proxy.formatTime;

const SORTS = computed(() => ({
    date: t('Date of run'),
    rank: t('Rank'),
    name: t('Map name'),
    map_added: t('Map release date'),
}));

const search = ref(props.search);
const sort = ref(props.sort);

// Both filters are a server round trip rather than client-side work: one page
// of rows is in the browser, so sorting or filtering what arrived would quietly
// reorder a slice instead of the whole list. No page parameter, so changing
// either goes back to the first page rather than to page 9 of a new search.
const reload = () => {
    router.get('/amnesty', { search: search.value, sort: sort.value }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ['records', 'search', 'sort'],
    });
};

let searchTimer = null;
watch(search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(reload, 350);
});
watch(sort, reload);

// Selection survives searching and re-sorting, so a player can collect runs
// from several searches before withdrawing them in one go.
//
// Keyed by id but holding the whole row, because that is the only copy of it
// there will be. A run ticked on page 1 and then searched away is no longer in
// `rows`, and the confirmation list has to name it anyway - id alone would
// leave the player confirming a number.
const picked = ref(new Map());
const pickedCount = computed(() => picked.value.size);

// Same order the list was read in, so the confirmation reads like the page it
// was picked from rather than like insertion order.
const pickedRows = computed(() => [...picked.value.values()]
    .sort((a, b) => (a.mapname ?? '').localeCompare(b.mapname ?? '')
        || (a.time ?? 0) - (b.time ?? 0)));

// Nothing is sent from the bar itself. The last thing between a player and a
// list of their own runs disappearing is a sentence they have to read. Sits
// here rather than beside the form because unpick() closes it.
const confirming = ref(false);

const toggle = (record) => {
    const next = new Map(picked.value);
    next.has(record.id) ? next.delete(record.id) : next.set(record.id, record);
    picked.value = next;
};

const allShownPicked = computed(() => rows.value.length > 0
    && rows.value.every((record) => picked.value.has(record.id)));

const toggleAllShown = () => {
    const next = new Map(picked.value);
    if (allShownPicked.value) {
        rows.value.forEach((record) => next.delete(record.id));
    } else {
        rows.value.forEach((record) => next.set(record.id, record));
    }
    picked.value = next;
};

const unpick = (id) => {
    const next = new Map(picked.value);
    next.delete(id);
    picked.value = next;

    // Emptying the list from inside the confirmation leaves nothing to
    // confirm, so it steps back to the page rather than offering to send a
    // batch of nothing.
    if (next.size === 0) {
        confirming.value = false;
    }
};

const clearPicked = () => { picked.value = new Map(); };

const form = useForm({
    record_ids: [],
    reason: 'other',
    handling: 'on_merge',
    note: '',
    confirm: false,
});

const askConfirm = () => {
    if (!form.confirm) {
        form.setError('confirm', t('Tick the box first.'));
        return;
    }
    confirming.value = true;
};

const submit = () => {
    form.record_ids = [...picked.value.keys()];
    form.post('/amnesty', {
        preserveScroll: true,
        onSuccess: () => {
            confirming.value = false;
            clearPicked();
            form.reset();
        },
    });
};

// A native <select> draws its option list with the operating system, which
// ignores every colour on this page - white rows and a blue highlight in the
// middle of a dark panel. So the list is ours, and it can hold labels as long
// as "No proper finish (client_finish=false)" without the browser cutting them.
const reasonOpen = ref(false);
const reasonBox = ref(null);

const closeReason = (event) => {
    if (reasonBox.value && !reasonBox.value.contains(event.target)) {
        reasonOpen.value = false;
    }
};

const closeReasonOnEscape = (event) => {
    if (event.key === 'Escape') {
        reasonOpen.value = false;
    }
};

onMounted(() => {
    document.addEventListener('mousedown', closeReason);
    document.addEventListener('keydown', closeReasonOnEscape);
});

onUnmounted(() => {
    document.removeEventListener('mousedown', closeReason);
    document.removeEventListener('keydown', closeReasonOnEscape);
});

const pickReason = (key) => {
    form.reason = key;
    reasonOpen.value = false;
};

const fmtDate = (value) => value ? new Date(value).toLocaleDateString(currentLocale()) : '';
const thumb = (path) => path ? `/storage/${path}` : '/images/unknown.jpg';
</script>

<template>
    <Head :title="$t('Amnesty - self report')" />

    <div class="">
        <!-- Header Section - same shape as Servers, Records and Ranking. -->
        <div class="relative bg-gradient-to-b from-black/25 via-black/10 to-transparent pt-6 pb-96 pointer-events-none">
            <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8 pointer-events-auto">
                <div class="flex justify-between items-center flex-wrap gap-4">
                    <h1 class="text-2xl md:text-3xl font-black text-gray-300/90">
                        {{ $t('Amnesty') }}
                    </h1>
                </div>

                <p class="text-gray-400 mt-2 max-w-3xl">
                    {{ $t('You cheated, and you have worked out which of your times should not be standing. This is where you put it right, privately. Send them one at a time or all at once, but the reason you give has to be true for every run in the batch - anything you did differently goes in a batch of its own.') }}
                </p>
            </div>
        </div>

        <!-- Bottom padding leaves room for the action bar, which is fixed and
             would otherwise cover the last rows of the list. -->
        <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8 pb-12" :class="pickedCount ? 'pb-56' : ''"
            style="margin-top: -22rem;">

            <!-- Said once, loudly, before anything else. The single reason
                 somebody would not use this is the fear that using it is itself
                 an admission everyone gets to see. -->
            <div class="rounded-2xl border border-emerald-400/40 bg-emerald-500/[0.09] p-5 flex gap-4 mb-4">
                <svg class="w-8 h-8 shrink-0 text-emerald-300" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                </svg>
                <div>
                    <h2 class="text-emerald-200 font-black text-lg mb-1">{{ $t('This is private, and it is your right') }}</h2>
                    <p class="text-gray-200 leading-relaxed">
                        {{ $t('Nobody is told that you withdrew a run, and nobody is told why. Not the validators, not the public, not the log, nowhere on the site - only the site admin can see it. Anyone can use this, at any time, on any of their own runs. You need no permission, you owe nobody an explanation, and nothing about it is ever held against you. That is what an amnesty means.') }}
                    </p>
                </div>
            </div>

            <!-- Set the expectation before anything else, because the first
                 complaint would otherwise be "I sent it and my run is still
                 there, on q3df too". -->
            <div class="rounded-2xl border border-amber-400/35 bg-amber-500/[0.08] p-5 mb-4">
                <h2 class="text-amber-200 font-black text-lg mb-1">{{ $t('Nothing disappears the moment you send it') }}</h2>
                <p class="text-gray-200 leading-relaxed">
                    {{ $t('These requests are handled when the mDd databases are merged. That merge is planned but not done, and until it happens a run taken off this site still stands on q3df.org - I cannot reach that database yet. So you choose which you want: have it hidden here as soon as an admin approves it and accept that q3df still shows it for now, or leave it queued and have both handled together at the merge. Either way an admin approves the hide, and your run stays on the board until they do.') }}
                </p>
            </div>

            <!-- Three facts people ask before clicking, and the one reason to
                 do it now rather than later. -->
            <div class="grid gap-3 md:grid-cols-2 lg:grid-cols-4 mb-8">
                <div class="rounded-xl border border-white/10 bg-black/40 p-4">
                    <div class="text-white font-bold text-sm mb-1">{{ $t('You cannot take it back') }}</div>
                    <p class="text-gray-400 text-sm leading-relaxed">
                        {{ $t('Once a run is handled it is off the board, and undoing that is not yours to do. Check what you have ticked before you send it.') }}
                    </p>
                </div>
                <div class="rounded-xl border border-white/10 bg-black/40 p-4">
                    <div class="text-white font-bold text-sm mb-1">{{ $t('No case, no review') }}</div>
                    <p class="text-gray-400 text-sm leading-relaxed">
                        {{ $t('A withdrawn run is not reported and not judged by anybody. It is treated as putting the leaderboard right, because that is what it is.') }}
                    </p>
                </div>
                <div class="rounded-xl border border-white/10 bg-black/40 p-4">
                    <div class="text-white font-bold text-sm mb-1">{{ $t('Beating it settles it') }}</div>
                    <p class="text-gray-400 text-sm leading-relaxed">
                        {{ $t('Go back and run the map properly, and the new time replaces the old one on its own. Your request closes itself as beaten - no admin, no waiting.') }}
                    </p>
                </div>

                <div class="rounded-xl border border-red-400/25 bg-red-500/[0.07] p-4">
                    <div class="text-red-300 font-bold text-sm mb-1">{{ $t('Only while you are ahead of a report') }}</div>
                    <p class="text-gray-400 text-sm leading-relaxed">
                        {{ $t('Once somebody else reports the run it becomes a case, and when validators decide it, the outcome is published with your name on it.') }}
                    </p>
                </div>
            </div>

            <!-- The one condition on an otherwise unconditional promise. It is
                 stated as plainly as the promise itself, because a rule people
                 only meet when it is used on them reads as an excuse. -->
            <div class="rounded-xl border border-amber-400/30 bg-amber-500/[0.07] p-4 mb-8">
                <span class="text-amber-300 font-bold">{{ $t('Abusing this loses you the amnesty.') }}</span>
                <span class="text-gray-300">
                    {{ $t("It is here to put the leaderboard right, not to launder anything. If it is shown that somebody used it to game the system, that player loses access to the amnesty programme and from then on their runs go through reports and validators like anybody else's.") }}
                </span>
            </div>

            <div v-if="blocked" class="bg-black/40 border border-red-400/40 rounded-2xl p-6 mb-8">
                <h2 class="text-red-300 font-black text-lg mb-1">{{ $t('You no longer have access to the amnesty') }}</h2>
                <p class="text-gray-300 leading-relaxed">
                    {{ $t('Withdrawn on :date', { date: fmtDate(blocked.since) }) }}<span v-if="blocked.reason">: {{ blocked.reason }}</span>.
                    {{ $t('Your runs are handled through the normal reporting and validation process from here on. Take it up with the admin if you think this is wrong.') }}
                </p>
            </div>

            <div v-if="!blocked && !hasMddAccount" class="bg-black/40 border border-yellow-400/30 rounded-2xl p-6 text-yellow-200">
                {{ $t('Your account is not linked to an mDd profile, so there are no records tied to it here. Link it in your settings first.') }}
            </div>

            <template v-else-if="!blocked">
                <!-- The list -->
                <div class="bg-black/40 backdrop-blur-sm border border-white/10 rounded-2xl overflow-hidden">
                    <div class="p-4 border-b border-white/5 flex flex-wrap items-center gap-3">
                        <input v-model="search" type="text" :placeholder="$t('Search your maps...')"
                            class="flex-1 min-w-[12rem] bg-black/50 border border-white/10 rounded-lg px-3 py-2 text-white placeholder-gray-500 focus:outline-none focus:border-emerald-400/50" />

                        <!-- Buttons rather than a <select> for the same reason
                             the reason picker is hand-built: four options are
                             worth four buttons, and the browser cannot repaint
                             these in system colours. -->
                        <div class="flex items-center gap-1 p-1 rounded-lg bg-black/40 border border-white/10">
                            <span class="text-xs uppercase tracking-wide text-gray-500 px-2">{{ $t('Sort') }}</span>
                            <button v-for="(label, key) in SORTS" :key="key" type="button" @click="sort = key"
                                class="px-3 py-1.5 rounded-md text-sm font-semibold transition-colors"
                                :class="sort === key ? 'bg-emerald-500/20 text-emerald-200' : 'text-gray-400 hover:text-white hover:bg-white/10'">
                                {{ label }}
                            </button>
                        </div>

                        <button type="button" @click="toggleAllShown" :disabled="!rows.length"
                            class="px-3 py-2 rounded-lg text-sm font-bold bg-white/5 hover:bg-white/10 text-gray-200 transition-colors disabled:opacity-40">
                            {{ allShownPicked ? $t('Unselect this page') : $t('Select this page') }}
                        </button>

                        <span class="text-xs text-gray-500 whitespace-nowrap">
                            {{ search
                                ? $tc(':count run found|:count runs found', records.total ?? rows.length)
                                : $tc(':count run in total|:count runs in total', records.total ?? rows.length) }}
                        </span>
                    </div>

                    <div v-if="!rows.length" class="p-10 text-center text-gray-500">
                        {{ $t('No runs found.') }}
                    </div>

                    <div v-else class="divide-y divide-white/5">
                        <label v-for="record in rows" :key="record.id"
                            class="flex items-center gap-4 p-3 cursor-pointer transition-colors"
                            :class="picked.has(record.id) ? 'bg-emerald-500/10' : 'hover:bg-white/[0.04]'">

                            <input type="checkbox" :checked="picked.has(record.id)" @change="toggle(record)"
                                class="w-5 h-5 shrink-0 rounded bg-black/50 border-white/20 text-emerald-500 focus:ring-emerald-500/40" />

                            <img :src="thumb(record.map_thumbnail)" alt=""
                                onerror="this.onerror=null;this.src='/images/unknown.jpg'"
                                class="w-24 h-14 shrink-0 rounded-lg object-cover bg-gray-900" />

                            <div class="min-w-0 flex-1">
                                <div class="font-semibold text-white truncate">{{ record.mapname }}</div>
                                <div class="text-xs text-gray-500 mt-0.5 flex flex-wrap gap-x-3">
                                    <span class="uppercase">{{ record.physics }} {{ record.mode }}</span>
                                    <span>{{ $t('run :date', { date: fmtDate(record.date_set) }) }}</span>
                                    <span v-if="record.map_added">{{ $t('map :date', { date: fmtDate(record.map_added) }) }}</span>
                                </div>
                            </div>

                            <div v-if="record.rank" class="text-center shrink-0 w-14">
                                <div class="text-[10px] uppercase tracking-wide text-gray-600">{{ $t('Rank') }}</div>
                                <div class="font-black" :class="record.rank === 1 ? 'text-yellow-400' : record.rank <= 3 ? 'text-gray-300' : 'text-gray-400'">
                                    {{ record.rank }}
                                </div>
                            </div>

                            <div class="font-mono text-gray-200 whitespace-nowrap w-24 text-right">{{ formatTime(record.time) }}</div>
                        </label>
                    </div>

                    <!-- Anything ticked stays ticked across pages, searches and
                         re-sorts: the selection is held in the browser, and the
                         page changes are partial reloads. -->
                    <div v-if="records.last_page > 1" class="p-3 border-t border-white/5">
                        <Pagination :last_page="records.last_page" :current_page="records.current_page"
                            :link="records.first_page_url" :only="['records']" />
                    </div>
                </div>

                <div v-if="mineCounts.all" class="mt-6 bg-black/40 backdrop-blur-sm border border-white/10 rounded-2xl overflow-hidden">
                    <div class="p-4 border-b border-white/5 flex flex-wrap items-baseline gap-x-3">
                        <span class="text-white font-bold">{{ $t('Runs you have sent') }}</span>
                        <span class="text-gray-500 text-sm">{{ $t(':count in total, and why', { count: mineCounts.all }) }}</span>
                        <span class="ml-auto text-xs text-gray-600">{{ $t('Visible to you and the site admin. Nobody else.') }}</span>
                    </div>

                    <!-- Same split the admin panel uses, so what you are told
                         here and what happens there are the same thing. -->
                    <div class="px-4 pt-3 flex flex-wrap gap-2">
                        <button v-for="(label, key) in MINE_STATES" :key="key" type="button"
                            v-show="key === 'all' || mineCounts[key]"
                            @click="setMineState(key)"
                            class="px-3 py-1.5 rounded-lg text-sm font-semibold border transition-colors"
                            :class="mineState === key
                                ? 'bg-emerald-500/20 border-emerald-400/50 text-emerald-200'
                                : 'bg-black/40 border-white/10 text-gray-400 hover:text-white hover:border-white/25'">
                            {{ label }}
                            <span class="ml-1 opacity-60">{{ mineCounts[key] ?? 0 }}</span>
                        </button>
                    </div>

                    <div class="divide-y divide-white/5 mt-3">
                        <div v-if="!mineRows.length" class="p-6 text-center text-gray-500 text-sm">
                            {{ $t('Nothing in this one.') }}
                        </div>
                        <div v-for="row in mineRows" :key="row.id" class="p-4 flex flex-wrap items-center gap-x-4 gap-y-1">
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="font-semibold text-white">{{ row.mapname }}</span>
                                    <span class="text-[11px] px-2 py-0.5 rounded border font-bold"
                                        :class="row.beaten
                                            ? 'border-blue-400/30 bg-blue-500/15 text-blue-200'
                                            : row.restored
                                                ? 'border-red-400/30 bg-red-500/15 text-red-200'
                                                : row.processed
                                                    ? 'border-green-400/30 bg-green-500/15 text-green-300'
                                                    : 'border-amber-400/40 bg-amber-500/15 text-amber-200'">
                                        {{ row.state }}
                                    </span>
                                </div>
                                <div class="text-sm text-gray-400 mt-0.5">
                                    {{ row.reason }}<span v-if="row.note" class="text-gray-500"> - {{ row.note }}</span>
                                </div>
                            </div>
                            <div class="text-sm text-gray-400 uppercase whitespace-nowrap">{{ row.physics }} {{ row.mode }}</div>
                            <div class="font-mono text-sm text-gray-300 whitespace-nowrap">{{ formatTime(row.time) }}</div>
                            <div class="text-sm text-gray-500 whitespace-nowrap">{{ fmtDate(row.created_at) }}</div>
                        </div>
                    </div>

                    <div v-if="mine.last_page > 1" class="p-4 border-t border-white/5">
                        <!-- Its own page parameter, or paging this list would
                             page the records above it as well. -->
                        <Pagination :last_page="mine.last_page" :current_page="mine.current_page"
                            :link="mine.first_page_url" page-name="mine_page"
                            :only="['mine', 'mineState', 'mineCounts']" />
                    </div>
                </div>

                <!-- Action bar. Fixed rather than sitting under the list: with
                     120 rows the confirmation would otherwise be a scroll away
                     from whatever was just ticked. -->
                <div v-if="pickedCount"
                    class="fixed bottom-0 inset-x-0 z-40 border-t border-emerald-400/30 bg-gray-950/95 backdrop-blur-xl shadow-[0_-8px_40px_rgba(0,0,0,0.6)]">
                    <form @submit.prevent="askConfirm" class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8 py-4 flex flex-wrap items-end gap-4">

                        <div class="shrink-0">
                            <div class="text-2xl font-black text-emerald-300 leading-none">{{ pickedCount }}</div>
                            <button type="button" @click="clearPicked" class="text-xs text-gray-500 hover:text-white">
                                {{ $tc('run picked - clear|runs picked - clear', pickedCount) }}
                            </button>
                        </div>

                        <div ref="reasonBox" class="relative min-w-[16rem] flex-1">
                            <label class="block text-xs text-gray-400 mb-1">
                                {{ $t('What you did in') }}
                                <span v-if="pickedCount === 1">{{ $t('this run') }}</span>
                                <span v-else>{{ $t('all :count of these runs', { count: pickedCount }) }}</span>
                            </label>
                            <button type="button" @click="reasonOpen = !reasonOpen"
                                class="w-full flex items-center justify-between gap-2 bg-black/50 border rounded-lg px-3 py-2 text-left text-white transition-colors"
                                :class="reasonOpen ? 'border-emerald-400/60' : 'border-white/10 hover:border-white/25'">
                                <span>{{ reasons[form.reason] }}</span>
                                <svg class="w-4 h-4 opacity-60 transition-transform duration-200 shrink-0"
                                    :class="reasonOpen ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24"
                                    stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>

                            <!-- Opens upward: the bar is already at the bottom
                                 of the window. -->
                            <div v-if="reasonOpen"
                                class="absolute z-50 bottom-full left-0 right-0 mb-2 p-1.5 rounded-xl border border-white/15 bg-gray-900/95 backdrop-blur-xl shadow-[0_8px_40px_rgba(0,0,0,0.6)] max-h-72 overflow-y-auto">
                                <button v-for="(label, key) in reasons" :key="key" type="button" @click="pickReason(key)"
                                    class="w-full flex items-center gap-2 text-left px-3 py-2 rounded-lg text-sm transition-colors"
                                    :class="form.reason === key
                                        ? 'bg-emerald-500/20 text-emerald-100'
                                        : 'text-gray-300 hover:bg-white/10 hover:text-white'">
                                    <svg class="w-4 h-4 shrink-0" :class="form.reason === key ? 'opacity-100' : 'opacity-0'"
                                        fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                    </svg>
                                    <span>{{ label }}</span>
                                </button>
                            </div>
                        </div>

                        <div class="min-w-[14rem] flex-1">
                            <label class="block text-xs text-gray-400 mb-1">{{ $t('Note (optional, admin only)') }}</label>
                            <input v-model="form.note" type="text" maxlength="500"
                                class="w-full bg-black/50 border border-white/10 rounded-lg px-3 py-2 text-white placeholder-gray-500 focus:outline-none focus:border-emerald-400/50"
                                :placeholder="$t('Only the site admin reads this.')" />
                        </div>

                        <div class="min-w-[18rem] flex-1">
                            <label class="block text-xs text-gray-400 mb-1">{{ $t('When') }}</label>
                            <div class="flex flex-col gap-1">
                                <button v-for="(label, key) in handlingOptions" :key="key" type="button"
                                    @click="form.handling = key"
                                    class="flex items-start gap-2 text-left px-3 py-1.5 rounded-lg text-sm transition-colors"
                                    :class="form.handling === key
                                        ? 'bg-emerald-500/20 text-emerald-100'
                                        : 'text-gray-400 hover:bg-white/10 hover:text-white'">
                                    <svg class="w-4 h-4 shrink-0 mt-0.5" :class="form.handling === key ? 'opacity-100' : 'opacity-0'"
                                        fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                    </svg>
                                    <span>{{ label }}</span>
                                </button>
                            </div>
                        </div>

                        <div class="flex items-center gap-4">
                            <label class="flex items-start gap-2 text-sm text-gray-300 max-w-sm">
                                <input type="checkbox" v-model="form.confirm" class="mt-0.5 w-5 h-5 shrink-0 rounded bg-black/50 border-white/20 text-emerald-500" />
                                <span>
                                    {{ $t('To the best of my knowledge and conscience, what I have said about these runs is true.') }}
                                </span>
                            </label>

                            <button type="submit" :disabled="form.processing"
                                class="px-5 py-2.5 rounded-lg bg-emerald-500 hover:bg-emerald-600 text-white font-bold transition-colors disabled:opacity-50 whitespace-nowrap">
                                {{ $tc('Withdraw :count run|Withdraw :count runs', pickedCount) }}
                            </button>
                        </div>

                        <div v-if="form.errors.confirm || form.errors.record_ids || form.errors.reason || form.errors.handling"
                            class="w-full text-red-400 text-sm">
                            {{ form.errors.confirm || form.errors.record_ids || form.errors.reason || form.errors.handling }}
                        </div>
                    </form>
                </div>

                <!-- The last thing between a player and their own runs coming
                     off the board. Deliberately blunt, and deliberately not a
                     browser confirm() - this one has to be read. -->
                <div v-if="confirming" class="fixed inset-0 z-[60] flex items-center justify-center p-4">
                    <div class="absolute inset-0 bg-black/80 backdrop-blur-sm" @click="confirming = false"></div>

                    <div class="relative w-full max-w-lg rounded-2xl border border-red-400/40 bg-gray-950 p-6 shadow-[0_20px_80px_rgba(0,0,0,0.8)]">
                        <h2 class="text-xl md:text-2xl font-black text-red-300 mb-3">
                            {{ $t('Are you sure you want to do this?') }}
                        </h2>

                        <p class="text-gray-200 leading-relaxed mb-3 [&_span]:font-bold [&_span]:text-white"
                           v-html="$tc('You are sending <span>:count</span> of your own run to be taken off the board. This step is irreversible - once they are handled you cannot bring them back.|You are sending <span>:count</span> of your own runs to be taken off the board. This step is irreversible - once they are handled you cannot bring them back.', pickedCount)"></p>

                        <p class="text-gray-300 leading-relaxed mb-3 [&_span]:font-semibold [&_span]:text-white"
                           v-html="$tc('You are declaring, to the best of your knowledge and conscience, that <span>:reason</span> is what happened in this run. If it is not true of all of them, go back and send them in separate batches.|You are declaring, to the best of your knowledge and conscience, that <span>:reason</span> is what happened in every one of these :count runs. If it is not true of all of them, go back and send them in separate batches.', pickedCount, { reason: reasons[form.reason] })"></p>

                        <p class="text-gray-300 leading-relaxed mb-5">
                            {{ $t('This exists for runs that should never have counted. Abusing it to delete times you are simply not happy with costs you access to this section permanently.') }}
                        </p>

                        <!-- The runs themselves, by name. Up to here the
                             confirmation only said how many there were, and a
                             selection is built across several searches and
                             pages - so the one moment it matters, nobody could
                             see what they had actually ticked. Each row can
                             still be dropped from here, because finding the
                             wrong one and having to go hunt for it again is
                             how people end up sending it anyway. -->
                        <div class="rounded-xl border border-white/10 bg-black/40 mb-3 overflow-hidden">
                            <div class="px-3 py-2 border-b border-white/10 text-[11px] font-bold uppercase tracking-wider text-gray-500">
                                {{ $tc('The run you are sending|The :count runs you are sending', pickedCount) }}
                            </div>

                            <div class="max-h-56 overflow-y-auto defrag-scrollbar divide-y divide-white/5" style="max-height: 14rem">
                                <div v-for="record in pickedRows" :key="record.id"
                                     class="flex items-center gap-3 px-3 py-2 text-sm">
                                    <div class="min-w-0 flex-1">
                                        <div class="font-semibold text-white truncate">{{ record.mapname }}</div>
                                        <div class="text-[11px] text-gray-500 flex flex-wrap gap-x-2">
                                            <span class="uppercase">{{ record.physics }} {{ record.mode }}</span>
                                            <span>{{ fmtDate(record.date_set) }}</span>
                                            <span v-if="record.rank">{{ $t('Rank') }} {{ record.rank }}</span>
                                        </div>
                                    </div>

                                    <span class="font-mono text-gray-200 whitespace-nowrap">{{ formatTime(record.time) }}</span>

                                    <button type="button" @click="unpick(record.id)"
                                            class="shrink-0 p-1 rounded text-gray-600 hover:text-red-300 hover:bg-red-500/10 transition-colors"
                                            :title="$t('Remove from this batch')">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-xl border border-white/10 bg-black/40 p-3 mb-5 text-sm">
                            <div class="flex justify-between gap-3 py-0.5">
                                <span class="text-gray-500">{{ $t('Runs') }}</span>
                                <span class="text-white font-semibold">{{ pickedCount }}</span>
                            </div>
                            <div class="flex justify-between gap-3 py-0.5">
                                <span class="text-gray-500">{{ $t('Reason') }}</span>
                                <span class="text-white font-semibold text-right">{{ reasons[form.reason] }}</span>
                            </div>
                            <div class="flex justify-between gap-3 py-0.5">
                                <span class="text-gray-500">{{ $t('When') }}</span>
                                <span class="text-white font-semibold text-right">{{ handlingOptions[form.handling] }}</span>
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-3 justify-end">
                            <button type="button" @click="confirming = false"
                                class="px-5 py-2.5 rounded-lg bg-white/5 hover:bg-white/10 text-gray-200 font-bold transition-colors">
                                {{ $t('No, take me back') }}
                            </button>
                            <button type="button" @click="submit" :disabled="form.processing"
                                class="px-5 py-2.5 rounded-lg bg-red-500 hover:bg-red-600 text-white font-bold transition-colors disabled:opacity-50">
                                {{ $tc('Yes, send :count run|Yes, send :count runs', pickedCount) }}
                            </button>
                        </div>
                    </div>
                </div>
            </template>

        </div>
    </div>
</template>
