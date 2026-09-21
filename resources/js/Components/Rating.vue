<script setup>
    import { Link, usePage } from '@inertiajs/vue3';
    import { computed, ref } from 'vue';

    const page = usePage();
    const fmtDate = (dateStr) => {
        const d = new Date(dateStr);
        const dd = String(d.getDate()).padStart(2, '0');
        const mm = String(d.getMonth() + 1).padStart(2, '0');
        const yy = String(d.getFullYear()).slice(-2);
        const yyyy = String(d.getFullYear());
        const fmt = page.props.dateFormat;
        if (fmt === 'dmY') return `${dd}/${mm}/${yyyy}`;
        if (fmt === 'Ymd') return `${yyyy}/${mm}/${dd}`;
        if (fmt === 'dmy') return `${dd}/${mm}/${yy}`;
        return `${yy}/${mm}/${dd}`;
    };

    const props = defineProps({
        rating: {
            type: Object,
            required: true
        },
        rank: {
            type: Number,
            default: () => ranking.rank
        }
    });

    const showDateTooltip = ref(false);
    const dateTooltipPos = ref({ x: 0, y: 0 });
    const onDateEnter = (e) => { showDateTooltip.value = true; dateTooltipPos.value = { x: e.clientX, y: e.clientY }; };
    const onDateMove = (e) => { dateTooltipPos.value = { x: e.clientX, y: e.clientY }; };
    const onDateLeave = () => { showDateTooltip.value = false; };

    // The linked account first, then the q3df profile: a player who never
    // linked an account here still has a country there, and the row used to
    // fall through to the placeholder flag for all of them.
    const bestratingCountry = computed(() => {
        const country = props.rating.user?.country
            || props.rating.mdd_profile?.country
            || props.rating.country;

        return (!country || country === 'XX') ? '_404' : country;
    });

    const getRoute = computed(() => {
        // Linked users with full profiles
        if (props.rating.user?.id) {
            return route('profile.index', props.rating.user.id);
        }

        // Fallback to mdd profile for unlinked accounts
        if (props.rating.mdd_id) {
            return route('profile.mdd', props.rating.mdd_id);
        }

        return null;
    });

</script>

<template>
    <component
        :is="getRoute ? Link : 'div'"
        :href="getRoute"
        :class="[
            'group relative flex items-center gap-3 py-2 px-4 -mx-4 -my-2 transition-all duration-300 border-b border-white/[0.02] last:border-0 first:rounded-t-[10px] last:rounded-b-[10px] hover:drop-shadow-[0_0_12px_rgba(255,255,255,0.08)]',
            !getRoute ? 'cursor-default opacity-60' : 'cursor-pointer'
        ]"
    >
        <!-- Background Profile Photo (always visible, blurred) -->
        <div class="absolute inset-0 transition-all duration-500 first:rounded-t-[10px] last:rounded-b-[10px] overflow-hidden">
            <img
                :src="rating.user?.profile_photo_path ? '/storage/' + rating.user?.profile_photo_path : '/images/null.jpg'"
                class="w-full h-full object-cover scale-110 group-hover:scale-105 opacity-0 group-hover:opacity-100 transition-all duration-500"
                :alt="rating.user?.name ?? rating.name"
            />
            <div class="absolute inset-0 bg-gradient-to-r from-black/98 via-black/95 to-black/98 group-hover:from-black/40 group-hover:via-black/30 group-hover:to-black/40 transition-all duration-500"></div>
        </div>

        <!-- Content (relative to background) -->
        <div class="relative flex items-center gap-2 sm:gap-3 w-full transition-all duration-300">
            <!-- Rank -->
            <div class="w-5 sm:w-8 flex-shrink-0 text-center flex items-center justify-center h-6">
                <span class="text-[10px] sm:text-xs font-bold tabular-nums drop-shadow-[0_2px_4px_rgba(0,0,0,0.8)] group-hover:drop-shadow-[0_2px_8px_rgba(0,0,0,1)] transition-all"
                    :class="rank === 1 ? 'text-yellow-400' : rank === 2 ? 'text-gray-300' : rank === 3 ? 'text-amber-600' : 'text-gray-400 group-hover:text-white'">{{ rank }}</span>
            </div>

            <!-- Profile Photo -->
            <div class="flex-shrink-0 overflow-visible">
                <div :class="'avatar-effect-' + (rating.user?.avatar_effect || 'none')" :style="`--effect-color: ${rating.user?.color || '#ffffff'}; --border-color: ${rating.user?.avatar_border_color || '#6b7280'}; --orbit-radius: 14px`">
                    <img
                        :src="rating.user?.profile_photo_path ? '/storage/' + rating.user?.profile_photo_path : '/images/null.jpg'"
                        class="h-5 w-5 sm:h-6 sm:w-6 rounded-full object-cover border-2 transition-all drop-shadow-[0_2px_6px_rgba(0,0,0,0.9)] relative"
                        :style="`border-color: ${rating.user?.avatar_border_color || '#6b7280'}`"
                        :alt="rating.user?.name ?? rating.name"
                    />
                </div>
            </div>

            <!-- Player Info -->
            <div class="flex items-center gap-1 sm:gap-1.5 flex-1 min-w-0">
                <img :src="`/images/flags/${bestratingCountry}.png`" class="w-3.5 h-2.5 sm:w-4 sm:h-3 flex-shrink-0 opacity-90 drop-shadow-[0_2px_4px_rgba(0,0,0,0.9)]" onerror="this.src='/images/flags/_404.png'" :title="bestratingCountry">
                <span :class="'name-effect-' + (rating.user?.name_effect || 'none')" :style="`--effect-color: ${rating.user?.color || '#ffffff'}`" class="text-[11px] sm:text-sm font-semibold text-gray-300 group-hover:text-white whitespace-nowrap overflow-visible group-hover:text-blue-200 transition-all" v-html="q3tohtml(rating.user?.name ?? rating.name)"></span>
            </div>

            <!-- Rating Score -->
            <div class="w-16 sm:w-20 flex-shrink-0 text-right">
                <div class="text-[10px] sm:text-sm font-bold tabular-nums text-white transition-all drop-shadow-[0_2px_4px_rgba(0,0,0,0.8)] group-hover:drop-shadow-[0_2px_8px_rgba(0,0,0,1)]">{{ rating.player_rating.toFixed(3) }}</div>
            </div>

            <!-- Physics Icon -->
            <!-- <div class="w-5 sm:w-6 flex-shrink-0 text-center">
                <img v-if="rating.physics.includes('cpm')" src="/images/modes/cpm-icon.svg" class="w-3.5 h-3.5 sm:w-4 sm:h-4 inline-block opacity-90 group-hover:opacity-100 transition-opacity drop-shadow-[0_3px_8px_rgba(0,0,0,1)] filter brightness-110" alt="CPM" />
                <img v-else src="/images/modes/vq3-icon.svg" class="w-3.5 h-3.5 sm:w-4 sm:h-4 inline-block opacity-90 group-hover:opacity-100 transition-opacity drop-shadow-[0_3px_8px_rgba(0,0,0,1)] filter brightness-110" alt="VQ3" />
            </div> -->

            <!-- Last Activity -->
            <div class="w-14 sm:w-20 flex-shrink-0 text-right"
                @mouseenter="onDateEnter"
                @mousemove="onDateMove"
                @mouseleave="onDateLeave"
            >
                <div class="text-[8px] sm:text-[10px] text-gray-300 group-hover:text-white font-mono transition-all drop-shadow-[0_2px_4px_rgba(0,0,0,0.8)] group-hover:drop-shadow-[0_2px_8px_rgba(0,0,0,1)]">
                    {{ fmtDate(rating.last_activity) }}
                </div>
            </div>
        </div>

        <!-- Last Activity Tooltip -->
        <Teleport to="body">
            <div
                v-if="showDateTooltip"
                class="fixed z-50 pointer-events-none"
                :style="{ left: dateTooltipPos.x + 15 + 'px', top: dateTooltipPos.y - 40 + 'px' }"
            >
                <div class="bg-gray-900 border border-white/10 text-gray-200 rounded-lg px-3 py-1.5 shadow-2xl text-xs font-semibold whitespace-nowrap">
                    <span class="text-gray-500">{{ $t('Last Active:') }}</span> {{ fmtDate(rating.last_activity) }}
                </div>
            </div>
        </Teleport>
    </component>
</template>
