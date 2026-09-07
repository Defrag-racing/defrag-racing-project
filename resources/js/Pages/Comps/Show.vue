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

    import CompsPlayer from '@/Components/Comps/CompsPlayer.vue';
    import CompsPayoutBadge from '@/Components/Comps/CompsPayoutBadge.vue';

    // A finished comp, opened from the history list. Standings only - the
    // ballot, the countdown and the upload form all belonged to a week that is
    // over.
    defineProps({
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
</script>

<template>
    <Head :title="comp.title" />

    <div class="">
        <!-- Same header shape as the hub and the rest of the site. -->
        <div class="relative bg-gradient-to-b from-black/25 via-black/10 to-transparent pt-6 pb-96 pointer-events-none">
            <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8 pointer-events-auto">
                <Link :href="route('comps.index')" class="text-sm text-gray-400 hover:text-white transition-colors drop-shadow-[0_2px_4px_rgba(0,0,0,0.8)]">
                    &larr; {{ $t('Comps') }}
                </Link>
                <h1 class="mt-2 text-2xl md:text-3xl font-black text-gray-300/90">{{ comp.title }}</h1>
            </div>
        </div>

    <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8 pb-12 space-y-8" style="margin-top: -22rem;">

        <section v-for="round in comp.rounds" :key="round.id" class="rounded-2xl border border-white/10 bg-black/40 backdrop-blur-sm overflow-hidden">
            <div class="flex flex-wrap items-center gap-3 border-b border-white/10 bg-white/5 backdrop-blur-sm px-5 py-3">
                <span v-if="comp.type === 'season'" class="rounded-full bg-white/10 px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider text-gray-300">
                    {{ $t('Round :n', { n: round.index }) }}
                </span>
                <span class="text-xs text-gray-500">
                    {{ categoryLabel(round.category) }}<template v-if="round.weapon"> ({{ round.weapon }})</template>
                </span>

                <!-- What the week paid. Without it a finished round reads like
                     a scoreboard from a friendly. -->
                <span v-if="round.prize_eur > 0"
                      class="ml-auto inline-flex items-baseline gap-1.5 rounded-lg border border-emerald-400/25 bg-emerald-500/10 px-2.5 py-0.5">
                    <span class="text-sm font-black tabular-nums text-emerald-300">{{ round.prize_eur }} EUR</span>
                    <span class="text-[10px] uppercase tracking-wider text-emerald-100/60">{{ $t('to the winner of each physics') }}</span>
                </span>
            </div>

            <div class="grid gap-5 p-5 md:grid-cols-2">
                <div v-for="physics in PHYSICS" :key="physics">
                    <div class="mb-3">
                        <div class="text-[10px] font-black uppercase tracking-widest text-gray-500">{{ physics }}</div>
                        <Link
                            v-if="round.maps?.[physics]?.name"
                            :href="route('maps.map', round.maps[physics].name)"
                            class="font-bold text-white hover:text-blue-300 transition-colors"
                        >
                            {{ round.maps[physics].name }}
                        </Link>
                        <span v-else class="text-gray-600">-</span>
                        <!-- Overruling the vote is the loudest thing that can
                             happen to a round, and it used to be a line of grey
                             text the same size as everything else. -->
                        <div v-if="round.wildcards?.[physics]"
                             class="mt-1 inline-flex items-center gap-1.5 rounded-lg border border-amber-400/40 bg-amber-500/15 px-2 py-0.5 text-[11px] font-bold text-amber-200">
                            <svg class="w-3 h-3" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l2.4 7.4H22l-6.2 4.5 2.4 7.4-6.2-4.6-6.2 4.6 2.4-7.4L2 9.4h7.6z" /></svg>
                            {{ $t('Chosen with a wildcard') }}
                            <CompsPlayer v-if="round.wildcards[physics].user" :player="round.wildcards[physics].user" size="sm" />
                        </div>
                        <div v-else-if="round.maps?.[physics]" class="text-[11px] text-gray-600">
                            {{ decidedLabel(round.maps[physics].decided_by) }}
                        </div>
                    </div>

                    <table v-if="round.results?.[physics]?.length" class="w-full text-sm">
                        <thead class="text-[10px] uppercase tracking-wider text-gray-500">
                            <tr>
                                <th class="py-1 text-left font-bold w-8">#</th>
                                <th class="py-1 text-left font-bold">{{ $t('Player') }}</th>
                                <th class="py-1 text-right font-bold">{{ $t('Time') }}</th>
                                <th v-if="comp.type === 'season'" class="py-1 text-right font-bold w-14">{{ $t('Points') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            <tr v-for="(row, i) in round.results[physics]" :key="i">
                                <td class="py-1.5 font-bold tabular-nums" :class="row.rank === 1 ? 'text-amber-300' : 'text-gray-500'">
                                    {{ row.rank }}
                                </td>
                                <td class="py-1.5"><CompsPlayer :player="row.user" size="sm" /></td>
                                <td class="py-1.5 text-right font-bold tabular-nums text-white">{{ formatTime(row.time) }}</td>
                                <td class="py-1.5 text-right tabular-nums">
                                    <!-- The prize and what became of it, in one
                                         label. A bare "15 EUR" beside the winner
                                         read as money still owed, whether it had
                                         been paid weeks ago or given back. -->
                                    <CompsPayoutBadge v-if="row.payout" :payout="row.payout" :amount="row.payout.amount" />
                                    <span v-else-if="row.rank === 1 && round.prize_eur > 0" class="font-black text-emerald-300">{{ round.prize_eur }} EUR</span>
                                </td>
                                <td v-if="comp.type === 'season'" class="py-1.5 text-right tabular-nums text-gray-400">{{ row.points }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-else class="text-sm text-gray-600">{{ $t('Nobody entered.') }}</p>

                    <!-- What people chose from, and by how much. The winning map
                         on its own does not say whether it was a landslide or
                         one vote, and the pool it came out of is half the story
                         of a week. -->
                    <div v-if="ballotFor(round, physics).length" class="mt-4 pt-3 border-t border-white/10">
                        <div class="mb-2 text-[10px] font-black uppercase tracking-wider text-gray-500">
                            {{ $t('Voted from') }} ({{ votesTotal(round, physics) }} {{ $t('votes') }})
                        </div>
                        <ul class="space-y-1">
                            <li v-for="c in ballotFor(round, physics)" :key="c.map"
                                class="flex items-center gap-2 text-xs">
                                <Link :href="route('maps.map', c.map)"
                                      class="w-40 truncate transition-colors"
                                      :class="c.map === round.maps?.[physics]?.name ? 'font-bold text-white hover:text-blue-300' : 'text-gray-400 hover:text-gray-200'">
                                    {{ c.map }}
                                </Link>
                                <span class="flex-1 h-1.5 rounded-full bg-black/50 overflow-hidden">
                                    <span class="block h-full rounded-full"
                                          :class="c.map === round.maps?.[physics]?.name ? 'bg-blue-400' : 'bg-gray-600'"
                                          :style="{ width: share(round, physics, c) + '%' }"></span>
                                </span>
                                <span class="w-6 text-right tabular-nums"
                                      :class="c.map === round.maps?.[physics]?.name ? 'font-bold text-white' : 'text-gray-500'">
                                    {{ c.votes[physics] }}
                                </span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </section>
        </div>
    </div>
</template>
