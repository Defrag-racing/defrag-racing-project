<script setup>
    import moment from 'moment';
    import { computed, ref } from 'vue';
    import { Link, usePage } from '@inertiajs/vue3';
    import CopyButton from '@/Components/Basic/CopyButton.vue';
    import AddToMaplistModal from '@/Components/Maplists/AddToMaplistModal.vue';
    import { t } from '@/utils/i18n';
    import { getWeaponName, getItemName, getFunctionName } from '@/utils/gameItems';
    const page = usePage();
    const showMaplistModal = ref(false);

    const props = defineProps({
        map: Object,
        transparent: {
            type: Boolean,
            default: true
        }
    });

    let weaponsList = props.map.weapons?.split(',') ?? [];

    if (weaponsList.length > 0) {
        if (weaponsList[0].length == 0) {
            weaponsList.splice(0, 1);
        }
    }

    let itemsList = props.map.items?.split(',') ?? [];

    if (itemsList.length > 0) {
        if (itemsList[0].length == 0) {
            itemsList.splice(0, 1);
        }
    }

    let functionsList = props.map.functions?.split(',') ?? [];

    if (functionsList.length > 0) {
        if (functionsList[0].length == 0) {
            functionsList.splice(0, 1);
        }
    }

    const difficultyLabels = computed(() => [
        { level: 1, label: t('Beginner'), color: 'bg-green-600' },
        { level: 2, label: t('Easy'), color: 'bg-lime-600' },
        { level: 3, label: t('Medium'), color: 'bg-yellow-600' },
        { level: 4, label: t('Hard'), color: 'bg-orange-600' },
        { level: 5, label: t('Extreme'), color: 'bg-red-600' },
    ]);

    const difficultyBadge = computed(() => {
        const avg = props.map.difficulty_ratings_avg_rating;
        if (!avg || !props.map.difficulty_ratings_count) return null;
        const level = Math.round(avg);
        return difficultyLabels.value[level - 1] || null;
    });

    // The pill itself stays dark, because every hue on a card is taken -
    // green by CPM and Easy, blue by VQ3, red by NSFW and Extreme - and the
    // physics shows in the light coming off it instead: blue for a VQ3
    // record, green for CPM, white when there are both and no single colour
    // would be honest. Fastcaps records are physics like any other here, the
    // ctf mode does not change the colour.
    const playedBadge = computed(() => {
        const badges = {
            vq3: 'played-badge played-badge-vq3',
            cpm: 'played-badge played-badge-cpm',
        };

        return badges[props.map.played_physics] ?? 'played-badge';
    });

    const playedDots = computed(() => {
        const dots = {
            vq3: ['bg-sky-400'],
            cpm: ['bg-green-400'],
            both: ['bg-sky-400', 'bg-green-400'],
        };

        return dots[props.map.played_physics] ?? [];
    });

    const background = computed(() => {
        const physics = props.map.physics.toLowerCase()
        const bgs = {
            cpm: 'cpm-badge',
            vq3: 'vq3-badge',
            all: 'bg-blackop-80'
        }

        if (physics in bgs) {
            return bgs[physics]
        }

        return bgs['all']
    });

    const date = computed(() => {
        const time = props.map.created_at.split('T')[1].split('.')[0];

        const result = props.map.date_added.split(' ')[0] + ' ' + time;

        return moment(result).format('DD.MM.YY');
    });

    const getGametype = computed(() => {
        let gametype = props.map?.gametype;

        if (!gametype) {
            return 'run'
        }

        return (gametype == 'fastcaps') ? 'ctf2' : 'run';
    });

    const getRouteData = computed(() => {
        let data ={
            mapname: props.map?.name
        }

        if (getGametype.value !== 'run') {
            data.gametype = 'ctf2';
        }

        return data;
    });
</script>

<template>
    <Link :href="getGametype === 'run' ? `/maps/${encodeURIComponent(map.name)}` : `/maps/${encodeURIComponent(map.name)}?gametype=ctf2`" class="block group">
        <div class="bg-black/40 backdrop-blur-sm border border-white/5 rounded-xl overflow-hidden hover:border-white/20 transition-all shadow-2xl hover:shadow-blue-500/20">
            <!-- Map Thumbnail -->
            <div class="relative w-full aspect-video overflow-hidden">
                <div :class="['absolute inset-0 bg-cover bg-center', map.is_nsfw && !page.props.auth.user?.nsfw_confirmed ? 'blur-xl scale-110' : '']" :style="`background-image: url('/storage/${map.thumbnail}')`"></div>
                <!-- NSFW overlay -->
                <div v-if="map.is_nsfw && !page.props.auth.user?.nsfw_confirmed" class="absolute inset-0 flex items-center justify-center bg-black/40">
                    <span class="px-3 py-1 bg-red-600/80 rounded-lg text-xs font-black text-white border border-red-500/50">NSFW</span>
                </div>
                <!-- Difficulty Badge (top left) -->
                <div v-if="difficultyBadge" class="absolute top-2 left-2">
                    <div :class="`px-2 py-0.5 rounded text-[11px] font-bold uppercase text-white ${difficultyBadge.color}`">
                        {{ difficultyBadge.label }}
                    </div>
                </div>
                <!-- Played Badge (top centre) - only ever shown to a player
                     whose account is paired to an MDD id, since it is their
                     own records it is built from -->
                <div v-if="map.played" class="absolute top-2 left-1/2 -translate-x-1/2">
                    <div :class="`${playedBadge} flex items-center gap-1 px-2.5 py-1 rounded border border-white/25 backdrop-blur-sm text-[12px] font-black uppercase tracking-wide text-white`">
                        <span>&check;</span>
                        {{ $t('Played') }}
                        <span v-for="dot in playedDots" :key="dot" :class="['w-2 h-2 rounded-full ring-1 ring-black/30', dot]"></span>
                    </div>
                </div>
                <!-- Physics Badge (top right) -->
                <div class="absolute top-2 right-2 flex flex-col gap-1 items-end">
                    <div v-if="map.is_nsfw" class="px-2 py-0.5 rounded text-[11px] font-bold uppercase text-white bg-red-600/80">NSFW</div>
                    <div :class="`px-2 py-0.5 rounded text-[11px] font-bold uppercase text-white ${background}`">
                        {{ map.physics }}
                    </div>
                </div>

                <!-- Items Overlay - Bottom Right. Pages that need a badge of
                     their own on the card (a queue number, say) drop it in
                     the slot so it stacks with the item rows instead of
                     sitting on top of them or of the physics badge. -->
                <div class="absolute bottom-2 right-2 flex flex-col gap-0.5 items-end">
                    <div v-if="weaponsList.length > 0" class="flex flex-wrap justify-end gap-0.5 bg-black/70 rounded px-1 py-0.5">
                        <div v-for="weapon in weaponsList" :key="weapon" :title="getWeaponName(weapon)" :class="`sprite-items sprite-${weapon} w-3 h-3`"></div>
                    </div>
                    <div v-if="itemsList.length > 0" class="flex flex-wrap justify-end gap-0.5 bg-black/70 rounded px-1 py-0.5">
                        <div v-for="item in itemsList" :key="item" :title="getItemName(item)" :class="`sprite-items sprite-${item} w-3 h-3`"></div>
                    </div>
                    <div v-if="functionsList.length > 0" class="flex flex-wrap justify-end gap-0.5 bg-black/70 rounded px-1 py-0.5">
                        <div v-for="func in functionsList" :key="func" :title="getFunctionName(func)" :class="`sprite-items sprite-${func} w-3 h-3`"></div>
                    </div>
                    <slot name="bottom-right" />
                </div>
            </div>

            <!-- Map Info -->
            <div class="p-2.5">
                <div class="flex items-center justify-between gap-2 mb-1.5">
                    <h3 class="text-sm font-bold text-blue-400 group-hover:text-blue-300 transition-colors truncate">
                        {{ map.name }}
                    </h3>

                    <div class="flex items-center gap-1 flex-shrink-0">
                        <!-- Copy Button -->
                        <CopyButton :text="map.name" size="xs" />

                        <!-- Save to Maplist Button (only if logged in) -->
                        <button
                            v-if="page.props.auth.user"
                            @click.prevent.stop="showMaplistModal = true"
                            class="save-maplist-btn"
                            :title="$t('Save to Maplist')"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3 h-3">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17.593 3.322c1.1.128 1.907 1.077 1.907 2.185V21L12 17.25 4.5 21V5.507c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0 1 11.186 0Z" />
                            </svg>
                            <span class="text-[9px] font-semibold">{{ $t('Save') }}</span>
                        </button>

                        <!-- Download Button. Hidden for maps that ship with the
                             game, which have no pk3 of their own to fetch. -->
                        <a
                            v-if="map?.pk3"
                            @click.stop
                            target="_blank"
                            :href="'https://dl.defrag.racing/downloads/maps/' + encodeURIComponent(map?.pk3?.split('/').pop() ?? '')"
                            class="p-0.5 text-gray-400 hover:text-blue-400 rounded transition-colors"
                            :title="$t('Download')"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3.5 h-3.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                            </svg>
                        </a>
                    </div>
                </div>

                <div class="flex items-center justify-between text-xs text-gray-400">
                    <Link @click.stop :href="route('maps.filters', {author: map?.author ?? 'unknown'})" class="truncate hover:text-blue-400 transition-colors">
                        {{ map.author }}
                    </Link>
                    <span class="text-[10px] whitespace-nowrap ml-2">{{ date }}</span>
                </div>
            </div>
        </div>
    </Link>

    <!-- Add to Maplist Modal -->
    <AddToMaplistModal
        :show="showMaplistModal"
        :map-id="map.id"
        @close="showMaplistModal = false"
    />
</template>

<style scoped>
.save-maplist-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.2rem;
    padding: 0.1rem 0.3rem;
    border-radius: 0.25rem;
    color: #d1d5db;
    background: rgba(255, 255, 255, 0.08);
    border: 1px solid rgba(255, 255, 255, 0.2);
    flex-shrink: 0;
    transition: all 0.2s ease;
    cursor: pointer;
    white-space: nowrap;
}
.save-maplist-btn:hover {
    color: #c084fc;
    background: rgba(255, 255, 255, 0.15);
    border-color: rgba(192, 132, 252, 0.5);
    transform: scale(1.05);
}
.save-maplist-btn:active {
    transform: scale(0.95);
}
</style>