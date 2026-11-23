<script setup>
    import { Link, usePage } from '@inertiajs/vue3';
    import { computed, ref } from 'vue';
    import axios from 'axios';
    import DemoReportModal from '@/Components/DemoReportModal.vue';
    import DemoFlagModal from '@/Components/DemoFlagModal.vue';
    import DemoRenderButton from '@/Components/DemoRenderButton.vue';
    import DemoDetails from '@/Components/DemoDetails.vue';
    import { t } from '@/utils/i18n';

    const props = defineProps({
        record: Object,
        cpmrecord: Object,
        vq3record: Object,
        physics: String,
        oldtop: {
            type: Boolean,
            default: false
        },
        demoMatches: {
            type: Array,
            default: () => []
        },
        showSourceChips: {
            type: Boolean,
            default: false
        },
        hideRank: {
            type: Boolean,
            default: false
        },
        // Compact mode: used by TimeHistoryExpand drawer. Disables the row
        // hover animations ("pop" scale, white wash) and "my record" highlight
        // so the sub-rows read as secondary data rather than another leaderboard.
        compact: {
            type: Boolean,
            default: false
        },
        // Time-history metadata attached to this row. When present, an inline
        // icon chip is rendered in the row's chip area; clicking it emits
        // `toggle-history` so the parent can render a drawer below the row.
        // Shape: { count, signals, demoId?, userId? }
        timeHistory: {
            type: Object,
            default: null,
        },
        // Parent-controlled open/close state for the time-history drawer.
        historyExpanded: {
            type: Boolean,
            default: false,
        },
        // Parent-controlled open/close state for the drawer of this player's
        // own older times on the map. Separate from the demo history above:
        // one answers "who else ran this", the other "how did they get here".
        improvementsExpanded: {
            type: Boolean,
            default: false,
        },
        // Inside a freestyle group the player is named once, on the group
        // header, so the rows underneath drop the avatar, flag and nick and
        // give the space to what the demo actually is.
        hideIdentity: {
            type: Boolean,
            default: false,
        },
        // Everything on the row that only makes sense against a record:
        // reporting ("this demo is on the wrong record"), flagging ("this
        // record is not legitimate") and assigning to one. A freestyle demo
        // sits on no record and claims no result, so none of them apply.
        hideRecordActions: {
            type: Boolean,
            default: false,
        },
    });

    const emit = defineEmits(['assign', 'assign-from-record', 'reassign-record', 'scoreHover', 'toggle-history', 'toggle-improvements', 'assign-user']);

    const page = usePage();
    const showReportModal = ref(false);
    const showFlagModal = ref(false);
    const showYoutubeEmbed = ref(false);
    const showYoutubeLinkModal = ref(false);
    const youtubeLinkInput = ref('');
    const youtubeLinkSaving = ref(false);
    const youtubeLinkError = ref(null);
    const actionsExpanded = ref(false);
    let actionsTimeout = null;
    const expandActions = () => {
        clearTimeout(actionsTimeout);
        actionsExpanded.value = true;
    };
    const collapseActions = () => {
        actionsTimeout = setTimeout(() => { actionsExpanded.value = false; }, 500);
    };

    const renderRequesting = ref(false);
    const renderRequested = ref(false);
    const renderError = ref(null);
    const showRenderConfirm = ref(false);
    const etiquetteAccepted = ref(false);

    // Clicking a download chip shows what the demo is first. The chip stays a
    // real link, so middle-click and ctrl-click still download straight away -
    // only a plain left click is intercepted.
    const showDemoInfo = ref(false);

    const openDemoInfo = (event) => {
        if (event.ctrlKey || event.metaKey || event.shiftKey || event.button === 1) {
            return;
        }

        if (! getDemoForReport.value) {
            return;
        }

        event.preventDefault();
        showDemoInfo.value = true;
    };

    const renderedVideo = computed(() => {
        const videos = props.record.rendered_videos;
        if (!videos || videos.length === 0) return null;
        if (videos.length === 1) return videos[0];

        // A record can have several rendered_videos (re-render attempts,
        // updated metadata, server-then-uploader demos that both got rendered).
        // Picking videos[0] from the latest()-ordered backend list means a
        // newer 'failed' attempt hides an older 'completed' one - the user
        // sees a "render failed" badge on a record whose YouTube video is
        // perfectly fine. Pick the most useful status instead.
        const priority = { completed: 0, uploading: 1, rendering: 2, pending: 3, failed: 4 };
        return [...videos].sort((a, b) => {
            const pa = priority[a.status] ?? 99;
            const pb = priority[b.status] ?? 99;
            return pa - pb;
        })[0];
    });

    const needsVerify = computed(() => isLoggedIn.value && !page.props.isVerified);

    const canRequestRender = computed(() => {
        if (!isLoggedIn.value || renderedVideo.value || renderRequested.value) return false;
        if (needsVerify.value) return false;
        if (props.record.uploaded_demos && props.record.uploaded_demos.length > 0) return true;
        if ((isOfflineRecord.value || isOnlineDemo.value) && props.record.demo) return true;
        return false;
    });

    const confirmRender = () => {
        renderError.value = null;
        etiquetteAccepted.value = false;
        showRenderConfirm.value = true;
    };

    const cancelRender = () => {
        showRenderConfirm.value = false;
        etiquetteAccepted.value = false;
    };

    const requestRender = async () => {
        if (renderRequesting.value || !canRequestRender.value || !etiquetteAccepted.value) return;
        renderRequesting.value = true;
        renderError.value = null;
        try {
            const demoId = props.record.uploaded_demos?.[0]?.id || props.record.demo?.id;
            const recordId = props.record.id || props.record.record_id;
            await axios.post('/render/request', { record_id: recordId, demo_id: demoId });
            renderRequested.value = true;
        } catch (e) {
            renderError.value = e.response?.data?.error || t('Failed');
        } finally {
            renderRequesting.value = false;
        }
    };

    const flagRecordId = computed(() => {
        if (isOfflineRecord.value || isOnlineDemo.value) return null;
        return props.record.id ?? null;
    });

    const flagDemoId = computed(() => {
        if ((isOfflineRecord.value || isOnlineDemo.value) && props.record.demo) return props.record.demo.id;
        if (props.record.uploaded_demos && props.record.uploaded_demos.length > 0) return props.record.uploaded_demos[0].id;
        return null;
    });
    const isLoggedIn = computed(() => !!page.props.auth?.user);

    const canReportDemo = computed(() => {
        if (props.hideRecordActions) return false;
        return isLoggedIn.value && page.props.canReportDemos;
    });

    // Reporting a demo says "this upload is wrong", which needs someone with
    // enough of a history here to be worth listening to. Flagging a record is
    // a different thing - it only says "this time looks off", staff decide
    // the rest - so it asks for nothing but an account.
    const canFlagRecord = computed(() => ! props.hideRecordActions && isLoggedIn.value);

    const isAdmin = computed(() => {
        return page.props.auth?.user?.is_admin || page.props.auth?.user?.admin;
    });

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
        return (fmt === 'Ymd' || fmt === 'dmY') ? 'w-[62px]' : 'w-[50px]';
    });

    const demoIdForYoutubeLink = computed(() => {
        if ((isOfflineRecord.value || isOnlineDemo.value) && props.record.demo) return props.record.demo.id;
        if (props.record.uploaded_demos && props.record.uploaded_demos.length > 0) return props.record.uploaded_demos[0].id;
        return null;
    });

    const linkYoutube = async () => {
        const input = youtubeLinkInput.value.trim();
        if (!input) return;

        // Extract video ID from URL or use directly
        let videoId = input;
        const match = input.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/)([\w-]+)/);
        if (match) videoId = match[1];

        const demoId = demoIdForYoutubeLink.value;
        if (!demoId) { youtubeLinkError.value = t('No demo found'); return; }

        youtubeLinkSaving.value = true;
        youtubeLinkError.value = null;
        try {
            await axios.post(`/demos/${demoId}/link-youtube`, { youtube_video_id: videoId });
            showYoutubeLinkModal.value = false;
            youtubeLinkInput.value = '';
            window.location.reload();
        } catch (e) {
            youtubeLinkError.value = e.response?.data?.message || t('Failed to link');
        } finally {
            youtubeLinkSaving.value = false;
        }
    };

    const showValidityTooltip = ref(false);
    const validityTooltipPos = ref({ x: 0, y: 0 });
    const showVerifiedTooltip = ref(false);
    const verifiedTooltipPos = ref({ x: 0, y: 0 });
    const showRecTooltip = ref(false);
    const recTooltipPos = ref({ x: 0, y: 0 });
    const hoveredCommunityFlag = ref(null);
    const communityFlagTooltipPos = ref({ x: 0, y: 0 });

    const onCommunityFlagHover = (event, flag) => {
        hoveredCommunityFlag.value = flag;
        const rect = event.target.getBoundingClientRect();
        communityFlagTooltipPos.value = {
            x: rect.left + rect.width / 2,
            y: rect.top,
        };
    };

    const onValidityHover = (event) => {
        showValidityTooltip.value = true;
        const rect = event.target.getBoundingClientRect();
        validityTooltipPos.value = {
            x: rect.left + rect.width / 2,
            y: rect.top,
        };
    };

    const VALIDITY_FLAGS = computed(() => ({
        'sv_cheats=1.0': { short: 'cheats', desc: t('sv_cheats was enabled - cheat commands allowed') },
        'client_finish=false': { short: 'no finish', desc: t('Player did not cross the finish line properly') },
        'tool_assisted=true': { short: 'TAS', desc: t('Tool-assisted speedrun - not human input') },
        'timescale=': { short: 'timescale', desc: t('Game speed was altered with timescale'), prefix: true },
        'g_speed=': { short: 'speed', desc: t('Player movement speed was modified'), prefix: true, showValue: true },
        'g_gravity=': { short: 'gravity', desc: t('Gravity was modified from default value'), prefix: true },
        'pmove_fixed=0.0': { short: 'pmove 0', desc: t('pmove_fixed=0 - physics not fixed to framerate') },
        'pmove_fixed=': { short: 'pmove', desc: t('Non-standard pmove_fixed value'), prefix: true, showValue: true },
        'pmove_msec=': { short: 'msec', desc: t('Non-standard pmove_msec value'), prefix: true, showValue: true },
        'sv_fps=': { short: 'fps', desc: t('Non-standard server framerate'), prefix: true, showValue: true },
        'com_maxfps=': { short: 'maxfps', desc: t('Non-standard max framerate cap'), prefix: true, showValue: true },
        'df_mp_interferenceoff=': { short: 'interf', desc: t('df_mp_interferenceoff - interference setting modified'), prefix: true, showValue: true },
    }));

    const formatValidityFlag = (flag) => {
        if (!flag) return { short: flag, original: flag, desc: '' };

        // Exact match first
        if (VALIDITY_FLAGS.value[flag]) {
            return { short: VALIDITY_FLAGS.value[flag].short, original: flag, desc: VALIDITY_FLAGS.value[flag].desc };
        }

        // Prefix match
        for (const [key, config] of Object.entries(VALIDITY_FLAGS.value)) {
            if (config.prefix && flag.startsWith(key)) {
                const value = flag.slice(key.length).replace(/\.0$/, '');
                const short = config.showValue ? `${config.short} ${value}` : config.short;
                return { short, original: flag, desc: `${config.desc} (${flag})` };
            }
        }

        // Unknown flag - just clean it up
        return { short: flag.replace(/=.*/, ''), original: flag, desc: flag };
    };

    const validityInfo = computed(() => {
        if (!props.record.verification_type) return null;
        if (['OFFLINE', 'ONLINE', 'verified'].includes(props.record.verification_type)) return null;
        return formatValidityFlag(props.record.verification_type);
    });

    // Community flags (approved by admin)
    const COMMUNITY_FLAG_LABELS = computed(() => ({
        'sv_cheats': { short: 'cheats', desc: t('sv_cheats was enabled - flagged by community') },
        'tool_assisted': { short: 'TAS', desc: t('Tool-assisted speedrun - flagged by community') },
        'client_finish': { short: 'no finish', desc: t('No proper finish - flagged by community') },
        'timescale': { short: 'timescale', desc: t('Game speed was altered - flagged by community') },
        'g_speed': { short: 'speed', desc: t('Movement speed modified - flagged by community') },
        'g_gravity': { short: 'gravity', desc: t('Gravity modified - flagged by community') },
        'sv_fps': { short: 'fps', desc: t('Non-standard server FPS - flagged by community') },
        'com_maxfps': { short: 'maxfps', desc: t('Non-standard max FPS - flagged by community') },
        'pmove_fixed': { short: 'pmove', desc: t('Non-standard pmove_fixed - flagged by community') },
        'pmove_msec': { short: 'msec', desc: t('Non-standard pmove_msec - flagged by community') },
        'df_mp_interferenceoff': { short: 'interf', desc: t('Interference setting modified - flagged by community') },
        'left_right': { short: '+left/+right', desc: t('+left / +right turn binds - flagged by community') },
        'scripts': { short: 'scripts', desc: t('Scripted movement - flagged by community') },
        'upmove': { short: 'upmove', desc: t('Upmove script - flagged by community') },
        'replay_cheat': { short: 'replay', desc: t('Replay cheat - flagged by community') },
        'engine_cheat': { short: 'engine', desc: t('Modified engine - flagged by community') },
        'other': { short: 'flagged', desc: t('Flagged by community for validity issue') },
    }));

    const communityFlags = computed(() => {
        const flags = props.record.approved_flags;
        if (!flags || flags.length === 0) return [];
        return flags.map(f => ({
            short: COMMUNITY_FLAG_LABELS.value[f.flag_type]?.short ?? f.flag_type,
            desc: COMMUNITY_FLAG_LABELS.value[f.flag_type]?.desc ?? f.flag_type,
            count: f.flag_count || 1,
            original: f.flag_type,
        }));
    });

    const demoDownloadUrl = computed(() => {
        if ((isOfflineRecord.value || isOnlineDemo.value) && props.record.demo) {
            return `/demos/${props.record.demo.id}/download`;
        }
        if (props.record.uploaded_demos && props.record.uploaded_demos.length > 0) {
            return `/demos/${props.record.uploaded_demos[0].id}/download`;
        }
        return null;
    });

    const getDemoForReport = computed(() => {
        if (isOfflineRecord.value && props.record.demo) {
            return props.record.demo;
        }
        if (isOnlineDemo.value && props.record.demo) {
            return props.record.demo;
        }
        if (props.record.uploaded_demos && props.record.uploaded_demos.length > 0) {
            return props.record.uploaded_demos[0];
        }
        return null;
    });

    // Check if this is an offline record (has demo_id instead of record_id)
    const isOfflineRecord = computed(() => {
        return !!props.record.demo_id && !props.record.record_id;
    });

    // Check if this is an online demo shown in Demos Top
    const isOnlineDemo = computed(() => {
        return props.record.is_online === true;
    });

    const isMyRecord = computed(() => {
        const userId = page.props.auth?.user?.id;
        if (!userId) return false;
        return props.record.user?.id === userId;
    });

    // A row that resolved to a registered profile (via its cluster's alias
    // match) shows that PROFILE's name - even for an offline demo. The demo
    // file's nick is just one of the player's aliases, so once we know whose
    // profile it is we display the profile, consistent with the avatar +
    // profile link (getRoute) which already use record.user. Only fall back
    // to the raw demo player_name when no profile was matched.
    const displayName = computed(() => {
        // Offline records: profile name if matched, else the demo file nick.
        if (isOfflineRecord.value) {
            return props.record.user?.name ?? props.record.player_name ?? props.record.name;
        }
        // For online demos assigned to records, ALWAYS use the 'name' field from backend
        // Backend sets this to record owner's name (either user.name or record.name)
        if (isOnlineDemo.value && props.record.record_id && props.record.name) {
            return props.record.name;
        }
        // Online demos NOT assigned to records (fallback): profile name if
        // matched, else the demo file nick.
        if (isOnlineDemo.value) {
            return props.record.user?.name ?? props.record.player_name ?? props.record.name;
        }
        // For regular online records, use user's login name
        return props.record.user?.name ?? props.record.name;
    });

    const bestrecordCountry = computed(() => {
        // Get country from user (uploader/record owner)
        let country = props.record.user?.country ?? props.record.country;
        return (country == 'XX') ? '_404' : country;
    });

    const timeDiff =  computed(() => {
        if (! props.record.besttime === -1) {
            return null;
        }

        return Math.abs(props.record.besttime - props.record.time)
    });

    const getRoute = computed(() => {
        // Linked users with full profiles - works for online records AND for
        // offline/Demos-Top reps that inherited a canonical user from their
        // cluster (e.g. NOOB's fastest offline demo links to NOOB's profile
        // when the cluster matched his alias).
        if (props.record.user) {
            return route('profile.index', props.record.user.id);
        }

        // Unmatched offline row - no profile link available.
        if (isOfflineRecord.value) {
            return null;
        }

        // Fallback to mdd profile for unlinked accounts
        if (props.record.mdd_id) {
            return route('profile.mdd', props.record.mdd_id);
        }

        return null;
    })

    const hasValidityIssue = computed(() => {
        return !!validityInfo.value || communityFlags.value.length > 0;
    });

    const isVerified = computed(() => {
        if (props.record.uploaded_demos && props.record.uploaded_demos.length > 0) return true;
        if (props.record.verification_type === 'verified') return true;
        return false;
    });

    const rankColorClass = computed(() => {
        if (hasValidityIssue.value) {
            return 'text-red-500/50';
        }
        if (props.record.rank === 1) {
            return 'text-yellow-200 !text-base [text-shadow:0_0_8px_#facc15,0_0_20px_#facc15,0_0_40px_#facc15,0_0_80px_#facc15,0_0_120px_rgba(250,204,21,0.5)]';
        }
        if (props.record.rank === 2) {
            return 'text-white !text-base [text-shadow:0_0_8px_#e2e8f0,0_0_20px_#e2e8f0,0_0_40px_#e2e8f0,0_0_80px_rgba(226,232,240,0.5)]';
        }
        if (props.record.rank === 3) {
            return 'text-amber-300 !text-base [text-shadow:0_0_8px_#f59e0b,0_0_20px_#f59e0b,0_0_40px_#f59e0b,0_0_80px_rgba(245,158,11,0.5)]';
        }
        if (isVerified.value) {
            return 'text-green-400 group-hover:text-green-300';
        }
        return 'text-gray-400';
    });

</script>

<template>
    <div
        class="group relative flex items-center gap-1.5 rounded-md transition-all duration-200 -ml-3"
        :class="{
            'py-1.5 hover:bg-white/10 hover:shadow-lg': !compact,
            'py-0 opacity-70': compact,
            'bg-gradient-to-r from-emerald-500/15 to-transparent border-l-2 border-emerald-400 hover:from-emerald-500/25 hover:border-emerald-300': isMyRecord && !record.oldtop && !compact,
            'border-l-2 border-transparent hover:border-blue-500/50': !isMyRecord && !compact,
            'border-l-2 border-transparent': compact,
        }"
    >
        <!-- Rank Number - LARGE and prominent with pop animation. The score
             took a column of its own and pushed the row's own buttons off the
             edge on a narrower screen, so it borrows this one on hover: the
             rank is what you read down the list, the score is what you look up
             for one row at a time. -->
        <div
            v-if="!hideRank"
            class="font-black text-sm w-10 flex-shrink-0 text-left pl-1.5 leading-none"
            :class="rankColorClass"
            :title="isVerified ? $t('Verified - demo attached') : ''"
            @mouseenter="record.map_score && $emit('scoreHover', { score: record.map_score, reltime: record.reltime, base_score: record.base_score, rank_multiplier: record.rank_multiplier, multiplier: record.multiplier, el: $event.target })"
            @mouseleave="$emit('scoreHover', null)"
        >
            <!-- Rank and score share a left edge, so the swap moves nothing:
                 a four-digit score simply reaches further right into room the
                 column already has. Centred, the wider one grew out of both
                 sides at once and the row read as jumping left. No pop on
                 hover either - the number changing is the whole effect. -->
            <span :class="record.map_score ? 'group-hover:hidden' : ''">
                <span v-if="hasValidityIssue || !record.rank" class="text-red-500/50" :title="$t('Flagged - does not count for ranking')">&#x2715;</span>
                <template v-else>{{ record.rank }}</template>
            </span>
            <span v-if="record.map_score" class="hidden group-hover:inline whitespace-nowrap text-yellow-400/90 tabular-nums cursor-help">
                {{ Math.round(record.map_score) }}
            </span>
        </div>
        <!-- Empty spacer preserves horizontal alignment with the parent row when rank is hidden -->
        <div v-else class="w-10 flex-shrink-0"></div>

        <!-- Player Info - Compact -->
        <component
            :is="getRoute ? Link : 'div'"
            :href="getRoute"
            :class="[
                'flex items-center gap-2 min-w-0 flex-1 overflow-visible group/player transition-all duration-200',
                !getRoute && isLoggedIn && !isOfflineRecord ? 'cursor-default opacity-70' : !getRoute && !isOfflineRecord ? 'cursor-help opacity-70' : !getRoute && isOfflineRecord ? 'cursor-default' : 'cursor-pointer'
            ]"
        >
            <div v-if="! hideIdentity" class="overflow-visible flex-shrink-0">
                <!-- Show user's avatar with effects (effects suppressed in compact mode) -->
                <div
                    :class="compact ? 'avatar-effect-none' : 'avatar-effect-' + (record.user?.avatar_effect || 'none')"
                    :style="compact ? '' : `--effect-color: ${record.user?.color || '#ffffff'}; --border-color: ${record.user?.avatar_border_color || '#6b7280'}; --orbit-radius: 16px`"
                >
                    <img
                        :class="['rounded-full object-cover border relative', compact ? 'h-4 w-4' : 'h-7 w-7 border-2']"
                        :style="compact ? 'border-color: #6b7280' : `border-color: ${record.user?.avatar_border_color || '#6b7280'}`"
                        :src="record.user?.profile_photo_path ? '/storage/' + record.user?.profile_photo_path : '/images/null.jpg'"
                        :alt="displayName"
                    />
                </div>
            </div>
            <img
                v-if="! hideIdentity"
                :src="`/images/flags/${bestrecordCountry}.png`"
                :class="['flex-shrink-0', compact ? 'w-3.5 h-2.5' : 'w-5 h-4']"
                onerror="this.src='/images/flags/_404.png'"
            >
            <span
                :class="[
                    compact ? 'name-effect-none' : ('name-effect-' + (record.user?.name_effect || 'none')),
                    'font-semibold whitespace-nowrap overflow-visible transition-colors',
                    compact ? 'text-[11px]' : 'text-sm',
                    !compact ? 'group-hover/player:text-blue-400' : '',
                    {
                        'text-emerald-200': isMyRecord && !compact,
                        'text-gray-200': !isMyRecord || compact,
                    }
                ]"
                :style="compact ? '--effect-color: #ffffff' : `--effect-color: ${record.user?.color || '#ffffff'}`"
                v-html="q3tohtml(displayName)"
                v-if="! hideIdentity"
            ></span>

            <!-- What the uploader called the demo. On a map with no timer the
                 run has no time to identify it by, and the trick is the whole
                 point - "oups-fs-b1_jpad_1xR_3xR" says more than any nick does.
                 Only set where the backend put one on the row. -->
            <span
                v-if="record.demo_label"
                class="ml-2 truncate font-semibold text-teal-300/90 bg-teal-500/10 border border-teal-500/20 rounded px-1.5 py-0.5"
                :class="compact ? 'text-[10px]' : 'text-xs'"
                :title="record.demo_label"
            >{{ record.demo_label }}</span>

            <!-- Source type chips (shown in mixed views) -->
            <template v-if="showSourceChips">
                <!-- Primary chip: what type of entry is this (clickable with download icon if demo available) -->
                <span v-if="record.oldtop" class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-500/20 text-amber-400 border border-amber-500/50">
                    {{ $t('old record') }}
                </span>
                <span
                    v-else-if="isOnlineDemo || (isOfflineRecord && record.is_online)"
                    class="ml-2 inline-flex items-center rounded text-xs font-medium overflow-hidden border border-blue-500/50"
                >
                    <component
                        :is="demoDownloadUrl ? 'a' : 'span'"
                        :href="demoDownloadUrl"
                        @click.stop="openDemoInfo"
                        class="inline-flex items-center gap-1 px-2 py-0.5 bg-blue-500/20 text-blue-400"
                        :class="{ 'hover:bg-blue-500/30 hover:text-blue-300 transition-colors': demoDownloadUrl }"
                        :title="demoDownloadUrl ? $t('Download online demo') : ''"
                    >
                        <svg v-if="demoDownloadUrl" class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM6.293 6.707a1 1 0 010-1.414l3-3a1 1 0 011.414 0l3 3a1 1 0 01-1.414 1.414L11 5.414V13a1 1 0 11-2 0V5.414L7.707 6.707a1 1 0 01-1.414 0z"/></svg>
                        {{ $t('online') }}
                    </component>
                    <DemoRenderButton
                        :renderedVideo="renderedVideo"
                        :record="record"
                        :canRequestRender="canRequestRender"
                        :needsVerify="needsVerify"
                        :renderRequesting="renderRequesting"
                        :renderRequested="renderRequested"
                        :renderError="renderError"
                        borderColor="border-blue-500/50"
                        @toggle-youtube="showYoutubeEmbed = !showYoutubeEmbed"
                        @confirm-render="confirmRender"
                    />
                </span>
                <span
                    v-else-if="isOfflineRecord"
                    class="ml-2 inline-flex items-center rounded text-xs font-medium overflow-hidden border border-gray-500/50"
                >
                    <component
                        :is="demoDownloadUrl ? 'a' : 'span'"
                        :href="demoDownloadUrl"
                        @click.stop="openDemoInfo"
                        class="inline-flex items-center gap-1 px-2 py-0.5 bg-gray-500/20 text-gray-400"
                        :class="{ 'hover:bg-gray-500/30 hover:text-gray-300 transition-colors': demoDownloadUrl }"
                        :title="demoDownloadUrl ? $t('Download offline demo') : ''"
                    >
                        <svg v-if="demoDownloadUrl" class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM6.293 6.707a1 1 0 010-1.414l3-3a1 1 0 011.414 0l3 3a1 1 0 01-1.414 1.414L11 5.414V13a1 1 0 11-2 0V5.414L7.707 6.707a1 1 0 01-1.414 0z"/></svg>
                        {{ $t('offline') }}
                    </component>
                    <DemoRenderButton
                        :renderedVideo="renderedVideo"
                        :record="record"
                        :canRequestRender="canRequestRender"
                        :needsVerify="needsVerify"
                        :renderRequesting="renderRequesting"
                        :renderRequested="renderRequested"
                        :renderError="renderError"
                        borderColor="border-gray-500/50"
                        @toggle-youtube="showYoutubeEmbed = !showYoutubeEmbed"
                        @confirm-render="confirmRender"
                    />
                </span>
                <span
                    v-else-if="isVerified"
                    class="ml-2 relative inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium bg-green-500/20 text-green-400 border border-green-500/50 cursor-help"
                    @mouseenter="showVerifiedTooltip = true; verifiedTooltipPos = { x: $event.clientX, y: $event.clientY }"
                    @mousemove="verifiedTooltipPos = { x: $event.clientX, y: $event.clientY }"
                    @mouseleave="showVerifiedTooltip = false"
                >
                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                    {{ $t('Verified') }}
                    <Teleport to="body" v-if="showVerifiedTooltip">
                        <div class="fixed z-[9999] pointer-events-none" :style="{ top: verifiedTooltipPos.y + 'px', left: verifiedTooltipPos.x + 'px' }">
                            <div class="bg-gray-900 border border-green-500/30 rounded-lg px-3 py-2 shadow-xl -translate-x-1/2 -translate-y-full -mt-2">
                                <div class="text-green-400 font-semibold text-xs">{{ $t('Verified Record') }}</div>
                                <div class="text-gray-300 text-xs mt-0.5">{{ $t('Online record + online demo matches') }}</div>
                            </div>
                        </div>
                    </Teleport>
                </span>
                <span
                    v-else
                    class="ml-2 relative inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium bg-amber-500/20 text-amber-400 border border-amber-500/50 cursor-help"
                    @mouseenter="showRecTooltip = true; recTooltipPos = { x: $event.clientX, y: $event.clientY }"
                    @mousemove="recTooltipPos = { x: $event.clientX, y: $event.clientY }"
                    @mouseleave="showRecTooltip = false"
                >
                    {{ $t('rec.') }}
                    <Teleport to="body" v-if="showRecTooltip">
                        <div class="fixed z-[9999] pointer-events-none" :style="{ top: recTooltipPos.y + 'px', left: recTooltipPos.x + 'px' }">
                            <div class="bg-gray-900 border border-amber-500/30 rounded-lg px-3 py-2 shadow-xl -translate-x-1/2 -translate-y-full -mt-2">
                                <div class="text-amber-400 font-semibold text-xs">{{ $t('Online Record') }}</div>
                                <div class="text-gray-300 text-xs mt-0.5">{{ $t('No demo attached - not verified') }}</div>
                            </div>
                        </div>
                    </Teleport>
                </span>

                <!-- Combined demo download + render chip -->
                <span
                    v-if="!record.oldtop && !isOfflineRecord && !isOnlineDemo && record.uploaded_demos && record.uploaded_demos.length > 0"
                    class="inline-flex items-center rounded text-xs font-medium overflow-hidden border border-blue-500/30"
                >
                    <a
                        :href="demoDownloadUrl"
                        @click.stop="openDemoInfo"
                        class="inline-flex items-center gap-1 px-2 py-0.5 bg-blue-500/10 text-blue-300 hover:bg-blue-500/20 hover:text-blue-200 transition-colors"
                        :class="{ 'rounded-r': !canRequestRender || renderedVideo }"
                        :title="$t('Download demo')"
                    >
                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM6.293 6.707a1 1 0 010-1.414l3-3a1 1 0 011.414 0l3 3a1 1 0 01-1.414 1.414L11 5.414V13a1 1 0 11-2 0V5.414L7.707 6.707a1 1 0 01-1.414 0z"/></svg>
                        {{ $t('online') }}
                    </a>
                    <DemoRenderButton
                        :renderedVideo="renderedVideo"
                        :record="record"
                        :canRequestRender="canRequestRender"
                        :needsVerify="needsVerify"
                        :renderRequesting="renderRequesting"
                        :renderRequested="renderRequested"
                        :renderError="renderError"
                        borderColor="border-blue-500/30"
                        @toggle-youtube="showYoutubeEmbed = !showYoutubeEmbed"
                        @confirm-render="confirmRender"
                    />
                </span>

                <!-- Secondary chip: validity flags (sv_cheats etc.) -->
                <span
                    v-if="validityInfo"
                    class="relative inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-500/20 text-red-400 border border-red-500/50 cursor-help"
                    @mouseenter="onValidityHover($event)"
                    @mouseleave="showValidityTooltip = false"
                >
                    {{ validityInfo.short }}
                    <Teleport to="body" v-if="showValidityTooltip">
                        <div class="fixed z-[9999] pointer-events-none" :style="{ top: validityTooltipPos.y + 'px', left: validityTooltipPos.x + 'px' }">
                            <div class="bg-gray-900 border border-red-500/30 rounded-lg px-3 py-2 shadow-xl -translate-x-1/2 -translate-y-full -mt-2">
                                <div class="text-red-400 font-semibold text-xs">{{ validityInfo.original }}</div>
                                <div class="text-gray-300 text-xs mt-0.5">{{ validityInfo.desc }}</div>
                            </div>
                        </div>
                    </Teleport>
                </span>

                <!-- Community flags (approved by admin) -->
                <span
                    v-for="cf in communityFlags"
                    :key="cf.original"
                    class="relative inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium bg-orange-500/20 text-orange-400 border border-orange-500/50 cursor-help"
                    @mouseenter="onCommunityFlagHover($event, cf)"
                    @mouseleave="hoveredCommunityFlag = null"
                >
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 21v-4m0 0V5a2 2 0 012-2h6.5l1 1H21l-3 6 3 6h-8.5l-1-1H5a2 2 0 00-2 2zm9-13.5V9" /></svg>
                    {{ cf.short }}
                    <span v-if="cf.count > 1" class="text-orange-300">({{ cf.count }})</span>
                </span>
            </template>

            <!-- Default view (no mixed mode) -->
            <template v-else>
                <!-- Record with demo attached: combined download + render chip -->
                <span v-if="!isOnlineDemo && !isOfflineRecord && record.uploaded_demos && record.uploaded_demos.length > 0"
                    class="inline-flex items-center rounded text-xs font-medium overflow-hidden border border-blue-500/30"
                >
                    <a
                        v-if="demoDownloadUrl"
                        :href="demoDownloadUrl"
                        @click.stop="openDemoInfo"
                        class="inline-flex items-center gap-1 px-2 py-0.5 bg-blue-500/10 text-blue-300 hover:bg-blue-500/20 hover:text-blue-200 transition-colors"
                        :class="{ 'rounded-r': !canRequestRender || renderedVideo }"
                        :title="$t('Download demo')"
                    >
                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM6.293 6.707a1 1 0 010-1.414l3-3a1 1 0 011.414 0l3 3a1 1 0 01-1.414 1.414L11 5.414V13a1 1 0 11-2 0V5.414L7.707 6.707a1 1 0 01-1.414 0z"/></svg>
                        {{ $t('online') }}
                    </a>
                    <DemoRenderButton
                        :renderedVideo="renderedVideo"
                        :record="record"
                        :canRequestRender="canRequestRender"
                        :needsVerify="needsVerify"
                        :renderRequesting="renderRequesting"
                        :renderRequested="renderRequested"
                        :renderError="renderError"
                        borderColor="border-blue-500/30"
                        @toggle-youtube="showYoutubeEmbed = !showYoutubeEmbed"
                        @confirm-render="confirmRender"
                    />
                </span>
                <!-- Offline demo chip (demos top) -->
                <span
                    v-else-if="record.verification_type === 'OFFLINE'"
                    class="ml-2 inline-flex items-center rounded text-xs font-medium overflow-hidden border border-gray-500/50"
                >
                    <component
                        :is="demoDownloadUrl ? 'a' : 'span'"
                        :href="demoDownloadUrl"
                        @click.stop="openDemoInfo"
                        class="inline-flex items-center gap-1 px-2 py-0.5 bg-gray-500/20 text-gray-400"
                        :class="{ 'hover:bg-gray-500/30 hover:text-gray-300 transition-colors': demoDownloadUrl }"
                        :title="demoDownloadUrl ? $t('Download offline demo') : ''"
                    >
                        <svg v-if="demoDownloadUrl" class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM6.293 6.707a1 1 0 010-1.414l3-3a1 1 0 011.414 0l3 3a1 1 0 01-1.414 1.414L11 5.414V13a1 1 0 11-2 0V5.414L7.707 6.707a1 1 0 01-1.414 0z"/></svg>
                        {{ $t('offline') }}
                    </component>
                    <DemoRenderButton
                        :renderedVideo="renderedVideo"
                        :record="record"
                        :canRequestRender="canRequestRender"
                        :needsVerify="needsVerify"
                        :renderRequesting="renderRequesting"
                        :renderRequested="renderRequested"
                        :renderError="renderError"
                        borderColor="border-gray-500/50"
                        @toggle-youtube="showYoutubeEmbed = !showYoutubeEmbed"
                        @confirm-render="confirmRender"
                    />
                </span>
                <!-- Online demo chip (demos top) -->
                <span
                    v-else-if="record.verification_type === 'ONLINE'"
                    class="ml-2 inline-flex items-center rounded text-xs font-medium overflow-hidden border border-blue-500/50"
                >
                    <component
                        :is="demoDownloadUrl ? 'a' : 'span'"
                        :href="demoDownloadUrl"
                        @click.stop="openDemoInfo"
                        class="inline-flex items-center gap-1 px-2 py-0.5 bg-blue-500/20 text-blue-400"
                        :class="{ 'hover:bg-blue-500/30 hover:text-blue-300 transition-colors': demoDownloadUrl }"
                        :title="demoDownloadUrl ? $t('Download online demo') : ''"
                    >
                        <svg v-if="demoDownloadUrl" class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM6.293 6.707a1 1 0 010-1.414l3-3a1 1 0 011.414 0l3 3a1 1 0 01-1.414 1.414L11 5.414V13a1 1 0 11-2 0V5.414L7.707 6.707a1 1 0 01-1.414 0z"/></svg>
                        {{ $t('online') }}
                    </component>
                    <DemoRenderButton
                        :renderedVideo="renderedVideo"
                        :record="record"
                        :canRequestRender="canRequestRender"
                        :needsVerify="needsVerify"
                        :renderRequesting="renderRequesting"
                        :renderRequested="renderRequested"
                        :renderError="renderError"
                        borderColor="border-blue-500/50"
                        @toggle-youtube="showYoutubeEmbed = !showYoutubeEmbed"
                        @confirm-render="confirmRender"
                    />
                </span>
                <span
                    v-else-if="validityInfo"
                    class="relative ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-500/20 text-red-400 border border-red-500/50 cursor-help"
                    @mouseenter="onValidityHover($event)"
                    @mouseleave="showValidityTooltip = false"
                >
                    {{ validityInfo.short }}
                    <Teleport to="body" v-if="showValidityTooltip">
                        <div class="fixed z-[9999] pointer-events-none" :style="{ top: validityTooltipPos.y + 'px', left: validityTooltipPos.x + 'px' }">
                            <div class="bg-gray-900 border border-red-500/30 rounded-lg px-3 py-2 shadow-xl -translate-x-1/2 -translate-y-full -mt-2">
                                <div class="text-red-400 font-semibold text-xs">{{ validityInfo.original }}</div>
                                <div class="text-gray-300 text-xs mt-0.5">{{ validityInfo.desc }}</div>
                            </div>
                        </div>
                    </Teleport>
                </span>

                <!-- Community flags (approved by admin) -->
                <span
                    v-for="cf in communityFlags"
                    :key="cf.original"
                    class="relative ml-2 inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium bg-orange-500/20 text-orange-400 border border-orange-500/50 cursor-help"
                    @mouseenter="onCommunityFlagHover($event, cf)"
                    @mouseleave="hoveredCommunityFlag = null"
                >
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 21v-4m0 0V5a2 2 0 012-2h6.5l1 1H21l-3 6 3 6h-8.5l-1-1H5a2 2 0 00-2 2zm9-13.5V9" /></svg>
                    {{ cf.short }}
                    <span v-if="cf.count > 1" class="text-orange-300">({{ cf.count }})</span>
                </span>
            </template>
        </component>

<!-- Community flag tooltip - TELEPORTED -->
        <Teleport to="body" v-if="hoveredCommunityFlag">
            <div class="fixed z-[9999] pointer-events-none" :style="{ top: communityFlagTooltipPos.y + 'px', left: communityFlagTooltipPos.x + 'px' }">
                <div class="bg-gray-900 border border-orange-500/30 rounded-lg px-3 py-2 shadow-xl -translate-x-1/2 -translate-y-full -mt-2">
                    <div class="text-orange-400 font-semibold text-xs flex items-center gap-1">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 21v-4m0 0V5a2 2 0 012-2h6.5l1 1H21l-3 6 3 6h-8.5l-1-1H5a2 2 0 00-2 2zm9-13.5V9" /></svg>
                        {{ $t('Community Flag') }}
                    </div>
                    <div class="text-gray-300 text-xs mt-0.5">{{ hoveredCommunityFlag.desc }}</div>
                    <div v-if="hoveredCommunityFlag.count > 1" class="text-orange-300/70 text-xs mt-0.5">{{ $tc('Flagged by :count user|Flagged by :count users', hoveredCommunityFlag.count) }}</div>
                </div>
            </div>
        </Teleport>

        <!-- Action Icons -->
        <div class="flex items-center justify-end flex-shrink-0 gap-0.5 ml-auto">
            <!-- A demo on no record has nothing that can say whose it is. Staff
                 can put a name on it; there is room on these rows for words. -->
            <button
                v-if="hideRecordActions && $page.props.canAssignDemoToUser"
                @click.stop="emit('assign-user', record)"
                class="mr-1 px-2 py-0.5 rounded text-[10px] font-semibold border transition-colors"
                :class="record.assigned_user_id
                    ? 'bg-teal-500/15 text-teal-300 border-teal-500/30 hover:bg-teal-500/25'
                    : 'bg-gray-700/40 text-gray-300 border-white/10 hover:bg-gray-700/70 hover:text-white'"
                :title="record.assigned_user_id ? $t('Attributed to an account - click to change') : $t('Attribute this demo to an account')"
            >
                {{ record.assigned_user_id ? $t('Assigned') : $t('Assign to account') }}
            </button>

            <!-- Always visible: assign/match/reassign -->
            <button
                v-if="! hideRecordActions && isLoggedIn && isOnlineDemo && !record.record_id && record.demo"
                @click.stop="emit('assign', record)"
                class="p-0.5 rounded transition-all hover:scale-110 bg-gray-700/50 text-gray-400 hover:text-green-400 hover:bg-green-500/10"
                :title="$t('Assign to online record')"
            >
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path>
                </svg>
            </button>

            <button
                v-if="! hideRecordActions && isLoggedIn && !isOnlineDemo && !isOfflineRecord && demoMatches.length > 0 && !(record.uploaded_demos && record.uploaded_demos.length > 0)"
                @click.stop="emit('assign-from-record', record)"
                class="p-0.5 rounded transition-all hover:scale-110 bg-purple-500/20 text-purple-400 hover:text-purple-300 hover:bg-purple-500/30 animate-pulse"
                :title="$tc(':count possible demo match|:count possible demo matches', demoMatches.length)"
            >
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path>
                </svg>
            </button>

            <button
                v-if="! hideRecordActions && isLoggedIn && !isOnlineDemo && !isOfflineRecord && record.uploaded_demos && record.uploaded_demos.length > 0"
                @click.stop="emit('reassign-record', record)"
                class="p-0.5 rounded transition-all hover:scale-110 bg-gray-700/50 text-gray-400 hover:text-yellow-400 hover:bg-yellow-500/10"
                :title="$t('Reassign demo')"
            >
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                </svg>
            </button>

            <!-- Collapsed: report/flag/youtube-link (expand on hover with 500ms delay) -->
            <div class="relative" @mouseenter="expandActions" @mouseleave="collapseActions">
                <div v-if="!actionsExpanded" class="flex items-center text-gray-500 hover:text-gray-300 cursor-pointer p-0.5 rounded bg-gray-700/50">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 11-3 0m3 0a1.5 1.5 0 10-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-9.75 0h9.75" />
                    </svg>
                </div>
                <div v-if="actionsExpanded" class="flex items-center gap-0.5">
                    <!-- Flagging a record needs no demo and no record count:
                         anybody can see a time that looks wrong, and whether
                         there is evidence behind it is for staff to find out. -->
                    <button
                        v-if="canFlagRecord && !record.oldtop"
                        @click.stop="showFlagModal = true"
                        class="p-0.5 rounded transition-all hover:scale-110 bg-gray-700/50 text-gray-400 hover:text-red-400 hover:bg-red-500/10"
                        :title="$t('Flag validity issue')"
                    >
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 21v-4m0 0V5a2 2 0 012-2h6.5l1 1H21l-3 6 3 6h-8.5l-1-1H5a2 2 0 00-2 2zm9-13.5V9" />
                        </svg>
                    </button>

                    <button
                        v-if="canReportDemo && getDemoForReport"
                        @click.stop="showReportModal = true"
                        class="p-0.5 rounded transition-all hover:scale-110 bg-gray-700/50 text-gray-400 hover:text-red-400 hover:bg-red-500/10"
                        :title="$t('Report demo')"
                    >
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                    </button>

                    <button
                        v-if="isAdmin && demoIdForYoutubeLink"
                        @click.stop="showYoutubeLinkModal = true"
                        class="p-0.5 rounded transition-all hover:scale-110 bg-gray-700/50 text-gray-400 hover:text-red-400 hover:bg-red-500/10"
                        :title="$t('Link YouTube video')"
                    >
                        <svg class="w-3 h-3" viewBox="0 0 24 24" fill="currentColor"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814z"/><path d="M9.545 15.568V8.432L15.818 12l-6.273 3.568z" fill="#fff"/></svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Time - MASSIVE and eye-catching. History trigger sits next to the
             time stack, vertically centered across both rows (time + diff). -->
        <div class="flex items-center justify-end gap-1 flex-shrink-0 ml-0.5">
            <!-- The road to this record: every time this player beat their own
                 time here, which the site kept and never showed on a map. -->
            <button
                v-if="record.history_count"
                type="button"
                @click.prevent.stop="emit('toggle-improvements')"
                :title="improvementsExpanded ? $t('Hide time history') : $t('Show time history')"
                :class="[
                    'inline-flex items-center gap-0.5 px-1 py-0.5 rounded text-[10px] font-bold tabular-nums border transition-colors',
                    improvementsExpanded
                        ? 'bg-blue-500/30 text-blue-200 border-blue-400/60'
                        : 'bg-white/5 text-gray-400 border-white/10 hover:bg-white/10 hover:text-white'
                ]">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
                {{ record.history_count }}
            </button>
            <button
                v-if="timeHistory && timeHistory.count"
                type="button"
                @click.prevent.stop="emit('toggle-history')"
                :title="historyExpanded ? $t('Hide time history') : $t('Show time history')"
                :class="[
                    'inline-flex flex-col items-center justify-center leading-none px-1.5 py-0.5 rounded text-[10px] font-bold border transition-colors [&_span]:mt-0.5',
                    historyExpanded
                        ? 'bg-blue-500/30 text-blue-200 border-blue-400/60'
                        : 'bg-white/5 text-gray-300 border-white/10 hover:bg-white/10 hover:text-white'
                ]"
                v-html="$t('Time <span>History</span>')"
            ></button>
            <!-- Sized to the time it holds, not to the longest time there
                 could be. The date after it is a fixed width, so every time
                 still ends on the same line - and the history button sits
                 against its own row's number instead of a gap left for the
                 minutes most runs do not have. -->
            <div class="text-right flex-shrink-0">
                <div
                    class="font-black tabular-nums leading-none drop-shadow-[0_2px_4px_rgba(0,0,0,0.8)]"
                    :class="{
                        'text-base': !compact,
                        'text-xs': compact,
                        'text-emerald-300': isMyRecord && !compact,
                        'text-white': !isMyRecord && !compact,
                        'text-gray-300': compact,
                    }"
                >
                    <!-- A trick, tutorial or pads demo never finished a run, so
                         it has no time. Printing formatTime(null) put "00.000"
                         on it, which reads as a run of zero seconds. -->
                    {{ record.time ? formatTime(record.time) : '-' }}
                </div>
                <div v-if="timeDiff" class="text-[10px] text-red-400 tabular-nums leading-none mt-0.5 drop-shadow-[0_2px_4px_rgba(0,0,0,0.8)]">
                    -{{ formatTime(timeDiff) }}
                </div>
            </div>
        </div>

        <!-- Date -->
        <div :class="[dateColWidth, 'flex-shrink-0 transition-opacity text-right', compact ? 'opacity-80' : 'opacity-90 group-hover:opacity-100']">
            <div :class="['whitespace-nowrap font-mono font-semibold drop-shadow-[0_2px_4px_rgba(0,0,0,0.8)] leading-none', compact ? 'text-[10px] text-gray-400' : 'text-xs text-gray-100 group-hover:text-white']">
                {{ fmtDate(record.date_set) }}
            </div>
            <div :class="['whitespace-nowrap font-mono drop-shadow-[0_2px_4px_rgba(0,0,0,0.8)] leading-none mt-0.5', compact ? 'text-[9px] text-gray-600' : 'text-[10px] text-gray-400 group-hover:text-gray-300']">
                {{ new Date(record.date_set).toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' }) }}
            </div>
        </div>

    </div>

    <!-- Render Confirm Dialog -->
    <Teleport to="body" v-if="showRenderConfirm">
        <div class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/60" @click.self="cancelRender">
            <div class="bg-gray-900 border border-white/10 rounded-xl p-5 shadow-2xl max-w-md mx-4">
                <template v-if="!renderRequested">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-10 h-10 rounded-lg bg-red-500/20 flex items-center justify-center">
                            <svg class="w-5 h-5 text-red-400" viewBox="0 0 24 24" fill="currentColor"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814z"/><path d="M9.545 15.568V8.432L15.818 12l-6.273 3.568z" fill="#fff"/></svg>
                        </div>
                        <h3 class="text-sm font-bold text-white">{{ $t('Render to YouTube?') }}</h3>
                    </div>
                    <p class="text-xs text-gray-400 mb-3">{{ $t('This queues this demo to be rendered into a video and uploaded to the defrag.racing YouTube channel. Rendering can take several minutes.') }}</p>

                    <div class="border border-red-500/30 bg-red-500/[0.06] rounded-lg p-3 mb-3">
                        <p class="text-[11px] text-gray-300 leading-relaxed [&_span]:font-bold [&_span]:text-red-300"
                           v-html="$t('<span>Renders cost real money.</span> The render farm time and storage are paid for by defrag.racing out of the project\'s own pocket, and YouTube caps how many videos demome can upload per day. Every render you queue spends part of that shared, limited budget - so please only render runs that are worth keeping.')"></p>
                    </div>

                    <div class="border border-white/10 bg-white/[0.02] rounded-lg p-3 mb-3">
                        <h4 class="text-xs font-bold text-white mb-2">{{ $t('Render etiquette') }}</h4>
                        <ul class="text-[11px] text-gray-400 space-y-1.5 list-disc pl-4">
                            <li>{{ $t('Render the best run. Don\'t queue a whole time history for a map when a faster time already exists - or will likely beat it within minutes or hours.') }}</li>
                            <li>{{ $t('Several near-identical times on the same map, a few ms apart? Pick one.') }}</li>
                            <li>{{ $t('Slower time but a genuinely cool trick or something worth showing off? That\'s totally fine, go for it.') }}</li>
                        </ul>
                    </div>

                    <label class="flex items-start gap-2 mb-3 cursor-pointer select-none">
                        <input type="checkbox" v-model="etiquetteAccepted" class="mt-0.5 w-3.5 h-3.5 rounded border-white/20 bg-white/5 text-red-600 focus:ring-red-500 focus:ring-offset-0">
                        <span class="text-xs text-gray-300">{{ $t('I\'ll only render runs actually worth keeping, not whole time histories.') }}</span>
                    </label>

                    <p v-if="renderError" class="text-xs text-red-400 mb-3">{{ renderError }}</p>
                    <div class="flex gap-2 justify-end">
                        <button @click="cancelRender" class="px-3 py-1.5 text-xs font-medium text-gray-400 bg-white/5 hover:bg-white/10 border border-white/10 rounded-lg transition-colors">
                            {{ $t('Cancel') }}
                        </button>
                        <button @click="requestRender" :disabled="renderRequesting || !etiquetteAccepted" class="px-3 py-1.5 text-xs font-bold text-white bg-red-600 hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed rounded-lg transition-colors">
                            {{ renderRequesting ? '...' : $t('Render') }}
                        </button>
                    </div>
                </template>
                <template v-else>
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-10 h-10 rounded-lg bg-green-500/20 flex items-center justify-center">
                            <svg class="w-5 h-5 text-green-400" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                            </svg>
                        </div>
                        <h3 class="text-sm font-bold text-white">{{ $t('Render Queued') }}</h3>
                    </div>
                    <p class="text-xs text-gray-400 mb-4 [&_a]:text-red-400 [&_a:hover]:text-red-300 [&_a]:underline"
                       v-html="$t('This demo has been added to the render queue. You can check rendering progress <a href=/rendered-demos>here</a>.')"></p>
                    <div class="flex justify-end">
                        <button @click="cancelRender" class="px-3 py-1.5 text-xs font-medium text-gray-300 bg-white/5 hover:bg-white/10 border border-white/10 rounded-lg transition-colors">
                            {{ $t('Close') }}
                        </button>
                    </div>
                </template>
            </div>
        </div>
    </Teleport>

    <!-- YouTube Link Modal (admin) -->
    <Teleport to="body" v-if="showYoutubeLinkModal">
        <div class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/60" @click.self="showYoutubeLinkModal = false">
            <div class="bg-gray-900 border border-white/10 rounded-xl p-5 shadow-2xl max-w-sm mx-4">
                <h3 class="text-sm font-bold text-white mb-3">{{ $t('Link YouTube Video') }}</h3>
                <input
                    v-model="youtubeLinkInput"
                    type="text"
                    :placeholder="$t('YouTube URL or video ID')"
                    class="w-full px-3 py-2 text-sm bg-gray-800 border border-white/10 rounded-lg text-white placeholder-gray-500 focus:outline-none focus:border-red-500/50"
                    @keyup.enter="linkYoutube"
                >
                <p v-if="youtubeLinkError" class="text-red-400 text-xs mt-2">{{ youtubeLinkError }}</p>
                <div class="flex gap-2 justify-end mt-4">
                    <button @click="showYoutubeLinkModal = false" class="px-3 py-1.5 text-xs font-medium text-gray-400 bg-white/5 hover:bg-white/10 border border-white/10 rounded-lg transition-colors">
                        {{ $t('Cancel') }}
                    </button>
                    <button @click="linkYoutube" :disabled="youtubeLinkSaving" class="px-3 py-1.5 text-xs font-bold text-white bg-red-600 hover:bg-red-700 rounded-lg transition-colors">
                        {{ youtubeLinkSaving ? '...' : $t('Link') }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>

    <!-- YouTube Embed -->
    <div v-if="showYoutubeEmbed && renderedVideo" class="bg-black/60 border border-white/10 rounded-lg overflow-hidden mx-1 mb-1">
        <div class="aspect-video">
            <iframe
                :src="`https://www.youtube.com/embed/${renderedVideo.youtube_video_id}?autoplay=1`"
                class="w-full h-full"
                frameborder="0"
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                allowfullscreen
            ></iframe>
        </div>
    </div>

    <!-- What this demo is, before a download is spent on it -->
    <Teleport to="body" v-if="showDemoInfo && getDemoForReport">
        <div class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/60 p-4" @click.self="showDemoInfo = false">
            <div class="bg-gray-900 border border-white/10 rounded-xl shadow-2xl w-full max-w-5xl max-h-[85vh] overflow-y-auto">
                <div class="flex items-start justify-between gap-4 px-5 pt-5 pb-3">
                    <div class="min-w-0">
                        <h3 class="text-sm font-bold text-white truncate">
                            {{ getDemoForReport.map_name || record.mapname }}
                        </h3>
                        <p class="text-xs text-gray-500 mt-0.5">{{ $t('Demo details') }}</p>
                    </div>
                    <button
                        @click="showDemoInfo = false"
                        class="flex-shrink-0 p-1 text-gray-500 hover:text-white transition-colors"
                        :aria-label="$t('Close')">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="px-5">
                    <DemoDetails :demo="getDemoForReport" />
                </div>

                <div class="flex justify-end gap-2 px-5 py-4">
                    <button
                        @click="showDemoInfo = false"
                        class="px-3 py-1.5 text-xs font-medium text-gray-400 bg-white/5 hover:bg-white/10 border border-white/10 rounded-lg transition-colors">
                        {{ $t('Cancel') }}
                    </button>
                    <a
                        :href="demoDownloadUrl"
                        @click="showDemoInfo = false"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold text-blue-200 bg-blue-600/30 hover:bg-blue-600/40 border border-blue-500/40 rounded-lg transition-colors">
                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM6.293 6.707a1 1 0 010-1.414l3-3a1 1 0 011.414 0l3 3a1 1 0 01-1.414 1.414L11 5.414V13a1 1 0 11-2 0V5.414L7.707 6.707a1 1 0 01-1.414 0z"/></svg>
                        {{ $t('Download') }}
                    </a>
                </div>
            </div>
        </div>
    </Teleport>

    <!-- Demo Report Modal -->
    <DemoReportModal
        :show="showReportModal"
        :demo="getDemoForReport"
        @close="showReportModal = false"
    />

    <!-- Demo Flag Modal -->
    <DemoFlagModal
        :show="showFlagModal"
        :record-id="flagRecordId"
        :demo-id="flagDemoId"
        @close="showFlagModal = false"
    />
</template>