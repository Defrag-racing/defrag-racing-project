<script>
    import MainLayout from '@/Layouts/MainLayout.vue'
    import { Link } from '@inertiajs/vue3';

    export default {
        layout: MainLayout,
    }
</script>

<script setup>
    import { Head, usePage } from '@inertiajs/vue3';
    import { computed, ref } from 'vue';
    import DefragliveContestBanner from '@/Components/DefragliveContestBanner.vue';
    import CompsBanner from '@/Components/CompsBanner.vue';
    import { t } from '@/utils/i18n';

    const props = defineProps({
        maps: Array,
        servers: Array,
        latestAnnouncement: Object,
        recentAnnouncements: Array,
        totalMaps: Number,
        totalDemos: Number,
        activeServers: Number,
        activePlayers: Number,
        totalRecords: Number,
        recentWorldRecords: Array,
        rankingHighlights: Object,
        recentChangelog: Array,
        recentChangelogLauncher: Array,
        upcomingTournaments: Array,
        latestModel: Object,
        latestMap: Object,
    });

    const page = usePage();
    const isAuthenticated = computed(() => page.props.auth?.user);
    const isLinked = computed(() => !!page.props.auth?.user?.mdd_id);

    const hasRecords = computed(() => (page.props.recordsCount || 0) > 0);
    const hasColoredNick = computed(() => !!page.props.auth?.user?.color);

    const downloadChecked = ref(localStorage.getItem('gettingStarted_download') === '1');
    const discordChecked = ref(localStorage.getItem('gettingStarted_discord') === '1');

    const toggleDownload = () => {
        downloadChecked.value = !downloadChecked.value;
        localStorage.setItem('gettingStarted_download', downloadChecked.value ? '1' : '0');
    };
    const toggleDiscord = () => {
        discordChecked.value = !discordChecked.value;
        localStorage.setItem('gettingStarted_discord', discordChecked.value ? '1' : '0');
    };

    const launcherChecked = ref(localStorage.getItem('gettingStarted_launcher') === '1');
    const toggleLauncher = () => {
        launcherChecked.value = !launcherChecked.value;
        localStorage.setItem('gettingStarted_launcher', launcherChecked.value ? '1' : '0');
    };

    const rulesChecked = ref(localStorage.getItem('gettingStarted_rules') === '1');
    const toggleRules = () => {
        rulesChecked.value = !rulesChecked.value;
        localStorage.setItem('gettingStarted_rules', rulesChecked.value ? '1' : '0');
    };

    const formatTime = (ms) => {
        if (!ms) return '-';
        const totalSec = ms / 1000;
        const minutes = Math.floor(totalSec / 60);
        const seconds = Math.floor(totalSec % 60);
        const millis = ms % 1000;
        if (minutes > 0) return `${minutes}:${String(seconds).padStart(2, '0')}.${String(millis).padStart(3, '0')}`;
        return `${seconds}.${String(millis).padStart(3, '0')}`;
    };

    const hoveredCommit = ref(null);
    const commitTooltipPos = ref({ x: 0, y: 0 });
    const onCommitEnter = (e, commit) => { hoveredCommit.value = commit; commitTooltipPos.value = { x: e.clientX, y: e.clientY }; };
    const onCommitMove = (e) => { commitTooltipPos.value = { x: e.clientX, y: e.clientY }; };
    const onCommitLeave = () => { hoveredCommit.value = null; };

    // Recent Changes feed: Website (this repo) vs. Launcher (separate repo,
    // fetched from GitHub in the controller). Tab switches the feed + the
    // GitHub repo the commit links point at.
    // Two feeds side by side rather than behind a tab. They are two different
    // things being built, both of them mine, and a tab made you click to find
    // out whether the other one had moved at all.
    const CHANGES_REPOS = {
        web: 'https://github.com/defrag-racing/defrag-racing-project',
        launcher: 'https://github.com/Defrag-racing/defrag-racing-launcher',
    };
    // Each column carries its own colours as whole class names. Tailwind reads
    // the source for literals, so a class glued together at runtime is one it
    // never generates. Website keeps the green the commit list has always had;
    // the launcher takes the blue it wears everywhere else on the site.
    const CHANGELOG_THEME = {
        web: {
            bar: 'bg-emerald-500/10 border-emerald-400/20',
            title: 'text-emerald-300',
            line: 'border-emerald-500/30',
            tick: 'bg-emerald-500/30',
            dot: 'bg-emerald-400',
            hash: 'text-emerald-400/80',
            row: 'from-emerald-500/10 hover:from-emerald-500/20',
        },
        launcher: {
            bar: 'bg-blue-500/10 border-blue-400/20',
            title: 'text-blue-300',
            line: 'border-blue-500/30',
            tick: 'bg-blue-500/30',
            dot: 'bg-blue-400',
            hash: 'text-blue-400/80',
            row: 'from-blue-500/10 hover:from-blue-500/20',
        },
    };

    const changelogColumns = computed(() => [
        { key: 'web', label: t('Website'), repo: CHANGES_REPOS.web, items: props.recentChangelog || [], c: CHANGELOG_THEME.web },
        { key: 'launcher', label: t('Launcher'), repo: CHANGES_REPOS.launcher, items: props.recentChangelogLauncher || [], c: CHANGELOG_THEME.launcher },
    ]);

    const timeAgo = (dateStr) => {
        if (!dateStr) return '';
        const now = new Date();
        const date = new Date(dateStr);
        const diffMs = now - date;
        const diffMin = Math.floor(diffMs / 60000);
        if (diffMin < 1) return t('just now');
        if (diffMin < 60) return t(':count m ago', { count: diffMin });
        const diffH = Math.floor(diffMin / 60);
        if (diffH < 24) return t(':count h ago', { count: diffH });
        const diffD = Math.floor(diffH / 24);
        if (diffD < 30) return t(':count d ago', { count: diffD });
        return date.toLocaleDateString();
    };
</script>

<template>
    <div class="">
        <Head :title="$t('Home')" />

        <!-- Hero Section -->
        <div class="relative bg-gradient-to-b from-black/25 via-black/10 to-transparent pt-6 pb-2">
            <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8">
                <div class="text-center mb-4">
                    <h1 class="text-2xl md:text-3xl font-black text-white mb-4 leading-tight">
                        {{ $t('Push Your Speed') }} <span class="text-blue-500">{{ $t('To The Limit') }}</span>
                    </h1>
                    <p class="text-base text-gray-400 mb-6 max-w-3xl mx-auto leading-relaxed">
                        {{ $t('Join the ultimate Quake 3 Defrag community. Master physics, dominate leaderboards, and compete against the best speedrunners in the world.') }}
                    </p>

                    <!-- Stats Row -->
                    <div class="grid grid-cols-2 md:grid-cols-5 gap-3 max-w-5xl mx-auto mb-1.5">
                        <Link :href="route('maps')" class="bg-black/40 backdrop-blur-sm rounded-xl p-4 border border-white/10 text-center hover:border-blue-500/50 transition-all cursor-pointer">
                            <div class="text-2xl md:text-3xl font-black text-blue-400 mb-1">{{ totalMaps >= 1000 ? (totalMaps / 1000).toFixed(1) + 'K' : totalMaps }}</div>
                            <div class="text-gray-400 font-semibold text-xs">{{ $t('Total Maps') }}</div>
                        </Link>
                        <Link href="/demos" class="bg-black/40 backdrop-blur-sm rounded-xl p-4 border border-white/10 text-center hover:border-yellow-500/50 transition-all cursor-pointer">
                            <div class="text-2xl md:text-3xl font-black text-yellow-400 mb-1">{{ totalDemos >= 1000 ? (totalDemos / 1000).toFixed(1) + 'K' : totalDemos }}</div>
                            <div class="text-gray-400 font-semibold text-xs">{{ $t('Total Demos') }}</div>
                        </Link>
                        <Link :href="route('servers')" class="bg-black/40 backdrop-blur-sm rounded-xl p-4 border border-white/10 text-center hover:border-green-500/50 transition-all cursor-pointer">
                            <div class="text-2xl md:text-3xl font-black text-green-400 mb-1">{{ activeServers }}</div>
                            <div class="text-gray-400 font-semibold text-xs">{{ $t('Active Servers') }}</div>
                        </Link>
                        <Link href="/ranking" class="bg-black/40 backdrop-blur-sm rounded-xl p-4 border border-white/10 text-center hover:border-purple-500/50 transition-all cursor-pointer">
                            <div class="text-2xl md:text-3xl font-black text-purple-400 mb-1">{{ activePlayers }}</div>
                            <div class="text-gray-400 font-semibold text-xs">{{ $t('Players (30d)') }}</div>
                        </Link>
                        <Link :href="route('records')" class="bg-black/40 backdrop-blur-sm rounded-xl p-4 border border-white/10 text-center hover:border-orange-500/50 transition-all cursor-pointer">
                            <div class="text-2xl md:text-3xl font-black text-orange-400 mb-1">{{ totalRecords >= 1000000 ? (totalRecords / 1000000).toFixed(1) + 'M' : totalRecords >= 1000 ? (totalRecords / 1000).toFixed(1) + 'K' : totalRecords }}</div>
                            <div class="text-gray-400 font-semibold text-xs">{{ $t('Records Stored') }}</div>
                        </Link>
                    </div>

                    <!-- Latest News -->
                    <div class="mt-3">
                        <div v-if="latestAnnouncement" class="relative max-w-3xl mx-auto">
                            <!-- Quasar jets -->
                            <div class="news-quasar-jet-left">
                                <div class="news-quasar-core"></div>
                                <div class="news-quasar-beam"></div>
                                <div class="news-quasar-beam news-quasar-beam-wide"></div>
                                <div class="news-quasar-beam news-quasar-beam-faint"></div>
                                <div class="news-quasar-particles">
                                    <span></span><span></span><span></span><span></span><span></span>
                                </div>
                            </div>
                            <div class="news-quasar-jet-right">
                                <div class="news-quasar-core"></div>
                                <div class="news-quasar-beam"></div>
                                <div class="news-quasar-beam news-quasar-beam-wide"></div>
                                <div class="news-quasar-beam news-quasar-beam-faint"></div>
                                <div class="news-quasar-particles">
                                    <span></span><span></span><span></span><span></span><span></span>
                                </div>
                            </div>
                            <Link
                                :href="route('announcements')"
                                class="relative z-10 flex items-center gap-3 px-5 py-3.5 bg-blue-950/80 backdrop-blur-sm border border-blue-400/50 rounded-xl text-gray-300 hover:bg-blue-900/70 hover:border-blue-400/80 hover:shadow-[0_0_50px_rgba(59,130,246,0.35)] transition-all group shadow-[0_4px_20px_rgba(0,0,0,0.4),0_0_30px_rgba(59,130,246,0.2)] cursor-pointer"
                            >
                                <div class="absolute -top-2 -right-2 px-2.5 py-0.5 bg-gradient-to-r from-blue-500 to-cyan-500 rounded-full text-[10px] font-black text-white shadow-lg">{{ $t('NEW') }}</div>
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5 text-blue-400 flex-shrink-0">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.34 15.84c-.688-.06-1.386-.09-2.09-.09H7.5a4.5 4.5 0 1 1 0-9h.75c.704 0 1.402-.03 2.09-.09m0 9.18c.253.962.584 1.892.985 2.783.247.55.06 1.21-.463 1.511l-.657.38c-.551.318-1.26.117-1.527-.461a20.845 20.845 0 0 1-1.44-4.282m3.102.069a18.03 18.03 0 0 1-.59-4.59c0-1.586.205-3.124.59-4.59m0 9.18a23.848 23.848 0 0 1 8.835 2.535M10.34 6.66a23.847 23.847 0 0 0 8.835-2.535m0 0A23.74 23.74 0 0 0 18.795 3m.38 1.125a23.91 23.91 0 0 1 1.014 5.395m-1.014 8.855c-.118.38-.245.754-.38 1.125m.38-1.125a23.91 23.91 0 0 0 1.014-5.395m0-3.46c.495.413.811 1.035.811 1.73 0 .695-.316 1.317-.811 1.73m0-3.46a24.347 24.347 0 0 1 0 3.46" />
                                </svg>
                                <div class="text-xs uppercase font-bold tracking-wider text-blue-300">{{ $t('Latest News') }}</div>
                                <h3 class="text-sm font-bold text-white group-hover:text-blue-200 transition-colors truncate flex-1">{{ latestAnnouncement.title }}</h3>
                                <svg class="w-5 h-5 group-hover:translate-x-1 transition-transform flex-shrink-0 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                            </Link>
                        </div>
                    </div>

                    <!-- What is running right now and pays money. Both hide
                         themselves when nothing is on, so the row disappears
                         entirely rather than leaving a gap.

                         Comps sits under the contest: it runs every week and
                         the contest does not, so the rarer one leads. They are
                         the same shape in two colours on purpose - two cards
                         side by side in different shapes read as one card and
                         one advert. -->
                    <div class="mt-3 max-w-3xl mx-auto flex flex-col gap-3">
                        <DefragliveContestBanner variant="hero" />
                        <CompsBanner variant="hero" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content Sections -->
        <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8 space-y-6 pb-4">

            <!-- Row 1: Active Servers + Recent World Records + Top Players + Tournaments -->
            <div class="grid grid-cols-1 lg:grid-cols-[1.2fr_1.5fr_1fr_1fr] gap-6">

                <!-- Active Servers -->
                <div class="bg-black/40 backdrop-blur-sm rounded-xl border border-white/10 overflow-hidden">
                    <div class="flex items-center justify-between px-5 py-3 border-b border-white/5">
                        <h2 class="text-sm font-bold text-white flex items-center gap-2">
                            <svg class="w-4 h-4 text-green-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M5.25 14.25h13.5m-13.5 0a3 3 0 0 1-3-3m3 3a3 3 0 1 0 0 6h13.5a3 3 0 1 0 0-6m-16.5-3a3 3 0 0 1 3-3h13.5a3 3 0 0 1 3 3m-19.5 0a4.5 4.5 0 0 1 .9-2.7L5.737 5.1a3.375 3.375 0 0 1 2.7-1.35h7.126c1.062 0 2.062.5 2.7 1.35l2.587 3.45a4.5 4.5 0 0 1 .9 2.7" /></svg>
                            {{ $t('Active Servers') }}
                        </h2>
                        <Link :href="route('servers')" class="text-xs text-blue-400 hover:text-blue-300 font-medium">{{ $t('View All') }}</Link>
                    </div>
                    <div v-if="servers && servers.length > 0" class="divide-y divide-white/5">
                        <div v-for="server in servers" :key="server.id" class="px-5 py-3 hover:bg-white/[0.02] transition-colors">
                            <div class="flex items-center justify-between gap-3">
                                <div class="flex-1 min-w-0">
                                    <div class="text-sm font-bold text-white truncate" v-html="q3tohtml(server.name)"></div>
                                    <div class="flex items-center gap-2 mt-1 text-xs text-gray-400">
                                        <span v-if="server.mapdata" class="text-blue-400">{{ server.mapdata.name || server.map }}</span>
                                        <span v-else class="text-blue-400">{{ server.map }}</span>
                                        <span class="text-white/20">|</span>
                                        <span :class="(server.online_players?.length || server.numplayers) > 0 ? 'text-green-400 font-medium' : ''">
                                            {{ $tc(':count player|:count players', server.online_players?.length || server.numplayers || 0) }}
                                        </span>
                                    </div>
                                </div>
                                <a :href="`defrag://${server.ip}:${server.port}`"
                                   class="shrink-0 flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-bold border border-green-500/40 text-green-400 hover:bg-green-500/20 transition-all">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 0 1 0 1.972l-11.54 6.347a1.125 1.125 0 0 1-1.667-.986V5.653Z" /></svg>
                                    {{ $t('Join') }}
                                </a>
                            </div>
                        </div>
                    </div>
                    <div v-else class="px-5 py-8 text-center text-sm text-gray-500">{{ $t('No servers online') }}</div>
                </div>

                <!-- Recent World Records -->
                <div class="bg-black/40 backdrop-blur-sm rounded-xl border border-white/10 overflow-hidden">
                    <div class="flex items-center justify-between px-5 py-3 border-b border-white/5">
                        <h2 class="text-sm font-bold text-white flex items-center gap-2">
                            <svg class="w-4 h-4 text-yellow-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 18.75h-9m9 0a3 3 0 0 1 3 3h-15a3 3 0 0 1 3-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.007 0H9.497m5.007 0a7.454 7.454 0 0 1-.982-3.172M9.497 14.25a7.454 7.454 0 0 0 .981-3.172M5.25 4.236c-.996.178-1.768.562-2.258 1.103m0 0 .238-.303a.25.25 0 0 1 .4.024l.002.002c.186.296.47.515.796.63l.013.003m-1.449-.356v3.273M18.75 4.236c.996.178 1.768.562 2.258 1.103m0 0-.238-.303a.25.25 0 0 0-.4.024l-.002.002a1.619 1.619 0 0 1-.796.63l-.013.003m1.449-.356v3.273m-15 0h15" /></svg>
                            {{ $t('Recent World Records') }}
                        </h2>
                        <Link :href="route('records')" class="text-xs text-blue-400 hover:text-blue-300 font-medium">{{ $t('View All') }}</Link>
                    </div>
                    <div v-if="recentWorldRecords && recentWorldRecords.length > 0" class="p-3 space-y-3">
                        <!-- VQ3 -->
                        <div>
                            <div class="text-[10px] font-bold text-blue-400 uppercase tracking-wider mb-1.5 px-1">VQ3</div>
                            <div class="space-y-0.5">
                                <Link v-for="wr in recentWorldRecords.filter(r => r.physics !== 'cpm').slice(0, 3)" :key="wr.id"
                                      :href="`/maps/${encodeURIComponent(wr.mapname)}`"
                                      class="flex items-center gap-2 px-2 py-1.5 rounded-lg bg-blue-500/5 hover:bg-blue-500/15 transition-colors group">
                                    <span class="text-sm text-blue-400 group-hover:text-blue-300 font-medium truncate flex-1">{{ wr.mapname }}</span>
                                    <span class="text-xs text-gray-300 font-medium shrink-0" v-html="q3tohtml(wr.user?.name || wr.name)"></span>
                                    <span class="text-xs font-mono text-white/70 shrink-0">{{ formatTime(wr.time) }}</span>
                                    <span class="text-[10px] text-gray-500 shrink-0">{{ timeAgo(wr.date_set) }}</span>
                                </Link>
                            </div>
                        </div>
                        <!-- CPM -->
                        <div>
                            <div class="text-[10px] font-bold text-purple-400 uppercase tracking-wider mb-1.5 px-1">CPM</div>
                            <div class="space-y-0.5">
                                <Link v-for="wr in recentWorldRecords.filter(r => r.physics === 'cpm').slice(0, 3)" :key="wr.id"
                                      :href="`/maps/${encodeURIComponent(wr.mapname)}`"
                                      class="flex items-center gap-2 px-2 py-1.5 rounded-lg bg-purple-500/5 hover:bg-purple-500/15 transition-colors group">
                                    <span class="text-sm text-purple-400 group-hover:text-purple-300 font-medium truncate flex-1">{{ wr.mapname }}</span>
                                    <span class="text-xs text-gray-300 font-medium shrink-0" v-html="q3tohtml(wr.user?.name || wr.name)"></span>
                                    <span class="text-xs font-mono text-white/70 shrink-0">{{ formatTime(wr.time) }}</span>
                                    <span class="text-[10px] text-gray-500 shrink-0">{{ timeAgo(wr.date_set) }}</span>
                                </Link>
                            </div>
                        </div>
                    </div>
                    <div v-else class="px-5 py-8 text-center text-sm text-gray-500">{{ $t('No records yet') }}</div>
                </div>

                <!-- Top Players -->
                <div class="bg-black/40 backdrop-blur-sm rounded-xl border border-white/10 overflow-hidden">
                    <div class="flex items-center justify-between px-5 py-3 border-b border-white/5">
                        <h2 class="text-sm font-bold text-white flex items-center gap-2">
                            <svg class="w-4 h-4 text-purple-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" /></svg>
                            {{ $t('Top Players') }}
                        </h2>
                        <Link href="/ranking" class="text-xs text-blue-400 hover:text-blue-300 font-medium">{{ $t('Full Ranking') }}</Link>
                    </div>
                    <div v-if="rankingHighlights" class="p-3 space-y-3">
                        <div>
                            <div class="text-[10px] font-bold text-blue-400 uppercase tracking-wider mb-1.5 px-1">VQ3</div>
                            <div class="space-y-0.5">
                                <Link v-for="(player, i) in rankingHighlights.vq3" :key="player.id"
                                      :href="player.user ? `/profile/${player.user.id}` : `/mdd/profile/${player.mdd_id}`"
                                      class="flex items-center gap-2 px-2 py-1.5 rounded-lg bg-blue-500/5 hover:bg-blue-500/15 transition-colors group">
                                    <span :class="['w-5 text-xs font-black text-center', i === 0 ? 'text-yellow-400' : i === 1 ? 'text-gray-300' : i === 2 ? 'text-orange-400' : 'text-gray-500']">{{ i + 1 }}</span>
                                    <span class="text-sm truncate flex-1" v-html="q3tohtml(player.user?.name || player.name)"></span>
                                    <span class="text-xs font-mono text-gray-400">{{ Math.round(player.player_rating) }}</span>
                                </Link>
                            </div>
                        </div>
                        <div>
                            <div class="text-[10px] font-bold text-purple-400 uppercase tracking-wider mb-1.5 px-1">CPM</div>
                            <div class="space-y-0.5">
                                <Link v-for="(player, i) in rankingHighlights.cpm" :key="player.id"
                                      :href="player.user ? `/profile/${player.user.id}` : `/mdd/profile/${player.mdd_id}`"
                                      class="flex items-center gap-2 px-2 py-1.5 rounded-lg bg-purple-500/5 hover:bg-purple-500/15 transition-colors group">
                                    <span :class="['w-5 text-xs font-black text-center', i === 0 ? 'text-yellow-400' : i === 1 ? 'text-gray-300' : i === 2 ? 'text-orange-400' : 'text-gray-500']">{{ i + 1 }}</span>
                                    <span class="text-sm truncate flex-1" v-html="q3tohtml(player.user?.name || player.name)"></span>
                                    <span class="text-xs font-mono text-gray-400">{{ Math.round(player.player_rating) }}</span>
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Maps & Models -->
                <div class="bg-black/40 backdrop-blur-sm rounded-xl border border-white/10 overflow-hidden">
                    <div class="flex items-center justify-between px-5 py-3 border-b border-white/5">
                        <h2 class="text-sm font-bold text-white flex items-center gap-2">
                            <svg class="w-4 h-4 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.455 2.456L21.75 6l-1.036.259a3.375 3.375 0 0 0-2.455 2.456ZM16.894 20.567 16.5 21.75l-.394-1.183a2.25 2.25 0 0 0-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 0 0 1.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 0 0 1.423 1.423l1.183.394-1.183.394a2.25 2.25 0 0 0-1.423 1.423Z" /></svg>
                            {{ $t('New Content') }}
                        </h2>
                    </div>
                    <div class="p-3 space-y-3">
                        <!-- Latest Map -->
                        <Link v-if="latestMap" :href="`/maps/${encodeURIComponent(latestMap.name)}`" class="block group">
                            <div class="relative rounded-lg overflow-hidden aspect-[16/9]">
                                <img v-if="latestMap.thumbnail" :src="`/storage/${latestMap.thumbnail}`" :alt="latestMap.name" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" />
                                <div v-else class="w-full h-full bg-gray-800 flex items-center justify-center">
                                    <span class="text-gray-500 text-xs">{{ $t('No preview') }}</span>
                                </div>
                                <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-transparent to-transparent"></div>
                                <div class="absolute bottom-0 left-0 right-0 p-2">
                                    <div class="text-[10px] text-blue-400 font-bold uppercase">{{ $t('Latest Map') }}</div>
                                    <div class="text-xs font-bold text-white truncate">{{ latestMap.name }}</div>
                                </div>
                            </div>
                        </Link>
                        <!-- Latest Model -->
                        <Link v-if="latestModel" :href="`/models/${latestModel.id}`" class="block group">
                            <div class="relative rounded-lg overflow-hidden aspect-[16/9] bg-gray-900">
                                <img :src="`/storage/${latestModel.gesture_gif || latestModel.idle_gif || latestModel.thumbnail}`" :alt="latestModel.name" class="w-full h-full object-contain group-hover:scale-105 transition-transform duration-300" />
                                <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-transparent to-transparent"></div>
                                <div class="absolute bottom-0 left-0 right-0 p-2">
                                    <div class="text-[10px] text-purple-400 font-bold uppercase">{{ $t('Latest Model') }}</div>
                                    <div class="text-xs font-bold text-white truncate">{{ latestModel.name }}</div>
                                </div>
                            </div>
                        </Link>
                    </div>
                </div>

                <!-- Tournaments (hidden for now) -->
                <!--
                <div class="bg-black/40 backdrop-blur-sm rounded-xl border border-white/10 overflow-hidden">
                    <div class="flex items-center justify-between px-5 py-3 border-b border-white/5">
                        <h2 class="text-sm font-bold text-white flex items-center gap-2">
                            <svg class="w-4 h-4 text-red-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.362 5.214A8.252 8.252 0 0 1 12 21 8.25 8.25 0 0 1 6.038 7.047 8.287 8.287 0 0 0 9 9.601a8.983 8.983 0 0 1 3.361-6.867 8.21 8.21 0 0 0 3 2.48Z" /></svg>
                            Tournaments
                        </h2>
                        <Link :href="route('tournaments.index')" class="text-xs text-blue-400 hover:text-blue-300 font-medium">{{ $t('View All') }}</Link>
                    </div>
                    <div v-if="upcomingTournaments && upcomingTournaments.length > 0" class="divide-y divide-white/5">
                        <Link v-for="tournament in upcomingTournaments" :key="tournament.id"
                              :href="`/tournaments/${tournament.id}`"
                              class="flex items-center gap-3 px-5 py-3 hover:bg-white/[0.02] transition-colors group">
                            <div class="flex-1 min-w-0">
                                <div class="text-sm font-bold text-white group-hover:text-red-400 transition-colors truncate">{{ tournament.name }}</div>
                                <div class="text-xs text-gray-400 mt-0.5">
                                    {{ new Date(tournament.start_date).toLocaleDateString() }}
                                    <span v-if="tournament.end_date"> - {{ new Date(tournament.end_date).toLocaleDateString() }}</span>
                                </div>
                            </div>
                            <span v-if="new Date(tournament.start_date) > new Date()" class="px-2 py-0.5 rounded text-[10px] font-bold bg-yellow-500/20 text-yellow-400 border border-yellow-500/30">Upcoming</span>
                            <span v-else class="px-2 py-0.5 rounded text-[10px] font-bold bg-green-500/20 text-green-400 border border-green-500/30 animate-pulse">Live</span>
                        </Link>
                    </div>
                    <div v-else class="px-5 py-8 text-center text-sm text-gray-500">No tournaments</div>
                </div>
                -->
            </div>

            <!-- Row 2: Changelog -->
            <div>
                <div class="bg-black/40 backdrop-blur-sm rounded-xl border border-white/10 overflow-hidden">
                    <div class="flex items-center justify-between px-5 py-3 border-b border-white/5 gap-3 flex-wrap">
                        <h2 class="text-sm font-bold text-white flex items-center gap-2">
                            <svg class="w-4 h-4 text-cyan-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M17.25 6.75 22.5 12l-5.25 5.25m-10.5 0L1.5 12l5.25-5.25m7.5-3-4.5 16.5" /></svg>
                            {{ $t('Recent Changes') }}
                        </h2>
                        <Link href="/roadmap" class="text-xs text-blue-400 hover:text-blue-300 font-medium">{{ $t('Roadmap') }}</Link>
                    </div>

                    <!-- Website on the left, launcher on the right. They used to
                         share one list behind a tab, which meant you had to click
                         to learn whether the other one had moved at all. -->
                    <div class="grid md:grid-cols-2 md:divide-x md:divide-white/5">
                        <div v-for="col in changelogColumns" :key="col.key" class="min-w-0">
                            <div class="flex items-center gap-2 px-4 py-2.5 border-b" :class="col.c.bar">
                                <svg class="w-4 h-4 flex-shrink-0" :class="col.c.title" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                                    <template v-if="col.key === 'web'"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582m15.686 0A11.953 11.953 0 0 1 12 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0 1 21 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0 1 12 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 0 1 3 12c0-1.605.42-3.113 1.157-4.418" /></template>
                                    <template v-else><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" /></template>
                                </svg>
                                <span class="text-sm font-black" :class="col.c.title">{{ col.label }}</span>
                            </div>

                            <div v-if="col.items.length" class="p-3 space-y-1">
                                <div v-for="commit in col.items" :key="commit.hash"
                                     class="relative pl-5 border-l-2"
                                     :class="[col.c.line, commit.description ? 'cursor-help' : '']"
                                     @mouseenter="commit.description ? onCommitEnter($event, commit) : null"
                                     @mousemove="commit.description ? onCommitMove($event) : null"
                                     @mouseleave="onCommitLeave">
                                    <div class="absolute left-0 top-2.5 w-3 h-0.5" :class="col.c.tick"></div>
                                    <a :href="`${col.repo}/commit/${commit.hash}`" target="_blank" class="flex items-center gap-2 px-2 py-1.5 bg-gradient-to-r to-transparent rounded transition-all" :class="col.c.row">
                                        <div class="w-1.5 h-1.5 rounded-full flex-shrink-0" :class="col.c.dot"></div>
                                        <span class="text-[10px] font-mono text-gray-500 w-16 flex-shrink-0">{{ commit.date }}</span>
                                        <span class="text-xs font-mono flex-shrink-0" :class="col.c.hash">{{ commit.hash }}</span>
                                        <span class="text-xs text-gray-300 truncate flex-1">{{ commit.title }}</span>
                                    </a>
                                </div>
                            </div>
                            <div v-else class="px-5 py-8 text-center text-sm text-gray-500">
                                {{ col.key === 'launcher' ? $t('No launcher changes to show') : $t('No recent changes') }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Getting Started (compact) -->
            <div>
                <div class="text-center mb-4">
                    <h2 class="text-2xl font-black text-white mb-1">{{ $t('Get Started') }}</h2>
                    <p class="text-gray-400 text-sm">{{ $t('New to Defrag? Follow these steps to join the community.') }}</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <!-- Register / Account -->
                    <Link v-if="!isAuthenticated" :href="route('register')" class="bg-black/40 backdrop-blur-sm rounded-xl border border-white/10 p-5 hover:border-blue-500/50 transition-all group">
                        <div class="flex items-center gap-4">
                            <div class="p-3 bg-blue-500/20 rounded-lg shrink-0">
                                <svg class="w-6 h-6 text-blue-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M18 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0ZM3 19.235v-.11a6.375 6.375 0 0 1 12.75 0v.109A12.318 12.318 0 0 1 9.374 21c-2.331 0-4.512-.645-6.374-1.766Z" /></svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <h3 class="text-base font-bold text-white group-hover:text-blue-400 transition-colors">{{ $t('Create Account') }}</h3>
                                <p class="text-xs text-gray-400 mt-0.5">{{ $t('Track records, compete on leaderboards') }}</p>
                            </div>
                        </div>
                    </Link>
                    <div v-else class="bg-green-500/10 backdrop-blur-sm rounded-xl border border-green-500/30 p-5">
                        <div class="flex items-center gap-4">
                            <div class="p-3 bg-green-500/20 rounded-lg shrink-0">
                                <svg class="w-6 h-6 text-green-400" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <h3 class="text-base font-bold text-green-400">{{ $t('Account Created') }}</h3>
                                <p class="text-xs text-gray-400 mt-0.5">{{ $t('Your records are tracked automatically') }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Link Q3DF -->
                    <Link v-if="isAuthenticated && !isLinked" href="/link-account" class="bg-yellow-500/5 backdrop-blur-sm rounded-xl border border-yellow-500/30 p-5 hover:border-yellow-500/50 transition-all group">
                        <div class="flex items-center gap-4">
                            <div class="p-3 bg-yellow-500/20 rounded-lg shrink-0 animate-pulse">
                                <svg class="w-6 h-6 text-yellow-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244" /></svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <h3 class="text-base font-bold text-yellow-400 group-hover:text-yellow-300 transition-colors">{{ $t('Link Q3DF Profile') }}</h3>
                                <p class="text-xs text-gray-400 mt-0.5">{{ $t('Unlock rankings, stats, demo uploads') }}</p>
                            </div>
                        </div>
                    </Link>
                    <div v-else-if="isAuthenticated && isLinked" class="bg-green-500/10 backdrop-blur-sm rounded-xl border border-green-500/30 p-5">
                        <div class="flex items-center gap-4">
                            <div class="p-3 bg-green-500/20 rounded-lg shrink-0">
                                <svg class="w-6 h-6 text-green-400" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <h3 class="text-base font-bold text-green-400">{{ $t('Profile Linked') }}</h3>
                                <p class="text-xs text-gray-400 mt-0.5">{{ $t('Synced with mDd database') }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Download -->
                    <div class="flex gap-2 items-stretch">
                        <Link :href="route('downloads')" :class="['flex-1 backdrop-blur-sm rounded-xl border p-5 transition-all group', downloadChecked ? 'bg-green-500/10 border-green-500/30' : 'bg-black/40 border-white/10 hover:border-blue-500/50']">
                            <div class="flex items-center gap-4">
                                <div :class="['p-3 rounded-lg shrink-0', downloadChecked ? 'bg-green-500/20' : 'bg-blue-500/20']">
                                    <svg :class="['w-6 h-6', downloadChecked ? 'text-green-400' : 'text-blue-400']" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <h3 :class="['text-base font-bold transition-colors', downloadChecked ? 'text-green-400' : 'text-white group-hover:text-blue-400']">{{ $t('Download Game') }}</h3>
                                    <p class="text-xs text-gray-400 mt-0.5">{{ $t('Complete Defrag bundle, one-click install') }}</p>
                                </div>
                            </div>
                        </Link>
                        <button v-if="isAuthenticated" @click="toggleDownload" :class="['shrink-0 w-10 rounded-lg border-2 flex items-center justify-center transition-all', downloadChecked ? 'bg-green-500/20 border-green-500 text-green-400' : 'border-white/20 hover:border-white/40 text-transparent hover:text-white/20']">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                        </button>
                    </div>

                    <!-- Download Launcher -->
                    <div class="flex gap-2 items-stretch">
                        <Link :href="route('launcher')" :class="['flex-1 backdrop-blur-sm rounded-xl border p-5 transition-all group', launcherChecked ? 'bg-green-500/10 border-green-500/30' : 'bg-black/40 border-white/10 hover:border-blue-500/50']">
                            <div class="flex items-center gap-4">
                                <div :class="['p-3 rounded-lg shrink-0', launcherChecked ? 'bg-green-500/20' : 'bg-blue-500/20']">
                                    <svg :class="['w-6 h-6', launcherChecked ? 'text-green-400' : 'text-blue-400']" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4m4-5 5 5m0 0 5-5m-5 5V3" /></svg>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <h3 :class="['text-base font-bold transition-colors', launcherChecked ? 'text-green-400' : 'text-white group-hover:text-blue-400']">{{ $t('Download Launcher') }}</h3>
                                    <p class="text-xs text-gray-400 mt-0.5">{{ $t('Auto-backup demos, 1-click Connect & more') }}</p>
                                </div>
                            </div>
                        </Link>
                        <button v-if="isAuthenticated" @click="toggleLauncher" :class="['shrink-0 w-10 rounded-lg border-2 flex items-center justify-center transition-all', launcherChecked ? 'bg-green-500/20 border-green-500 text-green-400' : 'border-white/20 hover:border-white/40 text-transparent hover:text-white/20']">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                        </button>
                    </div>

                    <!-- Discord -->
                    <div class="flex gap-2 items-stretch">
                        <a href="https://discord.defrag.racing" target="_blank" :class="['flex-1 backdrop-blur-sm rounded-xl border p-5 transition-all group', discordChecked ? 'bg-green-500/10 border-green-500/30' : 'bg-black/40 border-white/10 hover:border-blue-500/50']">
                            <div class="flex items-center gap-4">
                                <div :class="['p-3 rounded-lg shrink-0', discordChecked ? 'bg-green-500/20' : 'bg-blue-500/20']">
                                    <svg :class="['w-6 h-6', discordChecked ? 'text-green-400' : 'text-blue-400']" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 8.511c.884.284 1.5 1.128 1.5 2.097v4.286c0 1.136-.847 2.1-1.98 2.193-.34.027-.68.052-1.02.072v3.091l-3-3c-1.354 0-2.694-.055-4.02-.163a2.115 2.115 0 0 1-.825-.242m9.345-8.334a2.126 2.126 0 0 0-.476-.095 48.64 48.64 0 0 0-8.048 0c-1.131.094-1.976 1.057-1.976 2.192v4.286c0 .837.46 1.58 1.155 1.951m9.345-8.334V6.637c0-1.621-1.152-3.026-2.76-3.235A48.455 48.455 0 0 0 11.25 3c-2.115 0-4.198.137-6.24.402-1.608.209-2.76 1.614-2.76 3.235v6.226c0 1.621 1.152 3.026 2.76 3.235.577.075 1.157.14 1.74.194V21l4.155-4.155" /></svg>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <h3 :class="['text-base font-bold transition-colors', discordChecked ? 'text-green-400' : 'text-white group-hover:text-blue-400']">{{ $t('Join Discord') }}</h3>
                                    <p class="text-xs text-gray-400 mt-0.5">{{ $t('Community, help, events') }}</p>
                                </div>
                            </div>
                        </a>
                        <button v-if="isAuthenticated" @click="toggleDiscord" :class="['shrink-0 w-10 rounded-lg border-2 flex items-center justify-center transition-all', discordChecked ? 'bg-green-500/20 border-green-500 text-green-400' : 'border-white/20 hover:border-white/40 text-transparent hover:text-white/20']">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                        </button>
                    </div>

                    <!-- Rules. Amber rather than blue: it is the one step here
                         that costs somebody their records if they skip it. -->
                    <div class="flex gap-2 items-stretch">
                        <Link :href="route('rules')" :class="['flex-1 backdrop-blur-sm rounded-xl border p-5 transition-all group', rulesChecked ? 'bg-green-500/10 border-green-500/30' : 'bg-black/40 border-white/10 hover:border-amber-500/50']">
                            <div class="flex items-center gap-4">
                                <div :class="['p-3 rounded-lg shrink-0', rulesChecked ? 'bg-green-500/20' : 'bg-amber-500/20']">
                                    <svg :class="['w-6 h-6', rulesChecked ? 'text-green-400' : 'text-amber-400']" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v17.25m0 0c-1.472 0-2.882.265-4.185.75M12 20.25c1.472 0 2.882.265 4.185.75M18.75 4.97A48.416 48.416 0 0 0 12 4.5c-2.291 0-4.545.16-6.75.47m13.5 0c1.01.143 2.01.317 3 .52m-3-.52 2.62 10.726c.122.499-.106 1.028-.589 1.202a5.988 5.988 0 0 1-2.031.352 5.988 5.988 0 0 1-2.031-.352c-.483-.174-.711-.703-.59-1.202L18.75 4.971Zm-16.5.52c.99-.203 1.99-.377 3-.52m0 0 2.62 10.726c.122.499-.106 1.028-.589 1.202a5.989 5.989 0 0 1-2.031.352 5.989 5.989 0 0 1-2.031-.352c-.483-.174-.711-.703-.59-1.202L5.25 4.971Z" /></svg>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <h3 :class="['text-base font-bold transition-colors', rulesChecked ? 'text-green-400' : 'text-white group-hover:text-amber-400']">{{ $t('Read the Rules') }}</h3>
                                    <p class="text-xs text-gray-400 mt-0.5">{{ $t('What counts as a clean run, and what does not') }}</p>
                                </div>
                            </div>
                        </Link>
                        <button v-if="isAuthenticated" @click="toggleRules" :class="['shrink-0 w-10 rounded-lg border-2 flex items-center justify-center transition-all', rulesChecked ? 'bg-green-500/20 border-green-500 text-green-400' : 'border-white/20 hover:border-white/40 text-transparent hover:text-white/20']">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                        </button>
                    </div>

                    <!-- Explore Maps -->
                    <Link :href="route('maps')" :class="['backdrop-blur-sm rounded-xl border p-5 transition-all group', hasRecords ? 'bg-green-500/10 border-green-500/30' : 'bg-black/40 border-white/10 hover:border-blue-500/50']">
                        <div class="flex items-center gap-4">
                            <div :class="['p-3 rounded-lg shrink-0', hasRecords ? 'bg-green-500/20' : 'bg-blue-500/20']">
                                <svg :class="['w-6 h-6', hasRecords ? 'text-green-400' : 'text-blue-400']" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 6.75V15m6-6v8.25m.503 3.498 4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934c-.317.159-.69.159-1.006 0L9.503 3.252a1.125 1.125 0 0 0-1.006 0L3.622 5.689C3.24 5.88 3 6.27 3 6.695V19.18c0 .836.88 1.38 1.628 1.006l3.869-1.934c.317-.159.69-.159 1.006 0l4.994 2.497c.317.158.69.158 1.006 0Z" /></svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <h3 :class="['text-base font-bold transition-colors', hasRecords ? 'text-green-400' : 'text-white group-hover:text-blue-400']">{{ $t('Explore Maps') }}</h3>
                                <p class="text-xs text-gray-400 mt-0.5">{{ $t('Browse maps, study world records') }}</p>
                            </div>
                        </div>
                    </Link>

                    <!-- Play -->
                    <Link :href="route('servers')" :class="['backdrop-blur-sm rounded-xl border p-5 transition-all group', hasRecords ? 'bg-green-500/10 border-green-500/30' : 'bg-black/40 border-white/10 hover:border-blue-500/50']">
                        <div class="flex items-center gap-4">
                            <div :class="['p-3 rounded-lg shrink-0', hasRecords ? 'bg-green-500/20' : 'bg-blue-500/20']">
                                <svg :class="['w-6 h-6', hasRecords ? 'text-green-400' : 'text-blue-400']" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 0 1 0 1.972l-11.54 6.347a1.125 1.125 0 0 1-1.667-.986V5.653Z" /></svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <h3 :class="['text-base font-bold transition-colors', hasRecords ? 'text-green-400' : 'text-white group-hover:text-blue-400']">{{ $t('Connect & Play') }}</h3>
                                <p class="text-xs text-gray-400 mt-0.5">{{ $t('Join servers, records auto-submitted') }}</p>
                            </div>
                        </div>
                    </Link>
                </div>
            </div>
        </div>
        <!-- Commit Tooltip -->
        <Teleport to="body">
            <div
                v-if="hoveredCommit && hoveredCommit.description"
                class="fixed z-[9999] pointer-events-none"
                :style="{ left: commitTooltipPos.x + 20 + 'px', top: commitTooltipPos.y - 10 + 'px' }"
            >
                <div class="bg-gray-900 border border-cyan-500/30 text-gray-300 rounded-lg px-4 py-3 shadow-2xl max-w-[500px] text-xs leading-relaxed">
                    <strong class="text-cyan-400 block mb-1.5">{{ hoveredCommit.title }}</strong>
                    <p class="whitespace-pre-line">{{ hoveredCommit.description }}</p>
                </div>
            </div>
        </Teleport>
    </div>
</template>
