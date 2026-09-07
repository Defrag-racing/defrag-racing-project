<script setup>
    import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
    import CompsPlayer from '@/Components/Comps/CompsPlayer.vue';
    import { physicsText } from '@/utils/physics';

    // Who has been winning, not just who won last week. One table per year
    // and one for all time, the points being the ones every comp result
    // already carries. Ten rows to start with, the rest behind a button.
    const props = defineProps({
        periods: { type: Array, required: true },
        rows: { type: Object, required: true },
    });

    const period = ref(props.periods[0]?.key);
    const expanded = ref(false);

    // As many rows as the history beside it is tall, measured, not a fixed
    // ten: the two columns should end together. Show all appears only when
    // there are more rows than that. Below the lg breakpoint the columns
    // stack and every row is shown.
    const root = ref(null);
    const firstRow = ref(null);
    const fit = ref(10);
    let observer = null;

    const neighbour = () => root.value?.parentElement?.firstElementChild;

    const measure = () => {
        if (typeof window === 'undefined') return;
        if (window.innerWidth < 1024) { fit.value = Infinity; return; }
        const left = neighbour();
        if (!left || left === root.value) return;
        const rowHeight = firstRow.value?.getBoundingClientRect().height || 44;
        const box = root.value?.getBoundingClientRect();
        const chrome = (box?.height ?? 0) - (firstRow.value ? visible.value.length * rowHeight : 0);
        const room = left.getBoundingClientRect().height - chrome;
        fit.value = Math.max(5, Math.floor(room / rowHeight));
    };

    const all = computed(() => props.rows[period.value] ?? []);
    const visible = computed(() => expanded.value ? all.value : all.value.slice(0, fit.value));
    const overflows = computed(() => all.value.length > fit.value);

    onMounted(() => {
        nextTick(measure);
        window.addEventListener('resize', measure);
        // The history grows as its map pictures load; follow it.
        if (typeof ResizeObserver !== 'undefined' && neighbour()) {
            observer = new ResizeObserver(() => measure());
            observer.observe(neighbour());
        }
    });
    onBeforeUnmount(() => { window.removeEventListener('resize', measure); observer?.disconnect(); });
    watch(period, () => nextTick(measure));

    const RANK_STYLE = {
        1: 'bg-amber-400/20 border-amber-400/40 text-amber-300',
        2: 'bg-slate-300/15 border-slate-300/40 text-slate-200',
        3: 'bg-orange-700/25 border-orange-500/40 text-orange-300',
    };

    const pick = (key) => { period.value = key; expanded.value = false; };
</script>

<template>
    <section ref="root" class="rounded-2xl border border-white/10 bg-black/40 backdrop-blur-sm overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-x-6 gap-y-2 border-b border-white/10 bg-white/[0.04] px-4 py-3">
            <div>
                <h2 class="text-lg font-black text-white">{{ $t('Overall leaderboard') }}</h2>
                <p class="text-sm text-gray-300">{{ $t('Points from every finished comp, added up: 25 for a win, 18 for second, 15 for third, down to 1 for finishing.') }}</p>
            </div>
            <div class="flex gap-1 rounded-lg border border-white/10 bg-black/40 p-1">
                <button v-for="p in periods" :key="p.key" type="button" @click="pick(p.key)"
                        class="rounded-md px-3 py-1 text-xs font-black transition-colors"
                        :class="period === p.key ? 'bg-blue-500/30 text-white' : 'text-gray-300 hover:text-white'">
                    {{ p.key === 'all' ? $t('All time') : p.label }}
                </button>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-[15px]">
                <thead>
                    <tr class="text-[11px] font-black uppercase tracking-wider text-gray-300">
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
                    <tr v-for="(row, i) in visible" :key="row.id" :ref="(el) => { if (i === 0) firstRow = el; }" class="hover:bg-white/[0.03]" :class="row.rank > 3 && 'text-gray-300'">
                        <td class="px-3 py-2.5">
                            <span class="inline-flex w-6 h-6 items-center justify-center rounded-full border text-[11px] font-black"
                                  :class="RANK_STYLE[row.rank] ?? 'border-white/10 bg-white/5 text-gray-300'">{{ row.rank }}</span>
                        </td>
                        <td class="px-2 py-2.5"><CompsPlayer :player="row" /></td>
                        <td class="px-2 py-2.5 text-right tabular-nums">{{ row.comps }}</td>
                        <td class="px-2 py-2.5 text-right tabular-nums">{{ row.wins }}</td>
                        <td class="px-2 py-2.5 text-right tabular-nums text-gray-300">{{ row.points_cpm }}</td>
                        <td class="px-2 py-2.5 text-right tabular-nums text-gray-300">{{ row.points_vq3 }}</td>
                        <td class="px-3 py-2.5 text-right tabular-nums font-black text-white">{{ row.points }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="overflows" class="border-t border-white/10 px-4 py-2 text-center">
            <button type="button" @click="expanded = !expanded" class="text-xs font-bold text-blue-300/80 hover:text-blue-300">
                {{ expanded ? $t('Show less') : $tc('Show all :count', all.length) }}
            </button>
        </div>
    </section>
</template>
