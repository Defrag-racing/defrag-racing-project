<script setup>
    import { computed } from 'vue';
    // What became of a winner's prize, as one small label. The four endings
    // each get a colour of their own so the history table can be read at a
    // glance: green is money that left, blue and violet are money given back,
    // amber is money still owed.
    const props = defineProps({
        payout: { type: Object, default: null },
        amount: { type: [Number, String], default: null },
    });

    const STYLES = {
        paid: 'border-emerald-400/40 bg-emerald-500/15 text-emerald-200',
        donated_site: 'border-sky-400/40 bg-sky-500/15 text-sky-200',
        donated_comps: 'border-violet-400/40 bg-violet-500/15 text-violet-200',
        split: 'border-teal-400/40 bg-teal-500/15 text-teal-100',
        pending: 'border-amber-400/40 bg-amber-500/15 text-amber-200',
    };

    const LABELS = {
        paid: 'Paid out',
        donated_site: 'Donated to the website',
        donated_comps: 'Donated to the next comps',
        pending: 'Payout pending',
    };

    // A split prints every part with its amount, whether or not the caller
    // asked for the amount: "10 EUR paid out · 5 EUR donated to the website"
    // is the only honest way to say where 15 EUR went.
    const isSplit = computed(() => props.payout?.status === 'split');
    const parts = computed(() => Object.entries(props.payout?.parts ?? {}));
</script>

<template>
    <span v-if="payout"
          class="inline-flex flex-wrap items-center gap-x-1 rounded-lg border px-2 py-0.5 text-[11px] font-bold"
          :class="STYLES[payout.status] || STYLES.pending"
          :title="payout.resolved_at ? new Date(payout.resolved_at).toLocaleDateString() : ''">
        <template v-if="isSplit">
            <template v-for="([status, eur], i) in parts" :key="status">
                <span v-if="i" class="opacity-50">·</span>
                <span class="tabular-nums">{{ eur }} EUR</span>
                <span class="font-medium opacity-90">{{ $t(LABELS[status] || status).toLowerCase() }}</span>
            </template>
        </template>
        <template v-else>
            <span v-if="amount" class="tabular-nums">{{ amount }} EUR</span>
            <span v-if="amount">·</span>
            {{ $t(LABELS[payout.status] || payout.status) }}
        </template>
    </span>
</template>
