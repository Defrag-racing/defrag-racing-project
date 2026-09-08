<script setup>
/**
 * How a player's level moved over time. One line, month by month: the
 * average score or the average rank of the records set that month, VQ3
 * or CPM at a time. A record's score and rank are what they are today,
 * so the curve rising means the player has been setting records that
 * stand higher than their old ones. Rank is drawn with 1 at the top, so
 * up always means better whichever measure is shown. Months without a
 * record are gaps, not zeroes - the line does not bridge time away from
 * the game. Loads both physics on mount and opens on the one that has
 * records.
 */
import { ref, computed, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import { t } from '@/utils/i18n';

const props = defineProps({
    mddId: { type: [Number, String], required: true },
});

const series = ref({ vq3: null, cpm: null });
const physics = ref('vq3');
const metric = ref('score');
const loading = ref(true);
const error = ref(null);
const hovered = ref(null);
const containerRef = ref(null);
const width = ref(800);

const colors = {
    vq3: '#3b82f6',
    cpm: '#a855f7',
};

const metrics = {
    score: { avg: 'avg_score', best: 'best_score', inverted: false },
    rank: { avg: 'avg_rank', best: 'best_rank', inverted: true },
};

const height = 230;
const pad = { top: 16, right: 24, bottom: 28, left: 44 };

const allMonths = computed(() => series.value[physics.value] || []);

// The years the player has records in, and the window shown. Twenty
// years of months is a wall, so the chart opens on the last three and
// the reader widens it from there.
const years = computed(() => [...new Set(allMonths.value.map((m) => parseInt(m.month.slice(0, 4), 10)))]);
const fromYear = ref(null);
const toYear = ref(null);

const resetWindow = () => {
    if (!years.value.length) { fromYear.value = null; toYear.value = null; return; }
    toYear.value = years.value[years.value.length - 1];
    fromYear.value = Math.max(years.value[0], toYear.value - 2);
};

const showAll = () => {
    fromYear.value = years.value[0];
    toYear.value = years.value[years.value.length - 1];
};

const isAll = computed(() => years.value.length > 0 && fromYear.value === years.value[0] && toYear.value === years.value[years.value.length - 1]);

const setFrom = (value) => {
    fromYear.value = parseInt(value, 10);
    if (toYear.value < fromYear.value) toYear.value = fromYear.value;
    hovered.value = null;
};
const setTo = (value) => {
    toYear.value = parseInt(value, 10);
    if (fromYear.value > toYear.value) fromYear.value = toYear.value;
    hovered.value = null;
};

const months = computed(() => {
    if (fromYear.value === null) return allMonths.value;
    return allMonths.value.filter((m) => {
        const year = parseInt(m.month.slice(0, 4), 10);
        return year >= fromYear.value && year <= toYear.value;
    });
});
const hasData = computed(() => months.value.some((m) => m.avg_score !== null));

// Past a point there is no room for a dot per month; the line carries
// the shape and the hovered month gets its marker back.
const showMarkers = computed(() => months.value.length <= plotWidth.value / 10);
const avgKey = computed(() => metrics[metric.value].avg);
const bestKey = computed(() => metrics[metric.value].best);
const inverted = computed(() => metrics[metric.value].inverted);

const monthNames = computed(() => [t('Jan'), t('Feb'), t('Mar'), t('Apr'), t('May'), t('Jun'), t('Jul'), t('Aug'), t('Sep'), t('Oct'), t('Nov'), t('Dec')]);
const monthLabel = (key, withYear = true) => {
    const [y, m] = key.split('-');
    const name = monthNames.value[parseInt(m, 10) - 1];
    return withYear ? `${name} ${y}` : name;
};

// Ticks land on round numbers, and the plot runs from the tick under the
// data to the tick over it, so a player whose months all sit between 500
// and 700 gets the room to see them move instead of a flat line over a
// big zero. Score steps by hundreds; rank picks a step that gives four
// or five ticks.
const domain = computed(() => {
    const values = months.value.flatMap((m) => (m[avgKey.value] === null ? [] : [m[avgKey.value], m[bestKey.value]]));
    if (!values.length) return { min: 0, max: 1000, step: 250 };
    const lo = Math.min(...values);
    const hi = Math.max(...values);

    if (metric.value === 'score') {
        const min = Math.max(0, Math.floor(lo / 100) * 100 - 100);
        const step = hi - min <= 400 ? 100 : 200;
        // A score never passes 1000, so the plot stops there rather than
        // at a tick above a value nobody can reach.
        return { min, max: Math.min(1000, min + Math.max(1, Math.ceil((hi - min) / step)) * step), step };
    }

    const step = [1, 2, 5, 10, 20, 50, 100, 200, 500].find((s) => (hi - 1) / s <= 5) || 1000;
    return { min: 1, max: 1 + Math.max(1, Math.ceil((hi - 1) / step)) * step, step };
});

const plotWidth = computed(() => Math.max(0, width.value - pad.left - pad.right));
const plotHeight = height - pad.top - pad.bottom;

const x = (i) => pad.left + (months.value.length > 1 ? (i / (months.value.length - 1)) * plotWidth.value : plotWidth.value / 2);
const y = (v) => {
    const ratio = (v - domain.value.min) / (domain.value.max - domain.value.min);
    return pad.top + (inverted.value ? ratio : 1 - ratio) * plotHeight;
};

const points = computed(() => months.value.map((m, i) => ({
    ...m,
    i,
    x: x(i),
    value: m[avgKey.value],
    best: m[bestKey.value],
    y: m[avgKey.value] === null ? null : y(m[avgKey.value]),
})));

// One path per run of consecutive months with data, so a gap breaks the
// line instead of being drawn across.
const segments = computed(() => {
    const runs = [];
    let run = [];
    for (const p of points.value) {
        if (p.y === null) {
            if (run.length) runs.push(run);
            run = [];
        } else {
            run.push(p);
        }
    }
    if (run.length) runs.push(run);
    return runs;
});

const linePath = (run) => run.map((p, i) => `${i ? 'L' : 'M'}${p.x.toFixed(1)},${p.y.toFixed(1)}`).join(' ');
// The wash hangs from the line to the "worse" edge of the plot: the
// bottom for score, the bottom for rank too since rank 1 sits at the top.
const areaPath = (run) => {
    const base = (pad.top + plotHeight).toFixed(1);
    return `${linePath(run)} L${run[run.length - 1].x.toFixed(1)},${base} L${run[0].x.toFixed(1)},${base} Z`;
};

const gridValues = computed(() => {
    const { min, max, step } = domain.value;
    const out = [];
    for (let v = min; v <= max; v += step) out.push(v);
    return out;
});

// A label roughly every 90px, always on the first month, with the year
// whenever it changes.
const xLabels = computed(() => {
    const n = months.value.length;
    if (!n) return [];
    const every = Math.max(1, Math.ceil(n / Math.max(1, Math.floor(plotWidth.value / 90))));
    const out = [];
    let lastYear = null;
    points.value.forEach((p, i) => {
        if (i % every !== 0 && i !== n - 1) return;
        const year = p.month.slice(0, 4);
        out.push({ x: p.x, text: monthLabel(p.month, year !== lastYear), key: p.month });
        lastYear = year;
    });
    return out;
});

const fmt = (v) => (metric.value === 'rank' ? (Number.isInteger(v) ? String(v) : v.toFixed(1)) : String(Math.round(v)));

const lastPoint = computed(() => [...points.value].reverse().find((p) => p.y !== null) || null);
const bestEver = computed(() => {
    const bests = months.value.map((m) => m[bestKey.value]).filter((v) => v !== null);
    if (!bests.length) return null;
    return inverted.value ? Math.min(...bests) : Math.max(...bests);
});

const hoveredPoint = computed(() => (hovered.value === null ? null : points.value[hovered.value]));

const onMove = (event) => {
    if (!points.value.length) return;
    // Pointer position in the drawing's own units: the svg is laid out at
    // the container's width, which is not always the width it was drawn at.
    const rect = event.currentTarget.getBoundingClientRect();
    const px = (event.clientX - rect.left) * (width.value / (rect.width || width.value));
    let nearest = 0;
    let dist = Infinity;
    points.value.forEach((p) => {
        const d = Math.abs(p.x - px);
        if (d < dist) { dist = d; nearest = p.i; }
    });
    hovered.value = nearest;
};

const tooltipStyle = computed(() => {
    if (!hoveredPoint.value) return {};
    const flip = hoveredPoint.value.x > width.value * 0.7;
    return {
        left: `${hoveredPoint.value.x + (flip ? -12 : 12)}px`,
        top: `${pad.top}px`,
        transform: flip ? 'translateX(-100%)' : 'none',
    };
});

const load = async () => {
    loading.value = true;
    error.value = null;
    try {
        const [vq3, cpm] = await Promise.all(['vq3', 'cpm'].map((p) =>
            axios.get(`/api/profile/${props.mddId}/progression`, { params: { physics: p } }).then((r) => r.data.months || [])
        ));
        series.value = { vq3, cpm };
        if (!vq3.length && cpm.length) physics.value = 'cpm';
        resetWindow();
    } catch (e) {
        error.value = e.response?.data?.error || t('Failed to load progression');
    } finally {
        loading.value = false;
    }
};

let observer = null;
onMounted(() => {
    load();
    if (containerRef.value) {
        width.value = containerRef.value.clientWidth || 800;
        observer = new ResizeObserver((entries) => {
            for (const entry of entries) width.value = entry.contentRect.width || 800;
        });
        observer.observe(containerRef.value);
    }
});
onUnmounted(() => observer?.disconnect());
</script>

<template>
    <div class="bg-black/40 backdrop-blur-sm rounded-xl p-6 shadow-2xl border border-white/5">
        <div class="flex items-center justify-between gap-4 mb-4 flex-wrap">
            <div>
                <h3 class="text-lg font-semibold text-white">{{ $t('Progression') }}</h3>
                <p class="text-xs text-gray-500">{{ metric === 'score' ? $t('Average score of the records set each month') : $t('Average rank of the records set each month') }}</p>
            </div>
            <div class="flex items-center gap-3 text-sm">
                <template v-if="hasData && bestEver !== null">
                    <span class="text-gray-400">{{ metric === 'score' ? $t('Best score') : $t('Best rank') }} <span class="font-bold text-white tabular-nums">{{ fmt(bestEver) }}</span></span>
                    <span class="text-white/20">|</span>
                </template>
                <div class="flex rounded-lg overflow-hidden border border-white/10">
                    <button v-for="m in ['score', 'rank']" :key="m" type="button" @click="metric = m"
                        :class="['px-3 py-1 text-xs font-bold uppercase transition-colors', metric === m ? 'bg-white/20 text-white' : 'bg-white/5 text-gray-400 hover:text-white']">
                        {{ m === 'score' ? $t('Score') : $t('Rank') }}
                    </button>
                </div>
                <div class="flex rounded-lg overflow-hidden border border-white/10">
                    <button v-for="p in ['vq3', 'cpm']" :key="p" type="button" @click="physics = p; hovered = null; resetWindow()"
                        :class="['px-3 py-1 text-xs font-bold uppercase transition-colors', physics === p ? (p === 'vq3' ? 'bg-blue-600 text-white' : 'bg-purple-600 text-white') : 'bg-white/5 text-gray-400 hover:text-white']">
                        {{ p }}
                    </button>
                </div>
            </div>
        </div>

        <div ref="containerRef" class="relative" @mouseleave="hovered = null">
            <div v-if="loading" class="h-[230px] flex items-center justify-center text-sm text-gray-500">{{ $t('Loading') }}</div>
            <div v-else-if="error" class="h-[230px] flex items-center justify-center text-sm text-red-400">{{ error }}</div>
            <div v-else-if="!allMonths.length" class="h-[230px] flex items-center justify-center text-sm text-gray-600">{{ $t('No scored records yet') }}</div>

            <template v-else>
                <!-- Period: from one year to another, or everything -->
                <div class="flex items-center gap-2 mb-3 text-xs text-gray-400">
                    <span>{{ $t('From') }}</span>
                    <select :value="fromYear" @change="setFrom($event.target.value)" class="bg-white/5 border border-white/10 rounded px-2 py-0.5 text-xs text-white focus:outline-none focus:border-white/30" style="color-scheme: dark">
                        <option v-for="yr in years" :key="yr" :value="yr" class="bg-gray-900 text-white">{{ yr }}</option>
                    </select>
                    <span>{{ $t('to') }}</span>
                    <select :value="toYear" @change="setTo($event.target.value)" class="bg-white/5 border border-white/10 rounded px-2 py-0.5 text-xs text-white focus:outline-none focus:border-white/30" style="color-scheme: dark">
                        <option v-for="yr in years" :key="yr" :value="yr" class="bg-gray-900 text-white">{{ yr }}</option>
                    </select>
                    <button type="button" @click="showAll(); hovered = null"
                        :class="['px-2 py-0.5 rounded border text-xs font-bold transition-colors', isAll ? 'bg-white/20 border-white/20 text-white' : 'bg-white/5 border-white/10 text-gray-400 hover:text-white']">
                        {{ $t('All') }}
                    </button>
                    <span class="ml-auto text-gray-500">{{ $tc(':count month|:count months', months.length) }}</span>
                </div>

                <div v-if="!hasData" class="h-[230px] flex items-center justify-center text-sm text-gray-600">{{ $t('No records in this period') }}</div>
                <template v-else>
                <svg :width="width" :height="height" :viewBox="`0 0 ${width} ${height}`" class="block w-full" @mousemove="onMove">
                    <!-- Grid: hairlines, recessive, with the values they carry -->
                    <g v-for="v in gridValues" :key="v">
                        <line :x1="pad.left" :x2="width - pad.right" :y1="y(v)" :y2="y(v)" stroke="rgba(255,255,255,0.07)" stroke-width="1" />
                        <text :x="pad.left - 8" :y="y(v) + 4" text-anchor="end" font-size="10" font-family="sans-serif" class="fill-gray-500 tabular-nums">{{ v }}</text>
                    </g>

                    <!-- Month labels -->
                    <text v-for="l in xLabels" :key="l.key" :x="l.x" :y="height - 8" text-anchor="middle" font-size="10" font-family="sans-serif" class="fill-gray-500">{{ l.text }}</text>

                    <!-- Wash under the line, then the line, per run of months with data -->
                    <g v-for="(run, r) in segments" :key="r">
                        <path v-if="run.length > 1" :d="areaPath(run)" :fill="colors[physics]" fill-opacity="0.1" />
                        <path v-if="run.length > 1" :d="linePath(run)" fill="none" :stroke="colors[physics]" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" />
                    </g>

                    <!-- Crosshair on the hovered month -->
                    <line v-if="hoveredPoint" :x1="hoveredPoint.x" :x2="hoveredPoint.x" :y1="pad.top" :y2="pad.top + plotHeight" stroke="rgba(255,255,255,0.25)" stroke-width="1" />

                    <!-- Markers: series-filled, surface ring, bigger when hovered.
                         A lone month with no neighbour to draw a line to always
                         keeps its dot, or it would vanish. -->
                    <circle v-for="p in points.filter((p) => p.y !== null && (showMarkers || hovered === p.i || ((p.i === 0 || points[p.i - 1].y === null) && (p.i === points.length - 1 || points[p.i + 1].y === null))))" :key="p.month"
                        :cx="p.x" :cy="p.y" :r="hovered === p.i ? 6 : 4"
                        :fill="colors[physics]" stroke="#0d1119" stroke-width="2" />

                    <!-- The one direct label: where the player stands now -->
                    <text v-if="lastPoint" :x="lastPoint.x" :y="lastPoint.y - 12" text-anchor="middle" font-size="11" font-weight="700" font-family="sans-serif" class="fill-white tabular-nums">{{ fmt(lastPoint.value) }}</text>
                </svg>

                <div v-if="hoveredPoint" class="absolute z-10 pointer-events-none bg-gray-900 border border-white/15 rounded-lg shadow-xl px-3 py-2 text-xs whitespace-nowrap" :style="tooltipStyle">
                    <div class="font-bold text-white mb-1">{{ monthLabel(hoveredPoint.month) }}</div>
                    <template v-if="hoveredPoint.value !== null">
                        <div class="flex items-center gap-2">
                            <span class="inline-block w-3 h-0.5 rounded" :style="{ background: colors[physics] }"></span>
                            <span class="font-bold text-white tabular-nums">{{ fmt(hoveredPoint.value) }}</span>
                            <span class="text-gray-400">{{ metric === 'score' ? $t('Average score') : $t('Average rank') }}</span>
                        </div>
                        <div class="flex items-center gap-2 mt-0.5">
                            <span class="inline-block w-3"></span>
                            <span class="font-bold text-white tabular-nums">{{ fmt(hoveredPoint.best) }}</span>
                            <span class="text-gray-400">{{ metric === 'score' ? $t('Best score') : $t('Best rank') }}</span>
                        </div>
                        <div class="flex items-center gap-2 mt-0.5">
                            <span class="inline-block w-3"></span>
                            <span class="font-bold text-white tabular-nums">{{ hoveredPoint.records }}</span>
                            <span class="text-gray-400">{{ $tc(':count record|:count records', hoveredPoint.records).replace(/^\d+\s*/, '') }}</span>
                        </div>
                    </template>
                    <div v-else class="text-gray-500">{{ $t('No records this month') }}</div>
                </div>
                </template>
            </template>
        </div>
    </div>
</template>
