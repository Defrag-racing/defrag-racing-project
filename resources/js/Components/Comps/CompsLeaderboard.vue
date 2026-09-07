<script setup>
    import { computed, ref } from 'vue';
    import CompsPlayer from '@/Components/Comps/CompsPlayer.vue';
    import { physicsText } from '@/utils/physics';

    // Who has been winning, not just who won last week. One table per year
    // and one for all time, the points being the ones every comp result
    // already carries. Ten rows to start with, the rest behind a button.
    const props = defineProps({
        periods: { type: Array, required: true },
        rows: { type: Object, required: true },
    });

    const SHOWN = 10;
    const period = ref(props.periods[0]?.key);
    const expanded = ref(false);

    const all = computed(() => props.rows[period.value] ?? []);
    const visible = computed(() => expanded.value ? all.value : all.value.slice(0, SHOWN));

    const RANK_STYLE = {
        1: 'bg-amber-400/20 border-amber-400/40 text-amber-300',
        2: 'bg-slate-300/15 border-slate-300/40 text-slate-200',
        3: 'bg-orange-700/25 border-orange-500/40 text-orange-300',
    };

    const pick = (key) => { period.value = key; expanded.value = false; };
</script>

<template>
    <!-- Ten rows stick to the top of the screen while the history beside
         them scrolls. Unfolded it stops sticking and stands on the page like
         the history does: a sticky box taller than the screen cannot be
         scrolled to its own bottom, and a scrollbar inside it is worse. -->
    <section class="rounded-2xl border border-white/10 bg-black/40 backdrop-blur-sm overflow-hidden"
             :class="expanded ? '' : 'lg:sticky lg:top-4'">
        <div class="flex flex-wrap items-center justify-between gap-x-6 gap-y-2 border-b border-white/10 bg-white/[0.04] px-4 py-3">
            <div>
                <h2 class="text-lg font-black text-white">{{ $t('Overall leaderboard') }}</h2>
                <p class="text-xs text-gray-500">{{ $t('Points from every finished comp, added up: 25 for a win, 18 for second, 15 for third, down to 1 for finishing.') }}</p>
            </div>
            <div class="flex gap-1 rounded-lg border border-white/10 bg-black/40 p-1">
                <button v-for="p in periods" :key="p.key" type="button" @click="pick(p.key)"
                        class="rounded-md px-3 py-1 text-xs font-black transition-colors"
                        :class="period === p.key ? 'bg-blue-500/30 text-white' : 'text-gray-400 hover:text-white'">
                    {{ p.key === 'all' ? $t('All time') : p.label }}
                </button>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-[15px]">
                <thead>
                    <tr class="text-[11px] font-black uppercase tracking-wider text-gray-500">
                        <th class="px-3 py-2 text-left w-10">#</th>
                        <th class="px-2 py-2 text-left">{{ $t('Player') }}</th>
                        <th class="px-2 py-2 text-right">{{ $t('Comps') }}</th>
                        <th class="px-2 py-2 text-right">{{ $t('Wins') }}</th>
                        <th class="px-2 py-2 text-right" :class="physicsText('cpm')">CPM</th>
                        <th class="px-2 py-2 text-right" :class="physicsText('vq3')">VQ3</th>
                        <th class="px-3 py-2 text-right">{{ $t('Points') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    <tr v-for="row in visible" :key="row.id" class="hover:bg-white/[0.03]" :class="row.rank > 3 && 'text-gray-300'">
                        <td class="px-3 py-2.5">
                            <span class="inline-flex w-6 h-6 items-center justify-center rounded-full border text-[11px] font-black"
                                  :class="RANK_STYLE[row.rank] ?? 'border-white/10 bg-white/5 text-gray-400'">{{ row.rank }}</span>
                        </td>
                        <td class="px-2 py-2.5"><CompsPlayer :player="row" /></td>
                        <td class="px-2 py-2.5 text-right tabular-nums">{{ row.comps }}</td>
                        <td class="px-2 py-2.5 text-right tabular-nums">{{ row.wins }}</td>
                        <td class="px-2 py-2.5 text-right tabular-nums text-gray-400">{{ row.points_cpm }}</td>
                        <td class="px-2 py-2.5 text-right tabular-nums text-gray-400">{{ row.points_vq3 }}</td>
                        <td class="px-3 py-2.5 text-right tabular-nums font-black text-white">{{ row.points }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="all.length > SHOWN" class="border-t border-white/10 px-4 py-2 text-center">
            <button type="button" @click="expanded = !expanded" class="text-xs font-bold text-blue-300/80 hover:text-blue-300">
                {{ expanded ? $t('Show less') : $tc('Show all :count', all.length) }}
            </button>
        </div>
    </section>
</template>
