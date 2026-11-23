<script setup>
    import { Head, router, Link } from '@inertiajs/vue3';
    import Pagination from '@/Components/Basic/Pagination.vue';
    import { ref, computed, onMounted, getCurrentInstance } from 'vue';
    import { t } from '@/utils/i18n';
    import { useDeferredProps } from '@/utils/deferredProps';

    const props = defineProps({
        scores: Object,
        myScore: Object,
        tiers: Array,
        weights: Object,
    });

    const loaded = ref(props.scores !== null);
    const expandedRow = ref(null);

    const { proxy } = getCurrentInstance();
    const q3tohtml = proxy.q3tohtml;

    const getTier = (badgeScore) => {
        if (!props.tiers) return null;
        let tier = null;
        for (const t of props.tiers) {
            if (badgeScore >= t.min_score) {
                tier = t;
            }
        }
        return tier;
    };

    const myTier = computed(() => {
        if (!props.myScore) return null;
        return getTier(props.myScore.community_badge_score);
    });

    const toggleExpand = (id) => {
        expandedRow.value = expandedRow.value === id ? null : id;
    };

    const categories = computed(() => [
        { key: 'demos_uploaded', label: t('Demos Uploaded'), icon: '📁', desc: t('Demos uploaded and processed on the site') },
        { key: 'tags_added', label: t('Tags Added'), icon: '🏷', desc: t('Tags added to maps to help others find them') },
        { key: 'alias_reports', label: t('Alias Reports'), icon: '🔍', desc: t('Player alias reports that were resolved') },
        { key: 'demo_assignment_reports', label: t('Demo Assigns'), icon: '📋', desc: t('Demo assignment correction reports submitted') },
        { key: 'maplists_created', label: t('Maplists Created'), icon: '📝', desc: t('Public maplists created for the community') },
        { key: 'maplist_maps_added', label: t('Maplist Maps'), icon: '🗺', desc: t('Maps added to public maplists') },
        { key: 'maplist_likes_received', label: t('Maplist Likes'), icon: '👍', desc: t('Likes received on your maplists') },
        { key: 'maplist_favorites_received', label: t('Maplist Favorites'), icon: '⭐', desc: t('Favorites received on your maplists') },
        { key: 'play_later_maps', label: t('Play Later Maps'), icon: '⏰', desc: t('Maps saved to your Play Later list') },
        { key: 'marketplace_listings', label: t('Marketplace Listings'), icon: '🛒', desc: t('Listings created on the marketplace') },
        { key: 'marketplace_reviews_written', label: t('Reviews Written'), icon: '✍', desc: t('Reviews written for marketplace transactions') },
        { key: 'marketplace_reviews_received', label: t('Reviews Received'), icon: '💬', desc: t('Reviews received from marketplace transactions') },
        { key: 'headhunter_created', label: t('Challenges Created'), icon: '🎯', desc: t('Headhunter challenges created for others') },
        { key: 'headhunter_completed', label: t('Challenges Completed'), icon: '🏅', desc: t('Headhunter challenges completed and approved') },
        { key: 'record_flags', label: t('Record Flags'), icon: '🚩', desc: t('Suspicious records flagged and approved by admin') },
        { key: 'models_uploaded', label: t('Models Uploaded'), icon: '🎮', desc: t('Player models uploaded and approved') },
        { key: 'render_requests', label: t('Render Requests'), icon: '🎬', desc: t('YouTube video renders requested and completed') },
        { key: 'clan_created', label: t('Clans Created'), icon: '⚔', desc: t('Clans founded as owner') },
        { key: 'clan_membership', label: t('Clan Member'), icon: '🛡', desc: t('Being a member of a clan') },
        { key: 'nsfw_flags', label: t('NSFW Flags'), icon: '🔞', desc: t('Maps flagged as NSFW content') },
        { key: 'wiki_edits', label: t('Wiki Edits'), icon: '📖', desc: t('Wiki page revisions contributed') },
        { key: 'community_tasks_completed', label: t('Community Tasks'), icon: '🎰', desc: t('Tasks completed in Community Tasks (assigns, verifications, ratings, tags)') },
        { key: 'records_count', label: t('Records'), icon: '🏆', desc: t('Total records set in the game') },
        { key: 'maps_authored', label: t('Maps Authored'), icon: '🗺', desc: t('Maps you created as a mapper') },
        { key: 'models_authored', label: t('Models Authored'), icon: '🎨', desc: t('Player models you created as a modeler') },
        { key: 'social_connections', label: t('Social Connected'), icon: '🔗', desc: t('Social accounts connected via OAuth') },
        { key: 'profile_avatar', label: t('Custom Avatar'), icon: '🖼', desc: t('Uploaded a custom profile avatar') },
        { key: 'profile_background', label: t('Custom Background'), icon: '🌄', desc: t('Uploaded a custom profile background') },
        { key: 'profile_layout_customized', label: t('Custom Layout'), icon: '📐', desc: t('Customized your profile layout') },
        { key: 'name_effect_set', label: t('Name Effect'), icon: '✨', desc: t('Set a name effect on your profile') },
        { key: 'avatar_effect_set', label: t('Avatar Effect'), icon: '💫', desc: t('Set an avatar effect on your profile') },
        { key: 'difficulty_ratings', label: t('Difficulty Votes'), icon: '📊', desc: t('Map difficulty ratings submitted') },
        { key: 'donation_total_eur', label: t('Donations (EUR)'), icon: '💰', desc: t('Donated to support the site') },
    ]);

    const getTopCategories = (score) => {
        if (!score || !props.weights) return [];
        return categories.value
            .map(c => {
                let val = score[c.key];
                if (val === true) val = 1;
                if (val === false) val = 0;
                const w = props.weights[c.key] || 0;
                return { ...c, value: val, points: Math.round(val * w * 100) / 100 };
            })
            .filter(c => c.points > 0)
            .sort((a, b) => b.points - a.points)
            .slice(0, 12);
    };

    const getCategoryPoints = (score, cat) => {
        if (!score || !props.weights) return 0;
        let val = score[cat.key];
        if (val === true) val = 1;
        if (val === false) val = 0;
        return Math.round(val * (props.weights[cat.key] || 0) * 100) / 100;
    };

    const myScoreExpanded = ref(false);
    const myScoreHovered = ref(false);
    let myScoreHoverTimeout = null;
    const onMyScoreEnter = () => {
        clearTimeout(myScoreHoverTimeout);
        myScoreHoverTimeout = setTimeout(() => { myScoreHovered.value = true; }, 100);
    };
    const onMyScoreLeave = () => {
        clearTimeout(myScoreHoverTimeout);
        myScoreHovered.value = false;
    };
    const hoveredRow = ref(null);
    let hoverTimeout = null;
    const onRowEnter = (id) => {
        clearTimeout(hoverTimeout);
        hoverTimeout = setTimeout(() => { hoveredRow.value = id; }, 100);
    };
    const onRowLeave = () => {
        clearTimeout(hoverTimeout);
        hoveredRow.value = null;
    };

    const loadData = (page = 1) => {
        loaded.value = false;
        router.reload({
            only: ['scores'],
            data: { page },
            onFinish: () => {
                loaded.value = true;
            }
        });
    };

    // The list arrives in a second request, and comes again whenever the
    // page is re-rendered without it - a language switch, say.
    useDeferredProps(() => !props.scores, (done) => {
        const start = Date.now();
        router.reload({
            only: ['scores'],
            onFinish: () => {
                done();
                const remaining = 350 - (Date.now() - start);
                if (remaining > 0) {
                    setTimeout(() => { loaded.value = true; }, remaining);
                } else {
                    loaded.value = true;
                }
            }
        });
    });
</script>

<template>
    <Head :title="$t('Defragger Leaderboard')" />

    <!-- Header Section with gradient fade -->
    <div class="relative bg-gradient-to-b from-black/25 via-black/10 to-transparent pt-6 pb-96 pointer-events-none">
        <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8 pointer-events-auto">
            <div class="mb-8">
                <div class="flex items-center justify-between gap-4 mb-2">
                    <h1 class="text-2xl md:text-3xl font-black text-gray-300/90">{{ $t('Defragger Leaderboard') }}</h1>
                    <div class="flex-shrink-0 flex flex-wrap justify-end gap-2 bg-black/40 backdrop-blur-sm rounded-lg px-3 py-2">
                        <div v-for="tier in tiers" :key="tier.key"
                            class="flex items-center gap-1 px-2 py-1 rounded-lg border text-xs"
                            :style="{ borderColor: tier.color + '90', backgroundColor: tier.color + '35' }">
                            <img src="/images/svg/badge-defragger.png" class="w-3 h-3" :style="{ filter: `drop-shadow(0 0 2px ${tier.color})` }">
                            <span class="font-bold" :style="{ color: tier.color }">{{ tier.name }}</span>
                            <span class="text-[10px]" :style="{ color: tier.color + '99' }">{{ tier.min_score }}+</span>
                        </div>
                    </div>
                </div>
                <div class="text-sm bg-black/40 backdrop-blur-sm rounded-lg px-3 py-2 space-y-1.5">
                    <p class="text-gray-400">{{ $t('This is the') }} <span class="text-white font-semibold">{{ $t('Community') }}</span> {{ $t('Leaderboard - it rewards user-generated content and contributions that make defrag better for everyone. Uploading demos, tagging maps, writing reviews, creating maplists, and helping moderate all earn significant points here.') }}</p>
                    <p class="text-gray-500">{{ $t('Records are included with a minimal weight (0.1) because competitive performance already has its own dedicated') }} <a :href="route('ranking')" class="text-blue-400 hover:text-blue-300 underline">{{ $t('Rankings page') }}</a> {{ $t('with a specialized scoring formula. Here, community contributions matter most.') }}</p>
                    <p class="text-gray-500">{{ $t('Your') }} <span class="text-yellow-400 font-semibold">{{ $t('badge tier') }}</span> {{ $t('is calculated from community score') }} <span class="text-gray-400">{{ $t('without records') }}</span> {{ $t('- purely based on how much you contribute to the community.') }}</p>
                </div>
            </div>
        </div>
    </div>

    <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8 -mt-[27rem] relative z-10 pb-12">

        <!-- How it works - right aligned above table -->
        <div class="flex justify-end mb-2">
            <div class="group/how relative">
                <span class="text-xs text-gray-500 hover:text-gray-300 cursor-help transition border-b border-dashed border-gray-600">{{ $t('How it works') }}</span>
                <div class="absolute right-0 top-full mt-2 hidden group-hover/how:block bg-gray-900/95 border border-white/20 rounded-lg px-4 py-3 text-xs text-gray-200 shadow-xl z-50 pointer-events-none w-80">
                    <div class="font-bold text-white mb-2">{{ $t('How scoring works') }}</div>
                    <div class="text-gray-400 mb-2">{{ $t('Points are earned by contributing to the community. Each action has a weight:') }}</div>
                    <div class="text-gray-300 space-y-0.5">
                        <div><span class="text-yellow-400">{{ $t('5 pts:') }}</span> {{ $t('Headhunter challenges, models/maps authored') }}</div>
                        <div><span class="text-yellow-400">{{ $t('3 pts:') }}</span> {{ $t('Demo assigns, alias reports, maplists, marketplace listings/reviews, record flags, clan creation, wiki edits') }}</div>
                        <div><span class="text-yellow-400">{{ $t('2 pts:') }}</span> {{ $t('Demos uploaded, maplist maps, render requests, NSFW flags, avatar, background') }}</div>
                        <div><span class="text-yellow-400">{{ $t('1 pt:') }}</span> {{ $t('Tags, maplist likes/favs, play later, difficulty ratings, clan membership, socials, layout, effects') }}</div>
                        <div><span class="text-yellow-400">{{ $t('0.5 pt:') }}</span> {{ $t('Donations (per EUR)') }}</div>
                        <div><span class="text-yellow-400">{{ $t('0.1 pt:') }}</span> {{ $t('Community Tasks completed (50 tasks = 5 pts)') }}</div>
                    </div>
                    <div class="mt-2 pt-2 border-t border-white/10">
                        <div class="text-amber-400 font-semibold mb-1">{{ $t('Badge tier vs Total score') }}</div>
                        <div class="text-gray-400"><span class="text-white">{{ $t('Total score') }}</span> {{ $t('= everything above + records (0.1 pts each). Used for leaderboard ranking.') }}</div>
                        <div class="text-gray-400"><span class="text-white">{{ $t('Badge tier') }}</span> {{ $t('= total score') }} <span class="text-red-400">{{ $t('minus records') }}</span>{{ $t('. Only community contributions determine your badge color.') }}</div>
                        <div class="text-gray-500 mt-1">{{ $t('Scores recalculated every 30 min. Click a row for full breakdown.') }}</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Loading -->
        <!-- My Score Card -->
        <div v-if="myScore"
            @click="myScoreExpanded = !myScoreExpanded"
            @mouseenter="onMyScoreEnter"
            @mouseleave="onMyScoreLeave"
            class="mb-3 bg-black/40 backdrop-blur-sm border border-yellow-500/20 rounded-lg cursor-pointer hover:bg-yellow-500/5 transition-colors">
            <div class="flex items-center gap-4 px-4 py-2.5">
                <div class="text-xl font-bold" :style="myTier ? { color: myTier.color } : { color: '#9ca3af' }">#{{ myScore.rank }}</div>
                <div class="flex items-center gap-2">
                    <img :src="$page.props.auth?.user?.profile_photo_path ? '/storage/' + $page.props.auth.user.profile_photo_path : '/images/null.jpg'" class="w-6 h-6 rounded-full object-cover">
                    <span class="font-semibold text-yellow-300 text-sm">{{ $t('Your Score') }}</span>
                </div>
                <div class="flex gap-1.5 flex-wrap flex-1 hidden md:flex">
                    <span v-for="cat in getTopCategories(myScore)" :key="cat.key"
                        class="px-1.5 py-0.5 bg-gray-700/50 rounded text-xs text-gray-400">
                        {{ cat.icon }} {{ cat.value }}
                    </span>
                </div>
                <div class="flex items-center gap-2 ml-auto">
                    <span class="font-semibold" :style="myTier ? { color: myTier.color } : { color: 'white' }">{{ parseFloat(myScore.total_score).toFixed(1) }}</span>
                    <span v-if="myTier" class="inline-flex items-center gap-1 text-xs font-semibold" :style="{ color: myTier.color }">
                        <img src="/images/svg/badge-defragger.png" class="w-3 h-3" :style="{ filter: `drop-shadow(0 0 2px ${myTier.color})` }">
                        {{ myTier.name }}
                    </span>
                    <svg class="w-4 h-4 text-gray-600 transition-all duration-200" :class="myScoreExpanded ? 'rotate-180 !text-yellow-400' : 'animate-bounce'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                </div>
            </div>
            <!-- Hover peek -->
            <div v-if="myScoreHovered && !myScoreExpanded" class="overflow-hidden">
                <div class="peek-reveal">
                    <div class="peek-content">
                        <div class="bg-black/30 px-4 pt-3 pb-4">
                            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-2">
                                <div v-for="cat in categories" :key="cat.key"
                                    class="flex items-center gap-2 px-2 py-1.5 rounded"
                                    :class="getCategoryPoints(myScore, cat) > 0 ? 'bg-gray-700/50 text-gray-200' : 'text-gray-600'">
                                    <span class="text-sm">{{ cat.icon }}</span>
                                    <div class="flex-1 min-w-0">
                                        <div class="text-xs truncate">{{ cat.label }}</div>
                                        <div class="text-xs font-mono">
                                            <span>{{ myScore[cat.key] === true ? $t('Yes') : myScore[cat.key] === false ? $t('No') : myScore[cat.key] }}</span>
                                            <span v-if="getCategoryPoints(myScore, cat) > 0" class="text-green-400 ml-1">(+{{ getCategoryPoints(myScore, cat) }})</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="peek-fade">
                        <div class="absolute inset-0 flex items-center justify-around pointer-events-none px-8">
                            <div v-for="i in 9" :key="i" class="peek-chevron" :style="{ animationDelay: (i * 0.08) + 's' }">
                                <svg class="w-3.5 h-3.5 text-yellow-400/60" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="myScoreExpanded" class="border-t border-yellow-500/20 px-4 py-4 bg-black/30">
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-2">
                    <div v-for="cat in categories" :key="cat.key"
                        class="group/cat relative flex items-center gap-2 px-2 py-1.5 rounded"
                        :class="getCategoryPoints(myScore, cat) > 0 ? 'bg-gray-700/50 text-gray-200' : 'text-gray-600'">
                        <span class="text-sm">{{ cat.icon }}</span>
                        <div class="flex-1 min-w-0">
                            <div class="text-xs truncate">{{ cat.label }}</div>
                            <div class="text-xs font-mono">
                                <span>{{ myScore[cat.key] === true ? $t('Yes') : myScore[cat.key] === false ? $t('No') : myScore[cat.key] }}</span>
                                <span v-if="getCategoryPoints(myScore, cat) > 0" class="text-green-400 ml-1">(+{{ getCategoryPoints(myScore, cat) }})</span>
                            </div>
                        </div>
                        <div class="absolute left-1/2 -translate-x-1/2 bottom-full mb-1 hidden group-hover/cat:block bg-gray-900/95 border border-white/20 rounded-lg px-3 py-2 text-xs text-gray-200 whitespace-nowrap shadow-xl z-50 pointer-events-none">
                            <div class="font-bold text-white mb-0.5">{{ cat.label }}</div>
                            <div class="text-gray-400">{{ cat.desc }}</div>
                        </div>
                    </div>
                </div>
                <div class="mt-3 pt-3 border-t border-gray-700/50 flex items-center gap-4 text-sm">
                    <span class="text-gray-400">{{ $t('Total:') }} <span class="text-white font-bold">{{ parseFloat(myScore.total_score).toFixed(1) }}</span></span>
                    <span class="text-gray-400">{{ $t('Community:') }} <span class="text-white font-bold">{{ parseFloat(myScore.community_badge_score).toFixed(1) }}</span></span>
                    <span v-if="myTier" class="inline-flex items-center gap-1 font-semibold" :style="{ color: myTier.color }">
                        <img src="/images/svg/badge-defragger.png" class="w-3.5 h-3.5" :style="{ filter: `drop-shadow(0 0 2px ${myTier.color})` }">
                        {{ myTier.name }}
                    </span>
                </div>
            </div>
        </div>

        <!-- Leaderboard Table -->
        <!-- Loading skeleton -->
        <div v-if="!loaded" class="bg-black/40 backdrop-blur-sm rounded-lg border border-gray-700 overflow-hidden">
            <div class="border-b border-gray-700 px-4 py-3 flex gap-8">
                <div class="h-4 bg-white/10 rounded w-12 animate-pulse"></div>
                <div class="h-4 bg-white/10 rounded w-20 animate-pulse"></div>
                <div class="h-4 bg-white/10 rounded w-32 animate-pulse"></div>
                <div class="h-4 bg-white/10 rounded w-16 animate-pulse ml-auto"></div>
            </div>
            <div v-for="i in 15" :key="i" class="flex items-center gap-4 px-4 py-2 border-b border-gray-700/30">
                <div class="w-8 h-4 bg-white/10 rounded animate-pulse"></div>
                <div class="w-6 h-6 bg-white/10 rounded-full animate-pulse"></div>
                <div class="h-4 bg-white/10 rounded animate-pulse" :style="{ width: (80 + Math.random() * 80) + 'px' }"></div>
                <div class="flex gap-1.5 flex-1" style="padding-left: 100px;">
                    <div v-for="j in 4" :key="j" class="h-5 w-14 bg-white/5 rounded animate-pulse"></div>
                </div>
                <div class="h-4 bg-white/10 rounded w-12 animate-pulse ml-auto"></div>
            </div>
        </div>

        <div v-else-if="scores && scores.data && scores.data.length > 0" class="bg-black/40 backdrop-blur-sm rounded-lg border border-gray-700 overflow-hidden">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-gray-700 text-gray-400 text-sm">
                        <th class="text-left py-3 px-4" style="width: 60px;">{{ $t('Rank') }}</th>
                        <th class="text-left py-3 px-4" style="width: 200px;">{{ $t('Player') }}</th>
                        <th class="text-left py-3 pr-4 hidden md:table-cell" style="padding-left: 160px;">{{ $t('Top Contributions') }}</th>
                        <th class="text-right py-3 px-4" style="width: 90px;">{{ $t('Score') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <template v-for="score in scores.data" :key="score.id">
                        <tr @click="toggleExpand(score.id)"
                            @mouseenter="onRowEnter(score.id)"
                            @mouseleave="onRowLeave()"
                            class="border-b border-gray-700/50 hover:bg-blue-500/10 cursor-pointer transition-colors"
                            :class="expandedRow === score.id ? 'bg-blue-500/15 border-blue-500/30 !border-b-blue-500/30' : ''"
                            :style="getTier(score.community_badge_score) ? { borderLeft: `4px solid ${getTier(score.community_badge_score).color}` } : { borderLeft: '4px solid transparent' }">
                            <td class="py-1.5 px-4">
                                <span class="font-mono font-bold" :style="getTier(score.community_badge_score) ? { color: getTier(score.community_badge_score).color } : { color: '#9ca3af' }">{{ score.rank }}</span>
                            </td>
                            <td class="py-1.5 px-4">
                                <div class="flex items-center gap-2">
                                    <div :class="score.user?.avatar_effect ? 'avatar-effect-' + score.user.avatar_effect : ''" :style="score.user?.color ? `--effect-color: ${score.user.color}; --border-color: ${score.user.avatar_border_color || score.user.color}` : ''">
                                        <img :src="score.user?.profile_photo_path ? '/storage/' + score.user.profile_photo_path : '/images/null.jpg'" class="w-6 h-6 rounded-full object-cover" :style="score.user?.avatar_border_color ? `border: 2px solid ${score.user.avatar_border_color}` : ''">
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <img v-if="score.user?.country" :src="`/images/flags/${score.user.country}.png`" class="w-5 h-3.5" onerror="this.style.display='none'">
                                        <Link :href="`/profile/${score.user?.id}`" class="hover:text-blue-400 transition" :style="getTier(score.community_badge_score) ? { color: getTier(score.community_badge_score).color } : { color: 'white' }" @click.stop>
                                            <span :class="score.user?.name_effect ? 'name-effect-' + score.user.name_effect : ''" :style="score.user?.color ? `--effect-color: ${score.user.color}` : ''">
                                                <span v-html="q3tohtml(score.user?.name)"></span>
                                            </span>
                                        </Link>
                                    </div>
                                </div>
                            </td>
                            <td class="py-1.5 pr-4 hidden md:table-cell" style="padding-left: 160px;">
                                <div class="flex gap-1.5 flex-wrap">
                                    <span v-for="cat in getTopCategories(score)" :key="cat.key"
                                        class="group/chip relative px-1.5 py-0.5 bg-gray-700/50 rounded text-xs text-gray-400 hover:bg-blue-500/20 hover:text-blue-300 transition-colors cursor-help">
                                        {{ cat.icon }} {{ cat.value }}
                                        <div class="absolute left-1/2 -translate-x-1/2 bottom-full mb-1 hidden group-hover/chip:block bg-gray-900/95 border border-white/20 rounded-lg px-2.5 py-1.5 text-xs text-gray-200 whitespace-nowrap shadow-xl z-50 pointer-events-none">
                                            <div class="font-bold text-white">{{ cat.label }}</div>
                                            <div class="text-green-400 text-[10px]">{{ $t('+:points pts', { points: cat.points }) }}</div>
                                        </div>
                                    </span>
                                </div>
                            </td>
                            <td class="py-1.5 px-4 text-right relative">
                                <div class="flex items-center justify-end gap-2">
                                    <span class="font-semibold" :style="getTier(score.community_badge_score) ? { color: getTier(score.community_badge_score).color } : { color: 'white' }">{{ parseFloat(score.total_score).toFixed(1) }}</span>
                                    <svg class="w-4 h-4 text-gray-600 group-hover:text-blue-400 transition-all duration-200" :class="expandedRow === score.id ? 'rotate-180 !text-blue-400' : 'animate-bounce'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                                </div>
                            </td>
                        </tr>

                        <!-- Hover Peek Row -->
                        <tr v-if="hoveredRow === score.id && expandedRow !== score.id" class="pointer-events-none">
                            <td colspan="4" class="!p-0 border-0 overflow-hidden">
                                <div class="peek-reveal" style="position: relative; z-index: 10;">
                                    <div class="peek-content">
                                        <div class="bg-black/40 px-4 pt-3 pb-4">
                                            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-2">
                                                <div v-for="cat in categories" :key="cat.key"
                                                    class="flex items-center gap-2 px-2 py-1.5 rounded"
                                                    :class="getCategoryPoints(score, cat) > 0 ? 'bg-gray-700/50 text-gray-200' : 'text-gray-600'">
                                                    <span class="text-sm">{{ cat.icon }}</span>
                                                    <div class="flex-1 min-w-0">
                                                        <div class="text-xs truncate">{{ cat.label }}</div>
                                                        <div class="text-xs font-mono">
                                                            <span>{{ score[cat.key] === true ? $t('Yes') : score[cat.key] === false ? $t('No') : score[cat.key] }}</span>
                                                            <span v-if="getCategoryPoints(score, cat) > 0" class="text-green-400 ml-1">(+{{ getCategoryPoints(score, cat) }})</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="mt-3 pt-3 border-t border-gray-700/50 flex items-center gap-4 text-sm">
                                                <span class="text-gray-400">{{ $t('Total:') }} <span class="text-white font-bold">{{ parseFloat(score.total_score).toFixed(1) }}</span></span>
                                                <span class="text-gray-400">{{ $t('Community:') }} <span class="text-white font-bold">{{ parseFloat(score.community_badge_score).toFixed(1) }}</span></span>
                                                <span v-if="getTier(score.community_badge_score)" class="inline-flex items-center gap-1 font-semibold" :style="{ color: getTier(score.community_badge_score)?.color }">
                                                    <img src="/images/svg/badge-defragger.png" class="w-3.5 h-3.5" :style="{ filter: `drop-shadow(0 0 2px ${getTier(score.community_badge_score).color})` }">
                                                    {{ getTier(score.community_badge_score)?.name }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="peek-fade">
                                        <div class="absolute inset-0 flex items-center justify-around pointer-events-none px-8">
                                            <div v-for="i in 9" :key="i" class="peek-chevron" :style="{ animationDelay: (i * 0.08) + 's' }">
                                                <svg class="w-3.5 h-3.5 text-blue-400/60" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>

                        <!-- Expanded Row -->
                        <tr v-if="expandedRow === score.id">
                            <td colspan="4" class="bg-black/40 px-4 py-4 border-b border-white/10 border-t border-t-white/10">
                                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-2">
                                    <div v-for="cat in categories" :key="cat.key"
                                        class="group/cat relative flex items-center gap-2 px-2 py-1.5 rounded"
                                        :class="getCategoryPoints(score, cat) > 0 ? 'bg-gray-700/50 text-gray-200' : 'text-gray-600'">
                                        <span class="text-sm">{{ cat.icon }}</span>
                                        <div class="flex-1 min-w-0">
                                            <div class="text-xs truncate">{{ cat.label }}</div>
                                            <div class="text-xs font-mono">
                                                <span>{{ score[cat.key] === true ? $t('Yes') : score[cat.key] === false ? $t('No') : score[cat.key] }}</span>
                                                <span v-if="getCategoryPoints(score, cat) > 0" class="text-green-400 ml-1">(+{{ getCategoryPoints(score, cat) }})</span>
                                            </div>
                                        </div>
                                        <!-- Hover tooltip -->
                                        <div class="absolute left-1/2 -translate-x-1/2 bottom-full mb-1 hidden group-hover/cat:block bg-gray-900/95 border border-white/20 rounded-lg px-3 py-2 text-xs text-gray-200 whitespace-nowrap shadow-xl z-50 pointer-events-none">
                                            <div class="font-bold text-white mb-0.5">{{ cat.label }}</div>
                                            <div class="text-gray-400">{{ cat.desc }}</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-3 pt-3 border-t border-gray-700/50 flex items-center gap-4 text-sm">
                                    <span class="text-gray-400">{{ $t('Total:') }} <span class="text-white font-bold">{{ parseFloat(score.total_score).toFixed(1) }}</span></span>
                                    <span class="text-gray-400">{{ $t('Community:') }} <span class="text-white font-bold">{{ parseFloat(score.community_badge_score).toFixed(1) }}</span></span>
                                    <span v-if="getTier(score.community_badge_score)" class="inline-flex items-center gap-1 font-semibold" :style="{ color: getTier(score.community_badge_score)?.color }">
                                        <img src="/images/svg/badge-defragger.png" class="w-3.5 h-3.5" :style="{ filter: `drop-shadow(0 0 2px ${getTier(score.community_badge_score).color})` }">
                                        {{ getTier(score.community_badge_score)?.name }}
                                    </span>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>

            <!-- Pagination -->
            <div v-if="scores.last_page > 1" class="p-4 border-t border-gray-700">
                <Pagination :last_page="scores.last_page" :current_page="scores.current_page" :link="scores.first_page_url" :only="['scores']" />
            </div>
        </div>

        <!-- Empty State -->
        <div v-else-if="loaded" class="text-center py-20">
            <div class="text-gray-500 text-lg">{{ $t('No community scores calculated yet.') }}</div>
            <div class="text-gray-600 text-sm mt-2">{{ $t('Scores are calculated daily. Check back later.') }}</div>
        </div>
    </div>
</template>

<style scoped>
.peek-reveal {
    position: relative;
    max-height: 0;
    overflow: hidden;
    animation: peekOpen 0.4s cubic-bezier(0.22, 1, 0.36, 1) forwards;
}

.peek-fade {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 50%;
    background: linear-gradient(to bottom, transparent, rgb(17 24 39 / 0.95));
    pointer-events: none;
}

.peek-chevron {
    animation: chevronBounce 1s ease-in-out infinite;
}

@keyframes chevronBounce {
    0%, 100% { transform: translateY(-5px); opacity: 0.4; }
    50% { transform: translateY(-1px); opacity: 1; }
}

@keyframes peekOpen {
    from {
        max-height: 0;
        opacity: 0;
    }
    to {
        max-height: 16px;
        opacity: 1;
    }
}
</style>
