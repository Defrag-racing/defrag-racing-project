<script setup>
    import { computed, ref } from 'vue';
    import { Link } from '@inertiajs/vue3';
    import { t } from '@/utils/i18n';

    // One map on the ballot: a preview to watch, the count in both physics,
    // and the buttons that cast or move a vote.
    //
    // A map can be on one ballot and not the other - a map nobody can finish
    // in VQ3 is still a perfectly good CPM map - so every control here is per
    // physics rather than per card.
    const props = defineProps({
        candidate: { type: Object, required: true },
        myVotes: { type: Object, default: () => ({}) },
        wildcardsHeld: { type: Object, default: () => ({}) },
        mayVote: { type: Boolean, default: false },
        isOpen: { type: Boolean, default: false },
        totals: { type: Object, default: () => ({ cpm: 0, vq3: 0 }) },
    });

    const emit = defineEmits(['vote', 'wildcard', 'report']);

    const PHYSICS = ['cpm', 'vq3'];

    const showPreview = ref(false);
    const previewPhysics = ref('cpm');

    // CPM by default, and VQ3 only when there is no CPM video - most runs are
    // CPM and defaulting to the physics that usually has nothing would show an
    // empty box on the majority of maps.
    const preview = computed(() => props.candidate.preview ?? {});

    const previewFor = (physics) => preview.value?.[physics] ?? null;

    const hasVideo = (physics) => previewFor(physics)?.status === 'completed' && previewFor(physics)?.video_id;

    const rendering = computed(() => PHYSICS.some((p) => ['pending', 'processing'].includes(previewFor(p)?.status)));

    const anyVideo = computed(() => PHYSICS.some((p) => hasVideo(p)));

    const openPreview = () => {
        previewPhysics.value = hasVideo('cpm') ? 'cpm' : 'vq3';
        showPreview.value = true;
    };

    const blockedIn = (physics) => props.candidate.blocked_physics === physics;

    const votedIn = (physics) => props.myVotes?.[physics] === props.candidate.id;

    // Null while the ballot is open. The server leaves the counts out rather
    // than sending zeros, so this is the one place that decides whether there
    // is a tally to draw at all.
    const hasCounts = computed(() => props.candidate.votes != null);

    const share = (physics) => {
        const total = props.totals?.[physics] ?? 0;
        if (!total || !hasCounts.value) return 0;
        return Math.round((props.candidate.votes[physics] / total) * 100);
    };
</script>

<template>
    <div class="h-full rounded-xl border bg-black/40 backdrop-blur-sm overflow-hidden flex flex-col transition-colors"
         :class="mayVote && isOpen ? 'border-white/15 hover:border-white/25' : 'border-white/10'">
        <!-- Thumbnail, doubling as the preview trigger.
             One fixed 16:9 box, centre-cropped, and a stand-in picture when a
             map has no levelshot: five cards on a ballot are compared side by
             side, and a grey "no image" panel among four screenshots reads as a
             broken card rather than a map nobody has photographed. Levelshots
             come in whatever shape their author saved them in - 4:3 mostly,
             some square - so they are zoomed to fill and cropped from the
             centre rather than letterboxed: five pictures at five heights is
             what made the rows under them fail to line up.
             `flex-shrink-0` because this is a flex column and a tall card in
             the same grid row must not be able to squeeze the picture. -->
        <div class="relative aspect-video flex-shrink-0 bg-black/40">
            <img
                :src="candidate.thumbnail ? `/storage/${candidate.thumbnail}` : '/images/unknown.jpg'"
                onerror="this.src='/images/unknown.jpg'"
                :alt="candidate.map"
                class="w-full h-full object-cover object-center"
                loading="lazy"
            />

            <!-- Says why there is nothing to watch, rather than leaving the
                 play button missing and letting people wonder. It sits on the
                 picture because it is about the picture, and because a note
                 that only some cards carry costs those cards a row and knocks
                 every vote bar on the ballot out of line. -->
            <div v-if="!anyVideo"
                 class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/85 via-black/60 to-transparent px-2.5 pt-6 pb-1.5 text-[10px] font-medium text-gray-300">
                {{ rendering ? $t('Preview is rendering') : $t('No preview available') }}
            </div>

            <button
                v-if="anyVideo"
                type="button"
                @click="openPreview"
                class="absolute inset-0 flex items-center justify-center bg-black/40 hover:bg-black/20 transition-colors group"
                :title="$t('Watch a run of this map')"
            >
                <span class="w-14 h-14 rounded-full bg-red-600/90 group-hover:bg-red-500 flex items-center justify-center transition-colors">
                    <svg class="w-6 h-6 text-white ml-1" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z" /></svg>
                </span>
            </button>

            <!-- Name and author on the picture, top left, over a dark fade so
                 they read on any levelshot. They had a row of their own under
                 the picture and the ballot needs the height more than the row. -->
            <div class="absolute inset-x-0 top-0 bg-gradient-to-b from-black/85 via-black/50 to-transparent px-2.5 pt-2 pb-5 min-w-0 pointer-events-none">
                <Link
                    :href="route('maps.map', candidate.map)"
                    class="pointer-events-auto block font-bold text-white drop-shadow-[0_1px_3px_rgba(0,0,0,1)] hover:text-blue-300 transition-colors truncate"
                >
                    {{ candidate.map }}
                </Link>
                <div v-if="candidate.author" class="text-xs text-gray-300 drop-shadow-[0_1px_3px_rgba(0,0,0,1)] truncate">{{ candidate.author }}</div>
            </div>
        </div>

        <div class="p-3 flex-1 flex flex-col gap-3">
            <div class="space-y-2 mt-auto">
                <div v-for="physics in PHYSICS" :key="physics">
                    <!-- Not on this ballot, and it says which one and why. -->
                    <div
                        v-if="blockedIn(physics)"
                        class="flex items-center gap-2 rounded-lg bg-black/40 backdrop-blur-sm border border-white/5 px-2.5 py-1.5"
                    >
                        <span class="text-[10px] font-black uppercase tracking-wider text-gray-300 w-8">{{ physics }}</span>
                        <span class="text-[11px] text-gray-300 leading-tight">
                            {{ $t('Cannot be finished in this physics') }}
                        </span>
                    </div>

                    <div v-else class="flex items-center gap-2">
                        <!-- A row that can be voted on has to look like a
                             control before it is used, not only after. It used
                             to be a near-invisible border round a grey bar,
                             which reads as a label - so it carries a radio
                             mark, a border you can see and a hover that lights
                             up. Once voting is closed all of that comes off
                             and it goes back to being a result. -->
                        <button
                            type="button"
                            :disabled="!mayVote || !isOpen"
                            @click="emit('vote', { candidate: candidate.id, physics })"
                            :title="mayVote && isOpen ? $t('Vote for this map in :physics', { physics: physics.toUpperCase() }) : ''"
                            class="flex-1 flex items-center gap-2 rounded-lg border px-2.5 py-2 transition-all disabled:cursor-not-allowed"
                            :class="votedIn(physics)
                                ? 'bg-blue-600/30 border-blue-400/60 text-white shadow-[0_0_18px_-6px_rgba(96,165,250,0.6)]'
                                : (mayVote && isOpen
                                    ? 'cursor-pointer bg-white/[0.04] border-white/20 text-gray-200 hover:bg-blue-500/15 hover:border-blue-400/60'
                                    : 'bg-black/40 border-white/10 text-gray-300')"
                        >
                            <!-- The radio mark. Nothing else on the card says
                                 "one of these two, pick one". -->
                            <span class="flex-shrink-0 w-3.5 h-3.5 rounded-full border-2 flex items-center justify-center transition-colors"
                                  :class="votedIn(physics)
                                      ? 'border-blue-300'
                                      : (mayVote && isOpen ? 'border-white/40' : 'border-white/15')">
                                <span v-if="votedIn(physics)" class="w-1.5 h-1.5 rounded-full bg-blue-300"></span>
                            </span>

                            <span class="text-[10px] font-black uppercase tracking-wider w-8 text-left"
                                  :class="votedIn(physics) ? 'text-blue-200' : 'text-gray-300'">
                                {{ physics }}
                            </span>

                            <!-- While the ballot is open there is no tally to
                                 draw. The row keeps its shape so the card does
                                 not jump when the counts arrive, and your own
                                 vote is still marked - you have to be able to
                                 see where it sits to move it. -->
                            <template v-if="hasCounts">
                                <span class="flex-1 h-1.5 rounded-full bg-black/50 overflow-hidden">
                                    <span
                                        class="block h-full rounded-full transition-all duration-500"
                                        :class="votedIn(physics) ? 'bg-blue-400' : 'bg-gray-500'"
                                        :style="{ width: share(physics) + '%' }"
                                    ></span>
                                </span>

                                <span class="text-xs font-bold tabular-nums w-6 text-right"
                                      :class="votedIn(physics) ? 'text-white' : 'text-gray-300'">
                                    {{ candidate.votes[physics] }}
                                </span>
                            </template>

                            <template v-else>
                                <span class="flex-1 h-1.5 rounded-full bg-black/40"></span>

                                <span class="w-6 text-right text-xs font-bold"
                                      :class="votedIn(physics) ? 'text-blue-200' : 'text-gray-400'">
                                    {{ votedIn(physics) ? '&check;' : '&middot;' }}
                                </span>
                            </template>
                        </button>

                        <!-- Only rendered for somebody actually holding one. -->
                        <button
                            v-if="wildcardsHeld?.[physics] && isOpen"
                            type="button"
                            @click="emit('wildcard', { candidate: candidate.id, physics, map: candidate.map })"
                            class="flex-shrink-0 rounded-lg border border-amber-500/40 bg-amber-500/15 px-2 py-1.5 text-amber-300 hover:bg-amber-500/25 transition-colors"
                            :title="$t('Use your wildcard on this map')"
                        >
                            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l2.4 7.4H22l-6.2 4.5 2.4 7.4-6.2-4.6-6.2 4.6 2.4-7.4L2 9.4h7.6z" /></svg>
                        </button>
                    </div>
                </div>
            </div>

            <button
                v-if="mayVote && isOpen"
                type="button"
                @click="emit('report', { map_id: candidate.map_id, map: candidate.map })"
                class="text-[10px] text-gray-400 hover:text-gray-300 transition-colors text-left"
            >
                {{ $t('Report that this map cannot be finished') }}
            </button>
        </div>

        <!-- Preview -->
        <Teleport to="body">
            <div
                v-if="showPreview"
                class="fixed inset-0 z-[100] bg-black/85 backdrop-blur-sm flex items-center justify-center p-4"
                @click.self="showPreview = false"
            >
                <div class="w-full max-w-4xl">
                    <div class="flex items-center justify-between mb-2">
                        <div class="flex items-center gap-2">
                            <span class="font-bold text-white">{{ candidate.map }}</span>
                            <button
                                v-for="physics in PHYSICS"
                                :key="physics"
                                v-show="hasVideo(physics)"
                                type="button"
                                @click="previewPhysics = physics"
                                class="rounded px-2 py-0.5 text-[10px] font-black uppercase tracking-wider transition-colors"
                                :class="previewPhysics === physics ? 'bg-blue-600 text-white' : 'bg-white/10 text-gray-300 hover:text-white'"
                            >
                                {{ physics }}
                            </button>
                        </div>
                        <button type="button" @click="showPreview = false" class="text-gray-300 hover:text-white">
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>
                    <div class="aspect-video bg-black rounded-lg overflow-hidden">
                        <iframe
                            v-if="hasVideo(previewPhysics)"
                            :src="`https://www.youtube.com/embed/${previewFor(previewPhysics).video_id}`"
                            class="w-full h-full"
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                            allowfullscreen
                        ></iframe>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>
