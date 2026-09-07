<script setup>
    // What became of a prize, as plain coloured words: "5 EUR · Paid out",
    // or for a split every part in turn. A badge shouted over whatever
    // table it sat under; the same words without a box read as a footnote.
    defineProps({
        payout: { type: Object, required: true },
    });

    const TEXT = {
        paid: 'text-emerald-300',
        donated_site: 'text-sky-300',
        donated_comps: 'text-violet-300',
        split: 'text-teal-200',
        pending: 'text-amber-300',
    };

    const LABEL = {
        paid: 'Paid out',
        donated_site: 'Donated to the website',
        donated_comps: 'Donated to the next comps',
        pending: 'Payout pending',
    };
</script>

<template>
    <span class="font-bold" :class="TEXT[payout.status] ?? TEXT.pending"
          :title="payout.resolved_at ? new Date(payout.resolved_at).toLocaleDateString() : ''">
        <template v-if="payout.status === 'split'">
            <template v-for="(eur, status, i) in payout.parts" :key="status"><span v-if="i" class="text-gray-600"> · </span>{{ eur }} EUR {{ $t(LABEL[status] ?? status).toLowerCase() }}</template>
        </template>
        <template v-else>{{ payout.amount }} EUR · {{ $t(LABEL[payout.status] ?? payout.label) }}</template>
    </span>
</template>
