<script setup>
    import { Link } from '@inertiajs/vue3';

    // Who actually paid for the prize.
    //
    // A prize figure on its own reads as if the site produced it. It did not -
    // somebody handed money over, for a stretch of weeks they agreed to, and
    // the least this page can do is say who and for how long. It also answers
    // the question the number raises by itself: a week paying 30 instead of 10
    // looks arbitrary until you can see who is funding it and until when.
    //
    // Rendered twice on the page, in two shapes: as a floating banner in the
    // right margin where there is room for one, and inline under the prize
    // otherwise. Hence a component rather than the same twenty lines written
    // out twice and drifting apart.
    defineProps({
        funders: { type: Object, required: true },
        // `rail` stacks the entries one per line for a narrow column; the
        // inline version flows them across the width it has.
        rail: { type: Boolean, default: false },
    });
</script>

<template>
    <div>
        <div class="flex items-baseline justify-between gap-3 mb-2.5">
            <div class="text-[11px] font-black uppercase tracking-widest text-emerald-400/90">
                {{ $t('Paid for by') }}
            </div>
            <div v-if="funders.funded_through && !rail" class="text-xs text-emerald-100/50">
                {{ $t('funded through weekly :number', { number: funders.funded_through }) }}
            </div>
        </div>

        <!-- A donor with something to say grows into a card; one without stays
             a chip. Giving every entry the tall shape would make the ones with
             nothing written look like they forgot to say something. -->
        <ul class="flex gap-2" :class="rail ? 'flex-col' : 'flex-wrap'">
            <li v-for="d in funders.donors" :key="d.id"
                class="rounded-lg border border-white/10 bg-white/[0.03] px-3 py-1.5"
                :class="rail ? 'block' : (d.note ? 'block max-w-sm' : 'inline-flex items-baseline gap-2')">
                <div :class="rail ? 'flex flex-col' : (d.note ? 'flex items-baseline gap-2 flex-wrap' : 'contents')">
                    <component :is="d.user_id ? Link : 'span'"
                               :href="d.user_id ? `/profile/${d.user_id}` : undefined"
                               class="text-sm font-bold text-gray-100"
                               :class="[d.user_id ? 'hover:underline' : '', rail ? 'truncate' : '']">{{ d.name }}</component>
                    <span class="text-sm font-black text-emerald-300 tabular-nums">{{ d.amount }} EUR</span>
                    <!-- The span, not just the count: "over 10 weeks" and
                         "weeklies 6 to 15" answer different questions and
                         people ask the second one. -->
                    <span class="text-xs text-gray-400">
                        {{ $t('over :count weeklies (:from-:to)', { count: d.weeks, from: d.from_comp, to: d.to_comp }) }}
                    </span>
                </div>
                <!-- A note with a link in it becomes the link. Money that came
                     from somewhere else - a contest prize handed back, say -
                     is worth being able to click through to, and there is no
                     column for a URL: it is written into the sentence and the
                     server pulls it back out. -->
                <p v-if="d.note" class="mt-1 text-xs text-gray-400 italic leading-snug">
                    <a v-if="d.note_url" :href="d.note_url"
                       class="underline decoration-white/20 hover:text-gray-200">{{ d.note }}</a>
                    <template v-else>{{ d.note }}</template>
                </p>
            </li>
        </ul>

        <div v-if="funders.funded_through && rail" class="mt-2 text-xs text-emerald-100/50">
            {{ $t('funded through weekly :number', { number: funders.funded_through }) }}
        </div>
    </div>
</template>
