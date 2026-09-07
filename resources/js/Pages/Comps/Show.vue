<script>
import MainLayout from '@/Layouts/MainLayout.vue';

export default {
    layout: MainLayout,
};
</script>

<script setup>
    import { Head, Link } from '@inertiajs/vue3';
    import { t } from '@/utils/i18n';
    import { formatTime } from '@/utils/time';
    import { physicsBadge, physicsText } from '@/utils/physics';

    import CompsPlayer from '@/Components/Comps/CompsPlayer.vue';
    import CompsPayoutText from '@/Components/Comps/CompsPayoutText.vue';

    // A finished comp, opened from the history list. Standings only - the
    // ballot, the countdown and the upload form all belonged to a week that is
    // over. What is left is the record of it: who won, by how much, on which
    // map, chosen how, and the demos to watch it back.
    const props = defineProps({
        comp: { type: Object, required: true },
    });

    const PHYSICS = ['cpm', 'vq3'];

    const CATEGORY_LABELS = {
        strafe: () => t('Strafe'),
        weapon: () => t('Weapon'),
        combo: () => t('Combo'),
    };

    const categoryLabel = (category) => (CATEGORY_LABELS[category] ?? (() => category))();

    const decidedLabel = (by) => ({
        wildcard: () => t('Chosen with a wildcard'),
        carried: () => t('Nobody voted in this physics, so it took the other one\'s map'),
        random: () => t('Nobody voted at all, so it was drawn at random'),
    }[by] ?? (() => t('Chosen by vote')))();

    const dateRange = (from, to) => {
        const opts = { weekday: 'short', day: 'numeric', month: 'long' };
        const a = from ? new Date(from) : null;
        const b = to ? new Date(to) : null;
        if (!a || !b) return '';
        return `${a.toLocaleDateString(undefined, opts)} - ${b.toLocaleDateString(undefined, { ...opts, year: 'numeric' })}`;
    };

    // The ballot as it finished, sorted by what each map got in this physics.
    // A map that could not be finished in it was never on that ballot and is
    // left out rather than shown with a zero it never had a chance to beat.
    const ballotFor = (round, physics) => (round.ballot ?? [])
        .filter((c) => c.blocked_physics !== physics)
        .slice()
        .sort((a, b) => b.votes[physics] - a.votes[physics]);

    const votesTotal = (round, physics) =>
        ballotFor(round, physics).reduce((n, c) => n + c.votes[physics], 0);

    const share = (round, physics, candidate) => {
        const total = votesTotal(round, physics);
        return total ? Math.round((candidate.votes[physics] / total) * 100) : 0;
    };

    const winners = (round, physics) => (round.results?.[physics] ?? []).filter((r) => r.rank === 1);
    const rest = (round, physics) => (round.results?.[physics] ?? []).filter((r) => r.rank !== 1);

    // Behind the winner by how much. The time alone says who was fast; the
    // gap says whether it was a race.
    const gap = (round, physics, row) => {
        const best = winners(round, physics)[0]?.time;
        if (best === undefined || row.time <= best) return null;
        return '+' + formatTime(row.time - best);
    };

    const RANK_STYLES = {
        2: 'bg-slate-300/15 text-slate-200 border-slate-300/30',
        3: 'bg-amber-700/20 text-amber-400 border-amber-600/30',
    };

    const rankStyle = (rank) => RANK_STYLES[rank] ?? 'bg-white/[0.04] text-gray-300 border-white/5';

    const entrants = (round) => PHYSICS.reduce((n, p) => n + (round.results?.[p]?.length ?? 0), 0);
</script>

<template>
    <Head :title="comp.title" />

    <div class="">
        <!-- Same header shape as the hub and the rest of the site. -->
        <div class="relative bg-gradient-to-b from-black/25 via-black/10 to-transparent pt-6 pb-96 pointer-events-none">
            <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8 pointer-events-auto">
                <Link :href="route('comps.index')"
                      class="inline-flex items-center gap-2 rounded-lg border border-white/15 bg-black/40 backdrop-blur-sm px-3 py-1.5 text-sm font-bold text-gray-200 hover:bg-white/10 hover:text-white hover:border-white/30 transition-colors">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6" /></svg>
                    {{ $t('Back to comps') }}
                </Link>
                <div class="mt-4 flex flex-wrap items-end justify-between gap-x-6 gap-y-2">
                    <div>
                        <h1 class="text-3xl md:text-4xl font-black text-white drop-shadow-[0_2px_4px_rgba(0,0,0,0.8)]">{{ comp.title }}</h1>
                    </div>
                </div>
            </div>
        </div>

    <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8 pb-12 space-y-8" style="margin-top: -22rem;">

        <section v-for="round in comp.rounds" :key="round.id"
                 class="rounded-2xl border border-white/10 bg-gradient-to-br from-blue-500/[0.06] via-black/40 to-black/40 backdrop-blur-sm overflow-hidden shadow-[0_0_40px_-14px_rgba(59,130,246,0.35)]">

            <!-- The same row as Playing now on the hub: what it was, what it
                 paid, how many came. A finished round and a running one are
                 the same thing seen at different times, and the page should
                 say so. -->
            <div class="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-1.5 border-b border-white/10 bg-white/[0.04] backdrop-blur-sm px-5 py-3">
                <div class="flex flex-wrap items-center gap-x-3 gap-y-1 min-w-0">
                    <span v-if="comp.type === 'season'" class="rounded-full bg-white/10 px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider text-gray-300">
                        {{ $t('Round :n', { n: round.index }) }}
                    </span>
                    <span class="text-lg font-black uppercase tracking-wider text-blue-300/90 self-center">
                        {{ categoryLabel(round.category) }}<template v-if="round.weapon"> · {{ round.weapon }}</template>
                    </span>
                    <span class="text-xs text-gray-300">{{ $tc(':count player|:count players', entrants(round)) }}</span>
                    <!-- Finished, and when: on the same line as what it was.
                         They hung under the title on their own before and
                         looked like they belonged to nothing. -->
                    <span class="rounded-full border border-white/15 bg-white/10 px-2.5 py-1 text-[10px] font-black uppercase tracking-wider text-gray-200">
                        {{ $t('Finished') }}
                    </span>
                    <span v-if="comp.starts_at && comp.ends_at"
                          class="inline-flex items-center gap-1.5 rounded-full border border-blue-400/30 bg-blue-500/15 px-2.5 py-0.5 text-xs font-bold tabular-nums text-blue-100">
                        <svg class="w-3.5 h-3.5 text-blue-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="5" width="18" height="16" rx="2" /><path d="M3 10h18M8 3v4M16 3v4" /></svg>
                        {{ dateRange(comp.starts_at, comp.ends_at) }}
                    </span>
                </div>

                <span v-if="round.prize_eur > 0" class="inline-flex items-baseline gap-2">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-300/60">{{ $t('Played for') }}</span>
                    <span class="text-base font-black tabular-nums text-emerald-300">{{ round.prize_eur * 2 }} EUR</span>
                    <span class="text-[11px] tabular-nums text-emerald-100/50">{{ $t('(:amount EUR per physics)', { amount: round.prize_eur }) }}</span>
                </span>
            </div>

            <div class="p-5">
                <div class="rounded-xl border border-white/10 bg-black/30 backdrop-blur-sm overflow-hidden">
                    <div class="grid md:grid-cols-2 md:divide-x md:divide-white/10">
                        <div v-for="physics in PHYSICS" :key="physics" class="p-4 space-y-4">

                            <!-- ---------------- Map ---------------- -->
                            <!-- Everything about the map to the right of its
                                 picture: physics and name on one line, the
                                 author under it, then how it was chosen. The
                                 physics badge and the "chosen by" used to be
                                 a line of their own above the picture. -->
                            <div class="flex gap-5">
                                <Link v-if="round.maps?.[physics]?.name" :href="route('maps.map', round.maps[physics].name)"
                                      class="group flex-shrink-0 block w-2/5 aspect-square rounded-xl overflow-hidden border border-white/10 hover:border-blue-400/50 transition-colors">
                                    <img v-if="round.maps[physics].thumbnail"
                                         :src="`/storage/${round.maps[physics].thumbnail}`"
                                         :alt="round.maps[physics].name"
                                         class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" />
                                    <span v-else class="block w-full h-full bg-white/[0.03]"></span>
                                </Link>
                                <span v-else class="flex-shrink-0 block w-2/5 aspect-square rounded-xl border border-white/10 bg-white/[0.03]"></span>

                                <div class="min-w-0 flex-1 flex flex-col">
                                    <div class="flex items-center gap-2 min-w-0">
                                        <span class="shrink-0 rounded-md border px-2 py-1 text-xs font-black uppercase tracking-widest" :class="physicsBadge(physics)">
                                            {{ physics }}
                                        </span>
                                        <Link v-if="round.maps?.[physics]?.name" :href="route('maps.map', round.maps[physics].name)"
                                              class="text-2xl font-black text-white hover:text-blue-300 transition-colors truncate">
                                            {{ round.maps[physics].name }}
                                        </Link>
                                    </div>
                                    <div v-if="round.maps?.[physics]?.author" class="mt-1 text-base text-gray-300 truncate">{{ round.maps[physics].author }}</div>

                                    <!-- Who ran and what the winner took, per
                                         physics, right here where the map is.
                                         The header says both for the whole
                                         round; this is the half that matters
                                         under this picture. -->
                                    <div class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-0.5 text-base">
                                        <span class="font-bold text-gray-200">{{ $tc(':count player|:count players', round.results?.[physics]?.length ?? 0) }}</span>
                                        <span v-if="round.prize_eur > 0" class="font-black text-emerald-300">{{ $t('Winner gets :amount EUR', { amount: round.prize_eur }) }}</span>
                                    </div>

                                    <div v-if="round.maps?.[physics]" class="mt-2 text-sm text-gray-300">
                                        <template v-if="round.wildcards?.[physics]">
                                            <span class="inline-flex flex-wrap items-center gap-1 rounded-md border border-amber-500/30 bg-amber-500/10 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-amber-300">
                                                <svg class="w-3 h-3" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l2.4 7.4H22l-6.2 4.5 2.4 7.4-6.2-4.6-6.2 4.6 2.4-7.4L2 9.4h7.6z" /></svg>
                                                {{ $t('Chosen with a wildcard') }}
                                                <CompsPlayer v-if="round.wildcards[physics].user" :player="round.wildcards[physics].user" size="sm" />
                                            </span>
                                        </template>
                                        <template v-else>{{ decidedLabel(round.maps[physics].decided_by) }}</template>
                                    </div>
                                </div>
                            </div>

                            <!-- The week's demos, once. Two flavours of the
                                 same archive: with the names in the file
                                 names, or with only the rank and the time so
                                 you can guess. -->
                            <div v-if="round.demos?.[physics]" class="rounded-xl border border-white/10 bg-white/[0.03] p-3">
                                <div class="flex items-baseline justify-between gap-3 mb-1">
                                    <span class="text-xs font-black uppercase tracking-wider text-gray-300">{{ $t('Download the demos') }}</span>
                                    <span class="text-xs font-bold tabular-nums" :class="physicsText(physics)">{{ $tc(':count demo|:count demos', round.demos[physics].count) }} · 7z</span>
                                </div>
                                <p class="text-[11px] leading-snug text-gray-300 mb-2.5">
                                    {{ $t('Every run from the standings, one file each, named by rank and time. Anonymized leaves the names out so you can guess who ran what.') }}
                                </p>
                                <div class="grid grid-cols-2 gap-2">
                                    <a :href="round.demos[physics].anonymized"
                                       class="flex items-center justify-center gap-2 rounded-lg border border-white/15 bg-white/[0.06] px-3 py-2 text-sm font-bold text-gray-100 hover:bg-white/10 hover:border-white/25 transition-colors">
                                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3v12m0 0l-4-4m4 4l4-4M4 17v2a2 2 0 002 2h12a2 2 0 002-2v-2" /></svg>
                                        {{ $t('Anonymized') }}
                                    </a>
                                    <a :href="round.demos[physics].revealed"
                                       class="flex items-center justify-center gap-2 rounded-lg border px-3 py-2 text-sm font-bold transition-colors"
                                       :class="physics === 'cpm' ? 'border-violet-400/40 bg-violet-500/15 text-violet-100 hover:bg-violet-500/25' : 'border-sky-400/40 bg-sky-500/15 text-sky-100 hover:bg-sky-500/25'">
                                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3v12m0 0l-4-4m4 4l4-4M4 17v2a2 2 0 002 2h12a2 2 0 002-2v-2" /></svg>
                                        {{ $t('Revealed') }}
                                    </a>
                                </div>
                            </div>

                            <!-- ---------------- Winner ---------------- -->
                            <div v-if="winners(round, physics).length"
                                 class="rounded-xl border border-amber-400/30 bg-gradient-to-r from-amber-500/[0.12] to-transparent px-4 py-3">
                                <div class="text-[10px] font-black uppercase tracking-wider text-amber-300/70 mb-2">
                                    {{ winners(round, physics).length > 1 ? $t('Winners, tied') : $t('Winner') }}
                                </div>
                                <!-- The time stays on the right where every
                                     other row keeps it. What became of the
                                     prize is a line of its own under the
                                     name, plain words, no box. -->
                                <div v-for="w in winners(round, physics)" :key="w.user.id" class="space-y-1">
                                    <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
                                        <div class="flex items-center gap-3 min-w-0">
                                            <span class="flex-shrink-0 w-8 h-8 rounded-full bg-amber-400/20 border border-amber-400/40 flex items-center justify-center text-amber-300 font-black text-sm">1</span>
                                            <CompsPlayer :player="w.user" />
                                        </div>
                                        <span class="text-2xl font-black tabular-nums text-white">{{ formatTime(w.time) }}</span>
                                    </div>
                                    <div class="pl-11 text-xs">
                                        <CompsPayoutText v-if="w.payout" :payout="w.payout" />
                                        <span v-else-if="round.prize_eur > 0" class="font-bold text-emerald-300">{{ round.prize_eur }} EUR</span>
                                    </div>
                                </div>
                            </div>

                            <!-- ---------------- Standings ---------------- -->
                            <table v-if="rest(round, physics).length" class="w-full text-sm">
                                <tbody class="divide-y divide-white/5">
                                    <tr v-for="row in rest(round, physics)" :key="row.user.id" class="hover:bg-white/[0.03] transition-colors">
                                        <td class="py-1.5 pr-3 w-10">
                                            <span class="inline-flex w-7 h-7 items-center justify-center rounded-full border text-xs font-black tabular-nums"
                                                  :class="rankStyle(row.rank)">{{ row.rank }}</span>
                                        </td>
                                        <td class="py-1.5"><CompsPlayer :player="row.user" size="sm" /></td>
                                        <td class="py-1.5 text-right tabular-nums">
                                            <span class="font-bold text-white">{{ formatTime(row.time) }}</span>
                                            <span v-if="gap(round, physics, row)" class="ml-2 text-[11px] text-gray-300">{{ gap(round, physics, row) }}</span>
                                        </td>
                                        <td v-if="comp.type === 'season'" class="py-1.5 pl-3 text-right tabular-nums text-gray-300 w-14">{{ row.points }}</td>
                                    </tr>
                                </tbody>
                            </table>
                            <p v-if="!round.results?.[physics]?.length" class="rounded-lg border border-dashed border-white/10 px-4 py-6 text-center text-sm text-gray-400">
                                {{ $t('Nobody entered.') }}
                            </p>

                            <!-- ---------------- Ballot ---------------- -->
                            <details v-if="ballotFor(round, physics).length" open class="group pt-2 border-t border-white/10">
                                <summary class="cursor-pointer list-none flex items-center justify-between text-[10px] font-black uppercase tracking-wider text-gray-300 hover:text-gray-300 transition-colors">
                                    <span>{{ $t('Voted from') }} · {{ $tc(':count vote|:count votes', votesTotal(round, physics)) }}</span>
                                    <svg class="w-3.5 h-3.5 transition-transform group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6" /></svg>
                                </summary>
                                <ul class="mt-3 space-y-1.5">
                                    <li v-for="c in ballotFor(round, physics)" :key="c.map" class="flex items-center gap-2 text-xs">
                                        <Link :href="route('maps.map', c.map)"
                                              class="w-40 truncate transition-colors"
                                              :class="c.map === round.maps?.[physics]?.name ? 'font-bold text-white hover:text-blue-300' : 'text-gray-300 hover:text-gray-200'">
                                            {{ c.map }}
                                        </Link>
                                        <span class="flex-1 h-1.5 rounded-full bg-black/50 overflow-hidden">
                                            <span class="block h-full rounded-full"
                                                  :class="c.map === round.maps?.[physics]?.name ? 'bg-blue-400' : 'bg-gray-600'"
                                                  :style="{ width: share(round, physics, c) + '%' }"></span>
                                        </span>
                                        <span class="w-6 text-right tabular-nums"
                                              :class="c.map === round.maps?.[physics]?.name ? 'font-bold text-white' : 'text-gray-300'">
                                            {{ c.votes[physics] }}
                                        </span>
                                    </li>
                                </ul>
                            </details>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- The same way back as at the top. Somebody who has just read to
             the bottom of two leaderboards should not have to scroll up to
             leave. -->
        <div class="flex justify-center pt-2">
            <Link :href="route('comps.index')"
                  class="inline-flex items-center gap-2 rounded-xl border border-blue-400/30 bg-blue-500/15 px-5 py-2.5 text-sm font-bold text-blue-100 hover:bg-blue-500/25 hover:border-blue-400/50 transition-colors">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 18l-6-6 6-6" /></svg>
                {{ $t('Back to comps') }}
            </Link>
        </div>
        </div>
    </div>
</template>
