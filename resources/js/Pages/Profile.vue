<script setup>
    import { ref, watch, computed, onMounted } from 'vue';

    import { Head, router, Link, usePage } from '@inertiajs/vue3';
    import Pagination from '@/Components/Basic/Pagination.vue';
    import ActivityHeatmap from '@/Components/ActivityHeatmap.vue';
    import ProfileCreatorTab from '@/Components/ProfileCreatorTab.vue';
    import ProfileModelerTab from '@/Components/ProfileModelerTab.vue';
    import ProfileLayoutForm from '@/Pages/Profile/Partials/ProfileLayoutForm.vue';
    import AvatarNameEffectsForm from '@/Pages/Profile/Partials/AvatarNameEffectsForm.vue';
    import MapRecordsViewForm from '@/Pages/Profile/Partials/MapRecordsViewForm.vue';
    import PhysicsOrderForm from '@/Pages/Profile/Partials/PhysicsOrderForm.vue';
    import EffectsIntensityForm from '@/Pages/Profile/Partials/EffectsIntensityForm.vue';
    import CopyButton from '@/Components/Basic/CopyButton.vue';
    import DemoRenderButton from '@/Components/DemoRenderButton.vue';
    import DemoFlagModal from '@/Components/DemoFlagModal.vue';
    import FreestyleDemosTable from '@/Components/FreestyleDemosTable.vue';
    import axios from 'axios';

    const page = usePage();
    const cpmFirst = computed(() => page.props.physicsOrder === 'cpm_first');

    const props = defineProps({
        user: Object,
        vq3Records: Object,
        cpmRecords: Object,
        type: String,
        search: {
            type: String,
            default: '',
        },
        profile: Object,
        cpm_world_records: {
            type: Number,
            default: 0
        },
        vq3_world_records: {
            type: Number,
            default: 0
        },
        cpm_competitors: Object,
        vq3_competitors: Object,
        cpm_rivals: Object,
        vq3_rivals: Object,
        unplayed_maps: Object,
        total_maps: {
            type: Number,
            default: 0
        },
        played_maps_count: {
            type: Number,
            default: 0
        },
        user_maplists: {
            type: Array,
            default: () => []
        },
        aliases: {
            type: Array,
            default: () => []
        },
        alias_suggestions: {
            type: Array,
            default: () => []
        },
        can_suggest_alias: {
            type: Boolean,
            default: false
        },
        can_manage_aliases: {
            type: Boolean,
            default: false
        },
        topDownloadedDemos: {
            type: Array,
            default: () => []
        },
        demoStats: {
            type: Object,
            default: () => ({})
        },
        freestyleDemos: {
            type: Object,
            default: () => ({ total: 0, maps: [], map_total: 0 })
        },
        assignedDemoCounts: {
            type: Object,
            default: () => ({ offline: 0, online: 0 })
        },
        activity_data: {
            type: Object,
            default: () => ({})
        },
        activity_year: {
            type: Number,
            default: () => new Date().getFullYear()
        },
        activity_years: {
            type: Array,
            default: () => []
        },
        // Guest or unverified login: the server sent no stat numbers, no
        // activity data and only page one of the records. The page blurs a
        // made-up stand-in over each locked block (see ProfileController).
        profileLocked: {
            type: Boolean,
            default: false
        },
        hasProfile: Boolean,
        hasMapperProfile: {
            type: Boolean,
            default: false
        },
        hasModelerProfile: {
            type: Boolean,
            default: false
        },
        creatorClaimNames: {
            type: Array,
            default: () => []
        },
        isDonor: {
            type: Boolean,
            default: false
        },
        donorTier: {
            type: String,
            default: null
        },
        donationTotal: {
            type: Object,
            default: () => ({})
        },
        tagCount: {
            type: Number,
            default: 0
        },
        communityTier: {
            type: Object,
            default: null
        },
        playerRankings: {
            type: Array,
            default: () => []
        },
        load_times: Object,
        renderStats: {
            type: Object,
            default: () => ({})
        },
        latestRenderedVideos: {
            type: Array,
            default: () => []
        },
        visitorGlobalPreferences: {
            type: Object,
            default: null
        },
        aboutMePending: {
            type: Boolean,
            default: false
        }
    });

    const color = ref(props.user?.color ? props.user.color : '#ffffff');

    // Lazy-loaded competitors/rivals (fetched after initial render for faster page load)
    const cpmCompetitors = ref(null);
    const vq3Competitors = ref(null);
    const cpmRivals = ref(null);
    const vq3Rivals = ref(null);
    const loadingExtras = ref(true);

    onMounted(async () => {
        const mddId = props.profile?.id;
        if (!props.hasProfile || !mddId) {
            loadingExtras.value = false;
            return;
        }

        // Rivals/competitors are now gated behind auth - skip the fetch for
        // anon visitors. The matching template sections check the same flag
        // and won't render, so there's nothing to fill.
        if (!page.props.auth?.user) {
            loadingExtras.value = false;
            return;
        }

        try {
            const response = await fetch(`/api/profile/${mddId}/extras`);
            if (response.ok) {
                const data = await response.json();
                cpmCompetitors.value = data.cpm_competitors;
                vq3Competitors.value = data.vq3_competitors;
                cpmRivals.value = data.cpm_rivals;
                vq3Rivals.value = data.vq3_rivals;
            }
        } catch (e) {
            console.error('Failed to load profile extras:', e);
        } finally {
            loadingExtras.value = false;
        }
    });

    // Tab navigation
    const urlParams = new URLSearchParams(window.location.search);
    const hasCreatorTabs = computed(() => props.hasMapperProfile || props.hasModelerProfile);
    const initialTab = (() => {
        const tab = urlParams.get('tab');
        if (tab === 'mapper' && props.hasMapperProfile) return 'mapper';
        if (tab === 'modeler' && props.hasModelerProfile) return 'modeler';
        if (tab === 'creator' && props.hasMapperProfile) return 'mapper';
        return 'records';
    })();
    const activeTab = ref(initialTab);

    const switchTab = (tab) => {
        activeTab.value = tab;
        const url = new URL(window.location);
        if (tab === 'records') {
            url.searchParams.delete('tab');
        } else {
            url.searchParams.set('tab', tab);
        }
        window.history.replaceState({}, '', url);
    };

    // Profile layout customization
    const defaultStatBoxes = ['performance', 'activity', 'record_types', 'map_features', 'renders'];
    const defaultSections = [
        { id: 'activity_history', visible: true },
        { id: 'records', visible: true },
        { id: 'rendered_videos', visible: true },
        { id: 'similar_skill_rivals', visible: true },
        { id: 'competitor_comparison', visible: true },
        { id: 'known_aliases', visible: true },
        { id: 'featured_maplists', visible: true },
        { id: 'map_completionist', visible: true },
    ];

    const layout = computed(() => props.user?.profile_layout || {});
    const activeStatBoxes = computed(() => layout.value.stat_boxes || defaultStatBoxes);

    // Header items layout
    const defaultHeaderItems = [
        { id: 'badge_admin', visible: true, row: 1 },
        { id: 'badge_moderator', visible: true, row: 1 },
        { id: 'badge_donor', visible: true, row: 1 },
        { id: 'badge_community', visible: true, row: 1 },
        { id: 'badge_tagger', visible: true, row: 1 },
        { id: 'badge_assigner', visible: true, row: 1 },
        { id: 'player_rank', visible: true, row: 1 },
        { id: 'community_rank', visible: true, row: 1 },
        { id: 'clan', visible: true, row: 1 },
        { id: 'wr_counters', visible: true, row: 1 },
        { id: 'socials', visible: true, row: 2 },
    ];
    const headerItems = computed(() => {
        let saved = layout.value.header_items;
        if (!saved || !saved.length) return defaultHeaderItems;

        // Migrate old 'badges' group to individual items
        if (saved.find(h => h.id === 'badges')) {
            const oldBadges = saved.find(h => h.id === 'badges');
            const badgeIds = ['badge_admin', 'badge_moderator', 'badge_donor', 'badge_community', 'badge_tagger', 'badge_assigner'];
            const idx = saved.indexOf(oldBadges);
            const newBadgeItems = badgeIds.map(id => ({ id, visible: oldBadges.visible, row: oldBadges.row }));
            saved = [...saved.slice(0, idx), ...newBadgeItems, ...saved.slice(idx + 1)];
        }

        // Ensure all items exist
        const items = [...saved];
        for (const def of defaultHeaderItems) {
            if (!items.find(h => h.id === def.id)) items.push({ ...def });
        }
        return items;
    });
    const showHeaderItem = (id) => {
        const item = headerItems.value.find(h => h.id === id);
        return item ? item.visible : true;
    };
    const headerItemRow = (id) => {
        const item = headerItems.value.find(h => h.id === id);
        return item ? item.row : 1;
    };
    const headerItemOrder = (id) => {
        const idx = headerItems.value.findIndex(h => h.id === id);
        return idx >= 0 ? idx : 99;
    };
    const hasRow1Items = computed(() => headerItems.value.some(h => h.visible && h.row === 1));
    const hasRow2Items = computed(() => headerItems.value.some(h => h.visible && h.row === 2));
    const hasRow3Items = computed(() => headerItems.value.some(h => h.visible && h.row === 3));

    // Player rankings helpers
    const getRanking = (physics, mode, category = 'overall') => props.playerRankings.find(r => r.physics === physics && r.mode === mode && r.category === category);
    const hasPlayerRank = computed(() => props.playerRankings.length > 0);

    // Score tooltip (teleported to body to avoid overflow clipping)
    const scoreTooltip = ref(null);
    const scoreTooltipStyle = computed(() => {
        if (!scoreTooltip.value?.el) return {};
        const rect = scoreTooltip.value.el.getBoundingClientRect();
        return {
            position: 'fixed',
            left: rect.left + rect.width / 2 + 'px',
            top: rect.top - 8 + 'px',
            transform: 'translate(-50%, -100%)',
            zIndex: 99999,
        };
    });
    // Rating breakdown visibility (admin or moderator with permission)
    const canViewBreakdown = computed(() => page.props.canViewRatingBreakdown);
    const ratingBreakdownOpen = ref(false);
    const ratingBreakdownData = ref(null);
    const ratingBreakdownLoading = ref(false);
    const ratingBreakdownPhysics = ref('vq3');
    const ratingBreakdownCategory = ref('overall');
    const breakdownSortCol = ref('rank');
    const breakdownSortAsc = ref(true);

    const sortedBreakdown = computed(() => {
        if (!ratingBreakdownData.value?.breakdown) return [];
        const col = breakdownSortCol.value;
        const asc = breakdownSortAsc.value;
        return [...ratingBreakdownData.value.breakdown].sort((a, b) => {
            let va = a[col] ?? 0;
            let vb = b[col] ?? 0;
            if (typeof va === 'string') return asc ? va.localeCompare(vb) : vb.localeCompare(va);
            return asc ? va - vb : vb - va;
        });
    });

    function sortBreakdown(col) {
        if (breakdownSortCol.value === col) {
            breakdownSortAsc.value = !breakdownSortAsc.value;
        } else {
            breakdownSortCol.value = col;
            breakdownSortAsc.value = col === 'rank' || col === 'mapname' || col === 'record_rank';
        }
    }

    async function loadRatingBreakdown() {
        const mddId = props.profile?.id;
        if (!mddId) return;
        ratingBreakdownLoading.value = true;
        try {
            const res = await fetch(`/api/profile/${mddId}/rating-breakdown/${ratingBreakdownPhysics.value}?category=${ratingBreakdownCategory.value}`);
            if (res.ok) {
                ratingBreakdownData.value = await res.json();
            } else {
                ratingBreakdownData.value = null;
            }
        } catch (e) {
            ratingBreakdownData.value = null;
        }
        ratingBreakdownLoading.value = false;
    }

    function toggleRatingBreakdown() {
        ratingBreakdownOpen.value = !ratingBreakdownOpen.value;
        if (ratingBreakdownOpen.value && !ratingBreakdownData.value) {
            loadRatingBreakdown();
        }
    }

    function formatTimeMs(ms) {
        const m = Math.floor(ms / 60000);
        const s = Math.floor((ms % 60000) / 1000);
        const mil = ms % 1000;
        return `${m}:${String(s).padStart(2, '0')}.${String(mil).padStart(3, '0')}`;
    }

    const getDisplayRank = (physics, mode, category = 'overall') => {
        const r = getRanking(physics, mode, category);
        if (!r) return null;
        return (r.active_players_rank && r.active_players_rank > 0) ? r.active_players_rank : r.all_players_rank;
    };
    const vq3RunRank = computed(() => getDisplayRank('vq3', 'run'));
    const cpmRunRank = computed(() => getDisplayRank('cpm', 'run'));
    const rankModes = ['run', 'ctf1', 'ctf2', 'ctf3', 'ctf4', 'ctf5', 'ctf6', 'ctf7'];
    const rankCategories = ['overall', 'strafe', 'rocket', 'plasma', 'grenade', 'bfg', 'slick', 'tele', 'lg'];
    const activeModes = computed(() => rankModes.filter(m => props.playerRankings.some(r => r.mode === m)));
    const activeCategories = computed(() => rankCategories.filter(c => props.playerRankings.some(r => r.category === c)));
    const orderedSections = computed(() => {
        const saved = layout.value.sections;
        if (!saved || !saved.length) return defaultSections;
        // Ensure all sections exist - new ones go to the top
        const missing = [];
        for (const def of defaultSections) {
            if (!saved.find(s => s.id === def.id)) {
                missing.push({ ...def });
            }
        }
        return [...missing, ...saved];
    });

    const showStatBox = (id) => {
        if (!activeStatBoxes.value.includes(id)) return false;
        if (!props.user?.profile_layout && globallyHiddenStatBoxes.value.includes(id)) return false;
        return true;
    };
    const statBoxOrder = (id) => {
        const idx = activeStatBoxes.value.indexOf(id);
        return idx >= 0 ? idx : 99;
    };
    const showSection = (id) => {
        const section = orderedSections.value.find(s => s.id === id);
        const ownerVisible = section ? section.visible : true;
        if (!ownerVisible) return false;
        // Apply visitor's global preferences only on uncustomized profiles
        if (!props.user?.profile_layout && props.visitorGlobalPreferences?.hidden_sections?.includes(id)) {
            return false;
        }
        return true;
    };
    const sectionOrder = (id) => {
        const idx = orderedSections.value.findIndex(s => s.id === id);
        return idx >= 0 ? idx : 99;
    };
    // Detect new sections not yet in user's saved layout (show badge once)
    const dismissedSections = JSON.parse(localStorage.getItem('dismissed_new_sections') || '[]');
    const newSections = computed(() => {
        const saved = layout.value.sections;
        if (!saved || !saved.length) return [];
        return defaultSections
            .filter(def => !saved.find(s => s.id === def.id))
            .filter(def => !dismissedSections.includes(def.id))
            .map(s => s.id);
    });
    const isNewSection = (id) => newSections.value.includes(id);

    // Auto-dismiss new section badges after first render
    if (newSections.value.length > 0) {
        const updated = [...new Set([...dismissedSections, ...newSections.value])];
        localStorage.setItem('dismissed_new_sections', JSON.stringify(updated));
    }
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

    const dateColWidth = computed(() => {
        const fmt = page.props.dateFormat;
        return (fmt === 'Ymd' || fmt === 'dmY') ? 'w-[56px]' : 'w-[50px]';
    });

    // Records and freestyle demos share one panel, so which of the two is
    // showing lives here. Records first, always: a profile is read for its
    // times before anything else.
    const recordsTab = ref('records');

    // Each physics column pages on its own, through the same Pagination the
    // records use, which builds its links off the address bar. So the search
    // has to go in the address bar too, or paging after a search would drop
    // it. Searching also puts both columns back to page one: the page you were
    // on means nothing once the list behind it changes.
    const onFreestyleSearch = (term) => {
        const url = new URL(window.location.href);

        term ? url.searchParams.set('freestyle_search', term) : url.searchParams.delete('freestyle_search');
        url.searchParams.delete('freestyle_vq3_page');
        url.searchParams.delete('freestyle_cpm_page');

        const query = url.searchParams.toString();
        const target = url.pathname + (query ? '?' + query : '');
        const scrollPosition = window.scrollY;

        router.visit(target, {
            only: ['freestyleDemos'],
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                window.history.replaceState(window.history.state, '', target);
                window.scrollTo(0, scrollPosition);
            },
        });
    };

    // About Me
    const aboutMeEditing = ref(false);
    const aboutMeText = ref('');
    const aboutMeSubmitting = ref(false);

    async function submitAboutMe() {
        if (!aboutMeText.value.trim() || aboutMeSubmitting.value) return;
        aboutMeSubmitting.value = true;
        try {
            await axios.post(`/profile/${props.user.id}/about-me`, { content: aboutMeText.value.trim() });
            aboutMeEditing.value = false;
            aboutMeText.value = '';
            router.reload({ only: ['user', 'aboutMePending'] });
        } catch (e) {
            console.error(e);
        }
        aboutMeSubmitting.value = false;
    }

    async function requestDeleteAboutMe() {
        try {
            await axios.post(`/profile/${props.user.id}/about-me/delete`);
            router.reload({ only: ['user', 'aboutMePending'] });
        } catch (e) {
            console.error(e);
        }
    }

    const isOwnProfile = computed(() => page.props.auth?.user?.id === props.user?.id);
    const ownProfileNotLinked = computed(() => isOwnProfile.value && !props.hasProfile);

    // Locked profile (guest / unverified). The stat panels render a stand-in
    // with made-up numbers under a blur; the real ones never reached the
    // browser, so nothing is there to read out of the page.
    const lockedAsGuest = computed(() => props.profileLocked && !page.props.auth?.user);
    const lockedUnverified = computed(() => props.profileLocked && !!page.props.auth?.user);
    const fakeStats = {
        cpm_top3: 42, vq3_top3: 72, cpm_top10: 319, vq3_top10: 268,
        cpm_unique_maps: 1174, vq3_unique_maps: 457, cpm_avg_rank: 29.4, vq3_avg_rank: 12.9,
        cpm_slick: 323, vq3_slick: 183, cpm_jumppad: 284, vq3_jumppad: 94, cpm_teleporter: 463, vq3_teleporter: 159,
        cpm_dominance: 27.1, vq3_dominance: 58.5, longest_streak: 19,
        first_record_date: '2019-09-14', most_active_month: { month: '2020-03' }, weapon_specialist: 'rocket',
        cpm_strafe: 899, vq3_strafe: 359, cpm_fastcaps: 5, vq3_fastcaps: 4, cpm_grenade: 70, vq3_grenade: 34,
        cpm_rocket: 173, vq3_rocket: 61, cpm_plasma: 143, vq3_plasma: 62, cpm_bfg: 52, vq3_bfg: 10,
        cpm_records: 1177, vq3_records: 458,
    };
    const shownProfile = computed(() => props.profileLocked && props.profile ? { ...props.profile, ...fakeStats } : props.profile);
    const fakeActivity = computed(() => {
        const out = {};
        const year = props.activity_year || new Date().getFullYear();
        let seed = 7;
        for (let d = 0; d < 365; d += 1) {
            seed = (seed * 9301 + 49297) % 233280;
            if (seed / 233280 < 0.12) {
                const day = new Date(Date.UTC(year, 0, 1 + d));
                out[day.toISOString().slice(0, 10)] = { vq3: 1 + (seed % 3), cpm: seed % 2 };
            }
        }
        return out;
    });

    const sectionLabels = {
        activity_history: 'Activity History',
        records: 'Records',
        rendered_videos: 'Rendered Videos',
        similar_skill_rivals: 'Similar Skill Rivals',
        competitor_comparison: 'Competitor Comparison',
        known_aliases: 'Known Aliases',
        featured_maplists: 'Featured Maplists',
        map_completionist: 'Map Completionist',
    };

    const statBoxLabels = {
        performance: 'Performance',
        activity: 'Activity',
        record_types: 'Record Types',
        map_features: 'Map Features',
        renders: 'Renders',
    };

    const isGlobalCustomizationActive = computed(() => {
        if (isOwnProfile.value) return false;
        if (!props.hasProfile) return false;
        if (props.user?.profile_layout) return false;
        return true;
    });

    const globallyHiddenSections = computed(() => {
        if (!isGlobalCustomizationActive.value) return [];
        return props.visitorGlobalPreferences?.hidden_sections || [];
    });

    const globallyHiddenStatBoxes = computed(() => {
        if (!isGlobalCustomizationActive.value) return [];
        return props.visitorGlobalPreferences?.hidden_stat_boxes || [];
    });

    const showGlobalStatBox = (id) => {
        return !globallyHiddenStatBoxes.value.includes(id);
    };
    const showQuickSettings = ref(false);
    const quickSettingsPanel = ref(localStorage.getItem('quick_settings_panel') || 'layout');
    const quickSettingsDropdown = ref(false);
    const customizeArrowBtn = ref(null);
    const customizeDropdownStyle = computed(() => {
        if (!customizeArrowBtn.value) return {};
        const rect = customizeArrowBtn.value.getBoundingClientRect();
        return {
            top: `${rect.bottom + 4}px`,
            left: `${rect.right - 224}px`,
        };
    });

    const quickSettingsPanels = [
        { id: 'layout', label: 'Profile Layout' },
        { id: 'effects', label: 'Avatar & Name Effects' },
        { id: 'map_view', label: 'Map Records Default View' },
        { id: 'physics_order', label: 'Physics Column Order' },
        { id: 'effects_intensity', label: 'Effects Intensity' },
    ];

    const selectQuickPanel = (id) => {
        quickSettingsPanel.value = id;
        localStorage.setItem('quick_settings_panel', id);
        quickSettingsDropdown.value = false;
        if (!showQuickSettings.value) showQuickSettings.value = true;
    };

    const currentPanelLabel = computed(() => quickSettingsPanels.find(p => p.id === quickSettingsPanel.value)?.label || 'Profile Layout');

    const profileRoute = (params = {}) => {
        if (props.user?.id) {
            return route('profile.index', { userId: props.user.id, ...params });
        }
        return route('profile.mdd', { userId: props.profile.id, ...params });
    };

    const options = ref({
        'latest': {
            label: 'Latest Records',
            icon: 'clock',
            color: 'text-blue-400'
        },
        'recentlybeaten': {
            label: 'Recently Beaten',
            icon: 'brokenheart',
            color: 'text-red-400'
        },
        'tiedranks': {
            label: 'Tied Ranks',
            icon: 'circle-equal',
            color: 'text-yellow-400'
        },
        'untouchable': {
            label: 'Untouchable WRs',
            icon: 'trophy',
            color: 'text-yellow-400'
        },
        'bestscore': {
            label: 'Best Score',
            icon: 'linegraph',
            color: 'text-purple-400'
        },
        'bestranks': {
            label: 'Best Ranks',
            icon: 'fire',
            color: 'text-green-400'
        },
        'besttimes': {
            label: 'Best Times',
            icon: 'bolt',
            color: 'text-green-400'
        },
        'worstranks': {
            label: 'Worst Ranks',
            icon: 'trending-down',
            color: 'text-orange-400'
        },
        'worsttimes': {
            label: 'Worst Times',
            icon: 'hourglass',
            color: 'text-orange-400'
        },
    });

    const stats = [
        {
            label: 'Strafe Records',
            value: 'strafe_records',
            icon: 'strafe',
            color: 'text-orange-500',
            type: 'imgsvg'
        },
        {
            label: 'Fastcaps Records',
            value: 'ctf_records',
            icon: 'flag',
            color: 'text-cyan-500',
            type: 'svg'
        },
        {
            label: 'Machinegun Records',
            value: 'mg_records',
            icon: 'mg',
            type: 'item'
        },
        {
            label: 'Shotgun Records',
            value: 'sg_records',
            icon: 'sg',
            type: 'item'
        },
        {
            label: 'Grenade Records',
            value: 'grenade_records',
            icon: 'gl',
            type: 'item'
        },
        {
            label: 'Rocket Records',
            value: 'rocket_records',
            icon: 'rl',
            type: 'item'
        },
        {
            label: 'Lightning Records',
            value: 'lg_records',
            icon: 'lg',
            type: 'item'
        },
        {
            label: 'Railgun Records',
            value: 'rg_records',
            icon: 'rg',
            type: 'item'
        },
        {
            label: 'Plasma Records',
            value: 'plasma_records',
            icon: 'pg',
            type: 'item'
        },
        {
            label: 'BFG Records',
            value: 'bfg_records',
            icon: 'bfg',
            type: 'item'
        },
    ];

    const selectedOption = ref(props.type || 'latest');
    const loading = ref('');

    // Direct Competitor Comparison
    const competitorSearch = ref('');
    const competitorSearchResults = ref([]);
    const showCompetitorDropdown = ref(false);
    const selectedCompetitor = ref(null);
    const vq3Comparison = ref([]);
    const cpmComparison = ref([]);
    const loadingComparison = ref(false);
    const vq3Page = ref(1);
    const cpmPage = ref(1);
    const perPage = 10;

    // Computed properties for pagination
    const vq3ComparisonPaginated = computed(() => {
        const start = (vq3Page.value - 1) * perPage;
        const end = start + perPage;
        return vq3Comparison.value.slice(start, end);
    });

    const cpmComparisonPaginated = computed(() => {
        const start = (cpmPage.value - 1) * perPage;
        const end = start + perPage;
        return cpmComparison.value.slice(start, end);
    });

    const vq3TotalPages = computed(() => Math.ceil(vq3Comparison.value.length / perPage));
    const cpmTotalPages = computed(() => Math.ceil(cpmComparison.value.length / perPage));

    const mode = ref('all');
    const recordsSearch = ref(props.search || '');
    let recordsSearchTimeout = null;

    const selectOption = (option) => {
        if (loading.value === option) {
            return;
        }

        router.reload({
            data: {
                type: option,
                mode: mode.value,
                search: recordsSearch.value || undefined,
                vq3_page: 1,
                cpm_page: 1
            },
            onStart: () => {
                loading.value = option;
            },
            onFinish: () => {
                loading.value = '';
            }
        })
    }

    const sortByMode = (newMode) => {
        mode.value = newMode;
        router.reload({
            data: {
                type: props.type,
                mode: newMode,
                search: recordsSearch.value || undefined,
                vq3_page: 1,
                cpm_page: 1
            }
        });
    }

    const onRecordsSearchInput = () => {
        if (recordsSearchTimeout) clearTimeout(recordsSearchTimeout);
        recordsSearchTimeout = setTimeout(() => {
            router.reload({
                data: {
                    type: props.type,
                    mode: mode.value,
                    search: recordsSearch.value || undefined,
                    vq3_page: 1,
                    cpm_page: 1,
                },
                only: ['vq3Records', 'cpmRecords', 'search'],
                preserveState: true,
                preserveScroll: true,
            });
        }, 350);
    };

    const clearRecordsSearch = () => {
        recordsSearch.value = '';
        onRecordsSearchInput();
    };


    // Direct Competitor Comparison Methods
    let searchTimeout = null;
    const searchCompetitors = () => {
        if (searchTimeout) clearTimeout(searchTimeout);

        if (competitorSearch.value.length < 2) {
            competitorSearchResults.value = [];
            return;
        }

        searchTimeout = setTimeout(async () => {
            try {
                const response = await fetch(`/api/search-players?q=${encodeURIComponent(competitorSearch.value)}`);
                const data = await response.json();
                competitorSearchResults.value = data;
            } catch (error) {
                console.error('Error searching players:', error);
                competitorSearchResults.value = [];
            }
        }, 300);
    };

    const selectCompetitor = async (player) => {
        selectedCompetitor.value = player;
        showCompetitorDropdown.value = false;
        competitorSearch.value = player.plain_name;
        loadingComparison.value = true;

        // Reset pagination
        vq3Page.value = 1;
        cpmPage.value = 1;

        // Use player.user.id if user exists, otherwise use player.id directly
        const rivalUserId = player.user?.id || player.id;

        try {
            // Fetch VQ3 comparison
            const profileId = props.user?.id || props.profile?.id;
            const vq3Response = await fetch(`/api/profile/${profileId}/compare/${rivalUserId}?physics=vq3`);
            vq3Comparison.value = await vq3Response.json();

            // Fetch CPM comparison
            const cpmResponse = await fetch(`/api/profile/${profileId}/compare/${rivalUserId}?physics=cpm`);
            cpmComparison.value = await cpmResponse.json();
        } catch (error) {
            console.error('Error loading comparison:', error);
            vq3Comparison.value = [];
            cpmComparison.value = [];
        } finally {
            loadingComparison.value = false;
        }
    };

    const clearCompetitor = () => {
        selectedCompetitor.value = null;
        competitorSearch.value = '';
        competitorSearchResults.value = [];
        vq3Comparison.value = [];
        cpmComparison.value = [];
        vq3Page.value = 1;
        cpmPage.value = 1;
    };

    watch(() => props.type, (newVal) => {
        if (options.value[newVal]) {
            selectedOption.value = newVal;
        } else {
            selectedOption.value = 'latest';
        }
    });

    // Map Completionist progress calculation
    const completionistMode = ref('all');
    const localUnplayedMaps = ref(null);
    const localTotalMaps = ref(null);
    const localPlayedCount = ref(null);

    const currentUnplayedMaps = computed(() => localUnplayedMaps.value ?? props.unplayed_maps);
    const currentTotalMaps = computed(() => localTotalMaps.value ?? props.total_maps);

    const playedMapsCount = computed(() => {
        if (localPlayedCount.value !== null) return localPlayedCount.value;
        return props.played_maps_count || (props.total_maps - (props.unplayed_maps?.total || 0));
    });

    const completionPercentage = computed(() => {
        if (currentTotalMaps.value === 0) return 0;
        return ((playedMapsCount.value / currentTotalMaps.value) * 100).toFixed(3);
    });

    const sortCompletionistMode = async (newMode) => {
        completionistMode.value = newMode;
        try {
            const url = profileRoute({ completionist_mode: newMode, unplayed_page: 1 });
            const res = await fetch(url, {
                headers: {
                    'X-Inertia': 'true',
                    'X-Inertia-Partial-Data': 'unplayed_maps,total_maps,played_maps_count',
                    'X-Inertia-Partial-Component': 'Profile',
                    'Accept': 'application/json',
                }
            });
            const data = await res.json();
            console.log('Completionist response:', newMode, 'played:', data.props?.played_maps_count, 'unplayed total:', data.props?.unplayed_maps?.total);
            if (data.props) {
                localUnplayedMaps.value = data.props.unplayed_maps ?? localUnplayedMaps.value;
                localTotalMaps.value = data.props.total_maps ?? localTotalMaps.value;
                localPlayedCount.value = data.props.played_maps_count ?? localPlayedCount.value;
            }
        } catch (e) {
            console.error('Error fetching completionist data:', e);
        }
    };

    const fetchCompletionistPage = async (page) => {
        try {
            const url = profileRoute({ completionist_mode: completionistMode.value, unplayed_page: page });
            const res = await fetch(url, {
                headers: {
                    'X-Inertia': 'true',
                    'X-Inertia-Partial-Data': 'unplayed_maps,total_maps,played_maps_count',
                    'X-Inertia-Partial-Component': 'Profile',
                    'Accept': 'application/json',
                }
            });
            const data = await res.json();
            if (data.props) {
                localUnplayedMaps.value = data.props.unplayed_maps ?? localUnplayedMaps.value;
                localTotalMaps.value = data.props.total_maps ?? localTotalMaps.value;
                localPlayedCount.value = data.props.played_maps_count ?? localPlayedCount.value;
            }
        } catch (e) {
            console.error('Error fetching completionist page:', e);
        }
    };

    // Aliases collapsed by default
    const aliasesExpanded = ref(false);

    // Alias reporting
    const showReportModal = ref(false);
    const reportingAlias = ref(null);
    const reportReason = ref('');
    const reportingInProgress = ref(false);

    const reportAlias = (alias) => {
        reportingAlias.value = alias;
        showReportModal.value = true;
    };

    const closeReportModal = () => {
        showReportModal.value = false;
        reportingAlias.value = null;
        reportReason.value = '';
    };

    const submitAliasReport = () => {
        if (!reportReason.value.trim()) {
            return;
        }

        reportingInProgress.value = true;

        router.post(route('aliases.report', reportingAlias.value.id || reportingAlias.value.alias), {
            reason: reportReason.value,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                closeReportModal();
            },
            onFinish: () => {
                reportingInProgress.value = false;
            }
        });
    };

    // Alias suggestions
    const showSuggestionModal = ref(false);
    const suggestedAlias = ref('');
    const suggestionNote = ref('');
    const suggestionInProgress = ref(false);

    const openSuggestionModal = () => {
        showSuggestionModal.value = true;
    };

    const closeSuggestionModal = () => {
        showSuggestionModal.value = false;
        suggestedAlias.value = '';
        suggestionNote.value = '';
    };

    const submitAliasSuggestion = () => {
        if (!suggestedAlias.value.trim()) {
            return;
        }

        suggestionInProgress.value = true;

        router.post(route('alias-suggestions.store', props.user?.id || props.profile?.id), {
            alias: suggestedAlias.value,
            note: suggestionNote.value,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                closeSuggestionModal();
            },
            onFinish: () => {
                suggestionInProgress.value = false;
            }
        });
    };

    const approveSuggestion = (suggestion) => {
        if (!confirm(`Add alias "${suggestion.alias}" to your account?`)) {
            return;
        }

        router.post(route('alias-suggestions.approve', suggestion.id), {}, {
            preserveScroll: true,
        });
    };

    const rejectSuggestion = (suggestion) => {
        if (!confirm(`Reject alias suggestion "${suggestion.alias}"?`)) {
            return;
        }

        router.post(route('alias-suggestions.reject', suggestion.id), {}, {
            preserveScroll: true,
        });
    };

    // Demo render/flag state for profile records
    const renderStates = ref({});
    const showFlagModal = ref(false);
    const flagRecordId = ref(null);
    const flagDemoId = ref(null);

    const getRenderedVideo = (record) => {
        // Same status-priority pick as MapRecord.vue - a newer 'failed'
        // attempt must not hide an older 'completed' one in the badge.
        const videos = record.rendered_videos;
        if (videos && videos.length > 0) {
            if (videos.length === 1) return videos[0];
            const priority = { completed: 0, uploading: 1, rendering: 2, pending: 3, failed: 4 };
            return [...videos].sort((a, b) => (priority[a.status] ?? 99) - (priority[b.status] ?? 99))[0];
        }
        return record.uploaded_demos?.[0]?.rendered_video || null;
    };

    const canRequestRender = (record) => {
        if (!page.props.auth?.user) return false;
        if (getRenderedVideo(record)) return false;
        if (renderStates.value[record.id]?.requested) return false;
        return record.uploaded_demos?.length > 0;
    };

    const confirmRender = async (record) => {
        const state = renderStates.value[record.id] || {};
        if (state.requesting) return;
        renderStates.value[record.id] = { ...state, requesting: true, error: null };
        try {
            const demoId = record.uploaded_demos[0].id;
            await axios.post('/render/request', { record_id: record.id, demo_id: demoId });
            renderStates.value[record.id] = { ...state, requesting: false, requested: true };
        } catch (e) {
            renderStates.value[record.id] = { ...state, requesting: false, error: e.response?.data?.error || 'Failed' };
        }
    };

    // Flagging a record asks for nothing but an account; flagging a demo
    // still needs the record count, so the demo is only attached for users
    // who may actually flag one - otherwise the button would offer something
    // the server refuses.
    const canFlagRecord = computed(() => !!page.props.auth?.user);

    const openFlagModal = (record) => {
        flagRecordId.value = record.id;
        flagDemoId.value = page.props.canReportDemos
            ? (record.uploaded_demos?.[0]?.id || null)
            : null;
        showFlagModal.value = true;
    };

    const toggleYoutube = (record) => {
        const video = getRenderedVideo(record);
        if (video?.youtube_video_id) {
            window.open(`https://www.youtube.com/watch?v=${video.youtube_video_id}`, '_blank');
        }
    };

</script>

<template>
    <div>
        <Head :title="$t('Profile')" />

        <!-- Profile Header with Background -->
        <div class="relative h-[280px] z-30">
            <!-- Background Image -->
            <div v-if="user?.profile_background_path" class="absolute inset-0 overflow-hidden">
                <div class="absolute inset-0 flex justify-center">
                    <div class="relative w-full max-w-[1920px] h-full bg-top bg-no-repeat" :style="`background-image: url('/storage/${user.profile_background_path}'); background-size: 1920px auto;`">
                        <!-- Fade left edge of image -->
                        <div class="absolute left-0 top-0 bottom-0 w-80 bg-gradient-to-r from-gray-900 to-transparent"></div>
                        <!-- Fade right edge of image -->
                        <div class="absolute right-0 top-0 bottom-0 w-80 bg-gradient-to-l from-gray-900 to-transparent"></div>
                    </div>
                </div>
                <!-- Fade to transparent at bottom -->
                <div class="absolute inset-0 bg-gradient-to-b from-transparent from-0% via-transparent via-60% to-gray-900 to-100%"></div>
                <!-- Dark overlay on top -->
                <div class="absolute inset-0 bg-gradient-to-b from-black/70 via-black/40 to-transparent"></div>
            </div>

            <!-- Customize button (top-right, aligned to content max-width) -->
            <div v-if="isOwnProfile && !ownProfileNotLinked" class="absolute top-4 right-0 left-0 z-20 pointer-events-none">
            <div class="max-w-8xl mx-auto px-4 lg:px-8 flex justify-end">
            <div class="flex items-center shadow-xl pointer-events-auto">
                <button @click="showQuickSettings = !showQuickSettings" :class="showQuickSettings ? 'bg-blue-600/50 border-blue-400/50 text-white' : 'bg-black/50 border-white/20 hover:border-white/30 hover:bg-black/60 text-gray-400 hover:text-white'" class="px-3 py-1.5 rounded-l-lg transition-all backdrop-blur-sm flex items-center gap-1.5 border border-r-0">
                    <svg class="w-4 h-4 transition" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
                    <span class="text-xs font-bold transition">{{ $t('CUSTOMIZE') }}</span>
                </button>
                <button ref="customizeArrowBtn" @click="quickSettingsDropdown = !quickSettingsDropdown" :class="showQuickSettings ? 'bg-blue-600/50 border-blue-400/50 text-white' : 'bg-black/50 border-white/20 hover:border-white/30 hover:bg-black/60 text-gray-400 hover:text-white'" class="px-1.5 py-1.5 rounded-r-lg transition-all backdrop-blur-sm border">
                    <svg class="w-3.5 h-3.5 transition-transform" :class="quickSettingsDropdown ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
                </button>
                <Teleport to="body">
                    <div v-if="quickSettingsDropdown" @click="quickSettingsDropdown = false" class="fixed inset-0 z-[998]"></div>
                    <div v-if="quickSettingsDropdown" ref="customizeDropdownEl" class="fixed w-56 bg-gray-900/80 backdrop-blur-xl border border-white/10 rounded-lg overflow-hidden z-[999] shadow-2xl" :style="customizeDropdownStyle">
                        <button
                            v-for="panel in quickSettingsPanels"
                            :key="panel.id"
                            @click="selectQuickPanel(panel.id)"
                            :class="quickSettingsPanel === panel.id ? 'bg-blue-600/30 text-blue-300' : 'text-gray-300 hover:bg-white/10'"
                            class="w-full px-4 py-2 text-left text-sm transition-colors flex items-center gap-2"
                        >
                            <svg v-if="quickSettingsPanel === panel.id" class="w-3 h-3 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" /></svg>
                            <span :class="quickSettingsPanel !== panel.id ? 'ml-5' : ''">{{ panel.label }}</span>
                        </button>
                    </div>
                </Teleport>
            </div>
            </div>
            </div>

            <!-- Profile Content - Compact Design -->
            <div class="relative h-full flex items-center justify-center px-4 pt-2">
                <div class="flex items-center gap-6">
                    <!-- Avatar with Ring -->
                    <div class="relative group flex-shrink-0">
                        <!-- Animated Ring -->
                        <div class="absolute -inset-2 bg-gradient-to-r from-purple-600 via-pink-600 to-blue-600 rounded-full opacity-75 group-hover:opacity-100 blur-lg transition duration-500 group-hover:duration-200 animate-pulse"></div>

                        <!-- Avatar -->
                        <div class="relative bg-black/20 p-1.5 rounded-full">
                            <div :class="'avatar-effect-' + (user?.avatar_effect || 'none')" :style="`--effect-color: ${user?.color || '#ffffff'}; --border-color: ${user?.avatar_border_color || '#6b7280'}; --orbit-radius: 52px`">
                                <img class="h-24 w-24 rounded-full border-4 object-cover relative" :style="`border-color: ${user?.avatar_border_color || '#6b7280'}`" :src="user?.profile_photo_path ? '/storage/' + user.profile_photo_path : '/images/null.jpg'" :alt="user?.name ?? profile.name">
                            </div>
                        </div>
                    </div>

                    <!-- Info Section -->
                    <div class="flex flex-col gap-2">
                        <!-- Country Flag + Name -->
                        <div class="flex items-center gap-3">
                            <div class="px-1">
                                <img onerror="this.src='/images/flags/_404.png'" :src="`/images/flags/${user?.country ?? profile.country}.png`" :title="user?.country ?? profile.country" class="w-8 h-5">
                            </div>
                            <div :class="'name-effect-' + (user?.name_effect || 'none')" :style="`--effect-color: ${user?.color || '#ffffff'}`" class="text-4xl font-black text-white drop-shadow-[0_0_30px_rgba(0,0,0,0.8)] cursor-default truncate max-w-[600px]" style="text-shadow: 0 0 40px rgba(0,0,0,0.9), 0 4px 20px rgba(0,0,0,0.8);" :title="(user?.name ?? profile.name).replace(/\^\w/g, '')" v-html="q3tohtml(user?.name ?? profile.name)"></div>
                            <div v-if="user?.mdd_name && user.mdd_name !== user.name" class="text-sm text-gray-300 px-2 py-0.5 rounded bg-black/40 backdrop-blur-sm" style="text-shadow: 0 2px 8px rgba(0,0,0,0.9);">{{ $t('mDd:') }} <span v-html="q3tohtml(user.mdd_name)"></span></div>
                            <!-- LIVE Badge -->
                            <a v-if="user?.is_live && user?.twitch_id" :href="`https://twitch.tv/${user.twitch_name}`" target="_blank" class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-red-600/90 border-2 border-red-400 hover:bg-red-500/90 hover:border-red-300 transition-all hover:scale-105 shadow-xl animate-pulse">
                                <div class="w-2 h-2 rounded-full bg-white animate-ping absolute"></div>
                                <div class="w-2 h-2 rounded-full bg-white"></div>
                                <span class="text-sm font-black text-white uppercase tracking-wider">{{ $t('LIVE') }}</span>
                            </a>
                        </div>

                        <!-- About Me -->
                        <div v-if="user?.about_me || isOwnProfile || $page.props.auth?.user" class="relative group/aboutme">
                            <!-- Has about me text -->
                            <div v-if="user?.about_me && !aboutMeEditing" class="flex items-center gap-2">
                                <div class="text-sm text-gray-400 truncate max-w-md" style="text-shadow: 0 2px 8px rgba(0,0,0,0.9);">{{ user.about_me }}</div>
                                <div v-if="isOwnProfile" class="flex gap-1 shrink-0 opacity-0 group-hover/aboutme:opacity-100 transition-opacity">
                                    <button @click="aboutMeEditing = true; aboutMeText = user.about_me"
                                        class="text-[10px] text-blue-400/60 hover:text-blue-300 transition-colors">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                    </button>
                                    <button @click="requestDeleteAboutMe"
                                        class="text-[10px] text-red-400/60 hover:text-red-300 transition-colors">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                    </button>
                                </div>
                                <!-- Full text hover popup -->
                                <div v-if="user.about_me.length > 60"
                                    class="absolute top-full left-0 mt-1 w-80 bg-gray-900/95 border border-white/15 rounded-xl shadow-2xl z-50 backdrop-blur-sm p-3 hidden group-hover/aboutme:block">
                                    <div class="text-sm text-gray-200 leading-relaxed whitespace-pre-wrap">{{ user.about_me }}</div>
                                </div>
                            </div>
                            <!-- No about me - show action -->
                            <div v-else-if="!aboutMeEditing">
                                <div v-if="aboutMePending" class="text-[10px] text-yellow-500/80 italic">{{ $t('About me pending review...') }}</div>
                                <button v-else-if="$page.props.auth?.user"
                                    @click="aboutMeEditing = true; aboutMeText = ''"
                                    class="text-xs text-gray-600 hover:text-amber-400 transition-colors">
                                    {{ isOwnProfile ? $t('+ Add about me') : $t('+ Suggest about me') }}
                                </button>
                            </div>
                            <!-- Edit form (inline) -->
                            <div v-if="aboutMeEditing" class="w-full max-w-md">
                                <textarea v-model="aboutMeText"
                                    class="w-full bg-black/60 border border-gray-700 rounded-lg p-2 text-sm text-white placeholder-gray-600 focus:border-amber-500/50 focus:outline-none resize-none backdrop-blur-sm"
                                    rows="3"
                                    maxlength="500"
                                    :placeholder="isOwnProfile ? 'Write something about yourself...' : 'Write something nice about this player...'"></textarea>
                                <div class="flex items-center justify-between mt-1">
                                    <span class="text-[10px] text-gray-600">{{ aboutMeText.length }}/500</span>
                                    <div class="flex gap-2">
                                        <button @click="aboutMeEditing = false"
                                            class="px-2 py-0.5 text-[10px] text-gray-400 hover:text-gray-300">{{ $t('Cancel') }}</button>
                                        <button @click="submitAboutMe" :disabled="!aboutMeText.trim() || aboutMeSubmitting"
                                            class="px-2 py-0.5 bg-amber-600 hover:bg-amber-500 disabled:bg-gray-700 disabled:text-gray-500 text-white text-[10px] font-bold rounded transition-colors">
                                            {{ aboutMeSubmitting ? '...' : $t('Submit for Review') }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Header Row 1 -->
                        <div v-if="hasRow1Items" class="flex items-center gap-3 flex-wrap">
                            <!-- Admin Badge -->
                            <div v-if="user?.admin && showHeaderItem('badge_admin') && headerItemRow('badge_admin') === 1" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-red-950/70 border border-red-400/50 shadow-xl backdrop-blur-sm" :style="{ order: headerItemOrder('badge_admin') }">
                                <img src="/images/svg/badge-moderator.svg" class="w-4 h-4" :alt="$t('Admin')">
                                <span class="text-xs font-bold text-red-300 uppercase tracking-wider">{{ $t('Admin') }}</span>
                            </div>
                            <!-- Moderator Badge -->
                            <div v-if="user?.is_moderator && !user?.admin && showHeaderItem('badge_moderator') && headerItemRow('badge_moderator') === 1" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-950/70 border border-emerald-400/50 shadow-xl backdrop-blur-sm" :style="{ order: headerItemOrder('badge_moderator') }">
                                <img src="/images/svg/badge-moderator.svg" class="w-4 h-4" :alt="$t('Moderator')">
                                <span class="text-xs font-bold text-emerald-300 uppercase tracking-wider">{{ $t('Moderator') }}</span>
                            </div>
                            <!-- Donor Badge -->
                            <div v-if="donorTier && showHeaderItem('badge_donor') && headerItemRow('badge_donor') === 1" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg shadow-xl backdrop-blur-sm group relative" :style="{ order: headerItemOrder('badge_donor') }"
                                :class="{ 'bg-pink-950/70 border border-pink-400/50': donorTier === 'supporter', 'bg-amber-950/70 border border-amber-400/50': donorTier === 'gold', 'bg-cyan-950/70 border border-cyan-400/50': donorTier === 'diamond' }">
                                <img :src="donorTier === 'diamond' ? '/images/svg/badge-donor-diamond.svg' : donorTier === 'gold' ? '/images/svg/badge-donor-gold.svg' : '/images/svg/badge-donor.svg'" class="w-4 h-4" :alt="$t('Supporter')">
                                <span class="text-xs font-bold uppercase tracking-wider" :class="{ 'text-pink-300': donorTier === 'supporter', 'text-amber-300': donorTier === 'gold', 'text-cyan-300': donorTier === 'diamond' }">
                                    {{ $t(':tier Supporter', { tier: donorTier === 'diamond' ? $t('Diamond') : donorTier === 'gold' ? $t('Gold') : '' }) }}
                                </span>
                                <div v-if="Object.keys(donationTotal).length" class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-3 py-1.5 rounded-lg bg-black/90 border border-white/10 text-xs text-gray-300 whitespace-nowrap opacity-0 group-hover:opacity-100 transition pointer-events-none">
                                    {{ $t('Donated :amount', { amount: Object.entries(donationTotal).map(([c, a]) => `${parseFloat(a).toFixed(0)} ${c}`).join(' + ') }) }}
                                </div>
                            </div>
                            <!-- Community/Defragger Badge -->
                            <Link v-if="communityTier && showHeaderItem('badge_community') && headerItemRow('badge_community') === 1" :href="route('community')" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg shadow-xl backdrop-blur-sm group relative border hover:scale-105 transition-transform" :style="{ order: headerItemOrder('badge_community'), borderColor: communityTier.color + '90', backgroundColor: communityTier.color + '35' }">
                                <img src="/images/svg/badge-defragger.png" class="w-4 h-4" :style="{ filter: `drop-shadow(0 0 3px ${communityTier.color})` }" :alt="$t('Defragger')">
                                <span class="text-xs font-bold uppercase tracking-wider" :style="{ color: communityTier.color }">{{ communityTier.name }}</span>
                                <span class="text-xs font-black" :style="{ color: communityTier.color }">{{ communityTier.score }}</span>
                                <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-3 py-2 rounded-lg bg-black/90 border border-white/10 text-xs text-gray-300 whitespace-nowrap opacity-0 group-hover:opacity-100 transition pointer-events-none shadow-xl">
                                    <div class="font-bold text-white mb-1">{{ $t('Defragger Score') }}</div>
                                    <div class="text-gray-400">{{ $t('Community contribution score:') }} <span class="text-white font-semibold">{{ $t(':count pts', { count: communityTier.score }) }}</span></div>
                                    <div class="text-gray-400">{{ $t('Rank:') }} <span class="text-white font-semibold">#{{ communityTier.rank }}</span></div>
                                    <div class="text-gray-500 mt-1 text-[10px]">{{ $t('Click for more info') }}</div>
                                </div>
                            </Link>
                            <!-- Tagger Badge -->
                            <div v-if="tagCount > 0 && showHeaderItem('badge_tagger') && headerItemRow('badge_tagger') === 1" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-950/70 border border-amber-400/50 shadow-xl backdrop-blur-sm group relative" :style="{ order: headerItemOrder('badge_tagger') }">
                                <img src="/images/svg/badge-tagger.svg" class="w-4 h-4" :alt="$t('Tagger')">
                                <span class="text-xs font-bold text-amber-300 uppercase tracking-wider">{{ $t('Tagger') }}</span>
                                <span class="text-xs font-black text-amber-400">{{ tagCount }}</span>
                                <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-3 py-1.5 rounded-lg bg-black/90 border border-white/10 text-xs text-gray-300 whitespace-nowrap opacity-0 group-hover:opacity-100 transition pointer-events-none">
                                    {{ $tc('Contributed :count tag to maps and maplists|Contributed :count tags to maps and maplists', tagCount) }}
                                </div>
                            </div>
                            <!-- Assigner Badge -->
                            <div v-if="(assignedDemoCounts.offline + assignedDemoCounts.online) > 0 && showHeaderItem('badge_assigner') && headerItemRow('badge_assigner') === 1" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-lime-950/70 border border-lime-400/50 shadow-xl backdrop-blur-sm group relative" :style="{ order: headerItemOrder('badge_assigner') }">
                                <svg class="w-4 h-4 text-lime-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M1 8a2 2 0 0 1 2-2h.93a2 2 0 0 0 1.664-.89l.812-1.22A2 2 0 0 1 8.07 3h3.86a2 2 0 0 1 1.664.89l.812 1.22A2 2 0 0 0 16.07 6H17a2 2 0 0 1 2 2v7a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8Zm13.5 3a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0ZM10 14a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z" clip-rule="evenodd" /></svg>
                                <span class="text-xs font-bold text-lime-300 uppercase tracking-wider">{{ $t('Assigner') }}</span>
                                <span class="text-xs font-black text-lime-400">{{ assignedDemoCounts.offline + assignedDemoCounts.online }}</span>
                                <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-3 py-1.5 rounded-lg bg-black/90 border border-white/10 text-xs text-gray-300 whitespace-nowrap opacity-0 group-hover:opacity-100 transition pointer-events-none">
                                    {{ $tc('Manually assigned :count demo to records|Manually assigned :count demos to records', assignedDemoCounts.offline + assignedDemoCounts.online) }}
                                </div>
                            </div>
                            <!-- Player Rank (overall run rank, VQ3 + CPM) -->
                            <div v-if="hasPlayerRank && showHeaderItem('player_rank') && headerItemRow('player_rank') === 1" class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-orange-950/70 border border-orange-400/50 shadow-xl backdrop-blur-sm group relative" :style="{ order: headerItemOrder('player_rank') }">
                                <svg class="w-4 h-4 text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7.5 7.5 3m0 0L12 7.5M7.5 3v13.5m13.5-6L16.5 21m0 0L12 16.5m4.5 4.5V7.5" /></svg>
                                <span class="text-xs font-bold text-orange-300 uppercase tracking-wider">{{ $t('Rank') }}</span>
                                <span v-if="vq3RunRank" class="text-xs font-semibold text-blue-300 uppercase tracking-wider">VQ3</span>
                                <span v-if="vq3RunRank" class="text-sm font-black text-orange-400">#{{ vq3RunRank }}</span>
                                <span v-if="cpmRunRank && vq3RunRank" class="text-gray-600">|</span>
                                <span v-if="cpmRunRank" class="text-xs font-semibold text-purple-300 uppercase tracking-wider">CPM</span>
                                <span v-if="cpmRunRank" class="text-sm font-black text-orange-400">#{{ cpmRunRank }}</span>
                                <div class="absolute top-full left-1/2 -translate-x-1/2 mt-2 px-4 py-3 rounded-lg bg-black/95 border border-white/10 text-xs text-gray-300 opacity-0 group-hover:opacity-100 transition pointer-events-none shadow-2xl z-[100] min-w-[340px]">
                                    <template v-for="mode in activeModes" :key="mode">
                                        <div class="font-bold text-white mb-1.5" :class="mode !== activeModes[0] ? 'mt-3 pt-2 border-t border-white/10' : ''">{{ mode.toUpperCase() }}</div>
                                        <div class="grid grid-cols-[1fr_auto_auto_auto_auto] gap-x-3 gap-y-0.5">
                                            <div class="text-gray-500 font-semibold text-[10px] uppercase">{{ $t('Category') }}</div>
                                            <div class="text-blue-400 font-semibold text-[10px] uppercase text-right">VQ3</div>
                                            <div class="text-blue-400/40 font-semibold text-[10px] uppercase text-right">{{ $t('All') }}</div>
                                            <div class="text-purple-400 font-semibold text-[10px] uppercase text-right">CPM</div>
                                            <div class="text-purple-400/40 font-semibold text-[10px] uppercase text-right">{{ $t('All') }}</div>
                                            <template v-for="cat in activeCategories.filter(c => getRanking('vq3', mode, c) || getRanking('cpm', mode, c))" :key="mode+'-'+cat">
                                                <div class="text-gray-300" :class="cat === 'overall' ? 'font-semibold' : ''">{{ cat.charAt(0).toUpperCase() + cat.slice(1) }}</div>
                                                <div class="text-right font-semibold" :class="getDisplayRank('vq3', mode, cat) ? 'text-white' : 'text-gray-600'">
                                                    {{ getDisplayRank('vq3', mode, cat) ? '#' + getDisplayRank('vq3', mode, cat) : '-' }}
                                                </div>
                                                <div class="text-right text-gray-500">
                                                    {{ getRanking('vq3', mode, cat)?.all_players_rank ? '#' + getRanking('vq3', mode, cat).all_players_rank : '-' }}
                                                </div>
                                                <div class="text-right font-semibold" :class="getDisplayRank('cpm', mode, cat) ? 'text-white' : 'text-gray-600'">
                                                    {{ getDisplayRank('cpm', mode, cat) ? '#' + getDisplayRank('cpm', mode, cat) : '-' }}
                                                </div>
                                                <div class="text-right text-gray-500">
                                                    {{ getRanking('cpm', mode, cat)?.all_players_rank ? '#' + getRanking('cpm', mode, cat).all_players_rank : '-' }}
                                                </div>
                                            </template>
                                        </div>
                                    </template>
                                </div>
                            </div>
                            <!-- Community Rank -->
                            <Link v-if="communityTier && showHeaderItem('community_rank') && headerItemRow('community_rank') === 1" :href="route('community')" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-950/70 border border-emerald-400/50 shadow-xl backdrop-blur-sm group relative hover:scale-105 transition-transform" :style="{ order: headerItemOrder('community_rank') }">
                                <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" /></svg>
                                <span class="text-xs font-bold text-emerald-300 uppercase tracking-wider">{{ $t('Rank') }}</span>
                                <span class="text-sm font-black text-emerald-400">#{{ communityTier.rank }}</span>
                                <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-3 py-2 rounded-lg bg-black/90 border border-white/10 text-xs text-gray-300 whitespace-nowrap opacity-0 group-hover:opacity-100 transition pointer-events-none shadow-xl">
                                    <div class="font-bold text-white mb-1">{{ $t('Community Rank') }}</div>
                                    <div class="text-gray-400">{{ $t('Score:') }} <span class="text-white font-semibold">{{ $t(':count pts', { count: communityTier.score }) }}</span></div>
                                    <div class="text-gray-400">{{ $t('Tier:') }} <span class="font-semibold" :style="{ color: communityTier.color }">{{ communityTier.name }}</span></div>
                                    <div class="text-gray-500 mt-1 text-[10px]">{{ $t('Click for leaderboard') }}</div>
                                </div>
                            </Link>
                            <!-- Clan -->
                            <Link v-if="user?.clan && showHeaderItem('clan') && headerItemRow('clan') === 1" :href="route('clans.show', user.clan.id)" class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-blue-950/70 border border-blue-400/50 hover:border-blue-300/60 hover:bg-blue-900/70 transition-all hover:scale-105 shadow-xl backdrop-blur-sm group" :style="{ order: headerItemOrder('clan') }">
                                <div class="w-2 h-2 rounded-full bg-blue-400 animate-pulse shadow-lg shadow-blue-500/50"></div>
                                <span class="text-xs font-bold text-blue-300 uppercase tracking-wider">{{ $t('Clan') }}</span>
                                <span class="text-sm font-black text-white group-hover:text-blue-100 transition" v-html="q3tohtml(user.clan.name)"></span>
                            </Link>
                            <!-- WR Counters -->
                            <template v-if="showHeaderItem('wr_counters') && headerItemRow('wr_counters') === 1">
                                <div class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-purple-950/70 border border-purple-400/40 shadow-xl backdrop-blur-sm" :style="{ order: headerItemOrder('wr_counters') }">
                                    <span class="text-xs text-purple-300 font-semibold uppercase tracking-wider" style="text-shadow: 0 1px 4px rgba(0,0,0,0.8);">CPM</span>
                                    <span class="text-sm font-black text-purple-400" style="text-shadow: 0 1px 4px rgba(0,0,0,0.8);">{{ cpm_world_records }}</span>
                                    <svg class="w-3.5 h-3.5 text-purple-400" viewBox="0 0 20 20" fill="currentColor"><use href="/images/svg/icons.svg#icon-trophy"></use></svg>
                                </div>
                                <div class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-blue-950/70 border border-blue-400/40 shadow-xl backdrop-blur-sm" :style="{ order: headerItemOrder('wr_counters') }">
                                    <span class="text-xs text-blue-300 font-semibold uppercase tracking-wider" style="text-shadow: 0 1px 4px rgba(0,0,0,0.8);">VQ3</span>
                                    <span class="text-sm font-black text-blue-400" style="text-shadow: 0 1px 4px rgba(0,0,0,0.8);">{{ vq3_world_records }}</span>
                                    <svg class="w-3.5 h-3.5 text-blue-400" viewBox="0 0 20 20" fill="currentColor"><use href="/images/svg/icons.svg#icon-trophy"></use></svg>
                                </div>
                            </template>
                            <!-- Socials -->
                            <template v-if="showHeaderItem('socials') && headerItemRow('socials') === 1">
                                <a v-if="user?.twitch_id" :href="`https://www.twitch.tv/` + user?.twitch_name" target="_blank" class="group relative px-3 py-1.5 rounded-lg bg-purple-950/60 border border-purple-400/50 hover:border-purple-300/60 hover:bg-purple-900/60 transition-all hover:scale-110 shadow-xl backdrop-blur-sm flex items-center gap-1.5" :style="{ order: headerItemOrder('socials') }">
                                    <svg class="w-4 h-4 text-purple-300 transition group-hover:text-purple-200" width="800px" height="800px" viewBox="-0.5 0 20 20" version="1.1" xmlns="http://www.w3.org/2000/svg"><g fill="currentColor"><path d="M97,7249 L99,7249 L99,7244 L97,7244 L97,7249 Z M92,7249 L94,7249 L94,7244 L92,7244 L92,7249 Z M102,7250.307 L102,7241 L88,7241 L88,7253 L92,7253 L92,7255.953 L94.56,7253 L99.34,7253 L102,7250.307 Z M98.907,7256 L94.993,7256 L92.387,7259 L90,7259 L90,7256 L85,7256 L85,7242.48 L86.3,7239 L104,7239 L104,7251.173 L98.907,7256 Z" transform="translate(-85 -7239)"/></g></svg>
                                    <span class="text-xs font-bold text-purple-200 transition group-hover:text-white">{{ $t('TWITCH') }}</span>
                                </a>
                                <a v-if="user?.discord_id" :href="`https://discordapp.com/users/${user.discord_id}`" target="_blank" class="group relative px-3 py-1.5 rounded-lg bg-indigo-950/60 border border-indigo-400/50 hover:border-indigo-300/60 hover:bg-indigo-900/60 transition-all hover:scale-110 shadow-xl backdrop-blur-sm flex items-center gap-1.5" :style="{ order: headerItemOrder('socials') }">
                                    <svg class="w-4 h-4 text-indigo-300 transition group-hover:text-indigo-200" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M18.59 5.88997C17.36 5.31997 16.05 4.89997 14.67 4.65997C14.5 4.95997 14.3 5.36997 14.17 5.69997C12.71 5.47997 11.26 5.47997 9.83001 5.69997C9.69001 5.36997 9.49001 4.95997 9.32001 4.65997C7.94001 4.89997 6.63001 5.31997 5.40001 5.88997C2.92001 9.62997 2.25001 13.28 2.58001 16.87C4.23001 18.1 5.82001 18.84 7.39001 19.33C7.78001 18.8 8.12001 18.23 8.42001 17.64C7.85001 17.43 7.31001 17.16 6.80001 16.85C6.94001 16.75 7.07001 16.64 7.20001 16.54C10.33 18 13.72 18 16.81 16.54C16.94 16.65 17.07 16.75 17.21 16.85C16.7 17.16 16.15 17.42 15.59 17.64C15.89 18.23 16.23 18.8 16.62 19.33C18.19 18.84 19.79 18.1 21.43 16.87C21.82 12.7 20.76 9.08997 18.61 5.88997H18.59ZM8.84001 14.67C7.90001 14.67 7.13001 13.8 7.13001 12.73C7.13001 11.66 7.88001 10.79 8.84001 10.79C9.80001 10.79 10.56 11.66 10.55 12.73C10.55 13.79 9.80001 14.67 8.84001 14.67ZM15.15 14.67C14.21 14.67 13.44 13.8 13.44 12.73C13.44 11.66 14.19 10.79 15.15 10.79C16.11 10.79 16.87 11.66 16.86 12.73C16.86 13.79 16.11 14.67 15.15 14.67Z"/></svg>
                                    <span class="text-xs font-bold text-indigo-200 transition group-hover:text-white">{{ user.discord_name }}</span>
                                </a>
                                <a v-if="user?.twitter_name" :href="`https://www.x.com/` + user?.twitter_name" target="_blank" class="group relative px-3 py-1.5 rounded-lg bg-sky-950/60 border border-sky-400/50 hover:border-sky-300/60 hover:bg-sky-900/60 transition-all hover:scale-110 shadow-xl backdrop-blur-sm flex items-center gap-1.5" :style="{ order: headerItemOrder('socials') }">
                                    <svg class="w-4 h-4 text-sky-300 transition group-hover:text-sky-200" viewBox="0 -2 20 20" xmlns="http://www.w3.org/2000/svg"><g fill="currentColor"><path d="M10.29,7377 C17.837,7377 21.965,7370.84365 21.965,7365.50546 C21.965,7365.33021 21.965,7365.15595 21.953,7364.98267 C22.756,7364.41163 23.449,7363.70276 24,7362.8915 C23.252,7363.21837 22.457,7363.433 21.644,7363.52751 C22.5,7363.02244 23.141,7362.2289 23.448,7361.2926 C22.642,7361.76321 21.761,7362.095 20.842,7362.27321 C19.288,7360.64674 16.689,7360.56798 15.036,7362.09796 C13.971,7363.08447 13.518,7364.55538 13.849,7365.95835 C10.55,7365.79492 7.476,7364.261 5.392,7361.73762 C4.303,7363.58363 4.86,7365.94457 6.663,7367.12996 C6.01,7367.11125 5.371,7366.93797 4.8,7366.62489 L4.8,7366.67608 C4.801,7368.5989 6.178,7370.2549 8.092,7370.63591 C7.488,7370.79836 6.854,7370.82199 6.24,7370.70483 C6.777,7372.35099 8.318,7373.47829 10.073,7373.51078 C8.62,7374.63513 6.825,7375.24554 4.977,7375.24358 C4.651,7375.24259 4.325,7375.22388 4,7375.18549 C5.877,7376.37088 8.06,7377 10.29,7376.99705" transform="translate(-4 -7361)"/></g></svg>
                                    <span class="text-xs font-bold text-sky-200 transition group-hover:text-white">{{ $t('TWITTER') }}</span>
                                </a>
                            </template>
                        </div>

                        <!-- Header Row 2 -->
                        <div v-if="hasRow2Items || isOwnProfile" class="flex items-center gap-2 flex-wrap">
                            <!-- Admin Badge -->
                            <div v-if="user?.admin && showHeaderItem('badge_admin') && headerItemRow('badge_admin') === 2" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-red-950/70 border border-red-400/50 shadow-xl backdrop-blur-sm" :style="{ order: headerItemOrder('badge_admin') }">
                                <img src="/images/svg/badge-moderator.svg" class="w-4 h-4" :alt="$t('Admin')">
                                <span class="text-xs font-bold text-red-300 uppercase tracking-wider">{{ $t('Admin') }}</span>
                            </div>
                            <!-- Moderator Badge -->
                            <div v-if="user?.is_moderator && !user?.admin && showHeaderItem('badge_moderator') && headerItemRow('badge_moderator') === 2" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-950/70 border border-emerald-400/50 shadow-xl backdrop-blur-sm" :style="{ order: headerItemOrder('badge_moderator') }">
                                <img src="/images/svg/badge-moderator.svg" class="w-4 h-4" :alt="$t('Moderator')">
                                <span class="text-xs font-bold text-emerald-300 uppercase tracking-wider">{{ $t('Moderator') }}</span>
                            </div>
                            <!-- Donor Badge -->
                            <div v-if="donorTier && showHeaderItem('badge_donor') && headerItemRow('badge_donor') === 2" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg shadow-xl backdrop-blur-sm group relative" :style="{ order: headerItemOrder('badge_donor') }"
                                :class="{ 'bg-pink-950/70 border border-pink-400/50': donorTier === 'supporter', 'bg-amber-950/70 border border-amber-400/50': donorTier === 'gold', 'bg-cyan-950/70 border border-cyan-400/50': donorTier === 'diamond' }">
                                <img :src="donorTier === 'diamond' ? '/images/svg/badge-donor-diamond.svg' : donorTier === 'gold' ? '/images/svg/badge-donor-gold.svg' : '/images/svg/badge-donor.svg'" class="w-4 h-4" :alt="$t('Supporter')">
                                <span class="text-xs font-bold uppercase tracking-wider" :class="{ 'text-pink-300': donorTier === 'supporter', 'text-amber-300': donorTier === 'gold', 'text-cyan-300': donorTier === 'diamond' }">
                                    {{ $t(':tier Supporter', { tier: donorTier === 'diamond' ? $t('Diamond') : donorTier === 'gold' ? $t('Gold') : '' }) }}
                                </span>
                                <div v-if="Object.keys(donationTotal).length" class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-3 py-1.5 rounded-lg bg-black/90 border border-white/10 text-xs text-gray-300 whitespace-nowrap opacity-0 group-hover:opacity-100 transition pointer-events-none">
                                    {{ $t('Donated :amount', { amount: Object.entries(donationTotal).map(([c, a]) => `${parseFloat(a).toFixed(0)} ${c}`).join(' + ') }) }}
                                </div>
                            </div>
                            <!-- Community/Defragger Badge -->
                            <Link v-if="communityTier && showHeaderItem('badge_community') && headerItemRow('badge_community') === 2" :href="route('community')" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg shadow-xl backdrop-blur-sm group relative border hover:scale-105 transition-transform" :style="{ order: headerItemOrder('badge_community'), borderColor: communityTier.color + '90', backgroundColor: communityTier.color + '35' }">
                                <img src="/images/svg/badge-defragger.png" class="w-4 h-4" :style="{ filter: `drop-shadow(0 0 3px ${communityTier.color})` }" :alt="$t('Defragger')">
                                <span class="text-xs font-bold uppercase tracking-wider" :style="{ color: communityTier.color }">{{ communityTier.name }}</span>
                                <span class="text-xs font-black" :style="{ color: communityTier.color }">{{ communityTier.score }}</span>
                                <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-3 py-2 rounded-lg bg-black/90 border border-white/10 text-xs text-gray-300 whitespace-nowrap opacity-0 group-hover:opacity-100 transition pointer-events-none shadow-xl">
                                    <div class="font-bold text-white mb-1">{{ $t('Defragger Score') }}</div>
                                    <div class="text-gray-400">{{ $t('Community contribution score:') }} <span class="text-white font-semibold">{{ $t(':count pts', { count: communityTier.score }) }}</span></div>
                                    <div class="text-gray-400">{{ $t('Rank:') }} <span class="text-white font-semibold">#{{ communityTier.rank }}</span></div>
                                    <div class="text-gray-500 mt-1 text-[10px]">{{ $t('Click for more info') }}</div>
                                </div>
                            </Link>
                            <!-- Tagger Badge -->
                            <div v-if="tagCount > 0 && showHeaderItem('badge_tagger') && headerItemRow('badge_tagger') === 2" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-950/70 border border-amber-400/50 shadow-xl backdrop-blur-sm group relative" :style="{ order: headerItemOrder('badge_tagger') }">
                                <img src="/images/svg/badge-tagger.svg" class="w-4 h-4" :alt="$t('Tagger')">
                                <span class="text-xs font-bold text-amber-300 uppercase tracking-wider">{{ $t('Tagger') }}</span>
                                <span class="text-xs font-black text-amber-400">{{ tagCount }}</span>
                                <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-3 py-1.5 rounded-lg bg-black/90 border border-white/10 text-xs text-gray-300 whitespace-nowrap opacity-0 group-hover:opacity-100 transition pointer-events-none">
                                    {{ $tc('Contributed :count tag to maps and maplists|Contributed :count tags to maps and maplists', tagCount) }}
                                </div>
                            </div>
                            <!-- Assigner Badge -->
                            <div v-if="(assignedDemoCounts.offline + assignedDemoCounts.online) > 0 && showHeaderItem('badge_assigner') && headerItemRow('badge_assigner') === 2" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-lime-950/70 border border-lime-400/50 shadow-xl backdrop-blur-sm group relative" :style="{ order: headerItemOrder('badge_assigner') }">
                                <svg class="w-4 h-4 text-lime-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M1 8a2 2 0 0 1 2-2h.93a2 2 0 0 0 1.664-.89l.812-1.22A2 2 0 0 1 8.07 3h3.86a2 2 0 0 1 1.664.89l.812 1.22A2 2 0 0 0 16.07 6H17a2 2 0 0 1 2 2v7a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8Zm13.5 3a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0ZM10 14a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z" clip-rule="evenodd" /></svg>
                                <span class="text-xs font-bold text-lime-300 uppercase tracking-wider">{{ $t('Assigner') }}</span>
                                <span class="text-xs font-black text-lime-400">{{ assignedDemoCounts.offline + assignedDemoCounts.online }}</span>
                                <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-3 py-1.5 rounded-lg bg-black/90 border border-white/10 text-xs text-gray-300 whitespace-nowrap opacity-0 group-hover:opacity-100 transition pointer-events-none">
                                    {{ $tc('Manually assigned :count demo to records|Manually assigned :count demos to records', assignedDemoCounts.offline + assignedDemoCounts.online) }}
                                </div>
                            </div>
                            <!-- Player Rank (overall run rank, VQ3 + CPM) -->
                            <div v-if="hasPlayerRank && showHeaderItem('player_rank') && headerItemRow('player_rank') === 2" class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-orange-950/70 border border-orange-400/50 shadow-xl backdrop-blur-sm group relative" :style="{ order: headerItemOrder('player_rank') }">
                                <svg class="w-4 h-4 text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7.5 7.5 3m0 0L12 7.5M7.5 3v13.5m13.5-6L16.5 21m0 0L12 16.5m4.5 4.5V7.5" /></svg>
                                <span class="text-xs font-bold text-orange-300 uppercase tracking-wider">{{ $t('Rank') }}</span>
                                <span v-if="vq3RunRank" class="text-xs font-semibold text-blue-300 uppercase tracking-wider">VQ3</span>
                                <span v-if="vq3RunRank" class="text-sm font-black text-orange-400">#{{ vq3RunRank }}</span>
                                <span v-if="cpmRunRank && vq3RunRank" class="text-gray-600">|</span>
                                <span v-if="cpmRunRank" class="text-xs font-semibold text-purple-300 uppercase tracking-wider">CPM</span>
                                <span v-if="cpmRunRank" class="text-sm font-black text-orange-400">#{{ cpmRunRank }}</span>
                                <div class="absolute top-full left-1/2 -translate-x-1/2 mt-2 px-4 py-3 rounded-lg bg-black/95 border border-white/10 text-xs text-gray-300 opacity-0 group-hover:opacity-100 transition pointer-events-none shadow-2xl z-[100] min-w-[340px]">
                                    <template v-for="mode in activeModes" :key="mode">
                                        <div class="font-bold text-white mb-1.5" :class="mode !== activeModes[0] ? 'mt-3 pt-2 border-t border-white/10' : ''">{{ mode.toUpperCase() }}</div>
                                        <div class="grid grid-cols-[1fr_auto_auto_auto_auto] gap-x-3 gap-y-0.5">
                                            <div class="text-gray-500 font-semibold text-[10px] uppercase">{{ $t('Category') }}</div>
                                            <div class="text-blue-400 font-semibold text-[10px] uppercase text-right">VQ3</div>
                                            <div class="text-blue-400/40 font-semibold text-[10px] uppercase text-right">{{ $t('All') }}</div>
                                            <div class="text-purple-400 font-semibold text-[10px] uppercase text-right">CPM</div>
                                            <div class="text-purple-400/40 font-semibold text-[10px] uppercase text-right">{{ $t('All') }}</div>
                                            <template v-for="cat in activeCategories.filter(c => getRanking('vq3', mode, c) || getRanking('cpm', mode, c))" :key="mode+'-'+cat">
                                                <div class="text-gray-300" :class="cat === 'overall' ? 'font-semibold' : ''">{{ cat.charAt(0).toUpperCase() + cat.slice(1) }}</div>
                                                <div class="text-right font-semibold" :class="getDisplayRank('vq3', mode, cat) ? 'text-white' : 'text-gray-600'">
                                                    {{ getDisplayRank('vq3', mode, cat) ? '#' + getDisplayRank('vq3', mode, cat) : '-' }}
                                                </div>
                                                <div class="text-right text-gray-500">
                                                    {{ getRanking('vq3', mode, cat)?.all_players_rank ? '#' + getRanking('vq3', mode, cat).all_players_rank : '-' }}
                                                </div>
                                                <div class="text-right font-semibold" :class="getDisplayRank('cpm', mode, cat) ? 'text-white' : 'text-gray-600'">
                                                    {{ getDisplayRank('cpm', mode, cat) ? '#' + getDisplayRank('cpm', mode, cat) : '-' }}
                                                </div>
                                                <div class="text-right text-gray-500">
                                                    {{ getRanking('cpm', mode, cat)?.all_players_rank ? '#' + getRanking('cpm', mode, cat).all_players_rank : '-' }}
                                                </div>
                                            </template>
                                        </div>
                                    </template>
                                </div>
                            </div>
                            <!-- Community Rank -->
                            <Link v-if="communityTier && showHeaderItem('community_rank') && headerItemRow('community_rank') === 2" :href="route('community')" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-950/70 border border-emerald-400/50 shadow-xl backdrop-blur-sm group relative hover:scale-105 transition-transform" :style="{ order: headerItemOrder('community_rank') }">
                                <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" /></svg>
                                <span class="text-xs font-bold text-emerald-300 uppercase tracking-wider">{{ $t('Rank') }}</span>
                                <span class="text-sm font-black text-emerald-400">#{{ communityTier.rank }}</span>
                                <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-3 py-2 rounded-lg bg-black/90 border border-white/10 text-xs text-gray-300 whitespace-nowrap opacity-0 group-hover:opacity-100 transition pointer-events-none shadow-xl">
                                    <div class="font-bold text-white mb-1">{{ $t('Community Rank') }}</div>
                                    <div class="text-gray-400">{{ $t('Score:') }} <span class="text-white font-semibold">{{ $t(':count pts', { count: communityTier.score }) }}</span></div>
                                    <div class="text-gray-400">{{ $t('Tier:') }} <span class="font-semibold" :style="{ color: communityTier.color }">{{ communityTier.name }}</span></div>
                                    <div class="text-gray-500 mt-1 text-[10px]">{{ $t('Click for leaderboard') }}</div>
                                </div>
                            </Link>
                            <!-- Clan -->
                            <Link v-if="user?.clan && showHeaderItem('clan') && headerItemRow('clan') === 2" :href="route('clans.show', user.clan.id)" class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-blue-950/70 border border-blue-400/50 hover:border-blue-300/60 hover:bg-blue-900/70 transition-all hover:scale-105 shadow-xl backdrop-blur-sm group" :style="{ order: headerItemOrder('clan') }">
                                <div class="w-2 h-2 rounded-full bg-blue-400 animate-pulse shadow-lg shadow-blue-500/50"></div>
                                <span class="text-xs font-bold text-blue-300 uppercase tracking-wider">{{ $t('Clan') }}</span>
                                <span class="text-sm font-black text-white group-hover:text-blue-100 transition" v-html="q3tohtml(user.clan.name)"></span>
                            </Link>
                            <!-- WR Counters -->
                            <template v-if="showHeaderItem('wr_counters') && headerItemRow('wr_counters') === 2">
                                <div class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-purple-950/70 border border-purple-400/40 shadow-xl backdrop-blur-sm" :style="{ order: headerItemOrder('wr_counters') }">
                                    <span class="text-xs text-purple-300 font-semibold uppercase tracking-wider" style="text-shadow: 0 1px 4px rgba(0,0,0,0.8);">CPM</span>
                                    <span class="text-sm font-black text-purple-400" style="text-shadow: 0 1px 4px rgba(0,0,0,0.8);">{{ cpm_world_records }}</span>
                                    <svg class="w-3.5 h-3.5 text-purple-400" viewBox="0 0 20 20" fill="currentColor"><use href="/images/svg/icons.svg#icon-trophy"></use></svg>
                                </div>
                                <div class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-blue-950/70 border border-blue-400/40 shadow-xl backdrop-blur-sm" :style="{ order: headerItemOrder('wr_counters') }">
                                    <span class="text-xs text-blue-300 font-semibold uppercase tracking-wider" style="text-shadow: 0 1px 4px rgba(0,0,0,0.8);">VQ3</span>
                                    <span class="text-sm font-black text-blue-400" style="text-shadow: 0 1px 4px rgba(0,0,0,0.8);">{{ vq3_world_records }}</span>
                                    <svg class="w-3.5 h-3.5 text-blue-400" viewBox="0 0 20 20" fill="currentColor"><use href="/images/svg/icons.svg#icon-trophy"></use></svg>
                                </div>
                            </template>
                            <!-- Socials -->
                            <template v-if="showHeaderItem('socials') && headerItemRow('socials') === 2">
                                <a v-if="user?.twitch_id" :href="`https://www.twitch.tv/` + user?.twitch_name" target="_blank" class="group relative px-3 py-1.5 rounded-lg bg-purple-950/60 border border-purple-400/50 hover:border-purple-300/60 hover:bg-purple-900/60 transition-all hover:scale-110 shadow-xl backdrop-blur-sm flex items-center gap-1.5" :style="{ order: headerItemOrder('socials') }">
                                    <svg class="w-4 h-4 text-purple-300 transition group-hover:text-purple-200" width="800px" height="800px" viewBox="-0.5 0 20 20" version="1.1" xmlns="http://www.w3.org/2000/svg"><g fill="currentColor"><path d="M97,7249 L99,7249 L99,7244 L97,7244 L97,7249 Z M92,7249 L94,7249 L94,7244 L92,7244 L92,7249 Z M102,7250.307 L102,7241 L88,7241 L88,7253 L92,7253 L92,7255.953 L94.56,7253 L99.34,7253 L102,7250.307 Z M98.907,7256 L94.993,7256 L92.387,7259 L90,7259 L90,7256 L85,7256 L85,7242.48 L86.3,7239 L104,7239 L104,7251.173 L98.907,7256 Z" transform="translate(-85 -7239)"/></g></svg>
                                    <span class="text-xs font-bold text-purple-200 transition group-hover:text-white">{{ $t('TWITCH') }}</span>
                                </a>
                                <a v-if="user?.discord_id" :href="`https://discordapp.com/users/${user.discord_id}`" target="_blank" class="group relative px-3 py-1.5 rounded-lg bg-indigo-950/60 border border-indigo-400/50 hover:border-indigo-300/60 hover:bg-indigo-900/60 transition-all hover:scale-110 shadow-xl backdrop-blur-sm flex items-center gap-1.5" :style="{ order: headerItemOrder('socials') }">
                                    <svg class="w-4 h-4 text-indigo-300 transition group-hover:text-indigo-200" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M18.59 5.88997C17.36 5.31997 16.05 4.89997 14.67 4.65997C14.5 4.95997 14.3 5.36997 14.17 5.69997C12.71 5.47997 11.26 5.47997 9.83001 5.69997C9.69001 5.36997 9.49001 4.95997 9.32001 4.65997C7.94001 4.89997 6.63001 5.31997 5.40001 5.88997C2.92001 9.62997 2.25001 13.28 2.58001 16.87C4.23001 18.1 5.82001 18.84 7.39001 19.33C7.78001 18.8 8.12001 18.23 8.42001 17.64C7.85001 17.43 7.31001 17.16 6.80001 16.85C6.94001 16.75 7.07001 16.64 7.20001 16.54C10.33 18 13.72 18 16.81 16.54C16.94 16.65 17.07 16.75 17.21 16.85C16.7 17.16 16.15 17.42 15.59 17.64C15.89 18.23 16.23 18.8 16.62 19.33C18.19 18.84 19.79 18.1 21.43 16.87C21.82 12.7 20.76 9.08997 18.61 5.88997H18.59ZM8.84001 14.67C7.90001 14.67 7.13001 13.8 7.13001 12.73C7.13001 11.66 7.88001 10.79 8.84001 10.79C9.80001 10.79 10.56 11.66 10.55 12.73C10.55 13.79 9.80001 14.67 8.84001 14.67ZM15.15 14.67C14.21 14.67 13.44 13.8 13.44 12.73C13.44 11.66 14.19 10.79 15.15 10.79C16.11 10.79 16.87 11.66 16.86 12.73C16.86 13.79 16.11 14.67 15.15 14.67Z"/></svg>
                                    <span class="text-xs font-bold text-indigo-200 transition group-hover:text-white">{{ user.discord_name }}</span>
                                </a>
                                <a v-if="user?.twitter_name" :href="`https://www.x.com/` + user?.twitter_name" target="_blank" class="group relative px-3 py-1.5 rounded-lg bg-sky-950/60 border border-sky-400/50 hover:border-sky-300/60 hover:bg-sky-900/60 transition-all hover:scale-110 shadow-xl backdrop-blur-sm flex items-center gap-1.5" :style="{ order: headerItemOrder('socials') }">
                                    <svg class="w-4 h-4 text-sky-300 transition group-hover:text-sky-200" viewBox="0 -2 20 20" xmlns="http://www.w3.org/2000/svg"><g fill="currentColor"><path d="M10.29,7377 C17.837,7377 21.965,7370.84365 21.965,7365.50546 C21.965,7365.33021 21.965,7365.15595 21.953,7364.98267 C22.756,7364.41163 23.449,7363.70276 24,7362.8915 C23.252,7363.21837 22.457,7363.433 21.644,7363.52751 C22.5,7363.02244 23.141,7362.2289 23.448,7361.2926 C22.642,7361.76321 21.761,7362.095 20.842,7362.27321 C19.288,7360.64674 16.689,7360.56798 15.036,7362.09796 C13.971,7363.08447 13.518,7364.55538 13.849,7365.95835 C10.55,7365.79492 7.476,7364.261 5.392,7361.73762 C4.303,7363.58363 4.86,7365.94457 6.663,7367.12996 C6.01,7367.11125 5.371,7366.93797 4.8,7366.62489 L4.8,7366.67608 C4.801,7368.5989 6.178,7370.2549 8.092,7370.63591 C7.488,7370.79836 6.854,7370.82199 6.24,7370.70483 C6.777,7372.35099 8.318,7373.47829 10.073,7373.51078 C8.62,7374.63513 6.825,7375.24554 4.977,7375.24358 C4.651,7375.24259 4.325,7375.22388 4,7375.18549 C5.877,7376.37088 8.06,7377 10.29,7376.99705" transform="translate(-4 -7361)"/></g></svg>
                                    <span class="text-xs font-bold text-sky-200 transition group-hover:text-white">{{ $t('TWITTER') }}</span>
                                </a>
                            </template>

                            <!-- Customize Profile (moved to top-right) -->
                            <div v-if="false && isOwnProfile" class="relative flex items-center shadow-xl">
                                <button @click="showQuickSettings = !showQuickSettings" :class="showQuickSettings ? 'bg-blue-600/50 border-blue-400/50 text-white' : 'bg-black/50 border-white/20 hover:border-white/30 hover:bg-black/60 text-gray-400 hover:text-white'" class="px-3 py-1.5 rounded-l-lg transition-all backdrop-blur-sm flex items-center gap-1.5 border border-r-0">
                                    <svg class="w-4 h-4 transition" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
                                    <span class="text-xs font-bold transition">{{ $t('CUSTOMIZE') }}</span>
                                </button>
                                <button ref="customizeArrowBtn" @click="quickSettingsDropdown = !quickSettingsDropdown" :class="showQuickSettings ? 'bg-blue-600/50 border-blue-400/50 text-white' : 'bg-black/50 border-white/20 hover:border-white/30 hover:bg-black/60 text-gray-400 hover:text-white'" class="px-1.5 py-1.5 rounded-r-lg transition-all backdrop-blur-sm border">
                                    <svg class="w-3.5 h-3.5 transition-transform" :class="quickSettingsDropdown ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
                                </button>
                                <!-- Dropdown (teleported to body to avoid overflow-hidden clipping) -->
                                <Teleport to="body">
                                    <div v-if="quickSettingsDropdown" @click="quickSettingsDropdown = false" class="fixed inset-0 z-[998]"></div>
                                    <div v-if="quickSettingsDropdown" ref="customizeDropdownEl" class="fixed w-56 bg-gray-900/80 backdrop-blur-xl border border-white/10 rounded-lg overflow-hidden z-[999] shadow-2xl" :style="customizeDropdownStyle">
                                        <button
                                            v-for="panel in quickSettingsPanels"
                                            :key="panel.id"
                                            @click="selectQuickPanel(panel.id)"
                                            :class="quickSettingsPanel === panel.id ? 'bg-blue-600/30 text-blue-300' : 'text-gray-300 hover:bg-white/10'"
                                            class="w-full px-4 py-2 text-left text-sm transition-colors flex items-center gap-2"
                                        >
                                            <svg v-if="quickSettingsPanel === panel.id" class="w-3 h-3 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" /></svg>
                                            <span :class="quickSettingsPanel !== panel.id ? 'ml-5' : ''">{{ panel.label }}</span>
                                        </button>
                                    </div>
                                </Teleport>
                            </div>
                        </div>

                        <!-- Header Row 3 -->
                        <div v-if="hasRow3Items" class="flex items-center gap-2 flex-wrap">
                            <!-- Admin Badge -->
                            <div v-if="user?.admin && showHeaderItem('badge_admin') && headerItemRow('badge_admin') === 3" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-red-950/70 border border-red-400/50 shadow-xl backdrop-blur-sm" :style="{ order: headerItemOrder('badge_admin') }">
                                <img src="/images/svg/badge-moderator.svg" class="w-4 h-4" :alt="$t('Admin')">
                                <span class="text-xs font-bold text-red-300 uppercase tracking-wider">{{ $t('Admin') }}</span>
                            </div>
                            <!-- Moderator Badge -->
                            <div v-if="user?.is_moderator && !user?.admin && showHeaderItem('badge_moderator') && headerItemRow('badge_moderator') === 3" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-950/70 border border-emerald-400/50 shadow-xl backdrop-blur-sm" :style="{ order: headerItemOrder('badge_moderator') }">
                                <img src="/images/svg/badge-moderator.svg" class="w-4 h-4" :alt="$t('Moderator')">
                                <span class="text-xs font-bold text-emerald-300 uppercase tracking-wider">{{ $t('Moderator') }}</span>
                            </div>
                            <!-- Donor Badge -->
                            <div v-if="donorTier && showHeaderItem('badge_donor') && headerItemRow('badge_donor') === 3" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg shadow-xl backdrop-blur-sm group relative" :style="{ order: headerItemOrder('badge_donor') }"
                                :class="{ 'bg-pink-950/70 border border-pink-400/50': donorTier === 'supporter', 'bg-amber-950/70 border border-amber-400/50': donorTier === 'gold', 'bg-cyan-950/70 border border-cyan-400/50': donorTier === 'diamond' }">
                                <img :src="donorTier === 'diamond' ? '/images/svg/badge-donor-diamond.svg' : donorTier === 'gold' ? '/images/svg/badge-donor-gold.svg' : '/images/svg/badge-donor.svg'" class="w-4 h-4" :alt="$t('Supporter')">
                                <span class="text-xs font-bold uppercase tracking-wider" :class="{ 'text-pink-300': donorTier === 'supporter', 'text-amber-300': donorTier === 'gold', 'text-cyan-300': donorTier === 'diamond' }">
                                    {{ $t(':tier Supporter', { tier: donorTier === 'diamond' ? $t('Diamond') : donorTier === 'gold' ? $t('Gold') : '' }) }}
                                </span>
                                <div v-if="Object.keys(donationTotal).length" class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-3 py-1.5 rounded-lg bg-black/90 border border-white/10 text-xs text-gray-300 whitespace-nowrap opacity-0 group-hover:opacity-100 transition pointer-events-none">
                                    {{ $t('Donated :amount', { amount: Object.entries(donationTotal).map(([c, a]) => `${parseFloat(a).toFixed(0)} ${c}`).join(' + ') }) }}
                                </div>
                            </div>
                            <!-- Community/Defragger Badge -->
                            <Link v-if="communityTier && showHeaderItem('badge_community') && headerItemRow('badge_community') === 3" :href="route('community')" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg shadow-xl backdrop-blur-sm group relative border hover:scale-105 transition-transform" :style="{ order: headerItemOrder('badge_community'), borderColor: communityTier.color + '90', backgroundColor: communityTier.color + '35' }">
                                <img src="/images/svg/badge-defragger.png" class="w-4 h-4" :style="{ filter: `drop-shadow(0 0 3px ${communityTier.color})` }" :alt="$t('Defragger')">
                                <span class="text-xs font-bold uppercase tracking-wider" :style="{ color: communityTier.color }">{{ communityTier.name }}</span>
                                <span class="text-xs font-black" :style="{ color: communityTier.color }">{{ communityTier.score }}</span>
                                <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-3 py-2 rounded-lg bg-black/90 border border-white/10 text-xs text-gray-300 whitespace-nowrap opacity-0 group-hover:opacity-100 transition pointer-events-none shadow-xl">
                                    <div class="font-bold text-white mb-1">{{ $t('Defragger Score') }}</div>
                                    <div class="text-gray-400">{{ $t('Community contribution score:') }} <span class="text-white font-semibold">{{ $t(':count pts', { count: communityTier.score }) }}</span></div>
                                    <div class="text-gray-400">{{ $t('Rank:') }} <span class="text-white font-semibold">#{{ communityTier.rank }}</span></div>
                                    <div class="text-gray-500 mt-1 text-[10px]">{{ $t('Click for more info') }}</div>
                                </div>
                            </Link>
                            <!-- Tagger Badge -->
                            <div v-if="tagCount > 0 && showHeaderItem('badge_tagger') && headerItemRow('badge_tagger') === 3" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-950/70 border border-amber-400/50 shadow-xl backdrop-blur-sm group relative" :style="{ order: headerItemOrder('badge_tagger') }">
                                <img src="/images/svg/badge-tagger.svg" class="w-4 h-4" :alt="$t('Tagger')">
                                <span class="text-xs font-bold text-amber-300 uppercase tracking-wider">{{ $t('Tagger') }}</span>
                                <span class="text-xs font-black text-amber-400">{{ tagCount }}</span>
                                <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-3 py-1.5 rounded-lg bg-black/90 border border-white/10 text-xs text-gray-300 whitespace-nowrap opacity-0 group-hover:opacity-100 transition pointer-events-none">
                                    {{ $tc('Contributed :count tag to maps and maplists|Contributed :count tags to maps and maplists', tagCount) }}
                                </div>
                            </div>
                            <!-- Assigner Badge -->
                            <div v-if="(assignedDemoCounts.offline + assignedDemoCounts.online) > 0 && showHeaderItem('badge_assigner') && headerItemRow('badge_assigner') === 3" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-lime-950/70 border border-lime-400/50 shadow-xl backdrop-blur-sm group relative" :style="{ order: headerItemOrder('badge_assigner') }">
                                <svg class="w-4 h-4 text-lime-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M1 8a2 2 0 0 1 2-2h.93a2 2 0 0 0 1.664-.89l.812-1.22A2 2 0 0 1 8.07 3h3.86a2 2 0 0 1 1.664.89l.812 1.22A2 2 0 0 0 16.07 6H17a2 2 0 0 1 2 2v7a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8Zm13.5 3a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0ZM10 14a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z" clip-rule="evenodd" /></svg>
                                <span class="text-xs font-bold text-lime-300 uppercase tracking-wider">{{ $t('Assigner') }}</span>
                                <span class="text-xs font-black text-lime-400">{{ assignedDemoCounts.offline + assignedDemoCounts.online }}</span>
                                <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-3 py-1.5 rounded-lg bg-black/90 border border-white/10 text-xs text-gray-300 whitespace-nowrap opacity-0 group-hover:opacity-100 transition pointer-events-none">
                                    {{ $tc('Manually assigned :count demo to records|Manually assigned :count demos to records', assignedDemoCounts.offline + assignedDemoCounts.online) }}
                                </div>
                            </div>
                            <!-- Player Rank (overall run rank, VQ3 + CPM) -->
                            <div v-if="hasPlayerRank && showHeaderItem('player_rank') && headerItemRow('player_rank') === 3" class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-orange-950/70 border border-orange-400/50 shadow-xl backdrop-blur-sm group relative" :style="{ order: headerItemOrder('player_rank') }">
                                <svg class="w-4 h-4 text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7.5 7.5 3m0 0L12 7.5M7.5 3v13.5m13.5-6L16.5 21m0 0L12 16.5m4.5 4.5V7.5" /></svg>
                                <span class="text-xs font-bold text-orange-300 uppercase tracking-wider">{{ $t('Rank') }}</span>
                                <span v-if="vq3RunRank" class="text-xs font-semibold text-blue-300 uppercase tracking-wider">VQ3</span>
                                <span v-if="vq3RunRank" class="text-sm font-black text-orange-400">#{{ vq3RunRank }}</span>
                                <span v-if="cpmRunRank && vq3RunRank" class="text-gray-600">|</span>
                                <span v-if="cpmRunRank" class="text-xs font-semibold text-purple-300 uppercase tracking-wider">CPM</span>
                                <span v-if="cpmRunRank" class="text-sm font-black text-orange-400">#{{ cpmRunRank }}</span>
                                <div class="absolute top-full left-1/2 -translate-x-1/2 mt-2 px-4 py-3 rounded-lg bg-black/95 border border-white/10 text-xs text-gray-300 opacity-0 group-hover:opacity-100 transition pointer-events-none shadow-2xl z-[100] min-w-[340px]">
                                    <template v-for="mode in activeModes" :key="mode">
                                        <div class="font-bold text-white mb-1.5" :class="mode !== activeModes[0] ? 'mt-3 pt-2 border-t border-white/10' : ''">{{ mode.toUpperCase() }}</div>
                                        <div class="grid grid-cols-[1fr_auto_auto_auto_auto] gap-x-3 gap-y-0.5">
                                            <div class="text-gray-500 font-semibold text-[10px] uppercase">{{ $t('Category') }}</div>
                                            <div class="text-blue-400 font-semibold text-[10px] uppercase text-right">VQ3</div>
                                            <div class="text-blue-400/40 font-semibold text-[10px] uppercase text-right">{{ $t('All') }}</div>
                                            <div class="text-purple-400 font-semibold text-[10px] uppercase text-right">CPM</div>
                                            <div class="text-purple-400/40 font-semibold text-[10px] uppercase text-right">{{ $t('All') }}</div>
                                            <template v-for="cat in activeCategories.filter(c => getRanking('vq3', mode, c) || getRanking('cpm', mode, c))" :key="mode+'-'+cat">
                                                <div class="text-gray-300" :class="cat === 'overall' ? 'font-semibold' : ''">{{ cat.charAt(0).toUpperCase() + cat.slice(1) }}</div>
                                                <div class="text-right font-semibold" :class="getDisplayRank('vq3', mode, cat) ? 'text-white' : 'text-gray-600'">
                                                    {{ getDisplayRank('vq3', mode, cat) ? '#' + getDisplayRank('vq3', mode, cat) : '-' }}
                                                </div>
                                                <div class="text-right text-gray-500">
                                                    {{ getRanking('vq3', mode, cat)?.all_players_rank ? '#' + getRanking('vq3', mode, cat).all_players_rank : '-' }}
                                                </div>
                                                <div class="text-right font-semibold" :class="getDisplayRank('cpm', mode, cat) ? 'text-white' : 'text-gray-600'">
                                                    {{ getDisplayRank('cpm', mode, cat) ? '#' + getDisplayRank('cpm', mode, cat) : '-' }}
                                                </div>
                                                <div class="text-right text-gray-500">
                                                    {{ getRanking('cpm', mode, cat)?.all_players_rank ? '#' + getRanking('cpm', mode, cat).all_players_rank : '-' }}
                                                </div>
                                            </template>
                                        </div>
                                    </template>
                                </div>
                            </div>
                            <!-- Community Rank -->
                            <Link v-if="communityTier && showHeaderItem('community_rank') && headerItemRow('community_rank') === 3" :href="route('community')" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-950/70 border border-emerald-400/50 shadow-xl backdrop-blur-sm group relative hover:scale-105 transition-transform" :style="{ order: headerItemOrder('community_rank') }">
                                <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" /></svg>
                                <span class="text-xs font-bold text-emerald-300 uppercase tracking-wider">{{ $t('Rank') }}</span>
                                <span class="text-sm font-black text-emerald-400">#{{ communityTier.rank }}</span>
                                <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-3 py-2 rounded-lg bg-black/90 border border-white/10 text-xs text-gray-300 whitespace-nowrap opacity-0 group-hover:opacity-100 transition pointer-events-none shadow-xl">
                                    <div class="font-bold text-white mb-1">{{ $t('Community Rank') }}</div>
                                    <div class="text-gray-400">{{ $t('Score:') }} <span class="text-white font-semibold">{{ $t(':count pts', { count: communityTier.score }) }}</span></div>
                                    <div class="text-gray-400">{{ $t('Tier:') }} <span class="font-semibold" :style="{ color: communityTier.color }">{{ communityTier.name }}</span></div>
                                    <div class="text-gray-500 mt-1 text-[10px]">{{ $t('Click for leaderboard') }}</div>
                                </div>
                            </Link>
                            <!-- Clan -->
                            <Link v-if="user?.clan && showHeaderItem('clan') && headerItemRow('clan') === 3" :href="route('clans.show', user.clan.id)" class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-blue-950/70 border border-blue-400/50 hover:border-blue-300/60 hover:bg-blue-900/70 transition-all hover:scale-105 shadow-xl backdrop-blur-sm group" :style="{ order: headerItemOrder('clan') }">
                                <div class="w-2 h-2 rounded-full bg-blue-400 animate-pulse shadow-lg shadow-blue-500/50"></div>
                                <span class="text-xs font-bold text-blue-300 uppercase tracking-wider">{{ $t('Clan') }}</span>
                                <span class="text-sm font-black text-white group-hover:text-blue-100 transition" v-html="q3tohtml(user.clan.name)"></span>
                            </Link>
                            <!-- WR Counters -->
                            <template v-if="showHeaderItem('wr_counters') && headerItemRow('wr_counters') === 3">
                                <div class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-purple-950/70 border border-purple-400/40 shadow-xl backdrop-blur-sm" :style="{ order: headerItemOrder('wr_counters') }">
                                    <span class="text-xs text-purple-300 font-semibold uppercase tracking-wider" style="text-shadow: 0 1px 4px rgba(0,0,0,0.8);">CPM</span>
                                    <span class="text-sm font-black text-purple-400" style="text-shadow: 0 1px 4px rgba(0,0,0,0.8);">{{ cpm_world_records }}</span>
                                    <svg class="w-3.5 h-3.5 text-purple-400" viewBox="0 0 20 20" fill="currentColor"><use href="/images/svg/icons.svg#icon-trophy"></use></svg>
                                </div>
                                <div class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-blue-950/70 border border-blue-400/40 shadow-xl backdrop-blur-sm" :style="{ order: headerItemOrder('wr_counters') }">
                                    <span class="text-xs text-blue-300 font-semibold uppercase tracking-wider" style="text-shadow: 0 1px 4px rgba(0,0,0,0.8);">VQ3</span>
                                    <span class="text-sm font-black text-blue-400" style="text-shadow: 0 1px 4px rgba(0,0,0,0.8);">{{ vq3_world_records }}</span>
                                    <svg class="w-3.5 h-3.5 text-blue-400" viewBox="0 0 20 20" fill="currentColor"><use href="/images/svg/icons.svg#icon-trophy"></use></svg>
                                </div>
                            </template>
                            <!-- Socials -->
                            <template v-if="showHeaderItem('socials') && headerItemRow('socials') === 3">
                                <a v-if="user?.twitch_id" :href="`https://www.twitch.tv/` + user?.twitch_name" target="_blank" class="group relative px-3 py-1.5 rounded-lg bg-purple-950/60 border border-purple-400/50 hover:border-purple-300/60 hover:bg-purple-900/60 transition-all hover:scale-110 shadow-xl backdrop-blur-sm flex items-center gap-1.5" :style="{ order: headerItemOrder('socials') }">
                                    <svg class="w-4 h-4 text-purple-300 transition group-hover:text-purple-200" width="800px" height="800px" viewBox="-0.5 0 20 20" version="1.1" xmlns="http://www.w3.org/2000/svg"><g fill="currentColor"><path d="M97,7249 L99,7249 L99,7244 L97,7244 L97,7249 Z M92,7249 L94,7249 L94,7244 L92,7244 L92,7249 Z M102,7250.307 L102,7241 L88,7241 L88,7253 L92,7253 L92,7255.953 L94.56,7253 L99.34,7253 L102,7250.307 Z M98.907,7256 L94.993,7256 L92.387,7259 L90,7259 L90,7256 L85,7256 L85,7242.48 L86.3,7239 L104,7239 L104,7251.173 L98.907,7256 Z" transform="translate(-85 -7239)"/></g></svg>
                                    <span class="text-xs font-bold text-purple-200 transition group-hover:text-white">{{ $t('TWITCH') }}</span>
                                </a>
                                <a v-if="user?.discord_id" :href="`https://discordapp.com/users/${user.discord_id}`" target="_blank" class="group relative px-3 py-1.5 rounded-lg bg-indigo-950/60 border border-indigo-400/50 hover:border-indigo-300/60 hover:bg-indigo-900/60 transition-all hover:scale-110 shadow-xl backdrop-blur-sm flex items-center gap-1.5" :style="{ order: headerItemOrder('socials') }">
                                    <svg class="w-4 h-4 text-indigo-300 transition group-hover:text-indigo-200" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M18.59 5.88997C17.36 5.31997 16.05 4.89997 14.67 4.65997C14.5 4.95997 14.3 5.36997 14.17 5.69997C12.71 5.47997 11.26 5.47997 9.83001 5.69997C9.69001 5.36997 9.49001 4.95997 9.32001 4.65997C7.94001 4.89997 6.63001 5.31997 5.40001 5.88997C2.92001 9.62997 2.25001 13.28 2.58001 16.87C4.23001 18.1 5.82001 18.84 7.39001 19.33C7.78001 18.8 8.12001 18.23 8.42001 17.64C7.85001 17.43 7.31001 17.16 6.80001 16.85C6.94001 16.75 7.07001 16.64 7.20001 16.54C10.33 18 13.72 18 16.81 16.54C16.94 16.65 17.07 16.75 17.21 16.85C16.7 17.16 16.15 17.42 15.59 17.64C15.89 18.23 16.23 18.8 16.62 19.33C18.19 18.84 19.79 18.1 21.43 16.87C21.82 12.7 20.76 9.08997 18.61 5.88997H18.59ZM8.84001 14.67C7.90001 14.67 7.13001 13.8 7.13001 12.73C7.13001 11.66 7.88001 10.79 8.84001 10.79C9.80001 10.79 10.56 11.66 10.55 12.73C10.55 13.79 9.80001 14.67 8.84001 14.67ZM15.15 14.67C14.21 14.67 13.44 13.8 13.44 12.73C13.44 11.66 14.19 10.79 15.15 10.79C16.11 10.79 16.87 11.66 16.86 12.73C16.86 13.79 16.11 14.67 15.15 14.67Z"/></svg>
                                    <span class="text-xs font-bold text-indigo-200 transition group-hover:text-white">{{ user.discord_name }}</span>
                                </a>
                                <a v-if="user?.twitter_name" :href="`https://www.x.com/` + user?.twitter_name" target="_blank" class="group relative px-3 py-1.5 rounded-lg bg-sky-950/60 border border-sky-400/50 hover:border-sky-300/60 hover:bg-sky-900/60 transition-all hover:scale-110 shadow-xl backdrop-blur-sm flex items-center gap-1.5" :style="{ order: headerItemOrder('socials') }">
                                    <svg class="w-4 h-4 text-sky-300 transition group-hover:text-sky-200" viewBox="0 -2 20 20" xmlns="http://www.w3.org/2000/svg"><g fill="currentColor"><path d="M10.29,7377 C17.837,7377 21.965,7370.84365 21.965,7365.50546 C21.965,7365.33021 21.965,7365.15595 21.953,7364.98267 C22.756,7364.41163 23.449,7363.70276 24,7362.8915 C23.252,7363.21837 22.457,7363.433 21.644,7363.52751 C22.5,7363.02244 23.141,7362.2289 23.448,7361.2926 C22.642,7361.76321 21.761,7362.095 20.842,7362.27321 C19.288,7360.64674 16.689,7360.56798 15.036,7362.09796 C13.971,7363.08447 13.518,7364.55538 13.849,7365.95835 C10.55,7365.79492 7.476,7364.261 5.392,7361.73762 C4.303,7363.58363 4.86,7365.94457 6.663,7367.12996 C6.01,7367.11125 5.371,7366.93797 4.8,7366.62489 L4.8,7366.67608 C4.801,7368.5989 6.178,7370.2549 8.092,7370.63591 C7.488,7370.79836 6.854,7370.82199 6.24,7370.70483 C6.777,7372.35099 8.318,7373.47829 10.073,7373.51078 C8.62,7374.63513 6.825,7375.24554 4.977,7375.24358 C4.651,7375.24259 4.325,7375.22388 4,7375.18549 C5.877,7376.37088 8.06,7377 10.29,7376.99705" transform="translate(-4 -7361)"/></g></svg>
                                    <span class="text-xs font-bold text-sky-200 transition group-hover:text-white">{{ $t('TWITTER') }}</span>
                                </a>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Settings Panel (own profile only) -->
        <transition
            enter-active-class="transition-all duration-300 ease-out"
            leave-active-class="transition-all duration-200 ease-in"
            enter-from-class="opacity-0 -translate-y-2 max-h-0"
            enter-to-class="opacity-100 translate-y-0 max-h-[500px]"
            leave-from-class="opacity-100 translate-y-0 max-h-[500px]"
            leave-to-class="opacity-0 -translate-y-2 max-h-0"
        >
            <div v-if="isOwnProfile && showQuickSettings" class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 -mt-2 mb-3 relative z-20 overflow-hidden">
                <ProfileLayoutForm v-if="quickSettingsPanel === 'layout'" />
                <AvatarNameEffectsForm v-else-if="quickSettingsPanel === 'effects'" />
                <MapRecordsViewForm v-else-if="quickSettingsPanel === 'map_view'" />
                <PhysicsOrderForm v-else-if="quickSettingsPanel === 'physics_order'" />
                <EffectsIntensityForm v-else-if="quickSettingsPanel === 'effects_intensity'" />
            </div>
        </transition>

        <!-- Profile Tabs -->
        <div v-if="hasCreatorTabs && !ownProfileNotLinked" class="max-w-8xl mx-auto px-4 sm:px-6 lg:px-8 mt-2 mb-4 relative z-20">
            <div class="flex items-center gap-3 flex-wrap">
                <div class="flex items-center gap-1 bg-black/40 backdrop-blur-sm rounded-xl p-1 border border-white/10 w-fit">
                    <button @click="switchTab('records')"
                        class="px-5 py-2 rounded-lg text-sm font-bold transition-all"
                        :class="activeTab === 'records' ? 'bg-white/10 text-white border border-white/10' : 'text-gray-400 hover:text-white hover:bg-white/5'">
                        {{ $t('Records') }}
                    </button>
                    <button v-if="hasMapperProfile" @click="switchTab('mapper')"
                        class="px-5 py-2 rounded-lg text-sm font-bold transition-all flex items-center gap-2"
                        :class="activeTab === 'mapper' ? 'bg-green-600/20 text-green-400 border border-green-500/30' : 'text-gray-400 hover:text-green-400 hover:bg-green-500/10'">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"></path></svg>
                        {{ $t('Mapper') }}
                    </button>
                    <button v-if="hasModelerProfile" @click="switchTab('modeler')"
                        class="px-5 py-2 rounded-lg text-sm font-bold transition-all flex items-center gap-2"
                        :class="activeTab === 'modeler' ? 'bg-blue-600/20 text-blue-400 border border-blue-500/30' : 'text-gray-400 hover:text-blue-400 hover:bg-blue-500/10'">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 7.5l-2.25-1.313M21 7.5v2.25m0-2.25l-2.25 1.313M3 7.5l2.25-1.313M3 7.5l2.25 1.313M3 7.5v2.25m9 3l2.25-1.313M12 12.75l-2.25-1.313M12 12.75V15m0 6.75l2.25-1.313M12 21.75V19.5m0 2.25l-2.25-1.313m0-16.875L12 2.25l2.25 1.313M21 14.25v2.25l-2.25 1.313m-13.5 0L3 16.5v-2.25"></path></svg>
                        {{ $t('Modeler') }}
                    </button>
                </div>
                <div v-if="creatorClaimNames.length" class="flex items-center gap-2 flex-wrap">
                    <span class="text-xs font-bold text-gray-500 uppercase tracking-wider">{{ $t('Creator Names:') }}</span>
                    <span v-for="claim in creatorClaimNames" :key="claim.name + claim.type"
                        class="px-2.5 py-0.5 rounded-full text-xs font-bold backdrop-blur-sm"
                        :class="claim.type === 'map' ? 'bg-green-500/20 border border-green-500/30 text-green-400' : 'bg-blue-500/20 border border-blue-500/30 text-blue-400'">
                        {{ claim.name }}
                    </span>
                </div>
                <a v-if="isOwnProfile && activeTab === 'modeler'"
                    :href="route('settings.show') + '?tab=creator'"
                    class="ml-auto flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-black/50 border border-white/20 hover:border-blue-400/50 hover:bg-blue-600/20 text-gray-400 hover:text-blue-300 transition-all text-xs font-bold backdrop-blur-sm">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                    {{ $t('Customize Pins & Order') }}
                </a>
            </div>
        </div>

        <!-- Mapper Tab -->
        <div v-if="hasMapperProfile && activeTab === 'mapper'" class="max-w-8xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <ProfileCreatorTab :userId="user?.id" />
        </div>

        <!-- Modeler Tab -->
        <div v-if="hasModelerProfile && activeTab === 'modeler'" class="max-w-8xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <ProfileModelerTab :userId="user?.id" />
        </div>

        <!-- Locked profile: the same reduced view for a guest and for an
             unverified login, on every profile. Guest gets the login box,
             the unverified login gets the verify box. -->
        <div v-if="lockedAsGuest" class="max-w-8xl mx-auto px-4 sm:px-6 lg:px-8 -mt-6 relative z-10">
            <div class="relative z-20 mb-6">
                <div class="bg-gradient-to-r from-blue-500/10 via-blue-500/20 to-blue-500/10 border border-blue-500/30 rounded-2xl px-8 py-6 text-center backdrop-blur-sm">
                    <div class="text-3xl font-black text-blue-300 mb-2">{{ $t('See the full profile') }}</div>
                    <div class="text-sm text-gray-400 mb-4">{{ $t('Performance, activity, record types, map features, the activity calendar and every page of records open with a verified account.') }}</div>
                    <div class="flex justify-center gap-4">
                        <a href="/login" class="px-6 py-2 bg-blue-600 hover:bg-blue-500 text-white font-bold rounded-lg transition-colors">{{ $t('Login') }}</a>
                        <a href="/register" class="px-6 py-2 bg-white/10 hover:bg-white/20 text-white font-bold rounded-lg transition-colors">{{ $t('Register') }}</a>
                    </div>
                </div>
            </div>
        </div>
        <div v-if="lockedUnverified" class="max-w-8xl mx-auto px-4 sm:px-6 lg:px-8 -mt-6 relative z-10">
            <div class="relative z-20 mb-6">
                <div class="bg-gradient-to-r from-red-500/10 via-red-500/20 to-red-500/10 border border-red-500/30 rounded-2xl px-8 py-6 text-center backdrop-blur-sm">
                    <div class="text-3xl font-black text-red-400 mb-2">{{ $t('Verify Your Email') }}</div>
                    <div class="text-lg font-semibold text-red-200/80 mb-2">{{ $t('Your email address is not verified yet.') }}</div>
                    <div class="text-sm text-gray-400 mb-4">{{ $t('One click on the link in the mail opens the full profile, demo uploads, account linking and map tagging.') }}</div>
                    <Link href="/email/verify" class="inline-block px-6 py-2 bg-red-600 hover:bg-red-500 text-white font-bold rounded-lg transition-colors">
                        {{ $t('Verify Email') }}
                    </Link>
                </div>
            </div>
        </div>

        <!-- Own profile, verified but not linked -->
        <div v-if="ownProfileNotLinked && !profileLocked" class="max-w-8xl mx-auto px-4 sm:px-6 lg:px-8 -mt-6 relative z-10">
            <div class="relative z-20 mb-6">
                <div class="bg-gradient-to-r from-yellow-500/10 via-yellow-500/20 to-yellow-500/10 border border-yellow-500/30 rounded-2xl px-8 py-6 text-center backdrop-blur-sm">
                    <div class="text-3xl font-black text-yellow-400 mb-2">{{ $t('Link Your Q3DF Profile') }}</div>
                    <div class="text-lg font-semibold text-yellow-200/80 mb-2">{{ $t('Your account is not linked to a Q3DF/mDd profile yet.') }}</div>
                    <div class="text-sm text-gray-400 mb-4">{{ $t('Link your profile to unlock records, rankings, stats, demo matching, and more.') }}</div>
                    <Link href="/link-account" class="inline-block px-6 py-2 bg-yellow-600 hover:bg-yellow-500 text-white font-bold rounded-lg transition-colors">
                        {{ $t('Link Account Now') }}
                    </Link>
                </div>
            </div>
        </div>

        <!--
            The same fact the owner is told above, said to everybody else.
            Without it a profile with no Q3DF link reads as a player who has
            simply never run anything, which is a claim about them that is not
            true. Not shown on your own profile: you get the yellow box above,
            which also tells you what to do about it.
        -->
        <div v-if="!hasProfile && !isOwnProfile" class="max-w-8xl mx-auto px-4 sm:px-6 lg:px-8 -mt-6 relative z-10">
            <div class="relative z-20 mb-6">
                <div class="bg-white/[0.03] border border-white/10 rounded-2xl px-8 py-6 text-center backdrop-blur-sm">
                    <div class="text-xl font-bold text-gray-200 mb-2">{{ $t('No Q3DF profile linked') }}</div>
                    <div class="text-sm text-gray-400">{{ $t('This account is not linked to a Q3DF/mDd profile, so there are no records, rankings or stats to show.') }}</div>
                </div>
            </div>
        </div>

        <!-- Admin: Rating Breakdown -->
        <div v-if="canViewBreakdown && hasPlayerRank && activeTab === 'records' && !ownProfileNotLinked" class="max-w-8xl mx-auto px-4 sm:px-6 lg:px-8 mt-2 mb-2 relative z-10">
            <div>
                <button @click="toggleRatingBreakdown"
                    class="flex items-center gap-2 px-4 py-2.5 bg-orange-950/50 border border-orange-500/30 rounded-xl text-sm font-bold text-orange-400 hover:bg-orange-950/70 transition-all w-full">
                    <svg class="w-4 h-4 transition-transform" :class="ratingBreakdownOpen ? 'rotate-90' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                    </svg>
                    {{ $t('Rating Breakdown (Admin)') }}
                    <span class="text-[10px] text-orange-600 font-normal ml-auto">{{ $t('How the ranking score is calculated from individual map scores') }}</span>
                </button>

                <div v-if="ratingBreakdownOpen" class="mt-2 bg-black/60 border border-orange-500/20 rounded-xl overflow-hidden">
                    <!-- Controls -->
                    <div class="flex items-center gap-3 px-4 py-3 border-b border-orange-500/10">
                        <div class="flex gap-1">
                            <button v-for="p in ['vq3', 'cpm']" :key="p"
                                @click="ratingBreakdownPhysics = p; loadRatingBreakdown()"
                                class="px-3 py-1 rounded text-xs font-bold transition-all"
                                :class="ratingBreakdownPhysics === p
                                    ? (p === 'vq3' ? 'bg-blue-600 text-white' : 'bg-purple-600 text-white')
                                    : 'bg-gray-800 text-gray-500 hover:text-gray-300'">
                                {{ p.toUpperCase() }}
                            </button>
                        </div>
                        <select v-model="ratingBreakdownCategory" @change="loadRatingBreakdown()"
                            class="bg-gray-800 border border-gray-700 rounded px-2 py-1 text-xs text-gray-300">
                            <option v-for="cat in ['overall','strafe','rocket','plasma','grenade','bfg','slick','tele','lg']" :key="cat" :value="cat">
                                {{ cat.charAt(0).toUpperCase() + cat.slice(1) }}
                            </option>
                        </select>
                    </div>

                    <!-- Loading -->
                    <div v-if="ratingBreakdownLoading" class="p-6 text-center text-gray-500 text-sm">{{ $t('Loading breakdown...') }}</div>

                    <!-- No data -->
                    <div v-else-if="!ratingBreakdownData" class="p-6 text-center text-gray-600 text-sm">{{ $t('No rating data for this physics/category') }}</div>

                    <!-- Breakdown data -->
                    <div v-else>
                        <!-- Summary -->
                        <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-2 px-4 py-3 border-b border-orange-500/10">
                            <div class="bg-gray-900/60 rounded-lg p-2 text-center">
                                <div class="text-[9px] text-gray-500 uppercase">{{ $t('Final Rating') }}</div>
                                <div class="text-lg font-black text-orange-400">{{ ratingBreakdownData.player_rating }}</div>
                            </div>
                            <div class="bg-gray-900/60 rounded-lg p-2 text-center">
                                <div class="text-[9px] text-gray-500 uppercase">{{ $t('Calculated') }}</div>
                                <div class="text-lg font-black text-white">{{ ratingBreakdownData.calculated_rating }}</div>
                            </div>
                            <div class="bg-gray-900/60 rounded-lg p-2 text-center">
                                <div class="text-[9px] text-gray-500 uppercase">{{ $t('Raw (pre-penalty)') }}</div>
                                <div class="text-sm font-bold text-gray-300">{{ ratingBreakdownData.raw_rating }}</div>
                            </div>
                            <div class="bg-gray-900/60 rounded-lg p-2 text-center">
                                <div class="text-[9px] text-gray-500 uppercase">{{ $t('Records') }}</div>
                                <div class="text-sm font-bold text-gray-300">{{ ratingBreakdownData.num_records }}</div>
                            </div>
                            <div class="bg-gray-900/60 rounded-lg p-2 text-center">
                                <div class="text-[9px] text-gray-500 uppercase">{{ $t('Penalty') }}</div>
                                <div class="text-sm font-bold" :class="ratingBreakdownData.penalty < 1 ? 'text-red-400' : 'text-green-400'">
                                    {{ ratingBreakdownData.penalty < 1 ? (ratingBreakdownData.penalty * 100).toFixed(1) + '%' : 'None' }}
                                </div>
                            </div>
                            <div class="bg-gray-900/60 rounded-lg p-2 text-center">
                                <div class="text-[9px] text-gray-500 uppercase">{{ $t('Active Rank') }}</div>
                                <div class="text-sm font-bold text-orange-400">#{{ ratingBreakdownData.active_players_rank || '-' }}</div>
                            </div>
                            <div class="bg-gray-900/60 rounded-lg p-2 text-center">
                                <div class="text-[9px] text-gray-500 uppercase">{{ $t('All Rank') }}</div>
                                <div class="text-sm font-bold text-gray-400">#{{ ratingBreakdownData.all_players_rank }}</div>
                            </div>
                        </div>

                        <!-- Formula reminder -->
                        <div class="px-4 py-2 border-b border-orange-500/10 text-[10px] text-gray-600 font-mono">
                            rating = sum(score * weight) / sum(weight){{ ratingBreakdownData.penalty < 1 ? ` * ${ratingBreakdownData.num_records}/10` : '' }}
                            = {{ ratingBreakdownData.weighted_sum }} / {{ ratingBreakdownData.weight_sum }}{{ ratingBreakdownData.penalty < 1 ? ` * ${ratingBreakdownData.penalty}` : '' }}
                            = <span class="text-orange-400">{{ ratingBreakdownData.calculated_rating }}</span>
                        </div>

                        <!-- Table -->
                        <div class="max-h-[500px] overflow-y-auto">
                            <table class="w-full text-xs">
                                <thead class="sticky top-0 bg-gray-900 z-10">
                                    <tr class="text-gray-500 text-[10px] uppercase">
                                        <th v-for="col in [
                                            { key: 'rank', label: '#', align: 'left' },
                                            { key: 'mapname', label: 'Map', align: 'left' },
                                            { key: 'record_rank', label: 'Rank', align: 'right' },
                                            { key: 'total_players', label: 'Players', align: 'right' },
                                            { key: 'time', label: 'Time', align: 'right' },
                                            { key: 'reltime', label: 'Reltime', align: 'right' },
                                            { key: 'base_score', label: 'Base', align: 'right' },
                                            { key: 'rank_multiplier', label: 'Rank Mult', align: 'right' },
                                            { key: 'multiplier', label: 'Map Mult', align: 'right' },
                                            { key: 'map_score', label: 'Score', align: 'right' },
                                            { key: 'weight', label: 'Weight', align: 'right' },
                                            { key: 'contribution', label: 'Contrib', align: 'right' },
                                            { key: 'pct', label: '%', align: 'right' },
                                        ]" :key="col.key"
                                            @click="sortBreakdown(col.key)"
                                            class="px-3 py-2 cursor-pointer hover:text-gray-300 transition-colors select-none"
                                            :class="col.align === 'left' ? 'text-left' : 'text-right'">
                                            {{ col.label }}
                                            <span v-if="breakdownSortCol === col.key" class="text-orange-400">{{ breakdownSortAsc ? '&#9650;' : '&#9660;' }}</span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="row in sortedBreakdown" :key="row.rank"
                                        class="border-t border-white/5 hover:bg-white/5 transition-colors"
                                        :class="row.is_outlier ? 'opacity-50' : ''">
                                        <td class="px-3 py-1.5 text-gray-600 font-mono">{{ row.rank }}</td>
                                        <td class="px-3 py-1.5">
                                            <a :href="'/maps/' + encodeURIComponent(row.mapname)" target="_blank"
                                                class="text-gray-300 hover:text-blue-400 transition-colors">{{ row.mapname }}</a>
                                            <span v-if="row.is_outlier" class="ml-1 text-[9px] text-red-500">{{ $t('OUTLIER') }}</span>
                                        </td>
                                        <td class="px-3 py-1.5 text-right font-mono" :class="row.record_rank === 1 ? 'text-yellow-400 font-bold' : row.record_rank <= 3 ? 'text-orange-400' : 'text-gray-500'">
                                            {{ row.record_rank ? '#' + row.record_rank : '-' }}
                                        </td>
                                        <td class="px-3 py-1.5 text-right font-mono text-gray-500">{{ row.total_players || '-' }}</td>
                                        <td class="px-3 py-1.5 text-right font-mono text-gray-400">{{ formatTimeMs(row.time) }}</td>
                                        <td class="px-3 py-1.5 text-right font-mono" :class="row.reltime <= 1.05 ? 'text-green-400' : row.reltime <= 1.25 ? 'text-yellow-400' : row.reltime <= 2 ? 'text-orange-400' : 'text-red-400'">
                                            {{ row.reltime.toFixed(4) }}
                                        </td>
                                        <td class="px-3 py-1.5 text-right font-mono text-gray-400">{{ row.base_score?.toFixed(2) || row.map_score.toFixed(2) }}</td>
                                        <td class="px-3 py-1.5 text-right font-mono" :class="(row.rank_multiplier ?? 1) >= 0.9 ? 'text-green-400' : (row.rank_multiplier ?? 1) >= 0.5 ? 'text-yellow-400' : 'text-red-400'">
                                            {{ ((row.rank_multiplier ?? 1) * 100).toFixed(2) }}%
                                        </td>
                                        <td class="px-3 py-1.5 text-right font-mono" :class="row.multiplier >= 0.9 ? 'text-green-400' : row.multiplier >= 0.5 ? 'text-yellow-400' : 'text-red-400'">
                                            {{ (row.multiplier * 100).toFixed(2) }}%
                                        </td>
                                        <td class="px-3 py-1.5 text-right font-mono font-bold" :class="row.map_score >= 800 ? 'text-green-400' : row.map_score >= 500 ? 'text-yellow-400' : 'text-gray-400'">
                                            {{ row.map_score.toFixed(2) }}
                                        </td>
                                        <td class="px-3 py-1.5 text-right font-mono text-gray-500">{{ row.weight.toFixed(4) }}</td>
                                        <td class="px-3 py-1.5 text-right font-mono text-orange-400/80">{{ row.contribution.toFixed(2) }}</td>
                                        <td class="px-3 py-1.5 text-right font-mono text-gray-500">{{ row.pct?.toFixed(2) }}%</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- EPIC REDESIGN - Main Content (Records Tab) -->
        <div v-show="activeTab === 'records' && !ownProfileNotLinked" class="max-w-8xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <div v-if="!user?.id && !$page.props.auth?.user?.mdd_id" class="relative z-20 mb-6">
                <div class="bg-gradient-to-r from-orange-500/10 via-orange-500/20 to-orange-500/10 border border-orange-500/30 rounded-2xl px-8 py-6 text-center backdrop-blur-sm">
                    <div class="text-3xl font-black text-orange-400 mb-2">{{ $t('Not Linked Account') }}</div>
                    <div v-if="!$page.props.auth?.user" class="text-lg font-semibold text-orange-200/80">{{ $t('Is this you? Register and claim this profile to get full access!') }}</div>
                    <div v-else class="text-lg font-semibold text-orange-200/80">{{ $t('Is this you? Link your account to claim this profile!') }}</div>
                    <div class="flex justify-center gap-4 mt-4">
                        <template v-if="!$page.props.auth?.user">
                            <a href="/login" class="px-6 py-2 bg-blue-600 hover:bg-blue-500 text-white font-bold rounded-lg transition-colors">{{ $t('Login') }}</a>
                            <a href="/register" class="px-6 py-2 bg-green-600 hover:bg-green-500 text-white font-bold rounded-lg transition-colors">{{ $t('Register') }}</a>
                        </template>
                        <Link v-else href="/link-account" class="px-6 py-2 bg-yellow-600 hover:bg-yellow-500 text-white font-bold rounded-lg transition-colors">{{ $t('Link Account') }}</Link>
                    </div>
                </div>
            </div>

            <!-- Stats Grid - Clean Text Layout -->
            <div v-if="hasProfile && profile" class="relative z-[1] grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6" :class="profileLocked ? 'select-none' : ''">
                <!-- Locked: one overlay over the whole grid, blurring what is
                     under it (stand-in numbers, see shownProfile). -->
                <div v-if="profileLocked" class="absolute inset-0 z-10 rounded-xl backdrop-blur-[6px] bg-black/20 flex items-center justify-center p-4 pointer-events-auto">
                    <div class="bg-[#0a0e19]/95 border border-white/10 rounded-lg px-5 py-3 text-center max-w-xs">
                        <div class="text-sm font-bold text-white">{{ $t('Verified accounts only') }}</div>
                        <div class="text-xs text-gray-400 mt-0.5">
                            <a v-if="lockedAsGuest" href="/login" class="text-blue-400 font-bold hover:text-blue-300">{{ $t('Log in and verify to see this') }}</a>
                            <Link v-else href="/email/verify" class="text-red-400 font-bold hover:text-red-300">{{ $t('Verify your email to see this') }}</Link>
                        </div>
                    </div>
                </div>
                <div v-if="showStatBox('performance')" class="bg-black/40 backdrop-blur-sm rounded-xl p-4 shadow-2xl border border-white/5" :style="{ order: statBoxOrder('performance') }">
                    <div class="flex justify-between items-center mb-3">
                        <h3 class="text-sm font-bold text-white uppercase tracking-wide">{{ $t('Performance') }}</h3>
                        <div class="flex items-center gap-0">
                            <span :class="['text-xs font-black uppercase tracking-wider px-2.5 py-1 rounded-l border', cpmFirst ? 'text-purple-400 bg-purple-400/20 border-purple-400/30' : 'text-blue-400 bg-blue-400/20 border-blue-400/30']">{{ cpmFirst ? 'CPM' : 'VQ3' }}</span>
                            <span :class="['text-xs font-black uppercase tracking-wider px-2.5 py-1 rounded-r border border-l-0', cpmFirst ? 'text-blue-400 bg-blue-400/20 border-blue-400/30' : 'text-purple-400 bg-purple-400/20 border-purple-400/30']">{{ cpmFirst ? 'VQ3' : 'CPM' }}</span>
                        </div>
                    </div>
                    <div class="space-y-2">
                        <div class="flex justify-between items-center group relative">
                            <span class="text-xs text-gray-400 cursor-help">{{ $t('Total Records') }}</span>
                            <div class="absolute left-0 bottom-full mb-2 hidden group-hover:block z-10 w-64 p-2 bg-black/90 border border-white/20 rounded-lg text-xs text-gray-300">
                                {{ $t('Total number of records you have set across all maps') }}
                            </div>
                            <div class="flex items-center">
                                <span :class="['text-sm font-bold tabular-nums w-10 text-right', cpmFirst ? 'text-purple-400' : 'text-blue-400']">{{ cpmFirst ? (shownProfile.cpm_records || 0) : (shownProfile.vq3_records || 0) }}</span>
                                <span class="text-xs text-gray-600 w-4 text-center">/</span>
                                <span :class="['text-sm font-bold tabular-nums w-10 text-right', cpmFirst ? 'text-blue-400' : 'text-purple-400']">{{ cpmFirst ? (shownProfile.vq3_records || 0) : (shownProfile.cpm_records || 0) }}</span>
                            </div>
                        </div>
                        <div class="flex justify-between items-center group relative">
                            <span class="text-xs text-gray-400 cursor-help">{{ $t('World Records') }}</span>
                            <div class="absolute left-0 bottom-full mb-2 hidden group-hover:block z-10 w-64 p-2 bg-black/90 border border-white/20 rounded-lg text-xs text-gray-300">
                                {{ $t('Number of #1 ranked records you currently hold') }}
                            </div>
                            <div class="flex items-center">
                                <span :class="['text-sm font-bold tabular-nums w-10 text-right', cpmFirst ? 'text-purple-400' : 'text-blue-400']">{{ cpmFirst ? cpm_world_records : vq3_world_records }}</span>
                                <span class="text-xs text-gray-600 w-4 text-center">/</span>
                                <span :class="['text-sm font-bold tabular-nums w-10 text-right', cpmFirst ? 'text-blue-400' : 'text-purple-400']">{{ cpmFirst ? vq3_world_records : cpm_world_records }}</span>
                            </div>
                        </div>
                        <div class="flex justify-between items-center group relative">
                            <span class="text-xs text-gray-400 cursor-help">{{ $t('Top 3 Positions') }}</span>
                            <div class="absolute left-0 bottom-full mb-2 hidden group-hover:block z-10 w-64 p-2 bg-black/90 border border-white/20 rounded-lg text-xs text-gray-300">
                                {{ $t('Records ranked between 1st and 3rd place') }}
                            </div>
                            <div class="flex items-center">
                                <span :class="['text-sm font-bold tabular-nums w-10 text-right', cpmFirst ? 'text-purple-400' : 'text-blue-400']">{{ cpmFirst ? (shownProfile.cpm_top3 || 0) : (shownProfile.vq3_top3 || 0) }}</span>
                                <span class="text-xs text-gray-600 w-4 text-center">/</span>
                                <span :class="['text-sm font-bold tabular-nums w-10 text-right', cpmFirst ? 'text-blue-400' : 'text-purple-400']">{{ cpmFirst ? (shownProfile.vq3_top3 || 0) : (shownProfile.cpm_top3 || 0) }}</span>
                            </div>
                        </div>
                        <div class="flex justify-between items-center group relative">
                            <span class="text-xs text-gray-400 cursor-help">{{ $t('Top 10 Positions') }}</span>
                            <div class="absolute left-0 bottom-full mb-2 hidden group-hover:block z-10 w-64 p-2 bg-black/90 border border-white/20 rounded-lg text-xs text-gray-300">
                                {{ $t('Records ranked between 1st and 10th place') }}
                            </div>
                            <div class="flex items-center">
                                <span :class="['text-sm font-bold tabular-nums w-10 text-right', cpmFirst ? 'text-purple-400' : 'text-blue-400']">{{ cpmFirst ? (shownProfile.cpm_top10 || 0) : (shownProfile.vq3_top10 || 0) }}</span>
                                <span class="text-xs text-gray-600 w-4 text-center">/</span>
                                <span :class="['text-sm font-bold tabular-nums w-10 text-right', cpmFirst ? 'text-blue-400' : 'text-purple-400']">{{ cpmFirst ? (shownProfile.vq3_top10 || 0) : (shownProfile.cpm_top10 || 0) }}</span>
                            </div>
                        </div>
                        <div class="flex justify-between items-center group relative">
                            <span class="text-xs text-gray-400 cursor-help">{{ $t('Average Rank') }}</span>
                            <div class="absolute left-0 bottom-full mb-2 hidden group-hover:block z-10 w-64 p-2 bg-black/90 border border-white/20 rounded-lg text-xs text-gray-300">
                                {{ $t('Your average ranking position across all records (lower is better)') }}
                            </div>
                            <div class="flex items-center">
                                <span :class="['text-sm font-bold tabular-nums w-10 text-right', cpmFirst ? 'text-purple-400' : 'text-blue-400']">{{ cpmFirst ? (shownProfile.cpm_avg_rank || 0) : (shownProfile.vq3_avg_rank || 0) }}</span>
                                <span class="text-xs text-gray-600 w-4 text-center">/</span>
                                <span :class="['text-sm font-bold tabular-nums w-10 text-right', cpmFirst ? 'text-blue-400' : 'text-purple-400']">{{ cpmFirst ? (shownProfile.vq3_avg_rank || 0) : (shownProfile.cpm_avg_rank || 0) }}</span>
                            </div>
                        </div>
                        <div class="flex justify-between items-center group relative">
                            <span class="text-xs text-gray-400 cursor-help">{{ $t('Dominance') }}</span>
                            <div class="absolute left-0 bottom-full mb-2 hidden group-hover:block z-10 w-64 p-2 bg-black/90 border border-white/20 rounded-lg text-xs text-gray-300">
                                {{ $t('Percentage of your records that are in top 10 positions') }}
                            </div>
                            <div class="flex items-center">
                                <span :class="['text-sm font-bold tabular-nums w-10 text-right', cpmFirst ? 'text-purple-400' : 'text-blue-400']">{{ cpmFirst ? (shownProfile.cpm_dominance || 0) : (shownProfile.vq3_dominance || 0) }}%</span>
                                <span class="text-xs text-gray-600 w-4 text-center">/</span>
                                <span :class="['text-sm font-bold tabular-nums w-10 text-right', cpmFirst ? 'text-blue-400' : 'text-purple-400']">{{ cpmFirst ? (shownProfile.vq3_dominance || 0) : (shownProfile.cpm_dominance || 0) }}%</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Map Features -->
                <div v-if="showStatBox('map_features')" class="bg-black/40 backdrop-blur-sm rounded-xl p-4 shadow-2xl border border-white/5" :style="{ order: statBoxOrder('map_features') }">
                    <div class="flex justify-between items-center mb-3">
                        <h3 class="text-sm font-bold text-white uppercase tracking-wide">{{ $t('Map Features') }}</h3>
                        <div class="flex items-center gap-0">
                            <span :class="['text-xs font-black uppercase tracking-wider px-2.5 py-1 rounded-l border', cpmFirst ? 'text-purple-400 bg-purple-400/20 border-purple-400/30' : 'text-blue-400 bg-blue-400/20 border-blue-400/30']">{{ cpmFirst ? 'CPM' : 'VQ3' }}</span>
                            <span :class="['text-xs font-black uppercase tracking-wider px-2.5 py-1 rounded-r border border-l-0', cpmFirst ? 'text-blue-400 bg-blue-400/20 border-blue-400/30' : 'text-purple-400 bg-purple-400/20 border-purple-400/30']">{{ cpmFirst ? 'VQ3' : 'CPM' }}</span>
                        </div>
                    </div>
                    <div class="space-y-2">
                        <div class="flex justify-between items-center group relative">
                            <span class="text-xs text-gray-400 cursor-help">{{ $t('Unique Maps') }}</span>
                            <div class="absolute left-0 bottom-full mb-2 hidden group-hover:block z-10 w-64 p-2 bg-black/90 border border-white/20 rounded-lg text-xs text-gray-300">
                                {{ $t('Number of different maps you have set records on') }}
                            </div>
                            <div class="flex items-center">
                                <span :class="['text-sm font-bold tabular-nums w-10 text-right', cpmFirst ? 'text-purple-400' : 'text-blue-400']">{{ cpmFirst ? (shownProfile.cpm_unique_maps || 0) : (shownProfile.vq3_unique_maps || 0) }}</span>
                                <span class="text-xs text-gray-600 w-4 text-center">/</span>
                                <span :class="['text-sm font-bold tabular-nums w-10 text-right', cpmFirst ? 'text-blue-400' : 'text-purple-400']">{{ cpmFirst ? (shownProfile.vq3_unique_maps || 0) : (shownProfile.cpm_unique_maps || 0) }}</span>
                            </div>
                        </div>
                        <div class="flex justify-between items-center group relative">
                            <span class="text-xs text-gray-400 cursor-help">{{ $t('Slick') }}</span>
                            <div class="absolute left-0 bottom-full mb-2 hidden group-hover:block z-10 w-64 p-2 bg-black/90 border border-white/20 rounded-lg text-xs text-gray-300">
                                {{ $t('Records on maps with slick (low friction) surfaces') }}
                            </div>
                            <div class="flex items-center">
                                <span :class="['text-sm font-bold tabular-nums w-10 text-right', cpmFirst ? 'text-purple-400' : 'text-blue-400']">{{ cpmFirst ? (shownProfile.cpm_slick || 0) : (shownProfile.vq3_slick || 0) }}</span>
                                <span class="text-xs text-gray-600 w-4 text-center">/</span>
                                <span :class="['text-sm font-bold tabular-nums w-10 text-right', cpmFirst ? 'text-blue-400' : 'text-purple-400']">{{ cpmFirst ? (shownProfile.vq3_slick || 0) : (shownProfile.cpm_slick || 0) }}</span>
                            </div>
                        </div>
                        <div class="flex justify-between items-center group relative">
                            <span class="text-xs text-gray-400 cursor-help">{{ $t('Jump Pad') }}</span>
                            <div class="absolute left-0 bottom-full mb-2 hidden group-hover:block z-10 w-64 p-2 bg-black/90 border border-white/20 rounded-lg text-xs text-gray-300">
                                {{ $t('Records on maps featuring jump pads') }}
                            </div>
                            <div class="flex items-center">
                                <span :class="['text-sm font-bold tabular-nums w-10 text-right', cpmFirst ? 'text-purple-400' : 'text-blue-400']">{{ cpmFirst ? (shownProfile.cpm_jumppad || 0) : (shownProfile.vq3_jumppad || 0) }}</span>
                                <span class="text-xs text-gray-600 w-4 text-center">/</span>
                                <span :class="['text-sm font-bold tabular-nums w-10 text-right', cpmFirst ? 'text-blue-400' : 'text-purple-400']">{{ cpmFirst ? (shownProfile.vq3_jumppad || 0) : (shownProfile.cpm_jumppad || 0) }}</span>
                            </div>
                        </div>
                        <div class="flex justify-between items-center group relative">
                            <span class="text-xs text-gray-400 cursor-help">{{ $t('Teleporter') }}</span>
                            <div class="absolute left-0 bottom-full mb-2 hidden group-hover:block z-10 w-64 p-2 bg-black/90 border border-white/20 rounded-lg text-xs text-gray-300">
                                {{ $t('Records on maps featuring teleporters') }}
                            </div>
                            <div class="flex items-center">
                                <span :class="['text-sm font-bold tabular-nums w-10 text-right', cpmFirst ? 'text-purple-400' : 'text-blue-400']">{{ cpmFirst ? (shownProfile.cpm_teleporter || 0) : (shownProfile.vq3_teleporter || 0) }}</span>
                                <span class="text-xs text-gray-600 w-4 text-center">/</span>
                                <span :class="['text-sm font-bold tabular-nums w-10 text-right', cpmFirst ? 'text-blue-400' : 'text-purple-400']">{{ cpmFirst ? (shownProfile.vq3_teleporter || 0) : (shownProfile.cpm_teleporter || 0) }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Record Types -->
                <div v-if="showStatBox('record_types') && stats.filter(s => s.value !== 'world_records').some(s => (shownProfile?.hasOwnProperty('cpm_' + s.value) ? shownProfile['cpm_' + s.value] : 0) > 0 || (shownProfile?.hasOwnProperty('vq3_' + s.value) ? shownProfile['vq3_' + s.value] : 0) > 0)" class="bg-black/40 backdrop-blur-sm rounded-xl p-4 shadow-2xl border border-white/5" :style="{ order: statBoxOrder('record_types') }">
                    <div class="flex justify-between items-center mb-3">
                        <h3 class="text-sm font-bold text-white uppercase tracking-wide">{{ $t('Record Types') }}</h3>
                        <div class="flex items-center gap-0">
                            <span :class="['text-xs font-black uppercase tracking-wider px-2.5 py-1 rounded-l border', cpmFirst ? 'text-purple-400 bg-purple-400/20 border-purple-400/30' : 'text-blue-400 bg-blue-400/20 border-blue-400/30']">{{ cpmFirst ? 'CPM' : 'VQ3' }}</span>
                            <span :class="['text-xs font-black uppercase tracking-wider px-2.5 py-1 rounded-r border border-l-0', cpmFirst ? 'text-blue-400 bg-blue-400/20 border-blue-400/30' : 'text-purple-400 bg-purple-400/20 border-purple-400/30']">{{ cpmFirst ? 'VQ3' : 'CPM' }}</span>
                        </div>
                    </div>
                    <div class="space-y-2">
                        <div v-for="stat in stats.filter(s => s.value !== 'world_records' && ((shownProfile?.hasOwnProperty('cpm_' + s.value) ? shownProfile['cpm_' + s.value] : 0) > 0 || (shownProfile?.hasOwnProperty('vq3_' + s.value) ? shownProfile['vq3_' + s.value] : 0) > 0))" :key="stat.value" class="flex justify-between items-center group relative">
                            <span class="text-xs text-gray-400 cursor-help">{{ stat.label.replace(' Records', '') }}</span>
                            <div class="absolute left-0 bottom-full mb-2 hidden group-hover:block z-10 w-64 p-2 bg-black/90 border border-white/20 rounded-lg text-xs text-gray-300">
                                Records set on {{ stat.label.toLowerCase() }} maps or with specific game modes
                            </div>
                            <div class="flex items-center">
                                <span :class="['text-sm font-bold tabular-nums w-10 text-right', cpmFirst ? 'text-purple-400' : 'text-blue-400']">{{ shownProfile?.hasOwnProperty((cpmFirst ? 'cpm_' : 'vq3_') + stat.value) ? shownProfile[(cpmFirst ? 'cpm_' : 'vq3_') + stat.value] : 0 }}</span>
                                <span class="text-xs text-gray-600 w-4 text-center">/</span>
                                <span :class="['text-sm font-bold tabular-nums w-10 text-right', cpmFirst ? 'text-blue-400' : 'text-purple-400']">{{ shownProfile?.hasOwnProperty((cpmFirst ? 'vq3_' : 'cpm_') + stat.value) ? shownProfile[(cpmFirst ? 'vq3_' : 'cpm_') + stat.value] : 0 }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Activity & Misc -->
                <div v-if="showStatBox('activity')" class="bg-black/40 backdrop-blur-sm rounded-xl p-4 shadow-2xl border border-white/5" :style="{ order: statBoxOrder('activity') }">
                    <h3 class="text-sm font-bold text-white uppercase tracking-wide mb-3">{{ $t('Activity') }}</h3>
                    <div class="space-y-2">
                        <div class="flex justify-between items-center group relative">
                            <span class="text-xs text-gray-400 cursor-help">{{ $t('Best Streak') }}</span>
                            <div class="absolute left-0 bottom-full mb-2 hidden group-hover:block z-10 w-64 p-2 bg-black/90 border border-white/20 rounded-lg text-xs text-gray-300">
                                {{ $t('Your longest consecutive days of setting records') }}
                            </div>
                            <span class="text-sm font-bold text-white">{{ shownProfile.longest_streak || 0 }} days</span>
                        </div>
                        <div v-if="shownProfile.most_active_month" class="flex justify-between items-center group relative">
                            <span class="text-xs text-gray-400 cursor-help">{{ $t('Most Active') }}</span>
                            <div class="absolute left-0 bottom-full mb-2 hidden group-hover:block z-10 w-64 p-2 bg-black/90 border border-white/20 rounded-lg text-xs text-gray-300">
                                {{ $t('The month when you set the most records') }}
                            </div>
                            <span class="text-sm font-bold text-white">{{ shownProfile.most_active_month.month }}</span>
                        </div>
                        <div v-if="shownProfile.first_record_date" class="flex justify-between items-center group relative">
                            <span class="text-xs text-gray-400 cursor-help">{{ $t('First Record') }}</span>
                            <div class="absolute left-0 bottom-full mb-2 hidden group-hover:block z-10 w-64 p-2 bg-black/90 border border-white/20 rounded-lg text-xs text-gray-300">
                                {{ $t('When you set your very first record') }}
                            </div>
                            <span class="text-sm font-bold text-white">{{ new Date(shownProfile.first_record_date).toLocaleDateString('en-US', { year: 'numeric', month: 'short' }) }}</span>
                        </div>
                        <div v-if="shownProfile.weapon_specialist" class="flex justify-between items-center group relative">
                            <span class="text-xs text-gray-400 cursor-help">{{ $t('Best Weapon') }}</span>
                            <div class="absolute left-0 bottom-full mb-2 hidden group-hover:block z-10 w-64 p-2 bg-black/90 border border-white/20 rounded-lg text-xs text-gray-300">
                                {{ $t('The weapon category where you have the most records') }}
                            </div>
                            <span class="text-sm font-bold text-white capitalize">{{ shownProfile.weapon_specialist }}</span>
                        </div>
                    </div>
                </div>

                <!-- Demos Statistics -->
                <div v-if="showStatBox('demos_statistics')" class="bg-black/40 backdrop-blur-sm rounded-xl p-4 shadow-2xl border border-white/5" :style="{ order: statBoxOrder('demos_statistics') }">
                    <div class="flex justify-between items-center mb-3">
                        <h3 class="text-sm font-bold text-white uppercase tracking-wide">{{ $t('Demos') }}</h3>
                        <div class="flex items-center gap-0">
                            <span class="text-xs font-black uppercase tracking-wider px-2.5 py-1 rounded border text-pink-400 bg-pink-400/20 border-pink-400/30">{{ $t('Stats') }}</span>
                        </div>
                    </div>
                    <div class="space-y-2">
                        <div class="flex justify-between items-center group relative">
                            <span class="text-xs text-gray-400 cursor-help">{{ $t('Uploaded Demos') }}</span>
                            <div class="absolute left-0 bottom-full mb-2 hidden group-hover:block z-10 w-64 p-2 bg-black/90 border border-white/20 rounded-lg text-xs text-gray-300">
                                {{ $t('Total number of demos uploaded by this player') }}
                            </div>
                            <span class="text-sm font-bold text-pink-400 tabular-nums">{{ demoStats.total_demos || 0 }}</span>
                        </div>
                        <div class="flex justify-between items-center group relative">
                            <span class="text-xs text-gray-400 cursor-help">{{ $t('Total Downloads') }}</span>
                            <div class="absolute left-0 bottom-full mb-2 hidden group-hover:block z-10 w-64 p-2 bg-black/90 border border-white/20 rounded-lg text-xs text-gray-300">
                                {{ $t("How many times this player's demos have been downloaded") }}
                            </div>
                            <span class="text-sm font-bold text-pink-400 tabular-nums">{{ demoStats.total_downloads || 0 }}</span>
                        </div>
                        <div class="flex justify-between items-center group relative">
                            <span class="text-xs text-gray-400 cursor-help">{{ $t('Downloaded Demos') }}</span>
                            <div class="absolute left-0 bottom-full mb-2 hidden group-hover:block z-10 w-64 p-2 bg-black/90 border border-white/20 rounded-lg text-xs text-gray-300">
                                {{ $t('Number of demos that have at least one download') }}
                            </div>
                            <span class="text-sm font-bold text-pink-400 tabular-nums">{{ demoStats.demos_with_downloads || 0 }}</span>
                        </div>
                        <div class="flex justify-between items-center group relative">
                            <span class="text-xs text-gray-400 cursor-help">{{ $t('Unique Maps') }}</span>
                            <div class="absolute left-0 bottom-full mb-2 hidden group-hover:block z-10 w-64 p-2 bg-black/90 border border-white/20 rounded-lg text-xs text-gray-300">
                                {{ $t('Number of different maps this player has demos on') }}
                            </div>
                            <span class="text-sm font-bold text-pink-400 tabular-nums">{{ demoStats.unique_maps || 0 }}</span>
                        </div>
                        <div class="flex justify-between items-center group relative">
                            <span class="text-xs text-gray-400 cursor-help">{{ $t('Most Downloaded') }}</span>
                            <div class="absolute left-0 bottom-full mb-2 hidden group-hover:block z-10 w-64 p-2 bg-black/90 border border-white/20 rounded-lg text-xs text-gray-300">
                                {{ $t('Highest download count on a single demo') }}
                            </div>
                            <span class="text-sm font-bold text-pink-400 tabular-nums">{{ demoStats.most_downloaded || 0 }}</span>
                        </div>
                    </div>
                </div>

                <!-- Renders Statistics -->
                <div v-if="showStatBox('renders') && (renderStats.total_renders || 0) > 0" class="bg-black/40 backdrop-blur-sm rounded-xl p-4 shadow-2xl border border-white/5" :style="{ order: statBoxOrder('renders') }">
                    <div class="flex justify-between items-center mb-3">
                        <h3 class="text-sm font-bold text-white uppercase tracking-wide">{{ $t('Renders') }}</h3>
                        <div class="flex items-center gap-0">
                            <span class="text-xs font-black uppercase tracking-wider px-2.5 py-1 rounded border text-red-400 bg-red-400/20 border-red-400/30">{{ $t('YouTube') }}</span>
                        </div>
                    </div>
                    <div class="space-y-2">
                        <div class="flex justify-between items-center group relative">
                            <span class="text-xs text-gray-400 cursor-help">{{ $t('Videos Rendered') }}</span>
                            <div class="absolute left-0 bottom-full mb-2 hidden group-hover:block z-10 w-64 p-2 bg-black/90 border border-white/20 rounded-lg text-xs text-gray-300">
                                {{ $t('Total number of demos rendered to YouTube videos') }}
                            </div>
                            <span class="text-sm font-bold text-red-400 tabular-nums">{{ renderStats.total_renders || 0 }}</span>
                        </div>
                        <div class="flex justify-between items-center group relative">
                            <span class="text-xs text-gray-400 cursor-help">{{ $t('Render Time') }}</span>
                            <div class="absolute left-0 bottom-full mb-2 hidden group-hover:block z-10 w-64 p-2 bg-black/90 border border-white/20 rounded-lg text-xs text-gray-300">
                                {{ $t('Total time spent rendering demos to video') }}
                            </div>
                            <span class="text-sm font-bold text-red-400 tabular-nums">{{ Math.round((renderStats.total_render_seconds || 0) / 3600 * 10) / 10 }}h</span>
                        </div>
                    </div>
                </div>

                <!-- Top Downloaded Demos -->
                <div v-if="showStatBox('top_downloaded_demos')" class="bg-black/40 backdrop-blur-sm rounded-xl p-4 shadow-2xl border border-white/5" :style="{ order: statBoxOrder('top_downloaded_demos') }">
                    <div class="flex justify-between items-center mb-3">
                        <h3 class="text-sm font-bold text-white uppercase tracking-wide">{{ $t('Top Demos') }}</h3>
                        <div class="flex items-center gap-0">
                            <span class="text-xs font-black uppercase tracking-wider px-2.5 py-1 rounded border text-cyan-400 bg-cyan-400/20 border-cyan-400/30">{{ $t('Downloads') }}</span>
                        </div>
                    </div>
                    <div v-if="topDownloadedDemos && topDownloadedDemos.length > 0" class="space-y-1.5">
                        <Link
                            v-for="demo in topDownloadedDemos"
                            :key="demo.id"
                            :href="`/maps/${encodeURIComponent(demo.map_name)}`"
                            class="flex items-center justify-between rounded-lg px-2 py-1 hover:bg-white/5 transition-colors group"
                        >
                            <span class="text-xs text-gray-400 group-hover:text-white truncate mr-2">{{ demo.processed_filename || demo.original_filename }}</span>
                            <span class="text-xs font-bold text-cyan-400 tabular-nums whitespace-nowrap">{{ demo.download_count }}</span>
                        </Link>
                    </div>
                    <div v-else class="text-xs text-gray-600 text-center py-2">{{ $t('No downloaded demos') }}</div>
                </div>
            </div>
            <!-- Global Customization Notices -->
            <div v-if="globallyHiddenStatBoxes.length > 0" class="mb-2">
                <Link :href="route('settings.show') + '?tab=global-customize'" class="flex items-center gap-2.5 px-4 py-2 bg-yellow-500/10 border border-yellow-500/20 rounded-lg hover:bg-yellow-500/15 hover:border-yellow-500/30 transition-all group">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 text-yellow-400 shrink-0">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                    </svg>
                    <span class="text-xs text-yellow-300">
                        {{ $t('Your global customization is hiding stat boxes:') }} <span class="font-bold text-yellow-200">{{ globallyHiddenStatBoxes.map(id => statBoxLabels[id] || id).join(', ') }}</span>
                    </span>
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3.5 h-3.5 text-yellow-500 group-hover:text-yellow-300 transition-colors shrink-0 ml-auto">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                    </svg>
                </Link>
            </div>
            <div v-if="globallyHiddenSections.length > 0" class="mb-4">
                <Link :href="route('settings.show') + '?tab=global-customize'" class="flex items-center gap-2.5 px-4 py-2 bg-yellow-500/10 border border-yellow-500/20 rounded-lg hover:bg-yellow-500/15 hover:border-yellow-500/30 transition-all group">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 text-yellow-400 shrink-0">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                    </svg>
                    <span class="text-xs text-yellow-300">
                        {{ $t('Your global customization is hiding sections:') }} <span class="font-bold text-yellow-200">{{ globallyHiddenSections.map(id => sectionLabels[id] || id).join(', ') }}</span>
                    </span>
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3.5 h-3.5 text-yellow-500 group-hover:text-yellow-300 transition-colors shrink-0 ml-auto">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                    </svg>
                </Link>
            </div>

            <!-- Reorderable Sections Container -->
            <div class="flex flex-col">

            <!-- Activity History Heatmap -->
            <div v-if="hasProfile && showSection('activity_history') && activity_years && activity_years.length > 0" class="mb-6 relative" :style="{ order: sectionOrder('activity_history') }">
                <div v-if="profileLocked" class="absolute inset-0 z-10 rounded-xl backdrop-blur-[6px] bg-black/20 flex items-center justify-center p-4">
                    <div class="bg-[#0a0e19]/95 border border-white/10 rounded-lg px-5 py-3 text-center max-w-xs">
                        <div class="text-sm font-bold text-white">{{ $t('Verified accounts only') }}</div>
                        <div class="text-xs text-gray-400 mt-0.5">
                            <a v-if="lockedAsGuest" href="/login" class="text-blue-400 font-bold hover:text-blue-300">{{ $t('Log in and verify to see this') }}</a>
                            <Link v-else href="/email/verify" class="text-red-400 font-bold hover:text-red-300">{{ $t('Verify your email to see this') }}</Link>
                        </div>
                    </div>
                </div>
                <ActivityHeatmap
                    :activityData="profileLocked ? fakeActivity : activity_data"
                    :activityYear="activity_year"
                    :activityYears="activity_years"
                    :mddId="profile?.id || profile?.mdd_id"
                    :isNew="isOwnProfile && isNewSection('activity_history')"
                    :customizeUrl="route('settings.show') + '?tab=customize'"
                />
            </div>

            <!-- Rendered Videos -->
            <div v-if="showSection('rendered_videos') && latestRenderedVideos && latestRenderedVideos.length > 0" class="bg-black/40 backdrop-blur-sm rounded-xl p-6 shadow-2xl border border-white/5 mb-6" :style="{ order: sectionOrder('rendered_videos') }">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-bold text-white flex items-center gap-2">
                        <svg class="w-5 h-5 text-red-400" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814z"/>
                            <path d="M9.545 15.568V8.432L15.818 12l-6.273 3.568z" fill="#fff"/>
                        </svg>
                        {{ $t('Rendered Videos') }}
                    </h3>
                    <a href="/rendered-demos" class="text-xs text-gray-400 hover:text-white transition-colors">{{ $t('View all') }}</a>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                    <a
                        v-for="video in latestRenderedVideos"
                        :key="video.id"
                        :href="video.youtube_url"
                        target="_blank"
                        rel="noopener"
                        class="group relative rounded-lg overflow-hidden bg-black/40 border border-white/5 hover:border-white/20 transition-all"
                    >
                        <div class="aspect-video relative">
                            <img
                                :src="`https://img.youtube.com/vi/${video.youtube_video_id}/mqdefault.jpg`"
                                :alt="video.map_name"
                                class="w-full h-full object-cover"
                                loading="lazy"
                                decoding="async"
                            />
                            <div class="absolute inset-0 bg-black/30 group-hover:bg-black/10 transition-colors flex items-center justify-center">
                                <svg class="w-8 h-8 text-white/70 group-hover:text-white/90 transition-colors" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M8 5v14l11-7z"/>
                                </svg>
                            </div>
                        </div>
                        <div class="p-2">
                            <div class="text-xs font-semibold text-white truncate">{{ video.map_name }}</div>
                            <div class="text-xs text-gray-500 uppercase">{{ video.physics }}</div>
                        </div>
                    </a>
                </div>
            </div>

            <!-- Known Aliases -->
            <div v-if="showSection('known_aliases') && ((aliases && aliases.length > 0) || can_suggest_alias || (alias_suggestions && alias_suggestions.length > 0))" class="mb-6" :style="{ order: sectionOrder('known_aliases') }">
                <div>
                    <div class="bg-black/40 backdrop-blur-sm rounded-xl p-4 shadow-2xl border border-white/5">
                        <div class="flex items-center justify-between" :class="aliasesExpanded ? 'mb-3' : ''">
                            <h3 class="text-lg font-bold text-white flex items-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5 text-indigo-400">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                                </svg>
                                {{ $t('Known Aliases') }}
                                <span class="text-sm font-normal text-gray-500">({{ aliases?.length || 0 }})</span>
                            </h3>
                            <div class="flex items-center gap-2">
                                <!-- Edit button (own profile) -->
                                <Link
                                    v-if="can_manage_aliases"
                                    :href="route('settings.show')"
                                    class="p-1.5 rounded-lg text-gray-500 hover:text-indigo-400 hover:bg-white/5 transition-all"
                                    :title="$t('Edit aliases')"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                                    </svg>
                                </Link>
                                <!-- Suggest Alias button (for other profiles) -->
                                <button
                                    v-if="can_suggest_alias"
                                    @click="openSuggestionModal"
                                    class="px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold transition-colors flex items-center gap-1.5"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                    </svg>
                                    {{ $t('Suggest Alias') }}
                                </button>
                                <!-- Expand/Collapse button -->
                                <button
                                    v-if="aliases && aliases.length > 5"
                                    @click="aliasesExpanded = !aliasesExpanded"
                                    class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-semibold transition-all"
                                    :class="aliasesExpanded ? 'bg-indigo-500/10 text-indigo-400 border border-indigo-500/30' : 'bg-indigo-500/20 text-indigo-300 border border-indigo-400/40 animate-pulse'"
                                >
                                    <svg class="w-4 h-4 transition-transform duration-300" :class="aliasesExpanded ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
                                    {{ aliasesExpanded ? 'Less' : 'Show all' }}
                                </button>
                            </div>
                        </div>
                        <div v-show="aliasesExpanded">
                        <p class="text-xs text-gray-400 mb-3">{{ $t('Aliases are alternative nicknames this player has used in Defrag. They help match uploaded demos and make the player searchable by any of their past names.') }}</p>
                        </div>

                        <!-- Pending Suggestions (only visible on own profile) -->
                        <div v-if="alias_suggestions && alias_suggestions.length > 0" class="mb-4">
                            <h4 class="text-sm font-semibold text-yellow-400 mb-2">{{ $t('Pending Suggestions') }}</h4>
                            <div class="space-y-2">
                                <div
                                    v-for="suggestion in alias_suggestions"
                                    :key="suggestion.id"
                                    class="flex items-center justify-between bg-yellow-500/10 border border-yellow-500/30 rounded-lg px-3 py-2"
                                >
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2 mb-1">
                                            <span class="text-white font-semibold" v-html="q3tohtml(suggestion.alias)"></span>
                                        </div>
                                        <div class="text-xs text-gray-400">
                                            {{ $t('Suggested by') }} <span class="text-white" v-html="q3tohtml(suggestion.suggested_by.name)"></span>
                                            <span v-if="suggestion.note" class="block mt-1 text-gray-500">{{ suggestion.note }}</span>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2 ml-3">
                                        <button
                                            @click="approveSuggestion(suggestion)"
                                            class="px-3 py-1.5 rounded-lg bg-green-600 hover:bg-green-700 text-white text-xs font-semibold transition-colors"
                                            :title="$t('Approve and add this alias')"
                                        >
                                            {{ $t('Approve') }}
                                        </button>
                                        <button
                                            @click="rejectSuggestion(suggestion)"
                                            class="px-3 py-1.5 rounded-lg bg-red-600 hover:bg-red-700 text-white text-xs font-semibold transition-colors"
                                            :title="$t('Reject this suggestion')"
                                        >
                                            {{ $t('Reject') }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Approved Aliases -->
                        <div v-if="aliases && aliases.length > 0" class="relative" :class="!aliasesExpanded ? 'mt-3' : ''">
                        <!-- Fade overlay when collapsed -->
                        <div v-if="!aliasesExpanded && aliases.length > 5" class="absolute bottom-0 left-0 right-0 h-8 bg-gradient-to-t from-black/80 to-transparent z-10 pointer-events-none rounded-b-lg"></div>
                        <div class="flex flex-wrap gap-2 overflow-hidden transition-all duration-300" :style="aliasesExpanded ? '' : 'max-height: 38px'">
                            <div
                                v-for="alias in aliases"
                                :key="alias.alias_colored || alias.alias"
                                class="px-3 py-1.5 rounded-lg border border-white/10 backdrop-blur-[4px] text-sm flex items-center gap-2"
                                style="background: rgba(71,85,105,0.55);"
                                :title="alias.usage_count ? alias.usage_count + ' records with this name' : ''"
                            >
                                <span v-html="q3tohtml(alias.alias_colored || alias.alias)"></span>
                                <span v-if="alias.usage_count" class="text-[10px] text-gray-500 tabular-nums">({{ alias.usage_count }})</span>
                                <button
                                    v-if="$page.props.auth.user && user?.id && $page.props.auth.user.id !== user.id && alias.source !== 'mdd_import'"
                                    @click="reportAlias(alias)"
                                    class="text-gray-400 hover:text-red-400 transition-colors"
                                    :title="$t('Report this alias')"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>
                        </div>

                        <!-- No aliases message -->
                        <div v-else-if="!aliases || aliases.length === 0" class="text-center py-4">
                            <p class="text-gray-500 text-sm">{{ $t('No aliases yet') }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Records Container with Sidebar Tabs -->
            <div v-if="hasProfile && showSection('records')" class="mb-6" :style="{ order: sectionOrder('records') }">
                <!-- The records and the freestyle demos are the same thing seen
                     two ways: everything this player left behind on a map. The
                     search and the sort belong to the records, so they sit
                     inside that tab rather than above both. -->
                <div class="flex flex-col sm:flex-row gap-2 mb-4">
                    <button
                        @click="recordsTab = 'records'"
                        class="flex-1 flex items-center justify-center gap-3 px-6 py-4 rounded-xl border-2 transition-all duration-200"
                        :class="recordsTab === 'records'
                            ? 'bg-gradient-to-br from-blue-600/80 to-blue-500/50 border-blue-400 text-white shadow-lg shadow-blue-500/20'
                            : 'bg-black/40 border-white/10 text-gray-500 hover:text-gray-200 hover:border-white/25'"
                    >
                        <svg class="w-6 h-6 fill-current shrink-0" viewBox="0 0 20 20">
                            <use href="/images/svg/icons.svg#icon-trophy"></use>
                        </svg>
                        <span class="text-lg font-black uppercase tracking-wide">{{ $t('Records') }}</span>
                        <span
                            class="text-sm font-bold tabular-nums px-2.5 py-0.5 rounded-full"
                            :class="recordsTab === 'records' ? 'bg-black/30 text-white' : 'bg-white/5 text-gray-400'"
                        >{{ (vq3Records.total || 0) + (cpmRecords.total || 0) }}</span>
                    </button>
                    <button
                        v-if="freestyleDemos && freestyleDemos.total > 0"
                        @click="recordsTab = 'freestyle'"
                        class="flex-1 flex items-center justify-center gap-3 px-6 py-4 rounded-xl border-2 transition-all duration-200"
                        :class="recordsTab === 'freestyle'
                            ? 'bg-gradient-to-br from-teal-600/80 to-teal-500/50 border-teal-400 text-white shadow-lg shadow-teal-500/20'
                            : 'bg-black/40 border-white/10 text-gray-500 hover:text-gray-200 hover:border-white/25'"
                    >
                        <svg class="w-6 h-6 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.59 14.37a6 6 0 0 1-5.84 7.38v-4.8m5.84-2.58a14.98 14.98 0 0 0 6.16-12.12A14.98 14.98 0 0 0 9.63 8.41m5.96 5.96a14.926 14.926 0 0 1-5.84 2.58m0 0a6.003 6.003 0 0 0-7.38-5.84h4.8m2.58-5.96a6 6 0 0 0-7.38 5.84" />
                        </svg>
                        <span class="text-lg font-black uppercase tracking-wide">{{ $t('Freestyle & Tricks') }}</span>
                        <span
                            class="text-sm font-bold tabular-nums px-2.5 py-0.5 rounded-full"
                            :class="recordsTab === 'freestyle' ? 'bg-black/30 text-white' : 'bg-white/5 text-gray-400'"
                        >{{ freestyleDemos.total }}</span>
                    </button>
                </div>

                <div v-show="recordsTab === 'freestyle'" class="bg-black/40 backdrop-blur-sm rounded-xl p-4 sm:p-6 shadow-2xl border border-white/5">
                    <FreestyleDemosTable
                        :data="freestyleDemos"
                        :date-format="$page.props.dateFormat"
                        :cpm-first="cpmFirst"
                        @search="onFreestyleSearch"
                    />
                </div>

                <div v-show="recordsTab === 'records'" class="grid grid-cols-1 lg:grid-cols-10 gap-6">
                <!-- Sidebar Tabs -->
                <div class="lg:col-span-2 flex relative">
                    <div v-if="profileLocked" class="absolute inset-0 z-10 rounded-xl backdrop-blur-[4px] bg-black/20 flex items-center justify-center p-4">
                        <div class="bg-[#0a0e19]/95 border border-white/10 rounded-lg px-5 py-3 text-center max-w-xs">
                            <div class="text-sm font-bold text-white">{{ $t('Verified accounts only') }}</div>
                            <div class="text-xs text-gray-400 mt-0.5">{{ $t('Filters, sorting and pages past the first open with a verified account.') }}</div>
                            <div class="text-xs mt-1">
                                <a v-if="lockedAsGuest" href="/login" class="text-blue-400 font-bold hover:text-blue-300">{{ $t('Login') }}</a>
                                <Link v-else href="/email/verify" class="text-red-400 font-bold hover:text-red-300">{{ $t('Verify Email') }}</Link>
                            </div>
                        </div>
                    </div>
                    <div class="bg-black/40 backdrop-blur-sm rounded-xl p-3 shadow-2xl border border-white/5 w-full flex flex-col" :class="profileLocked ? 'select-none' : ''">
                        <!-- Search (map name or author) -->
                        <div class="mb-4">
                            <h3 class="text-xs font-bold text-gray-400 uppercase mb-3 px-1">{{ $t('Search') }}</h3>
                            <div class="relative">
                                <svg class="w-4 h-4 text-gray-500 absolute left-2.5 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                                </svg>
                                <input
                                    v-model="recordsSearch"
                                    @input="onRecordsSearchInput"
                                    type="text"
                                    :placeholder="$t('Map name or author...')"
                                    class="w-full bg-white/5 border border-white/10 rounded-lg pl-8 pr-8 py-2 text-sm text-white placeholder-gray-500 focus:outline-none focus:border-white/30 transition-colors"
                                />
                                <button
                                    v-if="recordsSearch"
                                    @click="clearRecordsSearch"
                                    type="button"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-500 hover:text-white transition-colors"
                                    :title="$t('Clear search')">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                        <!-- Gamemode Filter (MOVED TO TOP) -->
                        <div class="mb-4">
                            <h3 class="text-xs font-bold text-gray-400 uppercase mb-3 px-1">{{ $t('Mode') }}</h3>
                            <div class="space-y-2">
                                <!-- ALL / RUN / CTF on one row -->
                                <div class="grid grid-cols-3 gap-2">
                                    <button @click="sortByMode('all')" :class="mode === 'all' ? 'bg-gradient-to-r from-white/30 to-white/20 text-white shadow-lg' : 'bg-white/5 hover:bg-white/10 text-gray-400 hover:text-white border border-white/10'" class="px-2 py-2 rounded-lg transition-all text-sm font-bold">
                                        {{ $t('ALL') }}
                                    </button>
                                    <button @click="sortByMode('run')" :class="mode === 'run' ? 'bg-gradient-to-r from-green-600/80 to-green-500/60 text-white shadow-lg' : 'bg-white/5 hover:bg-green-600/20 text-gray-400 hover:text-white border border-green-500/20'" class="px-2 py-2 rounded-lg transition-all text-sm font-bold">
                                        {{ $t('RUN') }}
                                    </button>
                                    <button @click="sortByMode('ctf')" :class="mode === 'ctf' ? 'bg-gradient-to-r from-red-600/80 to-red-500/60 text-white shadow-lg' : 'bg-white/5 hover:bg-red-600/20 text-gray-400 hover:text-white border border-red-500/20'" class="px-2 py-2 rounded-lg transition-all text-sm font-bold">
                                        {{ $t('CTF') }}
                                    </button>
                                </div>

                                <!-- CTF 1-7 -->
                                <div class="grid grid-cols-7 gap-1">
                                    <button v-for="i in 7" :key="'ctf' + i" @click="sortByMode('ctf' + i)" :class="mode === 'ctf' + i ? 'bg-gradient-to-r from-red-600/80 to-red-500/60 text-white shadow-lg' : 'bg-white/5 hover:bg-red-600/20 text-gray-400 hover:text-white border border-red-500/20'" class="px-2 py-2 rounded transition-all text-xs font-bold">
                                        {{ i }}
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Sort options (MOVED BELOW MODES) -->
                        <div class="pt-4 border-t border-white/10">
                            <h3 class="text-xs font-bold text-gray-400 uppercase mb-3 px-1">{{ $t('Sort by') }}</h3>
                            <div class="space-y-2">
                                <button v-for="(data, option) in options" :key="option" @click="selectOption(option)"
                                     :class="selectedOption === option
                                        ? 'bg-white/10 text-white shadow-lg border border-white/20'
                                        : 'bg-white/5 hover:bg-white/10 text-gray-400 hover:text-white border border-white/10'"
                                     class="w-full flex items-center gap-3 px-4 py-3 rounded-lg transition-all text-sm font-bold">
                                    <svg v-if="loading !== option" :class="selectedOption === option ? 'text-white' : data.color"
                                         class="w-6 h-6 fill-current stroke-current transition-transform">
                                        <use :href="`/images/svg/icons.svg#icon-` + data.icon"></use>
                                    </svg>
                                    <svg v-else xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
                                         class="w-6 h-6 animate-spin">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                                    </svg>
                                    <span class="text-left flex-1 leading-normal">{{ data.label }}</span>
                                </button>
                            </div>
                        </div>
                        <!-- Spacer to match table height -->
                        <div class="flex-1"></div>
                    </div>
                </div>

                <!-- Records Tables - Two Side by Side -->
                <div class="lg:col-span-8">
                    <div v-if="hasProfile && (vq3Records.total > 0 || cpmRecords.total > 0)" class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                        <!-- VQ3 Records -->
                        <div :style="{ order: cpmFirst ? 2 : 1 }" class="bg-black/40 backdrop-blur-sm rounded-xl overflow-hidden shadow-2xl border border-blue-500/20 flex flex-col min-h-[800px]">
                            <div class="bg-gradient-to-r from-blue-600/20 to-blue-500/10 border-b border-blue-500/30 px-4 pt-1 pb-1">
                                <div class="flex items-center justify-between">
                                    <h2 class="text-lg font-bold text-blue-400">{{ $t('VQ3 Records') }}</h2>
                                    <Link v-if="page.props.auth?.user" href="/user/settings?tab=global-customize" class="text-xs text-gray-500 hover:text-blue-400 transition-colors underline decoration-dotted underline-offset-2">
                                        {{ $t('Swap VQ3/CPM sides') }}
                                    </Link>
                                </div>
                            </div>

                            <div v-if="vq3Records.total > 0" class="px-4 py-2 flex-1">
                                <!-- Column Headers -->
                                <div class="flex items-center gap-2 sm:gap-3 mb-1 pb-1 border-b border-white/15">
                                    <div class="w-5 sm:w-8 flex-shrink-0 text-center text-[10px] text-gray-400 uppercase tracking-wider font-semibold">#</div>
                                    <div class="flex-1 text-[10px] text-gray-400 uppercase tracking-wider font-semibold">{{ $t('Map') }}</div>
                                    <div class="flex items-center gap-0.5 ml-auto mr-1">
                                        <div class="w-12 sm:w-20 flex-shrink-0 text-[10px] text-gray-400 uppercase tracking-wider font-semibold text-right">{{ $t('Time') }}</div>
                                        <div class="w-8 sm:w-10 flex-shrink-0 text-center text-[10px] text-gray-400 uppercase tracking-wider font-semibold" style="padding-left: 5px">{{ $t('Score') }}</div>
                                        <div :class="[dateColWidth, 'flex-shrink-0 text-[10px] text-gray-400 uppercase tracking-wider font-semibold text-right']">{{ $t('Date') }}</div>
                                    </div>
                                </div>
                                <Link v-for="record in vq3Records.data" :key="record.id" :href="`/maps/${encodeURIComponent(record.mapname)}`" class="group relative flex items-center gap-3 py-2 px-4 -mx-4 -my-2 transition-all duration-300 border-b border-white/[0.02] last:border-0 overflow-hidden first:rounded-t-[10px] last:rounded-b-[10px]">
                                    <!-- Background Map Thumbnail -->
                                    <div v-if="record.map" class="absolute inset-0 transition-all duration-500 first:rounded-t-[10px] last:rounded-b-[10px]">
                                        <img
                                            :src="`/storage/${record.map.thumbnail}`"
                                            class="w-full h-full object-cover scale-110 blur-xl group-hover:blur-none group-hover:scale-105 opacity-0 group-hover:opacity-100 transition-all duration-500"
                                            :alt="record.mapname"
                                            onerror="this.src='/images/unknown.jpg'"
                                        />
                                        <div class="absolute inset-0 bg-gradient-to-r from-black/98 via-black/95 to-black/98 group-hover:from-black/40 group-hover:via-black/30 group-hover:to-black/40 transition-all duration-500"></div>
                                    </div>

                                    <!-- Content -->
                                    <div class="relative flex items-center gap-2 sm:gap-3 w-full">
                                        <!-- Rank -->
                                        <div class="w-5 sm:w-8 flex-shrink-0 text-center flex items-center justify-center h-6">
                                            <span class="text-xs sm:text-sm font-black tabular-nums transition-all"
                                                :class="{
                                                    'text-yellow-200 [text-shadow:0_0_8px_#facc15,0_0_20px_#facc15,0_0_40px_#facc15,0_0_80px_#facc15,0_0_120px_rgba(250,204,21,0.5)]': record.rank === 1,
                                                    'text-white [text-shadow:0_0_8px_#e2e8f0,0_0_20px_#e2e8f0,0_0_40px_#e2e8f0,0_0_80px_rgba(226,232,240,0.5)]': record.rank === 2,
                                                    'text-amber-300 [text-shadow:0_0_8px_#f59e0b,0_0_20px_#f59e0b,0_0_40px_#f59e0b,0_0_80px_rgba(245,158,11,0.5)]': record.rank === 3,
                                                    'text-gray-400': record.rank > 3
                                                }">
                                                {{ record.rank }}
                                            </span>
                                        </div>

                                        <!-- Map Name + Copy -->
                                        <div class="flex-1 flex items-center gap-1 min-w-0">
                                            <div class="min-w-0">
                                                <div class="text-xs sm:text-sm font-bold text-gray-300 group-hover:text-white transition-colors truncate drop-shadow-[0_2px_4px_rgba(0,0,0,0.8)]">{{ record.mapname }}</div>
                                                <div v-if="(type === 'recentlybeaten' || type === 'tiedranks') && (record.user_plain_name || record.mdd_plain_name)" class="text-[9px] text-gray-500 group-hover:text-gray-400 truncate drop-shadow-[0_2px_4px_rgba(0,0,0,0.8)]">
                                                    <span v-if="type === 'recentlybeaten'">by {{ record.user_plain_name || record.mdd_plain_name }}</span>
                                                    <span v-else-if="type === 'tiedranks'">tied with {{ record.user_plain_name || record.mdd_plain_name }}</span>
                                                </div>
                                            </div>
                                            <div class="opacity-0 group-hover:opacity-100 transition-opacity">
                                                <CopyButton :text="record.mapname" size="xs" />
                                            </div>
                                        </div>

                                        <!-- Demo buttons -->
                                        <div v-if="record.uploaded_demos?.length > 0" class="flex-shrink-0 flex items-center" @click.stop.prevent>
                                            <span class="inline-flex items-center rounded text-xs font-medium overflow-hidden border border-blue-500/30">
                                                <a :href="`/demos/${record.uploaded_demos[0].id}/download`" class="inline-flex items-center gap-1 px-1.5 py-0.5 bg-blue-500/10 text-blue-300 hover:bg-blue-500/20 hover:text-blue-200 transition-colors" :title="$t('Download demo')">
                                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM6.293 6.707a1 1 0 010-1.414l3-3a1 1 0 011.414 0l3 3a1 1 0 01-1.414 1.414L11 5.414V13a1 1 0 11-2 0V5.414L7.707 6.707a1 1 0 01-1.414 0z"/></svg>
                                                </a>
                                                <DemoRenderButton
                                                    :renderedVideo="getRenderedVideo(record)"
                                                    :record="record"
                                                    :canRequestRender="canRequestRender(record)"
                                                    :renderRequesting="renderStates[record.id]?.requesting || false"
                                                    :renderRequested="renderStates[record.id]?.requested || false"
                                                    :renderError="renderStates[record.id]?.error || null"
                                                    borderColor="border-blue-500/30"
                                                    @toggle-youtube="toggleYoutube(record)"
                                                    @confirm-render="confirmRender(record)"
                                                />
                                            </span>
                                        </div>

                                        <!-- Flag button -->
                                        <div v-if="canFlagRecord" class="flex-shrink-0 opacity-0 group-hover:opacity-100 transition-opacity" @click.stop.prevent>
                                            <button @click="openFlagModal(record)" class="p-0.5 rounded transition-all hover:scale-110 bg-gray-700/50 text-gray-400 hover:text-red-400 hover:bg-red-500/10" :title="$t('Flag record')">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 21v-4m0 0V5a2 2 0 012-2h6.5l1 1H21l-3 6 3 6h-8.5l-1-1H5a2 2 0 00-2 2zm9-13.5V9" /></svg>
                                            </button>
                                        </div>

                                        <!-- Time + Score + Date -->
                                        <div class="flex items-center gap-0.5 ml-auto mr-1">
                                        <div class="w-12 sm:w-20 flex-shrink-0 text-right">
                                            <div class="text-[10px] sm:text-sm font-bold tabular-nums text-white transition-colors drop-shadow-[0_2px_4px_rgba(0,0,0,0.8)]">{{ formatTime(record.time) }}</div>
                                        </div>

                                        <div class="w-8 sm:w-10 flex-shrink-0 text-center">
                                            <div v-if="record.map_score"
                                                class="text-[10px] sm:text-xs font-black tabular-nums leading-none text-yellow-400/80 drop-shadow-[0_2px_4px_rgba(0,0,0,0.8)] cursor-help" style="padding-left: 5px"
                                                @mouseenter="scoreTooltip = { score: record.map_score, reltime: record.reltime, base_score: record.base_score, rank_multiplier: record.rank_multiplier, multiplier: record.multiplier, el: $event.target }"
                                                @mouseleave="scoreTooltip = null">
                                                {{ Math.round(record.map_score) }}
                                            </div>
                                        </div>

                                        <div :class="[dateColWidth, 'flex-shrink-0 text-right opacity-90 group-hover:opacity-100 transition-opacity']">
                                            <div class="text-xs text-gray-100 whitespace-nowrap font-mono font-semibold group-hover:text-white drop-shadow-[0_2px_4px_rgba(0,0,0,0.8)] leading-none">
                                                {{ fmtDate(record.date_set) }}
                                            </div>
                                            <div class="text-[10px] text-gray-400 whitespace-nowrap font-mono group-hover:text-gray-300 drop-shadow-[0_2px_4px_rgba(0,0,0,0.8)] leading-none mt-0.5 text-right">
                                                {{ new Date(record.date_set).toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' }) }}
                                            </div>
                                        </div>
                                        </div>
                                    </div>

                                </Link>
                            </div>

                            <div v-else class="text-center py-10 flex-1 flex items-center justify-center">
                                <div class="text-sm font-bold text-gray-600">{{ $t('No VQ3 Records') }}</div>
                            </div>

                            <!-- Pagination -->
                            <div v-if="profileLocked && vq3Records.total > vq3Records.per_page" class="border-t border-blue-500/20 bg-transparent p-4 mt-auto text-center text-xs text-gray-400">
                                {{ $tc(':count more page for verified accounts.|:count more pages for verified accounts.', vq3Records.last_page - 1) }}
                                <a v-if="lockedAsGuest" href="/login" class="ml-1 text-blue-400 font-bold hover:text-blue-300">{{ $t('Login') }}</a>
                                <Link v-else href="/email/verify" class="ml-1 text-red-400 font-bold hover:text-red-300">{{ $t('Verify Email') }}</Link>
                            </div>
                            <div v-else-if="vq3Records.total > vq3Records.per_page" class="border-t border-blue-500/20 bg-transparent p-4 mt-auto">
                                <Pagination :last_page="vq3Records.last_page" :current_page="vq3Records.current_page" :link="vq3Records.first_page_url" pageName="vq3_page" :only="['vq3Records', 'cpmRecords']" />
                            </div>
                        </div>

                        <!-- CPM Records -->
                        <div :style="{ order: cpmFirst ? 1 : 2 }" class="bg-black/40 backdrop-blur-sm rounded-xl overflow-hidden shadow-2xl border border-purple-500/20 flex flex-col min-h-[800px]">
                            <div class="bg-gradient-to-r from-purple-600/20 to-purple-500/10 border-b border-purple-500/30 px-4 pt-1 pb-1">
                                <div class="flex items-center justify-between">
                                    <h2 class="text-lg font-bold text-purple-400">{{ $t('CPM Records') }}</h2>
                                    <Link v-if="page.props.auth?.user" href="/user/settings?tab=global-customize" class="text-xs text-gray-500 hover:text-purple-400 transition-colors underline decoration-dotted underline-offset-2">
                                        {{ $t('Swap VQ3/CPM sides') }}
                                    </Link>
                                </div>
                            </div>

                            <div v-if="cpmRecords.total > 0" class="px-4 py-2 flex-1">
                                <!-- Column Headers -->
                                <div class="flex items-center gap-2 sm:gap-3 mb-1 pb-1 border-b border-white/15">
                                    <div class="w-5 sm:w-8 flex-shrink-0 text-center text-[10px] text-gray-400 uppercase tracking-wider font-semibold">#</div>
                                    <div class="flex-1 text-[10px] text-gray-400 uppercase tracking-wider font-semibold">{{ $t('Map') }}</div>
                                    <div class="flex items-center gap-0.5 ml-auto mr-1">
                                        <div class="w-12 sm:w-20 flex-shrink-0 text-[10px] text-gray-400 uppercase tracking-wider font-semibold text-right">{{ $t('Time') }}</div>
                                        <div class="w-8 sm:w-10 flex-shrink-0 text-center text-[10px] text-gray-400 uppercase tracking-wider font-semibold" style="padding-left: 5px">{{ $t('Score') }}</div>
                                        <div :class="[dateColWidth, 'flex-shrink-0 text-[10px] text-gray-400 uppercase tracking-wider font-semibold text-right']">{{ $t('Date') }}</div>
                                    </div>
                                </div>
                                <Link v-for="record in cpmRecords.data" :key="record.id" :href="`/maps/${encodeURIComponent(record.mapname)}`" class="group relative flex items-center gap-3 py-2 px-4 -mx-4 -my-2 transition-all duration-300 border-b border-white/[0.02] last:border-0 overflow-hidden first:rounded-t-[10px] last:rounded-b-[10px]">
                                    <!-- Background Map Thumbnail -->
                                    <div v-if="record.map" class="absolute inset-0 transition-all duration-500 first:rounded-t-[10px] last:rounded-b-[10px]">
                                        <img
                                            :src="`/storage/${record.map.thumbnail}`"
                                            class="w-full h-full object-cover scale-110 blur-xl group-hover:blur-none group-hover:scale-105 opacity-0 group-hover:opacity-100 transition-all duration-500"
                                            :alt="record.mapname"
                                            onerror="this.src='/images/unknown.jpg'"
                                        />
                                        <div class="absolute inset-0 bg-gradient-to-r from-black/98 via-black/95 to-black/98 group-hover:from-black/40 group-hover:via-black/30 group-hover:to-black/40 transition-all duration-500"></div>
                                    </div>

                                    <!-- Content -->
                                    <div class="relative flex items-center gap-2 sm:gap-3 w-full">
                                        <!-- Rank -->
                                        <div class="w-5 sm:w-8 flex-shrink-0 text-center flex items-center justify-center h-6">
                                            <span class="text-xs sm:text-sm font-black tabular-nums transition-all"
                                                :class="{
                                                    'text-yellow-200 [text-shadow:0_0_8px_#facc15,0_0_20px_#facc15,0_0_40px_#facc15,0_0_80px_#facc15,0_0_120px_rgba(250,204,21,0.5)]': record.rank === 1,
                                                    'text-white [text-shadow:0_0_8px_#e2e8f0,0_0_20px_#e2e8f0,0_0_40px_#e2e8f0,0_0_80px_rgba(226,232,240,0.5)]': record.rank === 2,
                                                    'text-amber-300 [text-shadow:0_0_8px_#f59e0b,0_0_20px_#f59e0b,0_0_40px_#f59e0b,0_0_80px_rgba(245,158,11,0.5)]': record.rank === 3,
                                                    'text-gray-400': record.rank > 3
                                                }">
                                                {{ record.rank }}
                                            </span>
                                        </div>

                                        <!-- Map Name + Copy -->
                                        <div class="flex-1 flex items-center gap-1 min-w-0">
                                            <div class="min-w-0">
                                                <div class="text-xs sm:text-sm font-bold text-gray-300 group-hover:text-white transition-colors truncate drop-shadow-[0_2px_4px_rgba(0,0,0,0.8)]">{{ record.mapname }}</div>
                                                <div v-if="(type === 'recentlybeaten' || type === 'tiedranks') && (record.user_plain_name || record.mdd_plain_name)" class="text-[9px] text-gray-500 group-hover:text-gray-400 truncate drop-shadow-[0_2px_4px_rgba(0,0,0,0.8)]">
                                                    <span v-if="type === 'recentlybeaten'">by {{ record.user_plain_name || record.mdd_plain_name }}</span>
                                                    <span v-else-if="type === 'tiedranks'">tied with {{ record.user_plain_name || record.mdd_plain_name }}</span>
                                                </div>
                                            </div>
                                            <div class="opacity-0 group-hover:opacity-100 transition-opacity">
                                                <CopyButton :text="record.mapname" size="xs" />
                                            </div>
                                        </div>

                                        <!-- Demo buttons -->
                                        <div v-if="record.uploaded_demos?.length > 0" class="flex-shrink-0 flex items-center" @click.stop.prevent>
                                            <span class="inline-flex items-center rounded text-xs font-medium overflow-hidden border border-purple-500/30">
                                                <a :href="`/demos/${record.uploaded_demos[0].id}/download`" class="inline-flex items-center gap-1 px-1.5 py-0.5 bg-purple-500/10 text-purple-300 hover:bg-purple-500/20 hover:text-purple-200 transition-colors" :title="$t('Download demo')">
                                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM6.293 6.707a1 1 0 010-1.414l3-3a1 1 0 011.414 0l3 3a1 1 0 01-1.414 1.414L11 5.414V13a1 1 0 11-2 0V5.414L7.707 6.707a1 1 0 01-1.414 0z"/></svg>
                                                </a>
                                                <DemoRenderButton
                                                    :renderedVideo="getRenderedVideo(record)"
                                                    :record="record"
                                                    :canRequestRender="canRequestRender(record)"
                                                    :renderRequesting="renderStates[record.id]?.requesting || false"
                                                    :renderRequested="renderStates[record.id]?.requested || false"
                                                    :renderError="renderStates[record.id]?.error || null"
                                                    borderColor="border-purple-500/30"
                                                    @toggle-youtube="toggleYoutube(record)"
                                                    @confirm-render="confirmRender(record)"
                                                />
                                            </span>
                                        </div>

                                        <!-- Flag button -->
                                        <div v-if="canFlagRecord" class="flex-shrink-0 opacity-0 group-hover:opacity-100 transition-opacity" @click.stop.prevent>
                                            <button @click="openFlagModal(record)" class="p-0.5 rounded transition-all hover:scale-110 bg-gray-700/50 text-gray-400 hover:text-red-400 hover:bg-red-500/10" :title="$t('Flag record')">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 21v-4m0 0V5a2 2 0 012-2h6.5l1 1H21l-3 6 3 6h-8.5l-1-1H5a2 2 0 00-2 2zm9-13.5V9" /></svg>
                                            </button>
                                        </div>

                                        <!-- Time + Score + Date -->
                                        <div class="flex items-center gap-0.5 ml-auto mr-1">
                                        <div class="w-12 sm:w-20 flex-shrink-0 text-right">
                                            <div class="text-[10px] sm:text-sm font-bold tabular-nums text-white transition-colors drop-shadow-[0_2px_4px_rgba(0,0,0,0.8)]">{{ formatTime(record.time) }}</div>
                                        </div>

                                        <div class="w-8 sm:w-10 flex-shrink-0 text-center">
                                            <div v-if="record.map_score"
                                                class="text-[10px] sm:text-xs font-black tabular-nums leading-none text-yellow-400/80 drop-shadow-[0_2px_4px_rgba(0,0,0,0.8)] cursor-help" style="padding-left: 5px"
                                                @mouseenter="scoreTooltip = { score: record.map_score, reltime: record.reltime, base_score: record.base_score, rank_multiplier: record.rank_multiplier, multiplier: record.multiplier, el: $event.target }"
                                                @mouseleave="scoreTooltip = null">
                                                {{ Math.round(record.map_score) }}
                                            </div>
                                        </div>

                                        <div :class="[dateColWidth, 'flex-shrink-0 text-right opacity-90 group-hover:opacity-100 transition-opacity']">
                                            <div class="text-xs text-gray-100 whitespace-nowrap font-mono font-semibold group-hover:text-white drop-shadow-[0_2px_4px_rgba(0,0,0,0.8)] leading-none">
                                                {{ fmtDate(record.date_set) }}
                                            </div>
                                            <div class="text-[10px] text-gray-400 whitespace-nowrap font-mono group-hover:text-gray-300 drop-shadow-[0_2px_4px_rgba(0,0,0,0.8)] leading-none mt-0.5 text-right">
                                                {{ new Date(record.date_set).toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' }) }}
                                            </div>
                                        </div>
                                        </div>
                                    </div>

                                </Link>
                            </div>

                            <div v-else class="text-center py-10 flex-1 flex items-center justify-center">
                                <div class="text-sm font-bold text-gray-600">{{ $t('No CPM Records') }}</div>
                            </div>

                            <!-- Pagination -->
                            <div v-if="profileLocked && cpmRecords.total > cpmRecords.per_page" class="border-t border-purple-500/20 bg-transparent p-4 mt-auto text-center text-xs text-gray-400">
                                {{ $tc(':count more page for verified accounts.|:count more pages for verified accounts.', cpmRecords.last_page - 1) }}
                                <a v-if="lockedAsGuest" href="/login" class="ml-1 text-blue-400 font-bold hover:text-blue-300">{{ $t('Login') }}</a>
                                <Link v-else href="/email/verify" class="ml-1 text-red-400 font-bold hover:text-red-300">{{ $t('Verify Email') }}</Link>
                            </div>
                            <div v-else-if="cpmRecords.total > cpmRecords.per_page" class="border-t border-purple-500/20 bg-transparent p-4 mt-auto">
                                <Pagination :last_page="cpmRecords.last_page" :current_page="cpmRecords.current_page" :link="cpmRecords.first_page_url" pageName="cpm_page" :only="['vq3Records', 'cpmRecords']" />
                            </div>
                        </div>
                    </div>

                    <!-- No Records State -->
                    <div v-else class="bg-black/40 backdrop-blur-sm rounded-xl overflow-hidden shadow-2xl border border-white/5">
                        <div class="text-center py-20">
                            <svg class="w-16 h-16 mx-auto mb-4 text-gray-700 fill-current opacity-50" viewBox="0 0 20 20">
                                <use href="/images/svg/icons.svg#icon-trophy"></use>
                            </svg>
                            <div class="text-xl font-bold text-gray-600">{{ $t('No Records Yet') }}</div>
                            <div class="text-sm text-gray-700 mt-2">{{ $t("This player hasn't set any records") }}</div>
                        </div>
                    </div>
                </div>
                </div>
            </div>
            <!-- Competitors & Rivals Section -->
            <div v-if="hasProfile && showSection('similar_skill_rivals') && ($page.props.auth.user ? (cpmCompetitors || cpmRivals || loadingExtras) : true)" class="bg-black/40 backdrop-blur-sm rounded-xl shadow-2xl border border-white/5 mb-6 overflow-hidden relative" :style="{ order: sectionOrder('similar_skill_rivals') }">
                <!-- Anon teaser overlay: blurred skeleton + sign-in CTA -->
                <div v-if="!$page.props.auth.user" class="absolute inset-0 z-10 flex items-center justify-center bg-black/60 backdrop-blur-md">
                    <div class="text-center px-6 py-8">
                        <div class="text-lg font-bold text-white mb-1">{{ $t('Similar skill rivals') }}</div>
                        <div class="text-sm text-gray-400 mb-4 max-w-sm">{{ $t("Sign in to see who's in this player's league and who they regularly beat (or get beaten by).") }}</div>
                        <Link :href="route('login')" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-blue-500/20 hover:bg-blue-500/30 border border-blue-500/40 text-blue-200 text-sm font-semibold transition">
                            {{ $t('Sign in to use') }}
                        </Link>
                    </div>
                </div>
                <!-- Skeleton placeholder behind the overlay so the panel has bulk -->
                <div v-if="!$page.props.auth.user" class="p-5 opacity-40 pointer-events-none select-none">
                    <div class="h-5 w-48 bg-white/10 rounded mb-4"></div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div class="space-y-2">
                            <div v-for="n in 4" :key="'sk1-'+n" class="h-10 bg-white/5 rounded"></div>
                        </div>
                        <div class="space-y-2">
                            <div v-for="n in 4" :key="'sk2-'+n" class="h-10 bg-white/5 rounded"></div>
                        </div>
                    </div>
                </div>
                <!-- Loading skeleton -->
                <div v-if="loadingExtras" class="p-5">
                    <div class="animate-pulse space-y-3">
                        <div class="h-5 bg-white/10 rounded w-40"></div>
                        <div class="h-3 bg-white/5 rounded w-64"></div>
                        <div class="grid grid-cols-2 gap-3 mt-4">
                            <div v-for="i in 4" :key="i" class="flex items-center gap-2 p-2">
                                <div class="w-8 h-8 rounded-full bg-white/10"></div>
                                <div class="flex-1 space-y-1.5">
                                    <div class="h-3 bg-white/10 rounded w-24"></div>
                                    <div class="h-2 bg-white/5 rounded w-16"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div v-else class="grid grid-cols-1 lg:grid-cols-2 divide-y lg:divide-y-0 lg:divide-x divide-white/10">
                    <!-- Similar Skill Level -->
                    <div class="p-5">
                        <div class="mb-4">
                            <h3 class="text-lg font-bold text-white mb-1 flex items-center gap-2">
                                <svg class="w-5 h-5 text-purple-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" />
                                </svg>
                                {{ $t('Similar Skill Level') }}
                            </h3>
                            <p class="text-xs text-gray-500">{{ $t('Players with similar skill score. The number shows their score difference from yours.') }}</p>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <!-- Better Players -->
                            <div v-if="cpmCompetitors?.better?.length > 0">
                                <h4 class="text-xs font-bold text-green-400 uppercase tracking-wide mb-2">{{ $t('Better Players') }}</h4>
                                <div class="space-y-1.5">
                                    <Link v-for="player in cpmCompetitors.better" :key="player.id"
                                          :href="route(player.user?.id ? 'profile.index' : 'profile.mdd', player.user?.id || player.id)"
                                          class="w-full flex items-center gap-2.5 p-2 rounded-lg border border-white/10 hover:border-green-500/40 backdrop-blur-[4px] transition-all group cursor-pointer"
                                          style="background: rgba(71,85,105,0.55);">
                                        <img :src="player.user?.profile_photo_path ? `/storage/${player.user.profile_photo_path}` : '/images/null.jpg'"
                                             class="w-8 h-8 rounded-full" :alt="player.plain_name" />
                                        <div class="flex-1 min-w-0">
                                            <div class="text-sm font-bold text-white group-hover:text-green-300 transition-colors truncate" v-html="q3tohtml(player.name || player.plain_name)"></div>
                                            <div class="text-xs text-gray-500">{{ player.wrs }} WRs · {{ player.total_records }} records</div>
                                        </div>
                                        <div class="text-xs font-bold text-green-400/80 shrink-0">+{{ Math.round(player.score - cpmCompetitors.my_score) }}</div>
                                    </Link>
                                </div>
                            </div>

                            <!-- Worse Players -->
                            <div v-if="cpmCompetitors?.worse?.length > 0">
                                <h4 class="text-xs font-bold text-orange-400 uppercase tracking-wide mb-2">{{ $t('Target Practice') }}</h4>
                                <div class="space-y-1.5">
                                    <Link v-for="player in cpmCompetitors.worse" :key="player.id"
                                          :href="route(player.user?.id ? 'profile.index' : 'profile.mdd', player.user?.id || player.id)"
                                          class="w-full flex items-center gap-2.5 p-2 rounded-lg border border-white/10 hover:border-orange-500/40 backdrop-blur-[4px] transition-all group cursor-pointer"
                                          style="background: rgba(71,85,105,0.55);">
                                        <img :src="player.user?.profile_photo_path ? `/storage/${player.user.profile_photo_path}` : '/images/null.jpg'"
                                             class="w-8 h-8 rounded-full" :alt="player.plain_name" />
                                        <div class="flex-1 min-w-0">
                                            <div class="text-sm font-bold text-white group-hover:text-orange-300 transition-colors truncate" v-html="q3tohtml(player.name || player.plain_name)"></div>
                                            <div class="text-xs text-gray-500">{{ player.wrs }} WRs · {{ player.total_records }} records</div>
                                        </div>
                                        <div class="text-xs font-bold text-orange-400/80 shrink-0">-{{ Math.round(cpmCompetitors.my_score - player.score) }}</div>
                                    </Link>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Your Rivals -->
                    <div class="p-5">
                        <div class="mb-4">
                            <h3 class="text-lg font-bold text-white mb-1 flex items-center gap-2">
                                <svg class="w-5 h-5 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.362 5.214A8.252 8.252 0 0 1 12 21 8.25 8.25 0 0 1 6.038 7.047 8.287 8.287 0 0 0 9 9.601a8.983 8.983 0 0 1 3.361-6.867 8.21 8.21 0 0 0 3 2.48Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 18a3.75 3.75 0 0 0 .495-7.468 5.99 5.99 0 0 0-1.925 3.547 5.975 5.975 0 0 1-2.133-1.001A3.75 3.75 0 0 0 12 18Z" />
                                </svg>
                                {{ $t('Your Rivals') }}
                            </h3>
                            <p class="text-xs text-gray-500">{{ $t("Head-to-head competition on specific maps. The number shows how many times you've beaten each other.") }}</p>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <!-- Players You Beat Most -->
                            <div v-if="cpmRivals?.beaten?.length > 0">
                                <h4 class="text-xs font-bold text-blue-400 uppercase tracking-wide mb-2">{{ $t('You Dominate') }}</h4>
                                <div class="space-y-1.5">
                                    <Link v-for="rival in cpmRivals.beaten" :key="rival.id"
                                          :href="route(rival.user?.id ? 'profile.index' : 'profile.mdd', rival.user?.id || rival.id)"
                                          class="w-full flex items-center gap-2.5 p-2 rounded-lg border border-white/10 hover:border-blue-500/40 backdrop-blur-[4px] transition-all group cursor-pointer"
                                          style="background: rgba(71,85,105,0.55);">
                                        <img :src="rival.user?.profile_photo_path ? `/storage/${rival.user.profile_photo_path}` : '/images/null.jpg'"
                                             class="w-8 h-8 rounded-full" :alt="rival.plain_name" />
                                        <div class="flex-1 min-w-0">
                                            <div class="text-sm font-bold text-white group-hover:text-blue-300 transition-colors truncate" v-html="q3tohtml(rival.user?.name || rival.name || rival.plain_name)"></div>
                                            <div class="text-xs text-gray-500">Beaten on {{ rival.maps_beaten }} maps</div>
                                        </div>
                                        <div class="text-xs font-bold text-blue-400/80 shrink-0">{{ rival.times_beaten }}×</div>
                                    </Link>
                                </div>
                            </div>

                            <!-- Players Who Beat You Most -->
                            <div v-if="cpmRivals?.beaten_by?.length > 0">
                                <h4 class="text-xs font-bold text-red-400 uppercase tracking-wide mb-2">{{ $t('They Dominate You') }}</h4>
                                <div class="space-y-1.5">
                                    <Link v-for="rival in cpmRivals.beaten_by" :key="rival.id"
                                          :href="route(rival.user?.id ? 'profile.index' : 'profile.mdd', rival.user?.id || rival.id)"
                                          class="w-full flex items-center gap-2.5 p-2 rounded-lg border border-white/10 hover:border-red-500/40 backdrop-blur-[4px] transition-all group cursor-pointer"
                                          style="background: rgba(71,85,105,0.55);">
                                        <img :src="rival.user?.profile_photo_path ? `/storage/${rival.user.profile_photo_path}` : '/images/null.jpg'"
                                             class="w-8 h-8 rounded-full" :alt="rival.plain_name" />
                                        <div class="flex-1 min-w-0">
                                            <div class="text-sm font-bold text-white group-hover:text-red-300 transition-colors truncate" v-html="q3tohtml(rival.user?.name || rival.name || rival.plain_name)"></div>
                                            <div class="text-xs text-gray-500">Beat you on {{ rival.maps_beaten }} maps</div>
                                        </div>
                                        <div class="text-xs font-bold text-red-400/80 shrink-0">{{ rival.times_beaten }}×</div>
                                    </Link>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Direct Competitor Comparison -->
            <div v-if="hasProfile && showSection('competitor_comparison')" class="bg-black/40 backdrop-blur-sm rounded-xl p-6 shadow-2xl border border-white/5 mb-6 relative" :style="{ order: sectionOrder('competitor_comparison') }">
                <!-- Anon teaser overlay -->
                <template v-if="!$page.props.auth.user">
                    <div class="absolute inset-0 z-10 flex items-center justify-center rounded-xl bg-black/60 backdrop-blur-md">
                        <div class="text-center px-6 py-8">
                            <div class="text-lg font-bold text-white mb-1">{{ $t('Direct competitor comparison') }}</div>
                            <div class="text-sm text-gray-400 mb-4 max-w-sm">{{ $t('Sign in to pick any rival and see the easiest maps where you can grab time on them.') }}</div>
                            <Link :href="route('login')" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-blue-500/20 hover:bg-blue-500/30 border border-blue-500/40 text-blue-200 text-sm font-semibold transition">
                                {{ $t('Sign in to use') }}
                            </Link>
                        </div>
                    </div>
                    <div class="opacity-40 pointer-events-none select-none">
                        <div class="h-5 w-56 bg-white/10 rounded mb-4"></div>
                        <div class="h-10 bg-white/5 rounded mb-4"></div>
                        <div class="space-y-2">
                            <div v-for="n in 5" :key="'cc-sk-'+n" class="h-10 bg-white/5 rounded"></div>
                        </div>
                    </div>
                </template>
                <template v-else>
                <h2 class="text-xl font-bold text-white mb-4">
                    {{ $t('Direct Competitor Comparison') }}
                </h2>

                <!-- Player Search -->
                <div class="mb-6">
                    <label class="block text-sm font-bold text-gray-400 mb-2">{{ $t('Select player to compare against:') }}</label>
                    <input
                        v-model="competitorSearch"
                        @input="searchCompetitors"
                        @focus="showCompetitorDropdown = true"
                        type="text"
                        :placeholder="$t('Search for a player...')"
                        class="w-full px-4 py-2 bg-white/5 border border-white/10 rounded-lg text-white placeholder-gray-500 focus:outline-none focus:border-purple-500/50 transition-colors"
                    />

                    <!-- Search Results Dropdown -->
                    <div v-if="showCompetitorDropdown && competitorSearchResults.length > 0" class="mt-2 bg-black/90 border border-white/20 rounded-lg max-h-60 overflow-y-auto">
                        <button
                            v-for="player in competitorSearchResults"
                            :key="player.id"
                            @click="selectCompetitor(player)"
                            class="w-full flex items-center gap-3 p-3 hover:bg-white/10 transition-colors text-left border-b border-white/5 last:border-b-0"
                        >
                            <img :src="player.user?.profile_photo_path ? `/storage/${player.user.profile_photo_path}` : '/images/null.jpg'"
                                 class="w-8 h-8 rounded-full"
                                 :alt="player.plain_name" />
                            <div class="flex-1">
                                <div class="text-sm font-bold text-white" v-html="q3tohtml(player.name || player.plain_name)"></div>
                                <div class="text-xs text-gray-400">{{ player.country }}</div>
                            </div>
                        </button>
                    </div>
                </div>

                <!-- Comparison Results -->
                <div v-if="selectedCompetitor" class="space-y-6">
                    <!-- Selected Competitor Info -->
                    <div class="flex items-center gap-4 p-4 bg-white/5 rounded-lg border border-white/10">
                        <img :src="selectedCompetitor.user?.profile_photo_path ? `/storage/${selectedCompetitor.user.profile_photo_path}` : '/images/null.jpg'"
                             class="w-12 h-12 rounded-full"
                             :alt="selectedCompetitor.plain_name" />
                        <div class="flex-1">
                            <div class="text-lg font-bold text-white" v-html="q3tohtml(selectedCompetitor.name || selectedCompetitor.plain_name)"></div>
                            <div class="text-sm text-gray-400">{{ $t('Comparing records...') }}</div>
                        </div>
                        <button @click="clearCompetitor" class="px-4 py-2 bg-red-500/20 hover:bg-red-500/30 text-red-400 rounded-lg text-sm font-bold transition-colors">
                            {{ $t('Clear') }}
                        </button>
                    </div>

                    <!-- Explanation -->
                    <div class="text-sm text-gray-400 bg-white/5 border border-white/10 rounded-lg p-4">
                        <p>{{ $t('Maps are sorted by priority:') }} <span class="text-red-400 font-bold">{{ $t('Behind') }}</span> {{ $t('(easiest to beat),') }} <span class="text-gray-400 font-bold">{{ $t('No Record') }}</span> {{ $t('(opportunities), and') }} <span class="text-green-400 font-bold">{{ $t('Ahead') }}</span> {{ $t('(already winning).') }}</p>
                    </div>

                    <!-- VQ3 and CPM Comparison Tables Side by Side -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <!-- VQ3 Comparison Table -->
                        <div>
                            <h3 class="text-lg font-bold text-white mb-3 flex items-center gap-2">
                                <img src="/images/modes/vq3-icon.svg" class="w-6 h-6" alt="VQ3" />
                                🎯 {{ $t('Top :count Easiest VQ3 Maps to Beat Your Competitor!', { count: vq3Comparison.length }) }}
                            </h3>
                            <div v-if="loadingComparison" class="text-center py-10">
                                <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-purple-500"></div>
                            </div>
                            <div v-else-if="vq3ComparisonPaginated.length > 0" class="space-y-4">
                                <div class="bg-white/5 rounded-lg border border-white/10 overflow-hidden">
                                    <div class="overflow-x-auto">
                                        <table class="w-full">
                                            <thead class="bg-white/5 border-b border-white/10">
                                                <tr>
                                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-400 uppercase">{{ $t('Map') }}</th>
                                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-400 uppercase">{{ $t('Your Time') }}</th>
                                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-400 uppercase">{{ $t('Their Time') }}</th>
                                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-400 uppercase">{{ $t('Difference') }}</th>
                                                    <th class="px-4 py-3 text-left text-xs font-bold text-gray-400 uppercase">{{ $t('Status') }}</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-white/5">
                                                <tr v-for="record in vq3ComparisonPaginated" :key="record.mapname" class="hover:bg-white/5 transition-colors">
                                                    <td class="px-4 py-3 text-sm font-medium text-white">{{ record.mapname }}</td>
                                                    <td class="px-4 py-3 text-sm text-white">{{ record.my_time ? formatTime(record.my_time) : '-' }}</td>
                                                    <td class="px-4 py-3 text-sm text-white">{{ formatTime(record.rival_time) }}</td>
                                                    <td class="px-4 py-3 text-sm" :class="record.status === 'ahead' ? 'text-green-400' : record.status === 'behind' ? 'text-red-400' : 'text-gray-400'">
                                                        {{ record.time_diff ? (record.status === 'ahead' ? '-' : '+') + formatTime(Math.abs(record.time_diff)) : '-' }}
                                                    </td>
                                                    <td class="px-4 py-3 text-sm">
                                                        <span v-if="record.status === 'ahead'" class="px-2 py-1 bg-green-500/20 text-green-400 rounded text-xs font-bold">{{ $t('Ahead') }}</span>
                                                        <span v-else-if="record.status === 'behind'" class="px-2 py-1 bg-red-500/20 text-red-400 rounded text-xs font-bold">{{ $t('Behind') }}</span>
                                                        <span v-else class="px-2 py-1 bg-gray-500/20 text-gray-400 rounded text-xs font-bold">{{ $t('No Record') }}</span>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <!-- VQ3 Pagination -->
                                <div v-if="vq3TotalPages > 1" class="flex items-center justify-center gap-2">
                                    <button @click="vq3Page = Math.max(1, vq3Page - 1)" :disabled="vq3Page === 1" class="px-3 py-1 bg-white/5 hover:bg-white/10 disabled:opacity-50 disabled:cursor-not-allowed rounded text-sm text-white">
                                        {{ $t('Previous') }}
                                    </button>
                                    <span class="text-sm text-gray-400">{{ $t('Page :page of :total', { page: vq3Page, total: vq3TotalPages }) }}</span>
                                    <button @click="vq3Page = Math.min(vq3TotalPages, vq3Page + 1)" :disabled="vq3Page === vq3TotalPages" class="px-3 py-1 bg-white/5 hover:bg-white/10 disabled:opacity-50 disabled:cursor-not-allowed rounded text-sm text-white">
                                        {{ $t('Next') }}
                                    </button>
                                </div>
                            </div>
                            <div v-else class="text-center py-10 text-gray-500">
                                {{ $t('No VQ3 comparison data available') }}
                            </div>
                        </div>

                        <!-- CPM Comparison Table -->
                        <div>
                            <h3 class="text-lg font-bold text-white mb-3 flex items-center gap-2">
                                <img src="/images/modes/cpm-icon.svg" class="w-6 h-6" alt="CPM" />
                                🎯 {{ $t('Top :count Easiest CPM Maps to Beat Your Competitor!', { count: cpmComparison.length }) }}
                            </h3>
                        <div v-if="loadingComparison" class="text-center py-10">
                            <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-purple-500"></div>
                        </div>
                        <div v-else-if="cpmComparisonPaginated.length > 0" class="space-y-4">
                            <div class="bg-white/5 rounded-lg border border-white/10 overflow-hidden">
                                <div class="overflow-x-auto">
                                    <table class="w-full">
                                        <thead class="bg-white/5 border-b border-white/10">
                                            <tr>
                                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-400 uppercase">{{ $t('Map') }}</th>
                                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-400 uppercase">{{ $t('Your Time') }}</th>
                                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-400 uppercase">{{ $t('Their Time') }}</th>
                                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-400 uppercase">{{ $t('Difference') }}</th>
                                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-400 uppercase">{{ $t('Status') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-white/5">
                                            <tr v-for="record in cpmComparisonPaginated" :key="record.mapname" class="hover:bg-white/5 transition-colors">
                                                <td class="px-4 py-3 text-sm font-medium text-white">{{ record.mapname }}</td>
                                                <td class="px-4 py-3 text-sm text-white">{{ record.my_time ? formatTime(record.my_time) : '-' }}</td>
                                                <td class="px-4 py-3 text-sm text-white">{{ formatTime(record.rival_time) }}</td>
                                                <td class="px-4 py-3 text-sm" :class="record.status === 'ahead' ? 'text-green-400' : record.status === 'behind' ? 'text-red-400' : 'text-gray-400'">
                                                    {{ record.time_diff ? (record.status === 'ahead' ? '-' : '+') + formatTime(Math.abs(record.time_diff)) : '-' }}
                                                </td>
                                                <td class="px-4 py-3 text-sm">
                                                    <span v-if="record.status === 'ahead'" class="px-2 py-1 bg-green-500/20 text-green-400 rounded text-xs font-bold">{{ $t('Ahead') }}</span>
                                                    <span v-else-if="record.status === 'behind'" class="px-2 py-1 bg-red-500/20 text-red-400 rounded text-xs font-bold">{{ $t('Behind') }}</span>
                                                    <span v-else class="px-2 py-1 bg-gray-500/20 text-gray-400 rounded text-xs font-bold">{{ $t('No Record') }}</span>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <!-- CPM Pagination -->
                            <div v-if="cpmTotalPages > 1" class="flex items-center justify-center gap-2">
                                <button @click="cpmPage = Math.max(1, cpmPage - 1)" :disabled="cpmPage === 1" class="px-3 py-1 bg-white/5 hover:bg-white/10 disabled:opacity-50 disabled:cursor-not-allowed rounded text-sm text-white">
                                    {{ $t('Previous') }}
                                </button>
                                <span class="text-sm text-gray-400">{{ $t('Page :page of :total', { page: cpmPage, total: cpmTotalPages }) }}</span>
                                <button @click="cpmPage = Math.min(cpmTotalPages, cpmPage + 1)" :disabled="cpmPage === cpmTotalPages" class="px-3 py-1 bg-white/5 hover:bg-white/10 disabled:opacity-50 disabled:cursor-not-allowed rounded text-sm text-white">
                                    {{ $t('Next') }}
                                </button>
                            </div>
                        </div>
                            <div v-else class="text-center py-10 text-gray-500">
                                {{ $t('No CPM comparison data available') }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-else class="text-center py-5">
                    <svg class="w-12 h-12 mx-auto mb-2 text-gray-700 fill-current opacity-50" viewBox="0 0 20 20">
                        <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/>
                        <path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z" clip-rule="evenodd"/>
                    </svg>
                    <div class="text-sm text-gray-500">{{ $t('Search for a player to see head-to-head comparison') }}</div>
                </div>
                </template>
            </div>

            <!-- User's Featured Maplists -->
            <div v-if="showSection('featured_maplists') && user_maplists && user_maplists.length > 0" class="bg-black/40 backdrop-blur-sm rounded-xl p-6 shadow-2xl border border-white/5 mb-6" :style="{ order: sectionOrder('featured_maplists') }">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-2xl font-bold text-white flex items-center gap-2">
                        <svg class="w-6 h-6 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 010 3.75H5.625a1.875 1.875 0 010-3.75z" />
                        </svg>
                        {{ $t('Featured Maplists') }}
                    </h3>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <Link v-for="maplist in user_maplists" :key="maplist.id" :href="`/maplists/${maplist.id}`" class="bg-white/5 hover:bg-white/10 rounded-lg p-4 border border-white/10 hover:border-blue-500/50 transition group">
                        <div class="flex items-start justify-between mb-2">
                            <div class="flex items-center gap-2">
                                <h4 class="text-white font-bold group-hover:text-blue-400 transition">{{ maplist.name }}</h4>
                                <svg v-if="maplist.is_play_later" class="w-4 h-4 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" :title="$t('Play Later')">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <div v-if="maplist.is_public" class="text-xs text-green-400 px-2 py-1 bg-green-400/10 rounded">{{ $t('Public') }}</div>
                            <div v-else-if="maplist.is_play_later" class="text-xs text-blue-400 px-2 py-1 bg-blue-400/10 rounded">{{ $t('Play Later') }}</div>
                            <div v-else class="text-xs text-gray-500 px-2 py-1 bg-gray-500/10 rounded">{{ $t('Private') }}</div>
                        </div>

                        <p v-if="maplist.description" class="text-sm text-gray-400 mb-3 line-clamp-2">
                            {{ maplist.description }}
                        </p>

                        <div class="flex items-center justify-between text-sm">
                            <div class="flex items-center gap-4">
                                <div class="flex items-center gap-1 text-gray-400">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
                                    </svg>
                                    <span>{{ maplist.maps_count || 0 }}</span>
                                </div>
                                <div class="flex items-center gap-1 text-yellow-400">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                                    </svg>
                                    <span>{{ maplist.favorites_count || 0 }}</span>
                                </div>
                            </div>
                        </div>
                    </Link>
                </div>
            </div>

            <!-- Map Completionist List -->
            <div v-if="showSection('map_completionist') && currentUnplayedMaps && currentUnplayedMaps.total > 0" class="bg-black/40 backdrop-blur-sm rounded-xl p-6 shadow-2xl border border-white/5 mb-6" :style="{ order: sectionOrder('map_completionist') }">
                <div class="mb-6">
                    <div class="flex items-center justify-between mb-3">
                        <h2 class="text-xl font-bold text-white flex items-center gap-2">
                            <svg class="w-5 h-5 text-yellow-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z" />
                            </svg>
                            {{ $t('Map Completionist') }}
                        </h2>
                        <div class="text-right">
                            <div class="text-3xl font-black text-white">
                                {{ completionPercentage }}%
                            </div>
                            <div class="text-xs text-gray-400">{{ playedMapsCount }} / {{ currentTotalMaps }} maps</div>
                        </div>
                    </div>

                    <!-- Mode Filter -->
                    <div class="flex items-center gap-2 mb-3">
                        <button @click="sortCompletionistMode('all')" :class="completionistMode === 'all' ? 'bg-gradient-to-r from-white/30 to-white/20 text-white shadow-lg' : 'bg-white/5 hover:bg-white/10 text-gray-400 hover:text-white border border-white/10'" class="px-3 py-1.5 rounded-lg transition-all text-xs font-bold">
                            {{ $t('ALL') }}
                        </button>
                        <button @click="sortCompletionistMode('run')" :class="completionistMode === 'run' ? 'bg-gradient-to-r from-green-600/80 to-green-500/60 text-white shadow-lg' : 'bg-white/5 hover:bg-green-600/20 text-gray-400 hover:text-white border border-green-500/20'" class="px-3 py-1.5 rounded-lg transition-all text-xs font-bold">
                            {{ $t('RUN') }}
                        </button>
                        <button @click="sortCompletionistMode('ctf')" :class="completionistMode === 'ctf' ? 'bg-gradient-to-r from-red-600/80 to-red-500/60 text-white shadow-lg' : 'bg-white/5 hover:bg-red-600/20 text-gray-400 hover:text-white border border-red-500/20'" class="px-3 py-1.5 rounded-lg transition-all text-xs font-bold">
                            {{ $t('CTF') }}
                        </button>
                        <div class="flex gap-1">
                            <button v-for="i in 7" :key="'comp-ctf' + i" @click="sortCompletionistMode('ctf' + i)" :class="completionistMode === 'ctf' + i ? 'bg-gradient-to-r from-red-600/80 to-red-500/60 text-white shadow-lg' : 'bg-white/5 hover:bg-red-600/20 text-gray-400 hover:text-white border border-red-500/20'" class="px-2 py-1.5 rounded transition-all text-xs font-bold">
                                {{ i }}
                            </button>
                        </div>
                    </div>

                    <!-- Epic Progress Bar -->
                    <div class="relative">
                        <!-- Background track -->
                        <div class="h-8 bg-gray-800 rounded-full overflow-hidden border border-gray-600 shadow-inner">
                            <!-- Animated progress fill -->
                            <div
                                class="h-full relative overflow-hidden transition-all duration-1000 ease-out bg-blue-600"
                                :style="`width: ${completionPercentage}%`">

                                <!-- Animated moving stripes -->
                                <div class="absolute inset-0 bg-gradient-to-r from-transparent via-blue-500/40 to-transparent bg-[length:200%_100%] animate-[shimmer_1.5s_ease-in-out_infinite]"></div>

                                <!-- Pulse effect -->
                                <div class="absolute inset-0 bg-white/10 animate-pulse"></div>

                                <!-- Sliding highlight -->
                                <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white/30 to-transparent bg-[length:50%_100%] animate-[shimmer_2s_linear_infinite]"></div>
                            </div>

                            <!-- Particles/dots animation -->
                            <div class="absolute inset-y-0 left-0 right-0 pointer-events-none">
                                <div class="absolute top-1/2 -translate-y-1/2 w-2 h-2 bg-blue-400 rounded-full blur-sm animate-[shimmer_3s_linear_infinite] opacity-50"></div>
                                <div class="absolute top-1/2 -translate-y-1/2 w-1.5 h-1.5 bg-blue-300 rounded-full blur-sm animate-[shimmer_2.5s_linear_infinite] opacity-40" style="animation-delay: 0.5s"></div>
                            </div>
                        </div>

                        <!-- Percentage label overlay -->
                        <div class="absolute inset-0 flex items-center justify-center">
                            <span class="text-xs font-black text-white drop-shadow-[0_2px_4px_rgba(0,0,0,0.8)]">
                                {{ currentUnplayedMaps.total }} maps remaining
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Maps Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-3 mb-4">
                    <Link v-for="map in currentUnplayedMaps.data" :key="map.name"
                          :href="`/maps/${encodeURIComponent(map.name)}`"
                          class="group relative bg-white/5 rounded-lg p-3 shadow-lg border border-white/10 hover:border-yellow-500/50 transition-all hover:bg-yellow-500/10">
                        <div class="relative overflow-hidden rounded-md mb-2">
                            <img :src="`/storage/${map.thumbnail}`"
                                 onerror="this.src='/images/unknown.jpg'"
                                 class="w-full h-24 object-cover group-hover:scale-110 transition-transform duration-300"
                                 :alt="map.name" />
                        </div>
                        <div class="text-sm font-bold text-white group-hover:text-yellow-400 transition-colors truncate">{{ map.name }}</div>
                        <div class="text-xs text-gray-500 truncate">by {{ map.author || 'Unknown' }}</div>
                    </Link>
                </div>

                <!-- Pagination -->
                <div v-if="currentUnplayedMaps.last_page > 1" class="flex items-center justify-center gap-2">
                    <!-- Previous Button -->
                    <button v-if="currentUnplayedMaps.current_page > 1"
                          @click="fetchCompletionistPage(currentUnplayedMaps.current_page - 1)"
                          class="px-3 py-1 rounded-lg text-sm font-medium transition-all bg-white/5 text-gray-400 hover:bg-white/10 hover:text-white border border-white/10">
                        {{ $t('‹ Prev') }}
                    </button>

                    <!-- Page Numbers -->
                    <template v-for="pg in currentUnplayedMaps.last_page" :key="pg">
                        <button v-if="pg === 1 || pg === currentUnplayedMaps.last_page || (pg >= currentUnplayedMaps.current_page - 2 && pg <= currentUnplayedMaps.current_page + 2)"
                              @click="fetchCompletionistPage(pg)"
                              class="px-3 py-1 rounded-lg text-sm font-medium transition-all"
                              :class="currentUnplayedMaps.current_page === pg
                                ? 'bg-yellow-500/20 text-yellow-400 border border-yellow-500/50'
                                : 'bg-white/5 text-gray-400 hover:bg-white/10 hover:text-white border border-white/10'">
                            {{ pg }}
                        </button>
                        <span v-else-if="pg === currentUnplayedMaps.current_page - 3 || pg === currentUnplayedMaps.current_page + 3"
                              class="px-2 text-gray-600">...</span>
                    </template>

                    <!-- Next Button -->
                    <button v-if="currentUnplayedMaps.current_page < currentUnplayedMaps.last_page"
                          @click="fetchCompletionistPage(currentUnplayedMaps.current_page + 1)"
                          class="px-3 py-1 rounded-lg text-sm font-medium transition-all bg-white/5 text-gray-400 hover:bg-white/10 hover:text-white border border-white/10">
                        {{ $t('Next ›') }}
                    </button>
                </div>
            </div>

            </div> <!-- Close Reorderable Sections Container -->
        </div>


        <div class="h-4"></div>

        <!-- Alias Report Modal -->
        <Teleport to="body">
            <!-- Report Alias Modal -->
            <div v-if="showReportModal" class="fixed inset-0 z-50 overflow-y-auto">
                <!-- Backdrop -->
                <div class="fixed inset-0 bg-black/70" @click="closeReportModal"></div>

                <!-- Modal -->
                <div class="flex min-h-full items-center justify-center p-4">
                    <div class="relative bg-black/80 rounded-xl shadow-2xl max-w-md w-full border border-white/10">
                        <!-- Header -->
                        <div class="px-6 py-4 border-b border-white/10">
                            <h3 class="text-xl font-bold text-white">{{ $t('Report Alias') }}</h3>
                            <p class="text-sm text-gray-400 mt-1">
                                {{ $t('Reporting alias:') }} <span class="text-white" v-html="q3tohtml(reportingAlias?.alias || '')"></span>
                            </p>
                        </div>

                        <!-- Body -->
                        <form @submit.prevent="submitAliasReport" class="px-6 py-4 space-y-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-300 mb-2">{{ $t('Reason for report') }}</label>
                                <textarea
                                    v-model="reportReason"
                                    rows="4"
                                    class="w-full bg-white/5 border border-white/10 rounded-lg px-4 py-2 text-white focus:border-indigo-500 focus:outline-none resize-none"
                                    :placeholder="$t('Please explain why this alias is incorrect or fake...')"
                                    required
                                    maxlength="500"
                                ></textarea>
                                <p class="text-xs text-gray-500 mt-1">{{ reportReason.length }}/500 characters</p>
                            </div>

                            <!-- Footer -->
                            <div class="flex gap-2 justify-end">
                                <button
                                    type="button"
                                    @click="closeReportModal"
                                    class="px-4 py-2 rounded-lg bg-white/5 hover:bg-white/10 text-white transition-colors"
                                    :disabled="reportingInProgress"
                                >
                                    {{ $t('Cancel') }}
                                </button>
                                <button
                                    type="submit"
                                    class="px-4 py-2 rounded-lg bg-red-600 hover:bg-red-700 text-white transition-colors font-semibold disabled:opacity-50"
                                    :disabled="reportingInProgress || !reportReason.trim()"
                                >
                                    {{ reportingInProgress ? 'Submitting...' : 'Submit Report' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Suggest Alias Modal -->
            <div v-if="showSuggestionModal" class="fixed inset-0 z-50 overflow-y-auto">
                <!-- Backdrop -->
                <div class="fixed inset-0 bg-black/70" @click="closeSuggestionModal"></div>

                <!-- Modal -->
                <div class="flex min-h-full items-center justify-center p-4">
                    <div class="relative bg-black/80 rounded-xl shadow-2xl max-w-md w-full border border-white/10">
                        <!-- Header -->
                        <div class="px-6 py-4 border-b border-white/10">
                            <h3 class="text-xl font-bold text-white">{{ $t('Suggest Alias') }}</h3>
                            <p class="text-sm text-gray-400 mt-1">
                                {{ $t('Suggest an alias for') }} <span class="text-white" v-html="q3tohtml(user?.name || '')"></span>
                            </p>
                        </div>

                        <!-- Body -->
                        <form @submit.prevent="submitAliasSuggestion" class="px-6 py-4 space-y-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-300 mb-2">{{ $t('Alias') }} <span class="text-red-400">*</span></label>
                                <input
                                    v-model="suggestedAlias"
                                    type="text"
                                    class="w-full bg-white/5 border border-white/10 rounded-lg px-4 py-2 text-white focus:border-indigo-500 focus:outline-none"
                                    :placeholder="$t('Enter alias (no ^ color codes)')"
                                    required
                                    maxlength="255"
                                />
                                <p class="text-xs text-gray-500 mt-1">{{ $t('Plain text only - Quake color codes (^) are not allowed') }}</p>
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-300 mb-2">{{ $t('Note (optional)') }}</label>
                                <textarea
                                    v-model="suggestionNote"
                                    rows="3"
                                    class="w-full bg-white/5 border border-white/10 rounded-lg px-4 py-2 text-white focus:border-indigo-500 focus:outline-none resize-none"
                                    :placeholder="$t('Add context about where you\'ve seen this alias...')"
                                    maxlength="500"
                                ></textarea>
                                <p class="text-xs text-gray-500 mt-1">{{ suggestionNote.length }}/500 characters</p>
                            </div>

                            <!-- Footer -->
                            <div class="flex gap-2 justify-end">
                                <button
                                    type="button"
                                    @click="closeSuggestionModal"
                                    class="px-4 py-2 rounded-lg bg-white/5 hover:bg-white/10 text-white transition-colors"
                                    :disabled="suggestionInProgress"
                                >
                                    {{ $t('Cancel') }}
                                </button>
                                <button
                                    type="submit"
                                    class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white transition-colors font-semibold disabled:opacity-50"
                                    :disabled="suggestionInProgress || !suggestedAlias.trim()"
                                >
                                    {{ suggestionInProgress ? 'Submitting...' : 'Suggest Alias' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Flag Modal -->
        <DemoFlagModal
            :show="showFlagModal"
            :recordId="flagRecordId"
            :demoId="flagDemoId"
            @close="showFlagModal = false"
        />
    </div>

    <!-- Score tooltip (teleported to avoid overflow clipping) -->
    <Teleport to="body">
        <div v-if="scoreTooltip" :style="scoreTooltipStyle"
            class="px-3 py-2 rounded-lg bg-gray-900 border border-white/15 text-[10px] text-gray-300 whitespace-nowrap shadow-2xl pointer-events-none">
            <div>{{ $t('Reltime:') }} <span class="text-blue-400">{{ scoreTooltip.reltime }}</span></div>
            <div v-if="scoreTooltip.base_score != null">{{ $t('Base score:') }} <span class="text-gray-100">{{ scoreTooltip.base_score }}</span></div>
            <div v-if="scoreTooltip.rank_multiplier != null">{{ $t('Rank mult:') }} <span class="text-purple-400">× {{ scoreTooltip.rank_multiplier }}</span></div>
            <div v-if="scoreTooltip.multiplier != null">{{ $t('Map mult:') }} <span class="text-green-400">× {{ scoreTooltip.multiplier }}</span></div>
            <div class="border-t border-white/10 mt-1 pt-1">{{ $t('Score:') }} <span class="text-yellow-400 font-bold">{{ scoreTooltip.score }}</span></div>
        </div>
    </Teleport>
</template>

<style scoped>
</style>