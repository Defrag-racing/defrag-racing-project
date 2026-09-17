<script setup>
/**
 * The road to a record: the record itself, then every earlier time the
 * player set on this map, newest first. Each step shows how much it took
 * off the one before it. Sits under a record row on the profile and loads
 * on mount, so opening the drawer feels instant.
 */
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import { formatTime } from '@/utils/time';
import { t } from '@/utils/i18n';

const props = defineProps({
    mddId: { type: [Number, String], required: true },
    mapname: { type: String, required: true },
    gametype: { type: String, required: true },
    // The record the drawer hangs under: { time, date_set, rank }.
    current: { type: Object, required: true },
    // The profile's own date formatter, so the drawer matches the row above.
    formatDate: { type: Function, required: true },
});

const loading = ref(true);
const error = ref(null);
const history = ref([]);

const load = async () => {
    loading.value = true;
    error.value = null;
    try {
        const { data } = await axios.get(`/api/profile/${props.mddId}/record-history`, {
            params: { mapname: props.mapname, gametype: props.gametype },
        });
        history.value = data.history || [];
    } catch (e) {
        error.value = e.response?.data?.error || t('Failed to load time history');
    } finally {
        loading.value = false;
    }
};

// The record first, then the beaten times newest to oldest - the same way
// the records list above reads. Each step carries the time it took off
// the older one under it.
const steps = computed(() => {
    const all = [{ ...props.current, isCurrent: true }, ...[...history.value].reverse()];

    return all.map((entry, i) => ({
        ...entry,
        gain: i < all.length - 1 ? all[i + 1].time - entry.time : null,
    }));
});

const timeOfDay = (dateStr) => new Date(dateStr).toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });

onMounted(load);
</script>

<template>
    <div class="relative -mx-4 px-4 py-2 bg-black/30 border-l-2 border-blue-500/30 border-b border-white/[0.04]">
        <div class="flex items-center gap-2 text-[10px] text-gray-400 mb-1.5">
            <svg class="w-3.5 h-3.5 text-blue-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
            </svg>
            <span class="font-bold text-white uppercase tracking-wider">{{ $t('Time History') }}</span>
            <span v-if="!loading && !error" class="text-gray-500">
                · {{ $tc(':count improvement|:count improvements', history.length) }}
            </span>
        </div>

        <div v-if="loading" class="h-6 flex items-center gap-1.5 text-[10px] text-gray-500">
            <svg class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            {{ $t('Loading') }}
        </div>

        <div v-else-if="error" class="h-6 flex items-center text-[10px] text-red-400">{{ error }}</div>

        <div v-else-if="!history.length" class="h-6 flex items-center text-[10px] text-gray-500">{{ $t('No earlier times on this map') }}</div>

        <ol v-else class="space-y-0.5">
            <li
                v-for="(step, i) in steps"
                :key="step.isCurrent ? 'current' : step.id"
                :class="['flex items-center gap-2 sm:gap-3 text-xs font-mono tabular-nums', step.isCurrent ? 'text-white' : 'text-gray-400']">
                <span class="w-5 sm:w-8 flex-shrink-0 text-center text-[10px] text-gray-500">{{ i + 1 }}</span>
                <span class="flex-1 min-w-0 truncate">
                    {{ formatDate(step.date_set) }}
                    <span class="text-gray-500 ml-1">{{ timeOfDay(step.date_set) }}</span>
                </span>
                <span class="w-12 flex-shrink-0 text-right text-[10px] text-gray-500" :title="$t('Rank at the time')">
                    <template v-if="step.rank">#{{ step.rank }}</template>
                </span>
                <span :class="['w-14 sm:w-20 flex-shrink-0 text-right font-bold', step.isCurrent ? 'text-emerald-300' : '']">{{ formatTime(step.time) }}</span>
                <span class="w-14 sm:w-20 flex-shrink-0 text-right text-[10px]" :class="step.gain > 0 ? 'text-emerald-400' : 'text-gray-600'">
                    <template v-if="step.gain !== null && step.gain > 0">-{{ formatTime(step.gain) }}</template>
                </span>
            </li>
        </ol>
    </div>
</template>
