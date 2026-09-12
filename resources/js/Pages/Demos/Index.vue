<script>
import MainLayout from '@/Layouts/MainLayout.vue';

export default {
    layout: MainLayout,
    inheritAttrs: false,
}
</script>

<script setup>
import { Head, Link, useForm, router, usePage } from '@inertiajs/vue3';
import { ref, reactive, computed, onMounted, onUnmounted, watch } from 'vue';
import DemoFilters from '@/Components/Demos/DemoFilters.vue';
import LauncherBanner from '@/Components/LauncherBanner.vue';
import DemoDetails from '@/Components/DemoDetails.vue';
import DemoPhysicsBadges from '@/Components/DemoPhysicsBadges.vue';
import { t } from '@/utils/i18n';

const $page = usePage();

const props = defineProps({
    userDemos: Object,
    publicDemos: Object,
    demoCounts: Object,
    browseCounts: Object,
    downloadLimitInfo: Object,
    uploadLimitInfo: Object,
    // One filter set for both tabs, read and normalised by the controller.
    filters: Object,
    filtersNarrowed: Boolean,
    countries: Object,
    physicsOptions: Array,
});
const fileInput = ref(null);
const selectedFiles = ref([]);
const uploading = ref(false);
const uploadErrors = ref([]);
const uploadSuccess = ref([]);
const uploadSummary = ref(null); // { total_sent, total_received, queued, duplicates, errors, skipped_frontend }
const errorsExpanded = ref(false);
const successExpanded = ref(false);

// Grouped by the code the server sends, not by reading its English. The
// substrings this used to match on are translated now, and a translated
// sentence never contains the word "Duplicate" - every error would have
// landed under "Other" the moment somebody switched the site to Czech.
const ERROR_CATEGORIES = {
    duplicate: () => t('Duplicates'),
    duplicate_name: () => t('Duplicate Filename'),
    format: () => t('Invalid Format'),
    archive: () => t('Upload Failed'),
    failed: () => t('Upload Failed'),
};

const categorizedErrors = computed(() => {
    const cats = {};
    uploadErrors.value.forEach(err => {
        const category = (ERROR_CATEGORIES[err.code] ?? (() => t('Other')))();
        if (!cats[category]) cats[category] = [];
        cats[category].push(err);
    });
    return cats;
});
// Upload info modal state
const showUploadInfo = ref(false);
const uploadInfoTitle = ref('');
const uploadInfoMessage = ref('');
const uploadInfoType = ref('info'); // 'info', 'warning', 'error'

const showUploadInfoModal = (title, message, type = 'info') => {
    uploadInfoTitle.value = title;
    uploadInfoMessage.value = message;
    uploadInfoType.value = type;
    showUploadInfo.value = true;
};

const showDownloadLimitPopup = ref(false);
const showFailedFiles = ref(false);
const downloadLimitPopupMessage = ref('');
const downloadLimitPopupIsGuest = ref(false);
const localDownloadLimitInfo = ref(props.downloadLimitInfo ? { ...props.downloadLimitInfo } : null);
const localUploadLimitInfo = ref(props.uploadLimitInfo ? { ...props.uploadLimitInfo } : null);

const downloadDemo = async (demoId) => {
    try {
        const response = await axios.get(route('demos.download', demoId), { responseType: 'blob' });
        const contentDisposition = response.headers['content-disposition'];
        let filename = 'demo.dm_68';
        if (contentDisposition) {
            const match = contentDisposition.match(/filename="?([^";\n]+)"?/);
            if (match) filename = match[1];
        }
        const url = window.URL.createObjectURL(new Blob([response.data]));
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        document.body.removeChild(a);

        // Update remaining count locally
        if (localDownloadLimitInfo.value) {
            localDownloadLimitInfo.value = {
                ...localDownloadLimitInfo.value,
                used: localDownloadLimitInfo.value.used + 1,
                remaining: Math.max(0, localDownloadLimitInfo.value.remaining - 1),
            };
        }
    } catch (error) {
        if (error.response?.status === 429) {
            const data = JSON.parse(await error.response.data.text());
            downloadLimitPopupMessage.value = data.message;
            downloadLimitPopupIsGuest.value = data.isGuest;
            showDownloadLimitPopup.value = true;
        } else {
            // Fallback to direct navigation for non-rate-limit errors
            window.location.href = route('demos.download', demoId);
        }
    }
};

const processingDemos = ref([]);
const queueStats = ref({});
const statusPolling = ref(null);
const uploadProgress = ref(0);
const trackingDemoIds = ref([]);

const activelyProcessingDemos = computed(() => (processingDemos.value || []).filter(d => d.status === 'processing'));
const queuedDemoCount = computed(() => (queueStats.value?.total_queued || 0));
const reprocessingFailed = ref(false);
const recentlyProcessed = ref([]);

// Demos of theirs that comps is holding. They are deliberately missing from
// every list on this page - a run on a map being played is hidden site-wide -
// so a demo uploaded here leaves the processing panel and arrives nowhere,
// which reads as an upload that failed. This is the only place that says
// otherwise.
const compsNotices = ref([]);
const reprocessMessage = ref('');
const actionStartedAt = ref(null);
const showReprocessConfirm = ref(false);
const processingStartTime = ref(null);
const processingDuration = ref(null);
const processingResultsExpanded = ref(false);
const globalQueueExpanded = ref(false);

// The queue panel is only worth screen space while the queue has something
// in it. Empty, it was a box of zeroes on every page load. This is the same
// test that decides whether to poll at all, so the panel is on screen exactly
// while the numbers behind it move.
const queueHasWork = computed(() =>
    (queueStats.value?.total_queued || 0) > 0
    || (queueStats.value?.total_processing || 0) > 0
    || (queueStats.value?.user_queued || 0) > 0
    || (queueStats.value?.user_processing || 0) > 0
    || activelyProcessingDemos.value.length > 0
);

const processingSummary = computed(() => {
    if (recentlyProcessed.value.length === 0) return null;
    const groups = {
        assigned: { label: t('Assigned'), color: 'green', demos: [] },
        'fallback-assigned': { label: t('Fallback'), color: 'blue', demos: [] },
        processed: { label: t('Processed'), color: 'green', demos: [] },
        failed: { label: t('Failed'), color: 'red', demos: [] },
        'failed-validity': { label: t('Invalid'), color: 'orange', demos: [] },
        'unsupported-version': { label: t('Unsupported'), color: 'purple', demos: [] },
    };
    recentlyProcessed.value.forEach(d => {
        if (groups[d.status]) groups[d.status].demos.push(d);
        else if (groups.failed) groups.failed.demos.push(d);
    });
    // Only return groups that have demos
    const active = {};
    for (const [key, group] of Object.entries(groups)) {
        if (group.demos.length > 0) active[key] = group;
    }
    const successCount = (groups.assigned.demos.length + groups['fallback-assigned'].demos.length + groups.processed.demos.length);
    const failCount = recentlyProcessed.value.length - successCount;
    return { groups: active, total: recentlyProcessed.value.length, success: successCount, fail: failCount };
});

// The upload panel is folded away to start with. It is machinery, and most
// people open this page to watch a demo, not to send one.
//
// It opens by itself the moment it has something to show: files waiting, an
// upload running, a summary, or demos that just finished processing. It also
// opens when a file is dragged anywhere over the page, because the drop zone
// lives inside it and a folded panel cannot catch the drop.
const uploadOpen = ref(false);

watch(
    [selectedFiles, uploading, uploadSummary, processingSummary, compsNotices],
    ([files, busy, summary, processed, notices]) => {
        if (files.length || busy || summary || processed || notices.length) {
            uploadOpen.value = true;
        }
    },
    { deep: true }
);

const openUploadOnDrag = (event) => {
    if (!uploadOpen.value && Array.from(event.dataTransfer?.types || []).includes('Files')) {
        uploadOpen.value = true;
    }
};

const reprocessAllFailed = async () => {
    showReprocessConfirm.value = false;
    reprocessingFailed.value = true;
    reprocessMessage.value = '';
    try {
        const response = await axios.post(route('demos.reprocessAllFailed'));
        reprocessMessage.value = response.data.message;
        recentlyProcessed.value = [];
        processingDuration.value = null;
        processingStartTime.value = Date.now();
        actionStartedAt.value = new Date();
        startStatusPolling();
        router.reload({ only: ['userDemos', 'publicDemos', 'demoCounts'], preserveState: true });
    } catch (error) {
        reprocessMessage.value = t('Failed: :message', { message: error.response?.data?.message || error.message });
    } finally {
        reprocessingFailed.value = false;
    }
};

// Which list the page is showing. Only one is on screen at a time, so the
// page has one table instead of two stacked ones. It lives in the URL, which
// makes a link to somebody's own uploads shareable and survives a reload.
const activeList = ref(
    new URLSearchParams(window.location.search).get('list') === 'mine' ? 'mine' : 'all'
);

const changeList = (list) => {
    activeList.value = list;
    const currentUrl = new URL(window.location.href);

    if (list === 'all') {
        currentUrl.searchParams.delete('list');
    } else {
        currentUrl.searchParams.set('list', list);
    }

    // This has to name the props it wants. A plain visit is not a partial
    // reload, and the controller answers those with null lists on purpose,
    // so the table would empty itself on every tab click.
    // 'filters' comes back too. Switching tabs can drop a value the new tab
    // cannot use - a status like "waiting" exists only on your own list - and
    // without this the panel would keep showing a filter the server ignored.
    const only = list === 'mine'
        ? ['userDemos', 'demoCounts', 'filters', 'filtersNarrowed']
        : ['publicDemos', 'browseCounts', 'filters', 'filtersNarrowed'];

    const alreadyLoaded = list === 'mine' ? props.userDemos : props.publicDemos;
    if (!alreadyLoaded) {
        demosLoading.value = true;
    }

    router.visit(currentUrl.pathname + '?' + currentUrl.searchParams.toString(), {
        preserveScroll: true,
        preserveState: true,
        only,
        onFinish: () => { demosLoading.value = false; },
    });
};

// Filter state (for Your Uploads section)
// One filter set, shared by both tabs. The panel sits above the tabs, so
// somebody who has narrowed things down to a map keeps that when they switch.
// The values a tab cannot use are dropped by the controller rather than
// silently returning nothing.
const DEFAULT_FILTERS = {
    tab: 'all', status: 'all', search: '', map: '', players: [], physics: [],
    time_min: null, time_max: null, country: '', date_from: '', date_to: '',
    uploaded_by: '', rank_min: null, rank_max: null,
    confidence: '', other_user_matches: false,
    sort: 'created_at', order: 'desc',
};

const filterState = reactive({ ...DEFAULT_FILTERS, ...(props.filters || {}) });

// The controller normalises what it was given, so the panel follows it rather
// than keeping its own idea of the truth.
watch(() => props.filters, (next) => {
    if (next) Object.assign(filterState, next);
});

const isAdminUser = computed(() => !!($page.props.auth.user?.admin || $page.props.auth.user?.is_admin));

const applyFilters = (patch = {}) => {
    Object.assign(filterState, patch);

    const url = new URL(window.location.href);
    const params = url.searchParams;

    // Wipe every name this panel owns, including the old browse_* spellings a
    // saved link may still carry, then write back only what is set. Without
    // the wipe a filter that was cleared would live on in the URL.
    Object.keys(DEFAULT_FILTERS).forEach((key) => {
        params.delete(key);
        params.delete('browse_' + key);
    });
    params.delete('userPage');
    params.delete('browsePage');

    Object.entries(DEFAULT_FILTERS).forEach(([key, fallback]) => {
        const value = filterState[key];
        if (value === null || value === undefined || value === '' || value === false) return;
        if (Array.isArray(value)) {
            if (value.length) params.set(key, value.join(','));
            return;
        }
        if (value !== fallback) params.set(key, value);
    });

    router.visit(url.pathname + (params.toString() ? '?' + params.toString() : ''), {
        preserveScroll: true,
        preserveState: true,
        only: activeList.value === 'mine'
            ? ['userDemos', 'demoCounts', 'filters', 'filtersNarrowed']
            : ['publicDemos', 'browseCounts', 'filters', 'filtersNarrowed'],
    });
};

// The template still speaks the old names in a few places. These keep it
// working while everything reads from one object underneath.
const activeTab = computed(() => filterState.tab);
const activeStatusFilter = computed(() => filterState.status);
const activeBrowseTab = computed(() => filterState.tab);
const activeBrowseStatus = computed(() => filterState.status);
const sortBy = computed(() => filterState.sort);
const sortOrder = computed(() => filterState.order);
const browseSortBy = computed(() => filterState.sort);
const browseSortOrder = computed(() => filterState.order);

// Why a demo failed, in a sentence.
//
// `processing_output` is a log line written for us, not for the player, and
// two of its shapes carry a path on the server. This turns the four shapes
// that actually occur into something readable, and the raw line stays for
// staff. Counted on the 2 921 failed demos that recorded anything: 2 908
// could not be parsed, 10 timed out, 3 broke while being packed away.
const failureReason = (demo) => {
    const output = demo?.processing_output || '';

    if (!output) return t('No reason was recorded for this one.');
    if (output.includes('Could not parse demo file')) return t('The file could not be read as a demo.');
    if (output.includes('timed out')) return t('The demo took too long to read and was given up on.');
    if (output.includes('7z') || output.includes('rmdir')) return t('The demo was read, but storing it failed.');

    return t('Processing failed.');
};

// Tooltip state
const hoveredDemo = ref(null);
const tooltipPosition = ref({ x: 0, y: 0 });

const showTooltip = (demo, event) => {
    hoveredDemo.value = demo;
    updateTooltipPosition(event);
};

const hideTooltip = () => {
    hoveredDemo.value = null;
};

const updateTooltipPosition = (event) => {
    tooltipPosition.value = {
        x: event.clientX,
        y: event.clientY
    };
};

// Which rows have their details panel open. A set rather than a single id so
// two demos can be compared side by side, which is the point of looking.
const expandedDemos = ref(new Set());

const toggleDetails = (id) => {
    const next = new Set(expandedDemos.value);

    next.has(id) ? next.delete(id) : next.add(id);
    expandedDemos.value = next;
};

// Manual assignment state
const showAssignModal = ref(false);
const assigningDemo = ref(null);
const searchQuery = ref('');
const availableMaps = ref([]);
const selectedMap = ref('');
const selectedPhysics = ref('VQ3');
const physicsDropdownOpen = ref(false);
const availableRecords = ref([]);
const selectedRecord = ref('');
const loadingMaps = ref(false);
const loadingRecords = ref(false);

// Drag and drop state
const isDragOver = ref(false);
const dragCounter = ref(0);

const form = useForm({
    demos: [],
});

// Initialize filter state from URL parameters
const urlParams = new URLSearchParams(window.location.search);
if (urlParams.has('tab')) {
    activeTab.value = urlParams.get('tab');
}
if (urlParams.has('status')) {
    activeStatusFilter.value = urlParams.get('status');
}

// No client-side filtering needed - server handles it
const filteredDemos = computed(() => {
    return props.userDemos?.data || [];
});

// Upload restrictions
const canUpload = computed(() => {
    return $page.props.canUploadDemos || false;
});

const uploadRestrictionMessage = computed(() => {
    const user = $page.props.auth.user;
    if (!user) return '';

    if (user.upload_restricted) {
        return t('Your account has been restricted from uploading demos. Please contact an administrator.');
    }

    const recordsCount = $page.props.recordsCount || 0;
    const needed = 30 - recordsCount;
    return t('You need at least 30 records to upload demos. You currently have :count record(s). :needed more needed.', { count: recordsCount, needed });
});

const changeTabFilter = (tab) => applyFilters({ tab });
const changeStatusFilter = (status) => applyFilters({ status });

// Use server-provided counts instead of counting current page
const demoCountsComputed = computed(() => {
    const counts = props.demoCounts || {
        all: 0,
        online: 0,
        offline: 0,
        assigned: 0,
        fallback_assigned: 0,
        processed: 0,
        failed_validity: 0,
        failed: 0,
        unsupported_version: 0,
        online_assigned: 0,
        online_fallback_assigned: 0,
        online_processed: 0,
        online_failed_validity: 0,
        online_failed: 0,
        offline_assigned: 0,
        offline_fallback_assigned: 0,
        offline_processed: 0,
        offline_failed_validity: 0,
        offline_failed: 0,
    };

    // Return counts based on active tab
    if (activeTab.value === 'online') {
        return {
            all: counts.all,
            online: counts.online,
            offline: counts.offline,
            uploaded: counts.uploaded,
            assigned: counts.online_assigned,
            fallback_assigned: counts.online_fallback_assigned,
            processed: counts.online_processed,
            failed_validity: counts.online_failed_validity,
            failed: counts.online_failed,
            unsupported_version: counts.unsupported_version,
        };
    } else if (activeTab.value === 'offline') {
        return {
            all: counts.all,
            online: counts.online,
            offline: counts.offline,
            uploaded: counts.uploaded,
            assigned: counts.offline_assigned,
            fallback_assigned: counts.offline_fallback_assigned,
            processed: counts.offline_processed,
            failed_validity: counts.offline_failed_validity,
            failed: counts.offline_failed,
            unsupported_version: counts.unsupported_version,
        };
    } else {
        // 'all' tab - show total counts
        return {
            all: counts.all,
            online: counts.online,
            offline: counts.offline,
            uploaded: counts.uploaded,
            assigned: counts.assigned,
            fallback_assigned: counts.fallback_assigned,
            processed: counts.processed,
            failed_validity: counts.failed_validity,
            failed: counts.failed,
            unsupported_version: counts.unsupported_version,
        };
    }
});

// Browse counts computed
const browseCountsComputed = computed(() => {
    const counts = props.browseCounts || {
        all: 0,
        online: 0,
        offline: 0,
        assigned: 0,
        fallback_assigned: 0,
        processed: 0,
        failed_validity: 0,
        failed: 0,
        unsupported_version: 0,
        online_assigned: 0,
        online_fallback_assigned: 0,
        online_processed: 0,
        online_failed_validity: 0,
        online_failed: 0,
        offline_assigned: 0,
        offline_fallback_assigned: 0,
        offline_processed: 0,
        offline_failed_validity: 0,
        offline_failed: 0,
    };

    // Return counts based on active browse tab
    if (activeBrowseTab.value === 'online') {
        return {
            all: counts.all,
            online: counts.online,
            offline: counts.offline,
            assigned: counts.online_assigned,
            fallback_assigned: counts.online_fallback_assigned,
            processed: counts.online_processed,
            failed_validity: counts.online_failed_validity,
            failed: counts.online_failed,
        };
    } else if (activeBrowseTab.value === 'offline') {
        return {
            all: counts.all,
            online: counts.online,
            offline: counts.offline,
            assigned: counts.offline_assigned,
            fallback_assigned: counts.offline_fallback_assigned,
            processed: counts.offline_processed,
            failed_validity: counts.offline_failed_validity,
            failed: counts.offline_failed,
        };
    } else {
        // 'all' tab - show total counts
        return {
            all: counts.all,
            online: counts.online,
            offline: counts.offline,
            assigned: counts.assigned,
            fallback_assigned: counts.fallback_assigned,
            processed: counts.processed,
            failed_validity: counts.failed_validity,
            failed: counts.failed,
        };
    }
});

const changeBrowseTabFilter = (tab) => applyFilters({ tab });
const changeBrowseStatusFilter = (status) => applyFilters({ status });

const handleFileSelect = (event) => {
    const files = Array.from(event.target.files || event.dataTransfer.files);
    const validFiles = files.filter(file => {
        // Get full filename for pattern matching
        const fileName = file.name.toLowerCase();
        // Check if it's a demo file (.dm_68, .dm_66, etc.) or archive (.zip, .rar, .7z)
        return fileName.match(/\.dm_\d+$/) || fileName.endsWith('.zip') || fileName.endsWith('.rar') || fileName.endsWith('.7z');
    });

    if (validFiles.length !== files.length) {
        const skipped = files.length - validFiles.length;
        showUploadInfoModal(
            t('Files Filtered'),
            t('Found :found demo file(s). Skipped :skipped non-demo file(s).', { found: validFiles.length, skipped })
                + '\n\n' + t('Only demo files (.dm_68, .dm_66, etc.) and archives (.zip, .rar, .7z) are accepted.'),
            'info'
        );
    }

    selectedFiles.value = [...selectedFiles.value, ...validFiles];
};

// Drag and drop handlers
const handleDragEnter = (e) => {
    e.preventDefault();
    e.stopPropagation();
    dragCounter.value++;
    isDragOver.value = true;
};

const handleDragLeave = (e) => {
    e.preventDefault();
    e.stopPropagation();
    dragCounter.value--;
    if (dragCounter.value === 0) {
        isDragOver.value = false;
    }
};

const handleDragOver = (e) => {
    e.preventDefault();
    e.stopPropagation();
};

const handleDrop = async (e) => {
    e.preventDefault();
    e.stopPropagation();
    isDragOver.value = false;
    dragCounter.value = 0;

    // Use dataTransfer.files directly for simple file drops
    const files = Array.from(e.dataTransfer.files);

    if (files.length > 0) {
        const validFiles = files.filter(file => {
            const fileName = file.name.toLowerCase();
            return fileName.match(/\.dm_\d+$/) || fileName.endsWith('.zip') || fileName.endsWith('.rar') || fileName.endsWith('.7z');
        });

        if (validFiles.length !== files.length) {
            const skipped = files.length - validFiles.length;
            showUploadInfoModal(
                t('Files Filtered'),
                t('Found :found demo file(s). Skipped :skipped non-demo file(s).', { found: validFiles.length, skipped })
                    + '\n\n' + t('Only demo files (.dm_68, .dm_66, etc.) and archives (.zip, .rar, .7z) are accepted.'),
                'info'
            );
        }

        if (validFiles.length > 0) {
            selectedFiles.value = [...selectedFiles.value, ...validFiles];
        }
    }
};

const removeFile = (index) => {
    selectedFiles.value.splice(index, 1);
};

const clearAllFiles = () => {
    selectedFiles.value = [];
    if (fileInput.value) {
        fileInput.value.value = '';
    }
};

const BATCH_SIZE = 100;

const uploadDemos = async () => {
    if (selectedFiles.value.length === 0) {
        showUploadInfoModal(t('No Files Selected'), t('Please select demo files to upload.'), 'warning');
        return;
    }

    uploading.value = true;
    uploadProgress.value = 0;
    uploadErrors.value = [];
    uploadSuccess.value = [];
    uploadSummary.value = null;

    const uploadStartTime = Date.now();
    const files = [...selectedFiles.value];
    const totalFiles = files.length;
    const totalBatches = Math.ceil(totalFiles / BATCH_SIZE);
    let allUploaded = [];
    let allErrors = [];
    let allDemoIds = [];
    let totalReceived = 0;
    let totalQueued = 0;
    let totalDuplicates = 0;
    let totalOtherErrors = 0;
    let totalReplaced = 0;
    let totalRetriedBatches = 0;
    let totalFailedBatchFiles = 0;
    let failedFileNames = [];
    let pollingStarted = false;

    // Prepare tracking state before upload loop so polling can work during upload
    recentlyProcessed.value = [];
    processingDuration.value = null;
    processingStartTime.value = Date.now();
    actionStartedAt.value = new Date();

    const UPLOAD_TIMEOUT = 300000; // 5 minutes per batch

    const sendBatch = async (batchFiles, label) => {
        const formData = new FormData();
        batchFiles.forEach(file => formData.append('demos[]', file));
        // The date the file has on this machine, one per file, in the same
        // order. An upload carries bytes and a name and nothing else, and comps
        // has to be able to tell a run made this week from one that has been on
        // a hard drive for years. Sent as unix seconds.
        batchFiles.forEach(file => formData.append('demo_mtimes[]', Math.floor((file.lastModified || 0) / 1000)));

        try {
            const response = await axios.post(route('demos.upload'), formData, {
                headers: { 'Content-Type': 'multipart/form-data' },
                timeout: UPLOAD_TIMEOUT,
                onUploadProgress: function (progressEvent) {
                    if (progressEvent.lengthComputable) {
                        uploadProgress.value = Math.round(((totalFiles - files.length + totalReceived) / totalFiles) * 100);
                    }
                }
            });

            if (response.data.success) {
                console.log(`[Upload] ${label} done: queued=${response.data.summary?.queued || 0}, dupes=${response.data.summary?.duplicates || 0}`);
                allUploaded.push(...(response.data.uploaded || []));
                allErrors.push(...(response.data.errors || []));
                const demoIds = (response.data.uploaded || []).map(d => d.id).filter(Boolean);
                allDemoIds.push(...demoIds);
                trackingDemoIds.value = [...allDemoIds];

                if (!pollingStarted) {
                    pollingStarted = true;
                    startStatusPolling();
                }

                if (response.data.summary) {
                    totalReceived += response.data.summary.total_received || 0;
                    totalQueued += response.data.summary.queued || 0;
                    totalDuplicates += response.data.summary.duplicates || 0;
                    totalOtherErrors += response.data.summary.errors || 0;
                    totalReplaced += response.data.summary.replaced || 0;
                }
                return true;
            }
            return true; // Server-side failure, don't split
        } catch (error) {
            console.warn(`[Upload] ${label} failed (${batchFiles.length} files): ${error.message}`);
            return false;
        }
    };

    const uploadWithSplit = async (batchFiles, label) => {
        const success = await sendBatch(batchFiles, label);
        if (success) return;

        // Split failed batch: 100->50, 50->10, 10->1
        let subSize;
        if (batchFiles.length > 50) subSize = 50;
        else if (batchFiles.length > 10) subSize = 10;
        else subSize = 1;

        if (subSize >= batchFiles.length) {
            // Single file failed - mark as failed
            totalFailedBatchFiles += batchFiles.length;
            batchFiles.forEach(f => failedFileNames.push(f.name));
            allErrors.push({ file: batchFiles[0].name, code: 'failed', message: label });
            console.error(`[Upload] ${label} FAILED: ${batchFiles[0].name}`);
            return;
        }

        console.log(`[Upload] ${label} splitting ${batchFiles.length} -> ${subSize}s`);
        totalRetriedBatches++;

        for (let j = 0; j < batchFiles.length; j += subSize) {
            const subBatch = batchFiles.slice(j, j + subSize);
            await uploadWithSplit(subBatch, `${label}.${Math.floor(j / subSize) + 1}`);
        }
    };

    try {
        for (let i = 0; i < totalBatches; i++) {
            const batchFiles = files.slice(i * BATCH_SIZE, (i + 1) * BATCH_SIZE);
            console.log(`[Upload] Batch ${i + 1}/${totalBatches} sending ${batchFiles.length} files...`);

            await uploadWithSplit(batchFiles, `Batch ${i + 1}/${totalBatches}`);

            // Update summary after each top-level batch
            uploadSummary.value = {
                total_selected: totalFiles,
                total_sent: totalReceived,
                queued: totalQueued,
                replaced: totalReplaced,
                duplicates: totalDuplicates,
                errors: totalOtherErrors,
                skipped_frontend: 0,
                retried_batches: totalRetriedBatches,
                failed_batch_files: totalFailedBatchFiles,
                failed_file_names: [...failedFileNames],
                duration: ((Date.now() - uploadStartTime) / 1000).toFixed(1),
                batch_progress: `${i + 1}/${totalBatches}`,
            };
            uploadSuccess.value = allUploaded;
            uploadErrors.value = allErrors;
        }

        console.log(`[Upload] All batches done. Total queued=${totalQueued}, dupes=${totalDuplicates}, errors=${allErrors.length}`);
        uploadSuccess.value = allUploaded;
        uploadErrors.value = allErrors;

        // Build upload summary
        const skippedFrontend = totalFiles - totalReceived;
        const uploadDuration = ((Date.now() - uploadStartTime) / 1000).toFixed(1);
        uploadSummary.value = {
            total_selected: totalFiles,
            total_sent: totalReceived,
            queued: totalQueued,
            replaced: totalReplaced,
            duplicates: totalDuplicates,
            errors: totalOtherErrors,
            skipped_frontend: skippedFrontend > 0 ? skippedFrontend : 0,
            retried_batches: totalRetriedBatches,
            failed_batch_files: totalFailedBatchFiles,
            failed_file_names: [...failedFileNames],
            duration: uploadDuration,
        };

        // Clear selected files
        selectedFiles.value = [];
        if (fileInput.value) {
            fileInput.value.value = '';
        }

        // Ensure final tracking IDs are set (polling already started during upload)
        trackingDemoIds.value = allDemoIds;
        if (!pollingStarted) {
            startStatusPolling();
        }

        // Immediately reload the demos list
        router.reload({ only: ['userDemos', 'publicDemos'], preserveState: true });

        // Results stay visible until user dismisses them
    } catch (error) {
        console.error('Upload error:', error);
        uploadErrors.value = [...allErrors, {
            file: '',
            code: 'failed',
            message: t('Upload failed: :message', { message: error.response?.data?.message || error.message }),
        }];
    } finally {
        uploading.value = false;
        setTimeout(() => {
            uploadProgress.value = 0;
        }, 800);
    }
};

const formatFileSize = (bytes) => {
    if (bytes === 0) return '0 ' + t('Bytes');
    const k = 1024;
    const sizes = [t('Bytes'), 'KB', 'MB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
};

// Both sort handlers have to name the props they want, like every filter and
// pager on this page does. A visit without `only` is a full one, and the
// controller answers a full load with empty tables on purpose - the page
// fetches them itself once it is mounted, so the first paint is not held up by
// four queries. `preserveState` then keeps the very same component alive, so
// that mount never happens again and the table simply goes blank. Reported
// twice from the browse table: clicking Time emptied the list, while opening
// the identical URL by hand worked.
const goToPage = (pageName, page) => {
    const url = new URL(window.location.href);

    if (page <= 1) {
        url.searchParams.delete(pageName);
    } else {
        url.searchParams.set(pageName, page);
    }

    router.visit(url.pathname + (url.searchParams.toString() ? '?' + url.searchParams.toString() : ''), {
        preserveScroll: true,
        preserveState: true,
        only: activeList.value === 'mine' ? ['userDemos'] : ['publicDemos'],
    });
};

const sortColumn = (column) => {
    applyFilters({
        sort: column,
        order: filterState.sort === column && filterState.order === 'asc' ? 'desc' : 'asc',
    });
};

const sortBrowseColumn = sortColumn;

const formatTime = (ms) => {
    if (!ms) return '-';
    const minutes = Math.floor(ms / 60000);
    const seconds = Math.floor((ms % 60000) / 1000);
    const milliseconds = ms % 1000;
    return `${minutes}:${String(seconds).padStart(2, '0')}.${String(milliseconds).padStart(3, '0')}`;
};

const matchMethodLabel = (method) => {
    switch (method) {
        case 'q3df_colored_record': return 'q3df ✓ rec';
        case 'q3df_plain_record':   return 'q3df (plain) ✓ rec';
        case 'q3df_colored_profile': return 'q3df → profile';
        case 'q3df_plain_profile':  return 'q3df (plain) → profile';
        case 'uploader_record':     return 'uploader';
        case 'fuzzy_nick':          return 'nick fuzzy';
        default: return method;
    }
};

const matchMethodTooltip = (demo) => {
    const alias = demo.matched_alias ? ' ' + t('- matched alias: :alias', { alias: demo.matched_alias }) : '';
    switch (demo.match_method) {
        case 'q3df_colored_record':
            return t('Matched via colored q3df login + time to record') + alias;
        case 'q3df_plain_record':
            return t('Matched via plain q3df login + time to record') + alias;
        case 'q3df_colored_profile':
            return t('Matched via colored q3df login to profile (no record for this time)') + alias;
        case 'q3df_plain_profile':
            return t('Matched via plain q3df login to profile (no record for this time, alias is globally unique)') + alias;
        case 'uploader_record':
            return t('Uploader has a record with this exact time - no name check needed');
        case 'fuzzy_nick':
            return t('Matched via fuzzy nickname comparison') + alias;
        default: return '';
    }
};

const getStatusColor = (status) => {
    switch (status) {
        case 'uploaded': return 'text-yellow-500';
        case 'processing': return 'text-blue-500';
        case 'processed': return 'text-green-500';
        case 'assigned': return 'text-purple-500';
        case 'failed-validity': return 'text-orange-500';
        case 'failed': return 'text-red-500';
        case 'unsupported-version': return 'text-purple-500';
        default: return 'text-gray-500';
    }
};

const reprocessDemo = async (demoId) => {
    if (!confirm(t('Are you sure you want to reprocess this demo?'))) {
        return;
    }

    try {
        const response = await axios.post(route('demos.reprocess', demoId));
        if (response.data.success) {
            recentlyProcessed.value = [];
            actionStartedAt.value = new Date();
            startStatusPolling();
            router.reload({ only: ['userDemos', 'publicDemos', 'demoCounts'], preserveState: true });
        }
    } catch (error) {
        alert(t('Failed to reprocess demo: :message', { message: error.response?.data?.message || error.message }));
    }
};

const deleteDemo = async (demoId) => {
    if (!confirm(t('Are you sure you want to delete this demo?'))) {
        return;
    }

    try {
        await axios.delete(route('demos.destroy', demoId));
        router.reload({ only: ['userDemos', 'publicDemos'], preserveState: true });
    } catch (error) {
        alert(t('Failed to delete demo: :message', { message: error.response?.data?.message || error.message }));
    }
};

// Group demos by status
const groupedDemos = computed(() => {
    const groups = {
        assigned: [],
        processed: [],
        'failed-validity': [],
        failed: [],
        processing: [],
        pending: [],
        uploaded: []
    };

    if (!props.userDemos || !props.userDemos.data) return groups;

    props.userDemos.data.forEach(demo => {
        if (groups[demo.status]) {
            groups[demo.status].push(demo);
        } else {
            groups.failed.push(demo); // Default to failed if unknown status
        }
    });

    // Sort each group by created_at desc (newest first)
    Object.keys(groups).forEach(status => {
        groups[status].sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
    });

    return groups;
});

// Status polling functions
let pollInFlight = false;
const pollOnce = async () => {
    if (pollInFlight) return; // prevent stacking requests
    pollInFlight = true;
    try {
        let response;
        if (trackingDemoIds.value.length > 0) {
            // Use POST to avoid URL length limits with large tracking arrays
            response = await axios.post(route('demos.status'), { tracking_ids: trackingDemoIds.value });
        } else {
            response = await axios.get(route('demos.status'));
        }
        processingDemos.value = response.data.processing_demos;
        queueStats.value = response.data.queue_stats;
        compsNotices.value = response.data.comps_notices || [];

        // Backend returns recently completed demos (last 5 min + tracked IDs)
        const completed = response.data.completed_demos || [];
        if (completed.length > 0) {
            let newCompleted;
            if (trackingDemoIds.value.length > 0) {
                const trackedSet = new Set(trackingDemoIds.value);
                newCompleted = completed.filter(d => trackedSet.has(d.id));
            } else if (actionStartedAt.value) {
                newCompleted = completed.filter(d => new Date(d.updated_at) >= actionStartedAt.value);
            } else {
                newCompleted = completed;
            }

            // Accumulate results (merge new into existing) to prevent race conditions
            // with fast polling where out-of-order responses could overwrite results
            const existingMap = new Map(recentlyProcessed.value.map(d => [d.id, d]));
            newCompleted.forEach(d => existingMap.set(d.id, d)); // update or add
            recentlyProcessed.value = Array.from(existingMap.values());
        }

        // Stop polling when nothing is processing/queued globally (but never during active upload)
        const timeSinceAction = actionStartedAt.value ? (Date.now() - actionStartedAt.value.getTime()) : Infinity;
        const globalRemaining = (response.data.queue_stats?.total_queued || 0) + (response.data.queue_stats?.total_processing || 0);
        if (!uploading.value && (response.data.processing_demos || []).length === 0 && globalRemaining === 0 && timeSinceAction > 5000) {
            if (processingStartTime.value) {
                processingDuration.value = ((Date.now() - processingStartTime.value) / 1000).toFixed(1);
                processingStartTime.value = null;
            }
            trackingDemoIds.value = [];
            stopStatusPolling();
            router.reload({ only: ['userDemos', 'publicDemos', 'demoCounts'], preserveState: true });
        }
    } catch (error) {
        console.error('Status polling error:', error);
    } finally {
        pollInFlight = false;
    }
};

const startStatusPolling = () => {
    if (statusPolling.value) {
        clearInterval(statusPolling.value);
    }

    // Immediate first poll
    pollOnce();

    statusPolling.value = setInterval(() => pollOnce(), 2000);
};

const stopStatusPolling = () => {
    if (statusPolling.value) {
        clearInterval(statusPolling.value);
        statusPolling.value = null;
    }
};

// Start polling on component mount if there are processing demos or saved tracking
const checkForProcessingDemos = async () => {
    if ($page.props.auth.user) {
        try {
            const response = await axios.get(route('demos.status'));
            processingDemos.value = response.data.processing_demos;
            queueStats.value = response.data.queue_stats;
            // On load, not only while polling. A hold lasts until the round
            // ends, which is days after the upload panel has gone - so the
            // one moment this must not depend on is the upload itself.
            compsNotices.value = response.data.comps_notices || [];

            const hasWork = response.data.processing_demos.length > 0
                || (response.data.queue_stats.total_queued || 0) > 0
                || (response.data.queue_stats.total_processing || 0) > 0;
            if (hasWork) {
                startStatusPolling();
            }
        } catch (error) {
            console.error('Initial status check error:', error);
        }
    }
};

// Lifecycle hooks
// Lifecycle hooks are imported at the top of this <script setup>
const demosLoading = ref(true);

onMounted(() => {
    checkForProcessingDemos();

    if (new URLSearchParams(window.location.search).get('upload') === '1') {
        uploadOpen.value = true;
    }
    document.addEventListener('dragover', openUploadOnDrag);

    if (!props.userDemos && !props.publicDemos) {
        const start = Date.now();
        // Both counts, because both sit on the tab buttons. Only one list,
        // because only one list is on screen.
        router.reload({
            only: activeList.value === 'mine'
                ? ['userDemos', 'demoCounts', 'browseCounts']
                : ['publicDemos', 'demoCounts', 'browseCounts'],
            onFinish: () => {
                const remaining = 400 - (Date.now() - start);
                if (remaining > 0) {
                    setTimeout(() => { demosLoading.value = false; }, remaining);
                } else {
                    demosLoading.value = false;
                }
            }
        });
    } else {
        demosLoading.value = false;
    }
});

onUnmounted(() => {
    stopStatusPolling();
    document.removeEventListener('dragover', openUploadOnDrag);
});

// Manual assignment functions
const openAssignModal = (demo) => {
    assigningDemo.value = demo;
    showAssignModal.value = true;

    // Pre-fill physics from demo metadata
    if (demo.physics) {
        // `VQ3.TR` is a run with a timereset, the records are plain VQ3.
        selectedPhysics.value = String(demo.physics || 'VQ3').split('.')[0].toUpperCase();
    }

    // Pre-fill map from demo metadata and auto-select + load records
    if (demo.map_name) {
        searchQuery.value = demo.map_name;
        selectedMap.value = demo.map_name;
        loadRecords();
    } else {
        searchMaps();
    }
};

// Suggested matches - records sorted by time distance to demo's time
const suggestedRecords = computed(() => {
    if (!assigningDemo.value || availableRecords.value.length === 0) return [];
    const demoTime = assigningDemo.value.time_ms;
    if (!demoTime) return [];
    return [...availableRecords.value]
        .map(r => ({ ...r, timeDiff: Math.abs(r.time - demoTime) }))
        .sort((a, b) => a.timeDiff - b.timeDiff)
        .slice(0, 5);
});

const closeAssignModal = () => {
    showAssignModal.value = false;
    assigningDemo.value = null;
    searchQuery.value = '';
    availableMaps.value = [];
    selectedMap.value = '';
    availableRecords.value = [];
    selectedRecord.value = '';
};

const searchMaps = async () => {
    loadingMaps.value = true;
    try {
        const response = await axios.get(route('demos.maps'), {
            params: { search: searchQuery.value }
        });
        availableMaps.value = response.data;
    } catch (error) {
        console.error('Error searching maps:', error);
    } finally {
        loadingMaps.value = false;
    }
};

const selectMap = async (mapname) => {
    selectedMap.value = mapname;
    loadRecords();
};

const loadRecords = async () => {
    if (!selectedMap.value) return;

    loadingRecords.value = true;
    try {
        const response = await axios.get(route('demos.records', selectedMap.value), {
            params: { physics: selectedPhysics.value }
        });
        availableRecords.value = response.data;
    } catch (error) {
        console.error('Error loading records:', error);
    } finally {
        loadingRecords.value = false;
    }
};

const assignDemo = async () => {
    if (!selectedRecord.value) return;

    try {
        const response = await axios.post(route('demos.assign', assigningDemo.value.id), {
            record_id: selectedRecord.value
        });

        if (response.data.success) {
            closeAssignModal();
            router.reload({ only: ['userDemos', 'publicDemos'], preserveState: true });
        }
    } catch (error) {
        console.error('Error assigning demo:', error);
        alert(t('Failed to assign demo. Please try again.'));
    }
};

const unassignDemo = async (demo) => {
    if (!confirm(t('Are you sure you want to remove the assignment from this demo?'))) {
        return;
    }

    try {
        const response = await axios.post(route('demos.unassign', demo.id));

        if (response.data.success) {
            router.reload({ only: ['userDemos', 'publicDemos'], preserveState: true });
        }
    } catch (error) {
        console.error('Error unassigning demo:', error);
        alert(t('Failed to unassign demo. Please try again.'));
    }
};

// Watch for search query changes
watch(searchQuery, () => {
    if (searchQuery.value.length >= 2) {
        searchMaps();
    }
});

// Watch for physics changes
watch(selectedPhysics, () => {
    if (selectedMap.value) {
        loadRecords();
    }
});
</script>

<template>
    <div>
        <Head :title="$t('Demo Upload')" />

        <!-- Header Section -->
        <div class="relative bg-gradient-to-b from-black/25 via-black/10 to-transparent pt-6 pb-8">
            <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8">
                <!-- Everything in the header is one line tall now - the two
                     limit cards used to be two rows, which is why this used to
                     top-align. It still wraps on its own when the window is too
                     narrow to hold the lot. -->
                <div class="flex justify-between items-center flex-wrap gap-3">
                    <!-- Title, what the page is for, and the credit, on one
                         line. They wrap onto the next line on their own when
                         the window is too narrow to hold them. Aligned on the
                         baseline so the small text sits on the same line as the
                         letters of the heading rather than its box. -->
                    <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                        <h1 class="text-2xl md:text-3xl font-black text-gray-300/90">{{ $t('Demos') }}</h1>
                        <p class="text-sm text-gray-400">{{ $t('Upload and manage demo files') }}</p>
                        <span class="relative group inline-block text-xs">
                            <span class="cursor-help border-b border-dotted border-gray-600 text-gray-400">{{ $t('Special thanks') }}</span>
                            <span class="invisible opacity-0 group-hover:visible group-hover:opacity-100 transition-opacity absolute left-0 top-full z-30 w-64 rounded-lg bg-gray-900 border border-white/10 px-3 py-2 text-xs text-gray-300 shadow-xl leading-snug">
                                {{ $t('Special thanks to') }} <Link href="/profile/549" class="text-gray-200 hover:text-white underline transition-colors">Enter</Link> {{ $t('for his demo collection that helped populate this database.') }}
                            </span>
                        </span>
                    </div>

                    <Link :href="route('launcher')"
                          class="flex items-center gap-2 bg-gradient-to-r from-blue-600/30 to-blue-500/15 hover:from-blue-600/40 hover:to-blue-500/25 backdrop-blur-sm px-3 py-2 rounded-lg border border-blue-400/40 hover:border-blue-300/60 transition-colors text-sm">
                        <svg class="w-5 h-5 text-blue-300 flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                            <polyline points="7 10 12 15 17 10"/>
                            <line x1="12" y1="15" x2="12" y2="3"/>
                        </svg>
                        <span class="font-bold text-white whitespace-nowrap">{{ $t('Get the launcher') }}</span>
                        <!-- Same shape as the servers page, different half of
                             the launcher: there it is connecting, here it is
                             the watcher that sends your runs in as you play. -->
                        <span class="hidden lg:inline text-blue-200/80 font-semibold text-xs">{{ $t('auto-backup every run, and more') }}</span>
                    </Link>

                    <!-- Limits Info (Right Side) -->
                    <!-- One line each. The long sentence that used to sit under
                         the numbers is the hover text now, which is what made
                         these two rows tall and pushed the whole header down. -->
                    <div class="flex flex-wrap gap-2 items-center">
                        <!-- Download Limit -->
                        <div
                            v-if="localDownloadLimitInfo"
                            class="rounded-lg px-3 py-2 shadow-xl border backdrop-blur-sm text-xs whitespace-nowrap"
                            :class="localDownloadLimitInfo.isGuest ? 'bg-blue-900/20 border-blue-500/30' : localDownloadLimitInfo.remaining === 0 ? 'bg-red-900/20 border-red-500/30' : 'bg-white/[0.06] border-white/10'"
                            :title="localDownloadLimitInfo.isGuest
                                ? $t('Unlock more downloads after')
                                : localDownloadLimitInfo.raised
                                    ? $t('Raised because you donated. Thank you.')
                                    : $t('Downloads limited due to bandwidth costs.')"
                        >
                            <div class="flex items-center gap-2">
                                <svg v-if="localDownloadLimitInfo.isGuest" class="w-4 h-4 text-blue-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <svg v-else-if="localDownloadLimitInfo.remaining === 0" class="w-4 h-4 text-red-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                                </svg>
                                <svg v-else class="w-4 h-4 text-green-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>

                                <template v-if="localDownloadLimitInfo.isGuest">
                                    <span class="text-blue-200 font-semibold">{{ localDownloadLimitInfo.remaining }}/{{ localDownloadLimitInfo.limit }}</span>
                                    <span class="text-blue-300/80">{{ $t('downloads left today') }}</span>
                                    <a href="/login" class="text-blue-200 font-semibold underline hover:text-white transition-colors">{{ $t('login') }}</a>
                                    <span class="text-blue-300/50">/</span>
                                    <a href="/register" class="text-blue-200 font-semibold underline hover:text-white transition-colors">{{ $t('register') }}</a>
                                </template>

                                <template v-else-if="localDownloadLimitInfo.remaining === 0">
                                    <span class="text-red-200 font-semibold">{{ $t('Limit reached') }}</span>
                                    <a href="/donations" class="text-red-200 underline hover:text-white transition-colors">{{ $t('Donate') }}</a>
                                </template>

                                <template v-else>
                                    <span class="text-green-400 font-semibold">{{ localDownloadLimitInfo.remaining }}</span>
                                    <span class="text-gray-300">/{{ localDownloadLimitInfo.limit }}</span>
                                    <span class="text-gray-400">{{ $t('downloads left today') }}</span>
                                    <a
                                        v-if="!localDownloadLimitInfo.raised"
                                        href="/donations"
                                        class="text-gray-300 underline hover:text-white transition-colors"
                                    >{{ $t('Donate to raise it') }}</a>
                                </template>
                            </div>
                        </div>

                        <!-- Upload Limit -->
                        <div
                            v-if="localUploadLimitInfo"
                            class="rounded-lg px-3 py-2 shadow-xl border backdrop-blur-sm text-xs whitespace-nowrap"
                            :class="localUploadLimitInfo.isGuest ? 'bg-purple-900/20 border-purple-500/30' : 'bg-white/[0.06] border-white/10'"
                            :title="localUploadLimitInfo.isGuest ? $t('Unlock unlimited uploads after') : $t('Uploads are free, no bandwidth cost.')"
                        >
                            <div class="flex items-center gap-2">
                                <svg class="w-4 h-4 flex-shrink-0" :class="localUploadLimitInfo.isGuest ? 'text-purple-400' : 'text-green-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5"></path>
                                </svg>

                                <template v-if="localUploadLimitInfo.isGuest">
                                    <span class="text-purple-200 font-semibold">{{ localUploadLimitInfo.remaining }}/{{ localUploadLimitInfo.limit }}</span>
                                    <span class="text-purple-300/80">{{ $t('uploads left today') }}</span>
                                    <a href="/login" class="text-purple-200 font-semibold underline hover:text-white transition-colors">{{ $t('login') }}</a>
                                    <span class="text-purple-300/50">/</span>
                                    <a href="/register" class="text-purple-200 font-semibold underline hover:text-white transition-colors">{{ $t('register') }}</a>
                                </template>

                                <template v-else>
                                    <span class="text-green-400 font-semibold">{{ $t('Unlimited') }}</span>
                                    <span class="text-gray-400">{{ $t('uploads') }}</span>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="overflow-x-hidden">
            <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8 pb-12">

                <LauncherBanner variant="demos" />

                <!-- Upload Section (visible to all users; guests will have restricted actions) -->
                <div class="bg-black/40 backdrop-blur-sm rounded-xl p-3 mb-4 shadow-2xl border border-white/5">
                    <button
                        type="button"
                        @click="uploadOpen = !uploadOpen"
                        class="w-full flex items-center text-sm font-bold text-gray-300 hover:text-white transition-colors"
                        :class="uploadOpen ? 'mb-2' : ''"
                        :aria-expanded="uploadOpen"
                    >
                        <svg class="w-4 h-4 mr-1.5 text-blue-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                        </svg>
                        {{ $t('Upload Demos') }}
                        <span v-if="!uploadOpen" class="ml-2 font-normal text-xs text-gray-500 hidden sm:inline">
                            {{ $t('Drag files here or click to open') }}
                        </span>
                        <svg
                            class="w-4 h-4 ml-auto text-gray-500 transition-transform flex-shrink-0"
                            :class="uploadOpen ? 'rotate-180' : ''"
                            fill="none" stroke="currentColor" viewBox="0 0 24 24"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>

                    <div v-show="uploadOpen">

                    <!-- Not logged in: full clickable login overlay -->
                    <div v-if="!$page.props.auth.user" class="relative">
                        <div class="border-2 border-dashed border-gray-600 rounded-xl p-4 text-center blur-[2px] opacity-40 pointer-events-none">
                            <div class="space-y-2">
                                <div class="flex justify-center">
                                    <svg class="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                                    </svg>
                                </div>
                                <p class="text-base font-semibold text-gray-200">{{ $t('Drag demo files or folders here') }}</p>
                                <p class="text-gray-400 mt-1 text-sm">{{ $t('Or use buttons below to select files or folders') }}</p>
                                <div class="flex gap-2 justify-center">
                                    <span class="inline-flex items-center px-4 py-2 bg-gradient-to-r from-blue-600 to-blue-700 text-white font-semibold rounded-lg">{{ $t('Select Files') }}</span>
                                    <span class="inline-flex items-center px-4 py-2 bg-gradient-to-r from-green-600 to-green-700 text-white font-semibold rounded-lg">{{ $t('Select Folder') }}</span>
                                </div>
                            </div>
                        </div>
                        <Link
                            :href="route('login')"
                            class="absolute inset-0 flex flex-col items-center justify-center bg-black/60 rounded-xl border-2 border-dashed border-white/10 hover:border-blue-500/50 transition-all duration-300 cursor-pointer group"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-10 h-10 text-gray-400 group-hover:text-blue-400 transition-colors mb-3">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                            </svg>
                            <span class="text-gray-300 group-hover:text-blue-300 font-semibold text-lg transition-colors">{{ $t('Log in to upload demos') }}</span>
                            <span class="text-gray-500 text-sm mt-1">{{ $t('Click here to sign in') }}</span>
                        </Link>
                    </div>

                    <!-- Logged in but email not verified -->
                    <div v-else-if="!$page.props.isVerified" class="relative">
                        <div class="border-2 border-dashed border-red-500/30 rounded-xl p-6 text-center">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-10 h-10 text-red-400 mx-auto mb-3">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
                            </svg>
                            <p class="text-red-400 font-bold text-lg mb-1">{{ $t('Verify your email to upload demos') }}</p>
                            <p class="text-gray-500 text-sm mb-3">{{ $t('Email verification is required before you can upload demos') }}</p>
                            <Link href="/email/verify" class="inline-block px-6 py-2 bg-red-600 hover:bg-red-500 text-white font-bold rounded-lg transition-colors">
                                {{ $t('Verify Email') }}
                            </Link>
                        </div>
                    </div>

                    <!-- Logged in but can't upload (restricted / not enough records) -->
                    <div v-else-if="!canUpload" class="bg-red-500/10 border border-red-500/50 rounded-lg p-4 mb-4">
                        <p class="text-red-400">
                            {{ uploadRestrictionMessage }}
                        </p>
                    </div>

                    <!-- Drag and Drop Zone (authenticated users with upload permission) -->
                    <div
                        v-else
                        @dragenter="handleDragEnter"
                        @dragleave="handleDragLeave"
                        @dragover="handleDragOver"
                        @drop="handleDrop"
                        :class="[
                            'relative border-2 border-dashed rounded-lg p-3 text-center transition-all duration-300 ease-in-out',
                            isDragOver
                                ? 'border-blue-400 bg-blue-900/20 scale-[1.02]'
                                : 'border-gray-600 hover:border-gray-500 hover:bg-gray-700/30'
                        ]"
                    >
                        <!-- strong overlay shown while dragging files -->
                        <div v-if="isDragOver" class="absolute inset-0 bg-blue-900/60 rounded-xl flex items-center justify-center z-10 pointer-events-none">
                            <div class="text-center text-white">
                                <svg class="w-16 h-16 mx-auto mb-2 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                                </svg>
                                <div class="text-2xl font-semibold">{{ $t('Drop demo files to upload') }}</div>
                                <div class="text-sm text-blue-100 mt-1">{{ $t('Release to upload your selected demos') }}</div>
                            </div>
                        </div>
                        <div class="space-y-1.5">
                            <div class="flex items-center justify-center gap-2">
                                <svg :class="[
                                    'w-6 h-6 transition-all duration-300',
                                    isDragOver ? 'text-blue-400 scale-110' : 'text-gray-500'
                                ]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                                </svg>
                                <p :class="[
                                    'text-sm font-semibold transition-colors duration-300',
                                    isDragOver ? 'text-blue-300' : 'text-gray-300'
                                ]">
                                    {{ isDragOver ? $t('Drop files here') : $t('Drag demo files or folders here') }}
                                </p>
                            </div>

                            <!-- Hidden file inputs -->
                            <input
                                ref="fileInput"
                                type="file"
                                multiple
                                accept=".dm_68,.dm_66,.dm_67,.dm_73,.zip,.rar,.7z"
                                @change="handleFileSelect"
                                class="hidden"
                            />
                            <input
                                ref="folderInput"
                                type="file"
                                webkitdirectory
                                directory
                                @change="handleFileSelect"
                                class="hidden"
                            />

                            <!-- Browse buttons -->
                            <div class="flex gap-2 justify-center">
                                <button
                                    type="button"
                                    class="relative inline-flex items-center px-3 py-1.5 text-xs bg-gradient-to-r from-blue-600 to-blue-700 text-white font-semibold rounded-md shadow hover:from-blue-700 hover:to-blue-800 transition-all duration-200 focus:outline-none"
                                    @click="$refs.fileInput.click()"
                                >
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                    </svg>
                                    {{ $t('Select Files') }}
                                </button>
                                <button
                                    type="button"
                                    class="relative inline-flex items-center px-3 py-1.5 text-xs bg-gradient-to-r from-green-600 to-green-700 text-white font-semibold rounded-md shadow hover:from-green-700 hover:to-green-800 transition-all duration-200 focus:outline-none"
                                    @click="$refs.folderInput.click()"
                                >
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"></path>
                                    </svg>
                                    {{ $t('Select Folder') }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Selected Files List -->
                    <div v-if="selectedFiles.length > 0" class="mt-6">
                        <div class="flex items-center justify-between mb-4">
                            <h4 class="text-lg font-semibold text-gray-200 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                {{ $tc(':count file selected|:count files selected', selectedFiles.length) }}
                            </h4>
                            <button
                                @click="clearAllFiles"
                                class="text-sm text-red-400 hover:text-red-300 transition-colors duration-200"
                            >
                                {{ $t('Clear all') }}
                            </button>
                        </div>

                        <div class="grid gap-2 max-h-60 overflow-y-auto">
                            <div
                                v-for="(file, index) in selectedFiles"
                                :key="file.name + index"
                                class="flex items-center justify-between bg-gray-700/50 rounded-lg px-3 py-2 border border-gray-600/50 hover:bg-gray-700 transition-colors duration-200"
                            >
                                <div class="flex items-center space-x-2 min-w-0 flex-1">
                                    <div class="flex-shrink-0">
                                        <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                        </svg>
                                    </div>
                                    <p class="text-sm font-medium text-gray-200 truncate">{{ file.name }}</p>
                                    <p class="text-xs text-gray-400 flex-shrink-0">{{ formatFileSize(file.size) }}</p>
                                </div>
                                <button
                                    @click="removeFile(index)"
                                    class="flex-shrink-0 p-1 text-red-400 hover:text-red-300 hover:bg-red-900/20 rounded transition-colors duration-200 ml-2"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Upload Button -->
                    <div v-if="selectedFiles.length > 0" class="mt-6 flex justify-center">
                        <button
                            @click="uploadDemos"
                            :disabled="uploading || selectedFiles.length === 0"
                            class="group relative inline-flex items-center px-8 py-4 bg-gradient-to-r from-green-600 to-green-700 text-white font-bold text-lg rounded-xl shadow-xl hover:from-green-700 hover:to-green-800 transform hover:scale-105 disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none transition-all duration-300 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 focus:ring-offset-gray-800"
                        >
                            <svg v-if="!uploading" class="w-6 h-6 mr-3 group-hover:animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                            </svg>
                            <div v-else class="w-6 h-6 mr-3 animate-spin border-2 border-white border-t-transparent rounded-full"></div>
                            <span v-if="uploading">{{ $tc('Uploading :count demo...|Uploading :count demos...', selectedFiles.length) }}</span>
                            <span v-else>{{ $tc('Upload :count demo|Upload :count demos', selectedFiles.length) }}</span>
                        </button>
                    </div>

                    <!-- Global upload progress -->
                    <div v-if="uploading || uploadProgress > 0" class="mt-4">
                        <div class="w-full bg-gray-700 rounded-full h-3 overflow-hidden border border-gray-600/50">
                            <div :style="{ width: uploadProgress + '%' }" class="h-3 bg-green-500 transition-all duration-200"></div>
                        </div>
                        <div class="text-xs text-gray-400 mt-2">{{ $t('Progress: :percent%', { percent: uploadProgress }) }}</div>
                    </div>

                    <!-- Upload Summary -->
                    <div v-if="uploadSummary" class="mt-6 p-4 bg-gradient-to-r from-blue-900/30 to-blue-800/20 rounded-xl border border-blue-700/50">
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center">
                            <svg class="w-5 h-5 text-blue-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            <span class="text-blue-300 font-semibold">{{ $t('Upload Summary') }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-gray-500 text-xs">{{ $t('updates per batch') }}</span>
                                <button v-if="!uploading" @click="uploadSummary = null; uploadErrors = []; uploadSuccess = []" class="text-gray-400 hover:text-white transition-colors" :title="$t('Dismiss all results')">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                </button>
                            </div>
                        </div>
                        <div class="grid grid-cols-3 sm:grid-cols-4 lg:grid-cols-7 gap-2 text-sm">
                            <div class="bg-gray-800/50 rounded-lg px-2 py-2 text-center">
                                <div class="text-gray-400 text-xs">{{ $t('Selected') }}</div>
                                <div class="text-white font-bold text-lg">{{ uploadSummary.total_selected.toLocaleString() }}</div>
                            </div>
                            <div v-if="uploadSummary.skipped_frontend > 0" class="bg-yellow-900/30 rounded-lg px-2 py-2 text-center border border-yellow-700/30">
                                <div class="text-yellow-400 text-xs">{{ $t('Skipped') }}</div>
                                <div class="text-yellow-300 font-bold text-lg">{{ uploadSummary.skipped_frontend.toLocaleString() }}</div>
                            </div>
                            <div class="bg-green-900/30 rounded-lg px-2 py-2 text-center border border-green-700/30">
                                <div class="text-green-400 text-xs">{{ $t('Queued') }}</div>
                                <div class="text-green-300 font-bold text-lg">{{ uploadSummary.queued.toLocaleString() }}</div>
                                <div v-if="uploadSummary.replaced > 0" class="text-[10px] text-cyan-400 mt-0.5">({{ $t(':count replaced', { count: uploadSummary.replaced }) }})</div>
                            </div>
                            <div v-if="uploadSummary.duplicates > 0" class="bg-orange-900/30 rounded-lg px-2 py-2 text-center border border-orange-700/30">
                                <div class="text-orange-400 text-xs">{{ $t('Duplicates') }}</div>
                                <div class="text-orange-300 font-bold text-lg">{{ uploadSummary.duplicates.toLocaleString() }}</div>
                            </div>
                            <div v-if="uploadSummary.errors > 0" class="bg-red-900/30 rounded-lg px-2 py-2 text-center border border-red-700/30">
                                <div class="text-red-400 text-xs">{{ $t('Errors') }}</div>
                                <div class="text-red-300 font-bold text-lg">{{ uploadSummary.errors.toLocaleString() }}</div>
                            </div>
                            <div v-if="uploadSummary.retried_batches > 0" class="bg-amber-900/30 rounded-lg px-2 py-2 text-center border border-amber-700/30">
                                <div class="text-amber-400 text-xs">{{ $t('Recovered') }}</div>
                                <div class="text-amber-300 font-bold text-lg">{{ $tc(':count batch|:count batches', uploadSummary.retried_batches) }}</div>
                            </div>
                            <div v-if="uploadSummary.failed_batch_files > 0" class="bg-red-900/40 rounded-lg px-2 py-2 text-center border-2 border-red-500/60 ring-1 ring-red-500/30 cursor-pointer" @click="showFailedFiles = !showFailedFiles" :title="$t('Click to show/hide file names')">
                                <div class="text-red-400 text-xs font-semibold">{{ $t('Not Uploaded') }}</div>
                                <div class="text-red-300 font-bold text-lg">{{ $tc(':count file|:count files', uploadSummary.failed_batch_files) }}</div>
                                <div class="text-[10px] text-red-400 mt-0.5 font-medium">{{ $t('click to show files') }}</div>
                            </div>
                            <div v-if="uploadSummary.batch_progress && uploading" class="bg-blue-900/30 rounded-lg px-2 py-2 text-center border border-blue-700/30">
                                <div class="text-blue-400 text-xs">{{ $t('Batch') }}</div>
                                <div class="text-blue-300 font-bold text-lg">{{ uploadSummary.batch_progress }}</div>
                            </div>
                            <div class="bg-gray-800/50 rounded-lg px-2 py-2 text-center">
                                <div class="text-gray-400 text-xs">{{ $t('Duration') }}</div>
                                <div class="text-white font-bold text-lg">{{ uploadSummary.duration }}s</div>
                            </div>
                        </div>
                    </div>

                    <!-- Not Uploaded Files List -->
                    <div v-if="showFailedFiles && uploadSummary.failed_file_names && uploadSummary.failed_file_names.length > 0" class="mt-4 p-4 bg-red-900/20 rounded-xl border border-red-700/30">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-red-300 font-semibold text-sm">{{ $t('Not Uploaded Files (re-upload these)') }}</span>
                            <button @click="showFailedFiles = false" class="text-red-400 hover:text-white text-xs">{{ $t('close') }}</button>
                        </div>
                        <div class="max-h-60 overflow-y-auto space-y-0.5">
                            <div v-for="name in uploadSummary.failed_file_names" :key="name" class="text-xs text-red-200/80 font-mono truncate">{{ name }}</div>
                        </div>
                    </div>

                    <!-- Upload Results -->
                    <div v-if="uploadErrors.length > 0" class="mt-6 p-4 bg-gradient-to-r from-red-900/30 to-red-800/20 rounded-xl border border-red-700/50">
                        <button @click="errorsExpanded = !errorsExpanded" class="w-full flex items-center justify-between">
                            <div class="flex items-center">
                                <svg class="w-5 h-5 text-red-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <span class="text-red-300 font-semibold">{{ $t('Upload Errors') }}</span>
                                <span class="ml-2 px-2 py-0.5 bg-red-500/30 text-red-300 text-xs rounded-full">{{ uploadErrors.length }}</span>
                            </div>
                            <svg class="w-4 h-4 text-red-400 transition-transform" :class="{ 'rotate-180': errorsExpanded }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>
                        <div v-if="errorsExpanded" class="mt-3 space-y-2 max-h-60 overflow-y-auto pr-1">
                            <details v-for="(errors, category) in categorizedErrors" :key="category" class="group">
                                <summary class="cursor-pointer flex items-center gap-2 text-sm text-red-300/80 hover:text-red-200 py-1">
                                    <svg class="w-3 h-3 transition-transform group-open:rotate-90" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                    </svg>
                                    {{ category }}
                                    <span class="px-1.5 py-0.5 bg-red-500/20 text-red-400 text-[10px] rounded-full">{{ errors.length }}</span>
                                </summary>
                                <div class="ml-5 mt-1 space-y-1">
                                    <div v-for="(error, i) in errors" :key="i" class="flex items-start space-x-1.5">
                                        <svg class="w-3 h-3 text-red-400/60 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                        </svg>
                                        <span class="text-red-200/70 text-xs">
                                            <span v-if="error.file" class="text-red-300/60">{{ error.file }}:</span>
                                            {{ error.message }}
                                        </span>
                                    </div>
                                </div>
                            </details>
                        </div>
                    </div>

                    <div v-if="uploadSuccess.length > 0" class="mt-4 p-4 bg-gradient-to-r from-green-900/30 to-green-800/20 rounded-xl border border-green-700/50">
                        <button @click="successExpanded = !successExpanded" class="w-full flex items-center justify-between">
                            <div class="flex items-center">
                                <svg class="w-5 h-5 text-green-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <span class="text-green-300 font-semibold">{{ $t('Successfully Uploaded') }}</span>
                                <span class="ml-2 px-2 py-0.5 bg-green-500/30 text-green-300 text-xs rounded-full">{{ uploadSuccess.length }}</span>
                            </div>
                            <svg class="w-4 h-4 text-green-400 transition-transform" :class="{ 'rotate-180': successExpanded }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>
                        <div v-if="successExpanded" class="mt-3 space-y-1 max-h-60 overflow-y-auto pr-1">
                            <div v-for="demo in uploadSuccess" :key="demo.id" class="flex items-start space-x-1.5">
                                <svg class="w-3 h-3 text-green-400/60 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <div>
                                    <span class="text-green-200/80 text-xs">{{ demo.processed_filename || demo.original_filename }}</span>
                                    <span v-if="demo.record_id" class="text-purple-300 text-[10px] ml-1">{{ $t('auto-assigned') }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    </div>
                </div>

                <!-- Held by comps. Above the results panel, because a demo in
                     here is one that will never appear in it: the run is on a
                     map being played and the whole site hides it until the
                     round ends. Without this the upload simply goes quiet. -->
                <div v-if="compsNotices.length" class="bg-amber-500/10 border border-amber-500/30 rounded-xl p-4 mb-4">
                    <div class="flex items-center justify-between gap-3 mb-2">
                        <div class="text-sm font-bold text-amber-200">{{ $t('Held by comps') }}</div>
                        <Link :href="route('comps.index')" class="text-xs font-bold text-amber-300 underline decoration-amber-400/40 hover:text-amber-100">
                            {{ $t('Open comps') }}
                        </Link>
                    </div>
                    <div class="space-y-1.5">
                        <div v-for="notice in compsNotices" :key="notice.id" class="text-sm text-amber-100/80">
                            <span class="text-[11px] text-amber-200/50 mr-2">{{ notice.filename }}</span>
                            {{ notice.note }}
                        </div>
                    </div>
                </div>

                <!-- Processing Results (shows after demos finish processing) -->
                <div v-if="processingSummary" class="bg-black/40 backdrop-blur-sm rounded-xl p-4 mb-4 shadow-2xl border border-cyan-500/30">
                    <!-- Summary header -->
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-2">
                            <svg class="w-5 h-5 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path></svg>
                            <h3 class="text-base font-semibold text-cyan-300">{{ $t('Processing Results') }}</h3>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-gray-500 text-xs">{{ $t('updates every 2s') }}</span>
                            <button @click="recentlyProcessed = []; processingDuration = null;" class="text-gray-500 hover:text-gray-300 transition-colors">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                            </button>
                        </div>
                    </div>
                    <!-- Summary stats grid -->
                    <div class="grid grid-cols-3 sm:grid-cols-4 lg:grid-cols-7 gap-2 text-sm">
                        <div class="bg-gray-800/50 rounded-lg px-2 py-2 text-center">
                            <div class="text-gray-400 text-xs">{{ $t('Total') }}</div>
                            <div class="text-white font-bold text-lg">{{ processingSummary.total.toLocaleString() }}</div>
                        </div>
                        <div v-if="processingSummary.success > 0" class="bg-green-900/30 rounded-lg px-2 py-2 text-center border border-green-700/30">
                            <div class="text-green-400 text-xs">{{ $t('Success') }}</div>
                            <div class="text-green-300 font-bold text-lg">{{ processingSummary.success.toLocaleString() }}</div>
                        </div>
                        <div v-if="processingSummary.fail > 0" class="bg-red-900/30 rounded-lg px-2 py-2 text-center border border-red-700/30">
                            <div class="text-red-400 text-xs">{{ $t('Failed') }}</div>
                            <div class="text-red-300 font-bold text-lg">{{ processingSummary.fail.toLocaleString() }}</div>
                        </div>
                        <div v-if="processingDuration" class="bg-gray-800/50 rounded-lg px-2 py-2 text-center">
                            <div class="text-gray-400 text-xs">{{ $t('Duration') }}</div>
                            <div class="text-white font-bold text-lg">{{ processingDuration }}s</div>
                        </div>
                        <div v-else-if="processingStartTime" class="bg-gray-800/50 rounded-lg px-2 py-2 text-center">
                            <div class="text-gray-400 text-xs">{{ $t('Duration') }}</div>
                            <div class="text-yellow-300 font-bold text-lg animate-pulse">{{ $t('running...') }}</div>
                        </div>
                    </div>

                    <!-- Grouped details (collapsible) -->
                    <div class="mt-4">
                        <button @click="processingResultsExpanded = !processingResultsExpanded" class="w-full flex items-center justify-between p-3 bg-gradient-to-r from-cyan-900/20 to-cyan-800/10 rounded-xl border border-cyan-700/30">
                            <div class="flex items-center">
                                <svg class="w-5 h-5 text-cyan-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"></path></svg>
                                <span class="text-cyan-300 font-semibold text-sm">{{ $t('Details') }}</span>
                                <span class="ml-2 px-2 py-0.5 bg-cyan-500/20 text-cyan-300 text-xs rounded-full">{{ processingSummary.total }}</span>
                            </div>
                            <svg class="w-4 h-4 text-cyan-400 transition-transform" :class="{ 'rotate-180': processingResultsExpanded }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>
                        <div v-if="processingResultsExpanded" class="mt-3 space-y-2 max-h-80 overflow-y-auto pr-1">
                            <details v-for="(group, status) in processingSummary.groups" :key="status" class="group">
                                <summary class="cursor-pointer flex items-center gap-2 text-sm py-1.5 px-2 rounded-lg hover:bg-white/5"
                                    :class="{
                                        'text-green-300': ['assigned', 'fallback-assigned', 'processed'].includes(status),
                                        'text-red-300': status === 'failed',
                                        'text-orange-300': status === 'failed-validity',
                                        'text-purple-300': status === 'unsupported-version',
                                    }">
                                    <svg class="w-3 h-3 transition-transform group-open:rotate-90" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                    </svg>
                                    {{ group.label }}
                                    <span class="px-1.5 py-0.5 text-[10px] rounded-full"
                                        :class="{
                                            'bg-green-500/20 text-green-400': ['assigned', 'fallback-assigned', 'processed'].includes(status),
                                            'bg-red-500/20 text-red-400': status === 'failed',
                                            'bg-orange-500/20 text-orange-400': status === 'failed-validity',
                                            'bg-purple-500/20 text-purple-400': status === 'unsupported-version',
                                        }">{{ group.demos.length }}</span>
                                </summary>
                                <div class="ml-5 mt-1 space-y-1">
                                    <div v-for="demo in group.demos" :key="demo.id" class="flex items-start space-x-1.5">
                                        <svg v-if="['assigned', 'fallback-assigned', 'processed'].includes(demo.status)" class="w-3 h-3 text-green-400/60 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                        </svg>
                                        <svg v-else class="w-3 h-3 text-red-400/60 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                        </svg>
                                        <div class="min-w-0 flex-1">
                                            <span class="text-xs text-gray-300">{{ demo.processed_filename || demo.original_filename }}</span>
                                            <span v-if="demo.map_name" class="text-[10px] text-gray-500 ml-1">{{ demo.map_name }}</span>
                                            <span v-if="demo.processing_output" class="text-[10px] text-gray-500 ml-1">- {{ demo.processing_output }}</span>
                                        </div>
                                    </div>
                                </div>
                            </details>
                        </div>
                    </div>
                </div>

                <!-- Global Processing Status (logged in only) -->
                <div v-if="$page.props.auth.user && queueHasWork" class="bg-black/40 backdrop-blur-sm rounded-xl p-3 mb-4 shadow-2xl border border-white/5">
                    <button @click.stop="globalQueueExpanded = !globalQueueExpanded" class="w-full flex items-center gap-2 text-left" :class="{ 'mb-3': globalQueueExpanded }">
                        <span class="w-2 h-2 rounded-full bg-green-400 animate-pulse flex-shrink-0"></span>
                        <h3 class="text-sm font-semibold text-gray-200 flex-shrink-0">{{ $t('Global Queue Status') }}</h3>
                        <span v-if="!globalQueueExpanded" class="text-xs text-gray-400 truncate">
                            {{ $t('yours :yours, all :total', {
                                yours: (queueStats.user_queued || 0) + (queueStats.user_processing || 0),
                                total: (queueStats.total_queued || 0) + (queueStats.total_processing || 0)
                            }) }}
                        </span>
                        <span class="text-gray-500 text-xs ml-auto flex-shrink-0 hidden sm:inline">{{ $t('updates every 2s') }}</span>
                        <svg class="w-4 h-4 text-gray-400 transition-transform flex-shrink-0" :class="{ 'rotate-180': globalQueueExpanded, 'ml-auto sm:ml-0': true }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>

                    <!-- Queue Statistics -->
                    <div v-show="globalQueueExpanded" class="grid grid-cols-2 gap-2">
                        <div v-if="$page.props.auth.user" class="relative group bg-gradient-to-br from-yellow-600/20 to-yellow-700/10 rounded-lg p-3 text-center border border-yellow-600/30 cursor-help">
                            <div class="text-2xl font-bold text-yellow-400">{{ (queueStats.user_queued || 0) + (queueStats.user_processing || 0) }}</div>
                            <div class="text-xs text-yellow-300 mt-1">{{ $t('Your Remaining') }}</div>
                            <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-3 py-1.5 bg-gray-900 border border-gray-600 rounded-lg text-xs text-gray-200 whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-50">
                                {{ $t('Your demos not yet finished: :queued waiting in queue, :processing being processed right now', { queued: queueStats.user_queued || 0, processing: queueStats.user_processing || 0 }) }}
                            </div>
                        </div>
                        <div class="relative group bg-gradient-to-br from-green-600/20 to-green-700/10 rounded-lg p-3 text-center border border-green-600/30 cursor-help">
                            <div class="text-2xl font-bold text-green-400">{{ (queueStats.total_queued || 0) + (queueStats.total_processing || 0) }}</div>
                            <div class="text-xs text-green-300 mt-1">{{ $t('Total Remaining') }}</div>
                            <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-3 py-1.5 bg-gray-900 border border-gray-600 rounded-lg text-xs text-gray-200 whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-50">
                                {{ $t('Demos from all users not yet finished: :queued waiting in queue, :processing being processed right now', { queued: queueStats.total_queued || 0, processing: queueStats.total_processing || 0 }) }}
                            </div>
                        </div>
                    </div>

                    <!-- Actively Processing (only demos currently being worked on by workers) -->
                    <div v-if="activelyProcessingDemos.length > 0" v-show="globalQueueExpanded" class="mt-4 space-y-2">
                        <h4 class="text-sm font-semibold text-blue-300">{{ $t('Actively Processing (:count):', { count: activelyProcessingDemos.length }) }}</h4>
                        <div v-for="demo in activelyProcessingDemos" :key="demo.id" class="flex items-center justify-between bg-blue-900/20 rounded-lg p-2 border border-blue-600/30">
                            <div class="flex items-center space-x-2">
                                <svg class="w-5 h-5 text-blue-400 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                </svg>
                                <div>
                                    <div class="text-gray-200 font-medium text-sm truncate max-w-[700px]">{{ demo.original_filename }}</div>
                                </div>
                            </div>
                            <div class="flex items-center space-x-2">
                                <div class="flex space-x-1">
                                    <div class="w-1.5 h-1.5 bg-blue-400 rounded-full animate-pulse"></div>
                                    <div class="w-1.5 h-1.5 bg-blue-400 rounded-full animate-pulse" style="animation-delay: 0.2s"></div>
                                    <div class="w-1.5 h-1.5 bg-blue-400 rounded-full animate-pulse" style="animation-delay: 0.4s"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Queued count (just a summary, not the full list) -->
                    <div v-if="queuedDemoCount > 0" v-show="globalQueueExpanded" class="mt-3">
                        <div class="text-xs text-gray-400">{{ $tc(':count demo waiting in queue|:count demos waiting in queue', queuedDemoCount) }}</div>
                    </div>
                </div>

                <!-- Loading skeleton while demo lists load -->
                <div v-if="demosLoading" class="bg-black/40 backdrop-blur-sm rounded-xl p-6 border border-white/5 animate-pulse">
                    <div class="h-6 bg-white/10 rounded w-56 mb-4"></div>
                    <div class="flex gap-2 mb-4">
                        <div v-for="i in 5" :key="'tab'+i" class="h-8 bg-white/5 rounded-full w-24"></div>
                    </div>
                    <div class="space-y-3">
                        <div v-for="i in 8" :key="'row'+i" class="h-12 bg-white/5 rounded"></div>
                    </div>
                </div>

                <!-- One panel for both lists. It used to be written out twice,
                     once above each table, and the two copies had drifted. -->
                <!-- Tabs, filters and table are one panel. The tabs used to
                     float above it as a shape of their own, which read as a
                     control belonging to the page rather than to the list
                     underneath it. The active tab is the list's title now, so
                     the headings that repeated it are gone. -->
                <div v-if="!demosLoading" class="bg-black/40 backdrop-blur-sm rounded-xl shadow-2xl border border-white/5">
                    <div class="flex flex-wrap items-end gap-1 px-3 pt-2 border-b border-white/5">
                        <button
                            type="button"
                            @click="changeList('all')"
                            class="inline-flex items-center gap-2 h-9 px-3 text-sm font-semibold rounded-t-lg border-b-2 -mb-px transition-colors"
                            :class="activeList === 'all'
                                ? 'bg-white/[0.05] text-white border-blue-500'
                                : 'text-gray-400 border-transparent hover:text-gray-200 hover:bg-white/[0.03]'"
                        >
                            {{ $t('All Demos') }}
                            <span class="text-xs font-normal tabular-nums" :class="activeList === 'all' ? 'text-blue-300' : 'text-gray-500'">
                                {{ browseCountsComputed.all.toLocaleString() }}
                            </span>
                        </button>
                        <button
                            v-if="$page.props.auth.user"
                            type="button"
                            @click="changeList('mine')"
                            class="inline-flex items-center gap-2 h-9 px-3 text-sm font-semibold rounded-t-lg border-b-2 -mb-px transition-colors"
                            :class="activeList === 'mine'
                                ? 'bg-white/[0.05] text-white border-blue-500'
                                : 'text-gray-400 border-transparent hover:text-gray-200 hover:bg-white/[0.03]'"
                        >
                            {{ $t('My Uploads') }}
                            <span class="text-xs font-normal tabular-nums" :class="activeList === 'mine' ? 'text-blue-300' : 'text-gray-500'">
                                {{ demoCountsComputed.all.toLocaleString() }}
                            </span>
                        </button>
                    </div>

                    <DemoFilters
                        :filters="filterState"
                        :counts="activeList === 'mine' ? demoCountsComputed : browseCountsComputed"
                        :physics-options="physicsOptions || []"
                        :countries="countries || { codes: [], other: 0, none: 0 }"
                        :is-admin="isAdminUser"
                        :list="activeList"
                        @change="applyFilters"
                    />

                <!-- Your Uploads -->
                <div v-if="activeList === 'mine' && $page.props.auth.user && userDemos" class="p-4">
                    <!-- Show message when no demos uploaded at all -->
                    <div v-if="demoCountsComputed.all === 0" class="text-center py-12">
                        <svg class="w-16 h-16 mx-auto text-gray-500 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        <p class="text-gray-400 text-lg">{{ $t("You haven't uploaded any demos yet.") }}</p>
                    </div>

                    <template v-else>
                        <div v-if="filteredDemos.length === 0" class="text-gray-400 text-center py-8">
                            {{ $t('No demos match the selected filters.') }}
                        </div>

                    <div v-else class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-600">
                            <thead class="bg-gray-700/50">
                                <tr>
                                    <th class="px-2 py-2 text-left text-xs font-semibold text-gray-300 uppercase tracking-wider">
                                        <button @click="sortColumn('id')" class="flex items-center gap-1 hover:text-blue-400 transition-colors">
                                            <span>{{ $t('ID') }}</span>
                                            <svg v-if="sortBy === 'id'" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path v-if="sortOrder === 'asc'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"></path>
                                                <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                            </svg>
                                        </button>
                                    </th>
                                    <th class="px-2 py-2 text-left text-xs font-semibold text-gray-300 uppercase tracking-wider">
                                        <button @click="sortColumn('original_filename')" class="flex items-center gap-1 hover:text-blue-400 transition-colors">
                                            <span>{{ $t('Filename') }}</span>
                                            <svg v-if="sortBy === 'original_filename'" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path v-if="sortOrder === 'asc'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"></path>
                                                <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                            </svg>
                                        </button>
                                    </th>
                                    <th class="px-2 py-2 text-left text-xs font-semibold text-gray-300 uppercase tracking-wider">
                                        <button @click="sortColumn('created_at')" class="flex items-center gap-1 hover:text-blue-400 transition-colors">
                                            <span>{{ $t('Uploaded') }}</span>
                                            <svg v-if="sortBy === 'created_at'" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path v-if="sortOrder === 'asc'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"></path>
                                                <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                            </svg>
                                        </button>
                                    </th>
                                    <th class="px-2 py-2 text-left text-xs font-semibold text-gray-300 uppercase tracking-wider">
                                        <button @click="sortColumn('map_name')" class="flex items-center gap-1 hover:text-blue-400 transition-colors">
                                            <span>{{ $t('Map') }}</span>
                                            <svg v-if="sortBy === 'map_name'" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path v-if="sortOrder === 'asc'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"></path>
                                                <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                            </svg>
                                        </button>
                                    </th>
                                    <th class="px-1 py-2 text-left text-xs font-semibold text-gray-300 uppercase tracking-wider">
                                        {{ $t('Type') }}
                                    </th>
                                    <th class="px-1 py-2 text-left text-xs font-semibold text-gray-300 uppercase tracking-wider">
                                        {{ $t('Physics') }}
                                    </th>
                                    <th class="px-2 py-2 text-left text-xs font-semibold text-gray-300 uppercase tracking-wider">
                                        <button @click="sortColumn('time_ms')" class="flex items-center gap-1 hover:text-blue-400 transition-colors">
                                            <span>{{ $t('Time') }}</span>
                                            <svg v-if="sortBy === 'time_ms'" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path v-if="sortOrder === 'asc'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"></path>
                                                <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                            </svg>
                                        </button>
                                    </th>
                                    <th class="px-2 py-2 text-left text-xs font-semibold text-gray-300 uppercase tracking-wider">
                                        <button @click="sortColumn('status')" class="flex items-center gap-1 hover:text-blue-400 transition-colors">
                                            <span>{{ $t('Status') }}</span>
                                            <svg v-if="sortBy === 'status'" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path v-if="sortOrder === 'asc'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"></path>
                                                <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                            </svg>
                                        </button>
                                    </th>
                                    <th class="px-2 py-2 text-left text-xs font-semibold text-gray-300 uppercase tracking-wider">
                                        {{ $t('Actions') }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-700/50">
                                <template v-for="demo in filteredDemos" :key="demo.id">
                                <tr class="hover:bg-gray-700/30 transition-colors duration-200">
                                    <td class="px-2 py-1.5 text-xs text-gray-500 font-mono">
                                        {{ demo.id }}
                                    </td>
                                    <td class="px-2 py-1.5 text-xs">
                                        <div class="flex items-center gap-2">
                                            <div>
                                                <span class="text-gray-200 font-medium">{{ demo.processed_filename || demo.original_filename }}</span>

                                                <!-- Confidence Badge -->
                                                <div v-if="demo.name_confidence !== null" class="mt-1 flex items-center gap-2">
                                                    <span
                                                        :class="[
                                                            'text-xs px-2 py-0.5 rounded',
                                                            demo.name_confidence === 100 ? 'bg-green-500/20 text-green-400' :
                                                            demo.name_confidence >= 90 ? 'bg-blue-500/20 text-blue-400' :
                                                            demo.name_confidence >= 70 ? 'bg-yellow-500/20 text-yellow-400' :
                                                            'bg-red-500/20 text-red-400'
                                                        ]"
                                                    >
                                                        {{ $t(':percent% confidence', { percent: demo.name_confidence }) }}
                                                    </span>

                                                    <span v-if="demo.suggested_user" class="text-xs text-gray-400">
                                                        → <span v-html="q3tohtml(demo.suggested_user.name)"></span>
                                                    </span>

                                                    <span v-if="demo.manually_assigned" class="text-xs bg-purple-500/20 text-purple-400 px-2 py-0.5 rounded">
                                                        {{ $t('Manually Assigned') }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-2 py-1.5 text-xs text-gray-400">
                                        <span class="text-gray-300">{{ new Date(demo.created_at).toLocaleDateString() }}</span>
                                    </td>
                                    <td class="px-2 py-1.5 text-xs text-gray-300">
                                        <Link v-if="demo.map_name" :href="`/maps/${encodeURIComponent(demo.map_name)}`" class="text-blue-400 hover:text-blue-300 underline transition-colors duration-200 truncate block max-w-[120px]" :title="demo.map_name">
                                            {{ demo.map_name }}
                                        </Link>
                                        <span v-else class="text-gray-500">-</span>
                                    </td>
                                    <td class="px-1 py-1.5 text-xs text-gray-300">
                                        <span v-if="demo.gametype" class="inline-flex items-center px-1 py-0.5 rounded text-[10px] font-medium uppercase" :class="demo.gametype.startsWith('m') ? 'bg-green-900/50 text-green-200' : 'bg-purple-900/50 text-purple-200'" :title="demo.gametype.startsWith('m') ? $t('Online') : $t('Offline')">
                                            {{ demo.gametype }}
                                        </span>
                                        <span v-else class="text-gray-500">-</span>
                                    </td>
                                    <td class="px-1 py-1.5 text-xs text-gray-300">
                                        <DemoPhysicsBadges :physics="demo.physics" />
                                    </td>
                                    <td class="px-2 py-1.5 text-xs text-gray-300 font-mono">
                                        {{ formatTime(demo.time_ms) }}
                                    </td>
                                    <td class="px-2 py-1.5 text-xs">
                                        <div class="flex flex-col space-y-2">
                                            <div class="flex items-center space-x-2 flex-wrap gap-1">
                                                <span
                                                    class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium relative"
                                                    :class="{
                                                        'bg-yellow-900/50 text-yellow-200': demo.status === 'uploaded',
                                                        'bg-blue-900/50 text-blue-200': demo.status === 'processing',
                                                        'bg-green-900/50 text-green-200': demo.status === 'processed',
                                                        'bg-purple-500/20 text-purple-300 hover:bg-purple-500/30 cursor-help': demo.status === 'assigned' || demo.status === 'unsupported-version',
                                                        'bg-orange-500/20 text-orange-300 hover:bg-orange-500/30 cursor-help': demo.status === 'fallback-assigned' || demo.status === 'failed-validity',
                                                        'bg-red-500/20 text-red-300 hover:bg-red-500/30 cursor-help': demo.status === 'failed',
                                                        'bg-gray-500/20 text-gray-300': !['uploaded', 'processing', 'processed', 'assigned', 'fallback-assigned', 'failed-validity', 'failed', 'unsupported-version'].includes(demo.status)
                                                    }"
                                                    @mouseenter="demo.status === 'failed' || (demo.status === 'failed-validity' && demo.validity) || (demo.status === 'unsupported-version' && demo.processing_output) || (demo.status === 'assigned' && (demo.record || demo.offline_record)) || (demo.status === 'fallback-assigned' && demo.offline_record) ? showTooltip(demo, $event) : null"
                                                    @mouseleave="hideTooltip"
                                                    @mousemove="hoveredDemo?.id === demo.id ? updateTooltipPosition($event) : null"
                                                >
                                                    {{ demo.status }}
                                                    <svg v-if="demo.status === 'unsupported-version' && demo.processing_output" class="w-3 h-3 ml-1 text-purple-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                    </svg>
                                                    <svg v-if="demo.status === 'failed'" class="w-3 h-3 ml-1 text-red-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                    </svg>
                                                    <svg v-if="demo.status === 'assigned' && (demo.record || demo.offline_record)" class="w-3 h-3 ml-1 text-purple-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                    </svg>
                                                    <svg v-if="demo.status === 'fallback-assigned' && demo.offline_record" class="w-3 h-3 ml-1 text-orange-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                    </svg>
                                                </span>
                                                <!-- A demo comps is holding is missing from the public
                                                     site on purpose. Only this list and the admin's own
                                                     show it, so this chip is the only place the reason
                                                     is ever said. -->
                                                <span
                                                    v-if="demo.comps_hold"
                                                    class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold cursor-help"
                                                    :class="demo.comps_hold === 'withdrawn'
                                                        ? 'bg-gray-500/20 text-gray-300'
                                                        : 'bg-amber-500/20 text-amber-300'"
                                                    :title="demo.comps_hold === 'withdrawn'
                                                        ? $t('Taken out of comps by its uploader. Hidden from the public until the round ends.')
                                                        : $t('A comps entry. Hidden from the public until the round ends.')"
                                                >
                                                    {{ demo.comps_hold === 'withdrawn' ? $t('withdrawn') : $t('comps hold') }}
                                                </span>
                                                <span
                                                    v-if="demo.match_method"
                                                    class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold"
                                                    :class="{
                                                        'bg-emerald-500/20 text-emerald-300 ring-1 ring-emerald-400/30': demo.match_method === 'q3df_colored_record',
                                                        'bg-emerald-500/10 text-emerald-300': demo.match_method === 'q3df_colored_profile',
                                                        'bg-cyan-500/20 text-cyan-300 ring-1 ring-cyan-400/30': demo.match_method === 'q3df_plain_record',
                                                        'bg-cyan-500/10 text-cyan-300': demo.match_method === 'q3df_plain_profile',
                                                        'bg-gray-500/20 text-gray-300': demo.match_method === 'uploader_record',
                                                        'bg-yellow-500/20 text-yellow-300': demo.match_method === 'fuzzy_nick',
                                                    }"
                                                    :title="matchMethodTooltip(demo)"
                                                >
                                                    {{ matchMethodLabel(demo.match_method) }}
                                                </span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-2 py-1.5 text-xs">
                                        <div class="flex flex-wrap gap-1">
                                            <button
                                                @click.stop="toggleDetails(demo.id)"
                                                class="inline-flex items-center px-2 py-1 text-[11px] font-medium rounded transition-colors"
                                                :class="expandedDemos.has(demo.id) ? 'bg-amber-600/30 text-amber-200' : 'bg-white/[0.06] text-gray-300 hover:bg-white/10'"
                                                :title="expandedDemos.has(demo.id) ? $t('Hide details') : $t('What is in this demo')"
                                            >
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                </svg>
                                            </button>
                                            <button
                                                @click.stop="downloadDemo(demo.id)"
                                                class="inline-flex items-center px-2 py-1 bg-blue-600/20 text-blue-300 text-[11px] font-medium rounded hover:bg-blue-600/30 transition-colors"
                                                :title="$t('Download')"
                                            >
                                                <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                                </svg>
                                                {{ $t('DL') }}
                                            </button>
                                            <button
                                                v-if="$page.props.auth.user && (demo.user_id === $page.props.auth.user.id || $page.props.auth.user.is_admin || $page.props.auth.user.admin)"
                                                @click="reprocessDemo(demo.id)"
                                                class="inline-flex items-center px-2 py-1 bg-yellow-600/20 text-yellow-300 text-[11px] font-medium rounded hover:bg-yellow-600/30 transition-colors"
                                                :title="$t('Reprocess')"
                                            >
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                                </svg>
                                            </button>
                                            <button
                                                v-if="$page.props.auth.user && (demo.user_id === $page.props.auth.user.id || $page.props.auth.user.is_admin || $page.props.auth.user.admin) && (demo.status === 'processed' || demo.status === 'failed' || demo.status === 'fallback-assigned') && !demo.record_id"
                                                @click="openAssignModal(demo)"
                                                class="inline-flex items-center px-2 py-1 bg-green-600/20 text-green-300 text-[11px] font-medium rounded hover:bg-green-600/30 transition-colors"
                                                :title="$t('Assign')"
                                            >
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path>
                                                </svg>
                                            </button>
                                            <button
                                                v-if="$page.props.auth.user && (demo.user_id === $page.props.auth.user.id || $page.props.auth.user.is_admin || $page.props.auth.user.admin) && demo.record_id"
                                                @click="unassignDemo(demo)"
                                                class="inline-flex items-center px-2 py-1 bg-orange-600/20 text-orange-300 text-[11px] font-medium rounded hover:bg-orange-600/30 transition-colors"
                                                :title="$t('Unassign')"
                                            >
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path>
                                                </svg>
                                            </button>
                                            <button
                                                v-if="$page.props.auth.user && (demo.user_id === $page.props.auth.user.id || $page.props.auth.user.is_admin || $page.props.auth.user.admin) && !demo.record_id"
                                                @click="deleteDemo(demo.id)"
                                                class="inline-flex items-center px-2 py-1 bg-red-600/20 text-red-300 text-[11px] font-medium rounded hover:bg-red-600/30 transition-colors"
                                                :title="$t('Delete')"
                                            >
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                </svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="expandedDemos.has(demo.id)" class="bg-gray-900/40">
                                    <!-- Nine columns here; the browse table below has eight. -->
                                    <td colspan="9" class="px-2 pb-3 pt-0">
                                        <DemoDetails :demo="demo" />
                                    </td>
                                </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                        <!-- Pagination. There is no page count, on purpose.
                             Counting the matches of a broad filter reads all
                             369 000 rows and costs about half a second, while
                             the twenty rows above cost two. The total is shown
                             only when nothing is filtered, where it is a number
                             that was already cached. -->
                        <div v-if="userDemos.prev_page_url || userDemos.next_page_url" class="mt-6 flex flex-wrap items-center justify-center gap-3">
                            <button
                                type="button"
                                :disabled="!userDemos.prev_page_url"
                                @click="goToPage('userPage', userDemos.current_page - 1)"
                                class="px-3 py-1.5 rounded-lg text-sm bg-gray-700/50 border border-gray-600/50 text-gray-200 hover:bg-gray-700 disabled:opacity-30 disabled:cursor-not-allowed transition-colors"
                            >
                                {{ $t('Previous') }}
                            </button>
                            <span class="text-sm text-gray-400">
                                {{ $t('Page :page', { page: userDemos.current_page }) }}
                                <span v-if="!filtersNarrowed" class="text-gray-500">
                                    {{ $t('of :total demos', { total: (demoCountsComputed.all || 0).toLocaleString() }) }}
                                </span>
                            </span>
                            <button
                                type="button"
                                :disabled="!userDemos.next_page_url"
                                @click="goToPage('userPage', userDemos.current_page + 1)"
                                class="px-3 py-1.5 rounded-lg text-sm bg-gray-700/50 border border-gray-600/50 text-gray-200 hover:bg-gray-700 disabled:opacity-30 disabled:cursor-not-allowed transition-colors"
                            >
                                {{ $t('Next') }}
                            </button>
                        </div>
                    </template>
                </div>

                <!-- All Demos -->
                <div v-if="activeList === 'all' && publicDemos" class="p-4">

                    <div v-if="!publicDemos.data || publicDemos.data.length === 0" class="text-gray-400">
                        {{ $t('No demos available yet.') }}
                    </div>

                    <div v-else class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-600">
                            <thead class="bg-gray-700/50">
                                <tr>
                                    <th class="px-2 py-2 text-left text-xs font-semibold text-gray-300 uppercase tracking-wider">
                                        <button @click="sortBrowseColumn('original_filename')" class="flex items-center gap-1 hover:text-blue-400 transition-colors">
                                            <span>{{ $t('Filename') }}</span>
                                            <svg v-if="browseSortBy === 'original_filename'" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path v-if="browseSortOrder === 'asc'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"></path>
                                                <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                            </svg>
                                        </button>
                                    </th>
                                    <th class="px-2 py-2 text-left text-xs font-semibold text-gray-300 uppercase tracking-wider">
                                        {{ $t('Uploaded By') }}
                                    </th>
                                    <th class="px-2 py-2 text-left text-xs font-semibold text-gray-300 uppercase tracking-wider">
                                        <button @click="sortBrowseColumn('map_name')" class="flex items-center gap-1 hover:text-blue-400 transition-colors">
                                            <span>{{ $t('Map') }}</span>
                                            <svg v-if="browseSortBy === 'map_name'" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path v-if="browseSortOrder === 'asc'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"></path>
                                                <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                            </svg>
                                        </button>
                                    </th>
                                    <th class="px-1 py-2 text-left text-xs font-semibold text-gray-300 uppercase tracking-wider">
                                        <button @click="sortBrowseColumn('gametype')" class="flex items-center gap-1 hover:text-blue-400 transition-colors">
                                            <span>{{ $t('Type') }}</span>
                                            <svg v-if="browseSortBy === 'gametype'" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path v-if="browseSortOrder === 'asc'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"></path>
                                                <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                            </svg>
                                        </button>
                                    </th>
                                    <th class="px-1 py-2 text-left text-xs font-semibold text-gray-300 uppercase tracking-wider">
                                        <button @click="sortBrowseColumn('physics')" class="flex items-center gap-1 hover:text-blue-400 transition-colors">
                                            <span>{{ $t('Physics') }}</span>
                                            <svg v-if="browseSortBy === 'physics'" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path v-if="browseSortOrder === 'asc'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"></path>
                                                <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                            </svg>
                                        </button>
                                    </th>
                                    <th class="px-2 py-2 text-left text-xs font-semibold text-gray-300 uppercase tracking-wider">
                                        <button @click="sortBrowseColumn('time_ms')" class="flex items-center gap-1 hover:text-blue-400 transition-colors">
                                            <span>{{ $t('Time') }}</span>
                                            <svg v-if="browseSortBy === 'time_ms'" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path v-if="browseSortOrder === 'asc'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"></path>
                                                <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                            </svg>
                                        </button>
                                    </th>
                                    <th class="px-2 py-2 text-left text-xs font-semibold text-gray-300 uppercase tracking-wider">
                                        <button @click="sortBrowseColumn('status')" class="flex items-center gap-1 hover:text-blue-400 transition-colors">
                                            <span>{{ $t('Status') }}</span>
                                            <svg v-if="browseSortBy === 'status'" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path v-if="browseSortOrder === 'asc'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"></path>
                                                <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                            </svg>
                                        </button>
                                    </th>
                                    <th class="px-2 py-2 text-left text-xs font-semibold text-gray-300 uppercase tracking-wider">
                                        {{ $t('Actions') }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-700/50">
                                <template v-for="demo in publicDemos.data" :key="demo.id">
                                <tr class="hover:bg-gray-700/30 transition-colors duration-200">
                                    <td class="px-2 py-1.5 text-xs">
                                        <span class="text-gray-200 font-medium">{{ demo.processed_filename || demo.original_filename }}</span>
                                    </td>
                                    <td class="px-2 py-1.5 text-xs text-gray-300">
                                        <Link v-if="demo.user && demo.user.mdd_id" :href="`/profile/mdd/${demo.user.mdd_id}`" class="text-blue-400 hover:text-blue-300 transition-colors duration-200" v-html="q3tohtml(demo.user.name)"></Link>
                                        <span v-else-if="demo.user" v-html="q3tohtml(demo.user.name)"></span>
                                        <span v-else-if="demo.source === 'demome'" class="text-cyan-400">{{ $t('Demome') }}</span>
                                        <span v-else class="text-gray-500">{{ $t('Guest') }}</span>
                                    </td>
                                    <td class="px-2 py-1.5 text-xs text-gray-300">
                                        <Link v-if="demo.map_name" :href="`/maps/${encodeURIComponent(demo.map_name)}`" class="text-blue-400 hover:text-blue-300 underline transition-colors duration-200 truncate block max-w-[120px]" :title="demo.map_name">
                                            {{ demo.map_name }}
                                        </Link>
                                        <span v-else class="text-gray-500">-</span>
                                    </td>
                                    <td class="px-2 py-4 text-xs text-gray-300">
                                        <span v-if="demo.gametype" class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium uppercase" :class="demo.gametype.startsWith('m') ? 'bg-green-900/50 text-green-200' : 'bg-purple-900/50 text-purple-200'" :title="demo.gametype.startsWith('m') ? $t('Online') : $t('Offline')">
                                            {{ demo.gametype }}
                                        </span>
                                        <span v-else class="text-gray-500">-</span>
                                    </td>
                                    <td class="px-1 py-1.5 text-xs text-gray-300">
                                        <DemoPhysicsBadges :physics="demo.physics" />
                                    </td>
                                    <td class="px-2 py-1.5 text-xs text-gray-300 font-mono">
                                        {{ formatTime(demo.time_ms) }}
                                    </td>
                                    <td class="px-2 py-1.5 text-xs">
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded-md text-[11px] font-medium"
                                            :class="{
                                                'bg-yellow-900/50 text-yellow-200': demo.status === 'uploaded',
                                                'bg-blue-900/50 text-blue-200': demo.status === 'processing',
                                                'bg-green-900/50 text-green-200': demo.status === 'processed',
                                                'bg-purple-500/20 text-purple-300 hover:bg-purple-500/30 cursor-help': demo.status === 'assigned' || demo.status === 'unsupported-version',
                                                'bg-orange-500/20 text-orange-300 hover:bg-orange-500/30 cursor-help': demo.status === 'fallback-assigned' || demo.status === 'failed-validity',
                                                'bg-red-500/20 text-red-300 hover:bg-red-500/30 cursor-help': demo.status === 'failed',
                                                'bg-gray-500/20 text-gray-300': !['uploaded', 'processing', 'processed', 'assigned', 'fallback-assigned', 'failed-validity', 'failed', 'unsupported-version'].includes(demo.status)
                                            }"
                                            @mouseenter="demo.status === 'failed' || (demo.status === 'failed-validity' && demo.validity) || (demo.status === 'unsupported-version' && demo.processing_output) || (demo.status === 'assigned' && (demo.record || demo.offline_record)) || (demo.status === 'fallback-assigned' && demo.offline_record) ? showTooltip(demo, $event) : null"
                                            @mouseleave="hideTooltip"
                                            @mousemove="hoveredDemo?.id === demo.id ? updateTooltipPosition($event) : null"
                                        >
                                            {{ demo.status ? demo.status.charAt(0).toUpperCase() + demo.status.slice(1) : '-' }}
                                            <svg v-if="demo.status === 'unsupported-version' && demo.processing_output" class="w-3 h-3 ml-1 text-purple-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                            </svg>
                                            <svg v-if="demo.status === 'failed'" class="w-3 h-3 ml-1 text-red-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                            </svg>
                                            <svg v-if="demo.status === 'assigned' && (demo.record || demo.offline_record)" class="w-3 h-3 ml-1 text-purple-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                            </svg>
                                            <svg v-if="demo.status === 'fallback-assigned' && demo.offline_record" class="w-3 h-3 ml-1 text-orange-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                            </svg>
                                            <svg v-if="demo.status === 'failed-validity' && demo.validity" class="w-3 h-3 ml-1 text-orange-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                            </svg>
                                        </span>
                                    </td>
                                    <td class="px-2 py-1.5 text-xs">
                                        <div class="flex items-center space-x-1">
                                            <button
                                                @click.stop="toggleDetails(demo.id)"
                                                class="inline-flex items-center px-2 py-1 text-[11px] font-medium rounded transition-colors"
                                                :class="expandedDemos.has(demo.id) ? 'bg-amber-600/30 text-amber-200' : 'bg-white/[0.06] text-gray-300 hover:bg-white/10'"
                                                :title="expandedDemos.has(demo.id) ? $t('Hide details') : $t('What is in this demo')"
                                            >
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                </svg>
                                            </button>
                                            <button
                                                @click.stop="downloadDemo(demo.id)"
                                                class="inline-flex items-center px-2 py-1 bg-blue-600/20 text-blue-300 text-[11px] font-medium rounded hover:bg-blue-600/30 transition-colors"
                                                :title="$t('Download')"
                                            >
                                                <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                                </svg>
                                                {{ $t('DL') }}
                                            </button>
                                            <button
                                                v-if="$page.props.auth.user && !demo.record_id && ['processed', 'fallback-assigned', 'failed'].includes(demo.status)"
                                                @click="openAssignModal(demo)"
                                                class="inline-flex items-center px-2 py-1 bg-green-600/20 text-green-300 text-[11px] font-medium rounded hover:bg-green-600/30 transition-colors"
                                                :title="$t('Assign to online record')"
                                            >
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path>
                                                </svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="expandedDemos.has(demo.id)" class="bg-gray-900/40">
                                    <td colspan="8" class="px-2 pb-3 pt-0">
                                        <DemoDetails :demo="demo" />
                                    </td>
                                </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination. There is no page count, on purpose.
                         Counting the matches of a broad filter reads all
                         369 000 rows and costs about half a second, while
                         the twenty rows above cost two. The total is shown
                         only when nothing is filtered, where it is a number
                         that was already cached. -->
                    <div v-if="publicDemos.prev_page_url || publicDemos.next_page_url" class="mt-6 flex flex-wrap items-center justify-center gap-3">
                        <button
                            type="button"
                            :disabled="!publicDemos.prev_page_url"
                            @click="goToPage('browsePage', publicDemos.current_page - 1)"
                            class="px-3 py-1.5 rounded-lg text-sm bg-gray-700/50 border border-gray-600/50 text-gray-200 hover:bg-gray-700 disabled:opacity-30 disabled:cursor-not-allowed transition-colors"
                        >
                            {{ $t('Previous') }}
                        </button>
                        <span class="text-sm text-gray-400">
                            {{ $t('Page :page', { page: publicDemos.current_page }) }}
                            <span v-if="!filtersNarrowed" class="text-gray-500">
                                {{ $t('of :total demos', { total: (browseCountsComputed.all || 0).toLocaleString() }) }}
                            </span>
                        </span>
                        <button
                            type="button"
                            :disabled="!publicDemos.next_page_url"
                            @click="goToPage('browsePage', publicDemos.current_page + 1)"
                            class="px-3 py-1.5 rounded-lg text-sm bg-gray-700/50 border border-gray-600/50 text-gray-200 hover:bg-gray-700 disabled:opacity-30 disabled:cursor-not-allowed transition-colors"
                        >
                            {{ $t('Next') }}
                        </button>
                    </div>
                </div>
                </div>
            </div>
        </div>

        <!-- Manual Assignment Modal -->
        <div v-if="showAssignModal" class="fixed inset-0 bg-black/60 flex items-center justify-center z-50" @click="closeAssignModal">
            <div class="bg-gray-900/95 rounded-xl p-8 w-full max-w-3xl max-h-[85vh] overflow-y-auto border border-white/10 shadow-2xl" @click.stop>
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-xl font-bold text-gray-100">{{ $t('Assign Demo to Online Record') }}</h3>
                    <button @click="closeAssignModal" class="text-gray-400 hover:text-gray-200 transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <!-- Demo info -->
                <div v-if="assigningDemo" class="mb-6 p-4 bg-gray-800/60 rounded-lg border border-white/5">
                    <div class="grid grid-cols-2 gap-3">
                        <div class="text-sm"><span class="text-gray-500">{{ $t('Demo:') }}</span> <span class="text-gray-200 font-medium truncate block">{{ assigningDemo.processed_filename || assigningDemo.original_filename }}</span></div>
                        <div v-if="assigningDemo.physics" class="text-sm"><span class="text-gray-500">{{ $t('Physics:') }}</span> <span class="font-medium" :class="assigningDemo.physics === 'CPM' ? 'text-purple-400' : 'text-blue-400'">{{ assigningDemo.physics }}</span></div>
                        <div v-if="assigningDemo.map_name" class="text-sm"><span class="text-gray-500">{{ $t('Map:') }}</span> <span class="text-gray-200 font-medium">{{ assigningDemo.map_name }}</span></div>
                        <div v-if="assigningDemo.time_ms" class="text-sm"><span class="text-gray-500">{{ $t('Time:') }}</span> <span class="text-gray-200 font-mono font-medium">{{ formatTime(assigningDemo.time_ms) }}</span></div>
                    </div>
                </div>

                <!-- Physics Selection -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-400 mb-2">{{ $t('Physics') }}</label>
                    <div class="relative">
                        <button
                            @click="physicsDropdownOpen = !physicsDropdownOpen"
                            class="w-full bg-gray-800 border border-gray-700 rounded-lg px-3 py-2.5 text-white text-left flex items-center justify-between hover:border-gray-600 transition-colors"
                        >
                            <span>{{ selectedPhysics }}</span>
                            <svg class="w-4 h-4 text-gray-400 transition-transform" :class="physicsDropdownOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                            </svg>
                        </button>
                        <div v-if="physicsDropdownOpen" class="absolute top-full left-0 right-0 mt-1 bg-gray-900 border border-white/10 rounded-lg overflow-hidden z-50 shadow-2xl">
                            <button
                                v-for="p in ['VQ3', 'CPM']"
                                :key="p"
                                @click="selectedPhysics = p; physicsDropdownOpen = false; selectedMap && loadRecords()"
                                :class="selectedPhysics === p ? 'bg-blue-600/30 text-blue-300' : 'text-gray-300 hover:bg-white/10'"
                                class="w-full px-3 py-2 text-left text-sm transition-colors"
                            >
                                {{ p }}
                            </button>
                        </div>
                        <div v-if="physicsDropdownOpen" @click="physicsDropdownOpen = false" class="fixed inset-0 z-40"></div>
                    </div>
                </div>

                <!-- Map Search -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-400 mb-2">{{ $t('Search Map') }}</label>
                    <input
                        v-model="searchQuery"
                        @input="searchMaps"
                        type="text"
                        :placeholder="$t('Type map name...')"
                        class="w-full px-3 py-2.5 bg-gray-800 border border-gray-700 rounded-lg text-white placeholder-gray-500"
                    />
                </div>

                <!-- Available Maps -->
                <div v-if="availableMaps.length > 0 && !selectedMap" class="mb-4">
                    <label class="block text-sm font-medium text-gray-400 mb-2">{{ $t('Available Maps') }}</label>
                    <div class="max-h-32 overflow-y-auto border border-gray-700/50 rounded-lg">
                        <button
                            v-for="map in availableMaps"
                            :key="map"
                            @click="selectMap(map)"
                            :class="[
                                'w-full text-left px-4 py-2.5 hover:bg-white/5 border-b border-gray-800/50 last:border-b-0 transition-colors',
                                selectedMap === map ? 'bg-blue-600/30 text-white' : 'text-gray-300'
                            ]"
                        >
                            {{ map }}
                        </button>
                    </div>
                </div>

                <!-- Selected map indicator -->
                <div v-if="selectedMap" class="mb-4 flex items-center gap-2">
                    <span class="text-sm text-gray-400">{{ $t('Map:') }}</span>
                    <span class="text-sm font-medium text-gray-200 bg-gray-800 px-3 py-1 rounded-lg">{{ selectedMap }}</span>
                    <button @click="selectedMap = ''; availableRecords = []; selectedRecord = ''" class="text-xs text-gray-500 hover:text-gray-300">{{ $t('(change)') }}</button>
                </div>

                <!-- Loading indicator for maps -->
                <div v-if="loadingMaps" class="mb-4 text-center">
                    <div class="text-gray-400">{{ $t('Loading maps...') }}</div>
                </div>

                <!-- Suggested matches -->
                <div v-if="selectedMap && !loadingRecords && suggestedRecords.length > 0" class="mb-5">
                    <label class="block text-sm font-medium text-green-400 mb-2">
                        {{ $t('Closest time matches') }}
                    </label>
                    <div class="border border-green-700/30 rounded-lg bg-green-900/10 overflow-hidden">
                        <button
                            v-for="record in suggestedRecords"
                            :key="'suggested-' + record.id"
                            @click="selectedRecord = record.id"
                            :class="[
                                'w-full text-left px-4 py-3 hover:bg-green-800/20 border-b border-green-800/20 last:border-b-0 transition-all',
                                selectedRecord === record.id ? 'bg-green-600/20 ring-1 ring-green-500/50' : ''
                            ]"
                        >
                            <div class="flex justify-between items-center">
                                <div class="flex items-center gap-3">
                                    <span class="text-gray-500 font-bold text-sm w-8 text-right">#{{ record.rank }}</span>
                                    <span class="text-base" v-html="q3tohtml(record.player_name)"></span>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="text-xs" :class="record.timeDiff === 0 ? 'text-green-400 font-bold' : 'text-gray-500'">
                                        {{ record.timeDiff === 0 ? $t('EXACT') : $t(':diff diff', { diff: record.timeDiff < 1000 ? record.timeDiff + 'ms' : formatTime(record.timeDiff) }) }}
                                    </span>
                                    <span class="text-sm font-mono" :class="selectedRecord === record.id ? 'text-green-300' : 'text-gray-400'">{{ record.formatted_time }}</span>
                                </div>
                            </div>
                        </button>
                    </div>
                </div>

                <!-- All Records -->
                <div v-if="selectedMap && !loadingRecords && availableRecords.length > 0" class="mb-6">
                    <label class="block text-sm font-medium text-gray-400 mb-3">
                        {{ $t('All records (:count)', { count: availableRecords.length }) }}
                    </label>
                    <div class="max-h-[400px] overflow-y-auto border border-gray-700/50 rounded-lg">
                        <button
                            v-for="record in availableRecords"
                            :key="record.id"
                            @click="selectedRecord = record.id"
                            :class="[
                                'w-full text-left px-4 py-3 hover:bg-white/5 border-b border-gray-800/50 last:border-b-0 transition-all',
                                selectedRecord === record.id ? 'bg-green-600/20 ring-1 ring-green-500/50' : ''
                            ]"
                        >
                            <div class="flex justify-between items-center">
                                <div class="flex items-center gap-3">
                                    <span class="text-gray-500 font-bold text-sm w-8 text-right">#{{ record.rank }}</span>
                                    <span class="text-base" v-html="q3tohtml(record.player_name)"></span>
                                </div>
                                <span class="text-sm font-mono" :class="selectedRecord === record.id ? 'text-green-300' : 'text-gray-400'">{{ record.formatted_time }}</span>
                            </div>
                        </button>
                    </div>
                </div>

                <!-- Loading indicator for records -->
                <div v-if="loadingRecords" class="mb-4 text-center py-4">
                    <div class="text-gray-400">{{ $t('Loading records...') }}</div>
                </div>

                <!-- No records found -->
                <div v-if="selectedMap && !loadingRecords && availableRecords.length === 0" class="mb-6 text-center text-gray-400 py-8">
                    {{ $t('No records found for :map (:physics)', { map: selectedMap, physics: selectedPhysics }) }}
                </div>

                <!-- Action Buttons -->
                <div class="flex justify-end space-x-3 pt-2">
                    <button @click="closeAssignModal" class="px-5 py-2.5 bg-gray-700 text-white rounded-lg hover:bg-gray-600 transition-colors">
                        {{ $t('Cancel') }}
                    </button>
                    <button
                        @click="assignDemo"
                        :disabled="!selectedRecord"
                        :class="[
                            'px-5 py-2.5 rounded-lg text-white font-medium transition-colors',
                            selectedRecord ? 'bg-green-600 hover:bg-green-500' : 'bg-gray-600 cursor-not-allowed'
                        ]"
                    >
                        {{ $t('Assign Demo') }}
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Custom Tooltip for Failed and Assigned Demos -->
    <Teleport to="body">
        <!-- Failed Demo Tooltip -->
        <div
            v-if="hoveredDemo && hoveredDemo.status === 'unsupported-version' && hoveredDemo.processing_output"
            class="fixed z-50 pointer-events-none"
            :style="{
                left: tooltipPosition.x + 15 + 'px',
                top: tooltipPosition.y + 15 + 'px',
                maxWidth: '500px'
            }"
        >
            <div class="bg-gray-900 border border-purple-600/50 rounded-lg shadow-2xl p-3 text-xs">
                <div class="font-semibold text-purple-300 mb-2 flex items-center">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    {{ $t('Unparseable Demo') }}
                </div>
                <div class="text-[11px] text-purple-200/80 leading-relaxed">
                    {{ $t('This demo file could not be parsed. It may be corrupted or recorded with an incompatible engine version.') }}
                </div>
            </div>
        </div>

        <!-- Failed Demo Tooltip -->
        <div
            v-if="hoveredDemo && hoveredDemo.status === 'failed'"
            class="fixed z-50 pointer-events-none"
            :style="{
                left: tooltipPosition.x + 15 + 'px',
                top: tooltipPosition.y + 15 + 'px',
                maxWidth: '500px'
            }"
        >
            <div class="bg-gray-900 border border-red-600/50 rounded-lg shadow-2xl p-3 text-xs">
                <div class="font-semibold text-red-300 mb-2 flex items-center">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    {{ $t('Error Details:') }}
                </div>
                <div class="text-[11px] text-red-100 leading-snug">{{ failureReason(hoveredDemo) }}</div>
                <div
                    v-if="isAdminUser && hoveredDemo.processing_output"
                    class="mt-2 pt-2 border-t border-red-600/30 font-mono text-[10px] text-red-200/70 whitespace-pre-wrap break-words max-h-48 overflow-y-auto"
                >{{ hoveredDemo.processing_output }}</div>
            </div>
        </div>

        <!-- Failed-Validity Demo Tooltip -->
        <div
            v-if="hoveredDemo && hoveredDemo.status === 'failed-validity' && hoveredDemo.validity"
            class="fixed z-50 pointer-events-none"
            :style="{
                left: tooltipPosition.x + 15 + 'px',
                top: tooltipPosition.y + 15 + 'px',
                maxWidth: '400px'
            }"
        >
            <div class="bg-gray-900 border border-orange-600/50 rounded-lg shadow-2xl p-3 text-xs">
                <div class="font-semibold text-orange-300 mb-2 flex items-center">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                    {{ $t('Invalid Settings:') }}
                </div>
                <div class="space-y-1 text-orange-200">
                    <div v-for="(value, key) in (typeof hoveredDemo.validity === 'string' ? JSON.parse(hoveredDemo.validity) : hoveredDemo.validity)" :key="key">
                        <span class="font-semibold">{{ key }}:</span> <span class="font-mono">{{ value }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Assigned Demo Tooltip (Online Record) -->
        <div
            v-if="hoveredDemo && hoveredDemo.status === 'assigned' && hoveredDemo.record"
            class="fixed z-50 pointer-events-none"
            :style="{
                left: tooltipPosition.x + 15 + 'px',
                top: tooltipPosition.y + 15 + 'px',
                maxWidth: '400px'
            }"
        >
            <div class="bg-gray-900 border border-purple-600/50 rounded-lg shadow-2xl p-3 text-xs">
                <div class="font-semibold text-purple-300 mb-2 flex items-center">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    {{ $t('Online Record Details:') }}
                </div>
                <div class="space-y-1 text-gray-300">
                    <div><span class="text-gray-400">{{ $t('Record ID:') }}</span> <span class="font-semibold text-purple-300">#{{ hoveredDemo.record_id }}</span></div>
                    <div><span class="text-gray-400">{{ $t('Map:') }}</span> <span class="font-semibold text-blue-300">{{ hoveredDemo.record.mapname }}</span></div>
                    <div v-if="hoveredDemo.record.user"><span class="text-gray-400">{{ $t('Player:') }}</span> <span class="font-semibold" v-html="q3tohtml(hoveredDemo.record.user.name)"></span></div>
                    <div><span class="text-gray-400">{{ $t('Time:') }}</span> <span class="font-semibold font-mono text-yellow-300">{{ formatTime(hoveredDemo.record.time) }}</span></div>
                    <div v-if="hoveredDemo.record.date_set"><span class="text-gray-400">{{ $t('Date:') }}</span> <span class="font-semibold text-gray-300">{{ new Date(hoveredDemo.record.date_set).toLocaleDateString() }}</span></div>
                </div>
                <div class="border-t border-purple-600/30 pt-2 mt-2 space-y-1">
                    <div class="text-[10px] text-purple-200/70 font-semibold mb-1">{{ $t('Match details:') }}</div>
                    <div class="text-[10px] text-gray-400">
                        <span class="text-gray-500">{{ $t('Method:') }}</span>
                        <span v-if="hoveredDemo.user_id && hoveredDemo.record.user_id === hoveredDemo.user_id" class="text-green-400">{{ $t('Uploader match') }}</span>
                        <span v-else class="text-blue-400">{{ $t('Name match') }}</span>
                    </div>
                    <div v-if="hoveredDemo.name_confidence !== null" class="text-[10px] text-gray-400">
                        <span class="text-gray-500">{{ $t('Confidence:') }}</span>
                        <span :class="hoveredDemo.name_confidence === 100 ? 'text-green-400' : hoveredDemo.name_confidence >= 90 ? 'text-blue-400' : 'text-yellow-400'">{{ hoveredDemo.name_confidence }}%</span>
                    </div>
                    <div v-if="hoveredDemo.matched_alias" class="text-[10px] text-gray-400">
                        <span class="text-gray-500">{{ $t('Alias:') }}</span>
                        <span class="text-purple-300" v-html="q3tohtml(hoveredDemo.matched_alias)"></span>
                    </div>
                    <div class="text-[10px] text-gray-400">
                        <span class="text-gray-500">{{ $t('Matched:') }}</span>
                        <span class="text-gray-300">{{ $t('map + gametype + time + player') }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Assigned Demo Tooltip (Offline Record) -->
        <div
            v-if="hoveredDemo && hoveredDemo.status === 'assigned' && hoveredDemo.offline_record && !hoveredDemo.record"
            class="fixed z-50 pointer-events-none"
            :style="{
                left: tooltipPosition.x + 15 + 'px',
                top: tooltipPosition.y + 15 + 'px',
                maxWidth: '400px'
            }"
        >
            <div class="bg-gray-900 border border-purple-600/50 rounded-lg shadow-2xl p-3 text-xs">
                <div class="font-semibold text-purple-300 mb-2 flex items-center">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    {{ $t('Offline Record Details:') }}
                </div>
                <div class="space-y-1 text-gray-300">
                    <div><span class="text-gray-400">{{ $t('Record ID:') }}</span> <span class="font-semibold text-purple-300">#{{ hoveredDemo.offline_record.id }}</span></div>
                    <div><span class="text-gray-400">{{ $t('Map:') }}</span> <span class="font-semibold text-blue-300">{{ hoveredDemo.offline_record.map_name }}</span></div>
                    <div><span class="text-gray-400">{{ $t('Player:') }}</span> <span class="font-semibold" v-html="q3tohtml(hoveredDemo.offline_record.player_name)"></span></div>
                    <div><span class="text-gray-400">{{ $t('Time:') }}</span> <span class="font-semibold font-mono text-yellow-300">{{ formatTime(hoveredDemo.offline_record.time_ms) }}</span></div>
                    <div><span class="text-gray-400">{{ $t('Rank:') }}</span> <span class="font-semibold text-orange-300">#{{ hoveredDemo.offline_record.rank }}</span></div>
                    <div><span class="text-gray-400">{{ $t('Gametype:') }}</span> <span class="font-semibold text-cyan-300 uppercase">{{ hoveredDemo.offline_record.gametype }}</span></div>
                    <div v-if="hoveredDemo.offline_record.date_set"><span class="text-gray-400">{{ $t('Date:') }}</span> <span class="font-semibold text-gray-300">{{ new Date(hoveredDemo.offline_record.date_set).toLocaleDateString() }}</span></div>
                </div>
                <div class="border-t border-purple-600/30 pt-2 mt-2 space-y-1">
                    <div class="text-[10px] text-purple-200/70 font-semibold mb-1">{{ $t('Match details:') }}</div>
                    <div class="text-[10px] text-gray-400">
                        <span class="text-gray-500">{{ $t('Method:') }}</span>
                        <span class="text-cyan-400">{{ $t('Offline demo (direct record)') }}</span>
                    </div>
                    <div v-if="hoveredDemo.name_confidence !== null" class="text-[10px] text-gray-400">
                        <span class="text-gray-500">{{ $t('Confidence:') }}</span>
                        <span :class="hoveredDemo.name_confidence === 100 ? 'text-green-400' : hoveredDemo.name_confidence >= 90 ? 'text-blue-400' : 'text-yellow-400'">{{ hoveredDemo.name_confidence }}%</span>
                    </div>
                    <div v-if="hoveredDemo.matched_alias" class="text-[10px] text-gray-400">
                        <span class="text-gray-500">{{ $t('Alias:') }}</span>
                        <span class="text-purple-300" v-html="q3tohtml(hoveredDemo.matched_alias)"></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Fallback-Assigned Demo Tooltip (Offline Record - Rematchable) -->
        <div
            v-if="hoveredDemo && hoveredDemo.status === 'fallback-assigned' && hoveredDemo.offline_record"
            class="fixed z-50 pointer-events-none"
            :style="{
                left: tooltipPosition.x + 15 + 'px',
                top: tooltipPosition.y + 15 + 'px',
                maxWidth: '400px'
            }"
        >
            <div class="bg-gray-900 border border-orange-600/50 rounded-lg shadow-2xl p-3 text-xs">
                <div class="font-semibold text-orange-300 mb-2 flex items-center">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                    {{ $t('Fallback Offline Record (Rematchable):') }}
                </div>
                <div class="space-y-1 text-gray-300 mb-2">
                    <div><span class="text-gray-400">{{ $t('Record ID:') }}</span> <span class="font-semibold text-orange-300">#{{ hoveredDemo.offline_record.id }}</span></div>
                    <div><span class="text-gray-400">{{ $t('Map:') }}</span> <span class="font-semibold text-blue-300">{{ hoveredDemo.offline_record.map_name }}</span></div>
                    <div><span class="text-gray-400">{{ $t('Player:') }}</span> <span class="font-semibold" v-html="q3tohtml(hoveredDemo.offline_record.player_name)"></span></div>
                    <div><span class="text-gray-400">{{ $t('Time:') }}</span> <span class="font-semibold font-mono text-yellow-300">{{ formatTime(hoveredDemo.offline_record.time_ms) }}</span></div>
                    <div><span class="text-gray-400">{{ $t('Rank:') }}</span> <span class="font-semibold text-orange-300">#{{ hoveredDemo.offline_record.rank }}</span></div>
                    <div><span class="text-gray-400">{{ $t('Gametype:') }}</span> <span class="font-semibold text-cyan-300 uppercase">{{ hoveredDemo.offline_record.gametype }}</span></div>
                    <div v-if="hoveredDemo.offline_record.date_set"><span class="text-gray-400">{{ $t('Date:') }}</span> <span class="font-semibold text-gray-300">{{ new Date(hoveredDemo.offline_record.date_set).toLocaleDateString() }}</span></div>
                </div>
                <div class="border-t border-orange-600/30 pt-2 mt-2 space-y-1">
                    <div class="text-[10px] text-orange-200/70 font-semibold mb-1">{{ $t('Why fallback?') }}</div>
                    <div v-if="hoveredDemo.name_confidence !== null && hoveredDemo.name_confidence < 100" class="text-[10px] text-gray-400">
                        <span class="text-gray-500">{{ $t('Confidence:') }}</span>
                        <span :class="hoveredDemo.name_confidence >= 90 ? 'text-blue-400' : hoveredDemo.name_confidence >= 70 ? 'text-yellow-400' : 'text-red-400'">{{ hoveredDemo.name_confidence }}%</span>
                        <span class="text-gray-500">{{ $t('(needs 100% for direct match)') }}</span>
                    </div>
                    <div v-else class="text-[10px] text-gray-400">
                        <span class="text-orange-300">{{ $t('No matching online record found') }}</span>
                    </div>
                    <div v-if="hoveredDemo.matched_alias" class="text-[10px] text-gray-400">
                        <span class="text-gray-500">{{ $t('Alias:') }}</span>
                        <span class="text-orange-300" v-html="q3tohtml(hoveredDemo.matched_alias)"></span>
                    </div>
                    <div class="text-[10px] text-orange-200/50 mt-1">
                        {{ $t('Can still be matched to an online record later.') }}
                    </div>
                </div>
            </div>
        </div>
    </Teleport>

    <!-- Reprocess Confirm Modal -->
    <Teleport to="body">
        <div v-if="showReprocessConfirm" class="fixed inset-0 z-[60] flex items-center justify-center" @click.self="showReprocessConfirm = false">
            <div class="fixed inset-0 bg-black/60"></div>
            <div class="relative bg-gray-800 border border-gray-600/50 rounded-xl shadow-2xl p-6 max-w-sm w-full mx-4">
                <div class="flex items-center space-x-3 mb-4">
                    <div class="w-10 h-10 rounded-full bg-orange-500/20 flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-gray-100">{{ $t('Reprocess Failed Demos') }}</h3>
                        <p class="text-sm text-gray-400">{{ demoCountsComputed.failed }} demo(s) will be queued</p>
                    </div>
                </div>
                <p class="text-sm text-gray-300 mb-6">{{ $t('All failed demos will be sent back to the processing queue. You can track the progress in the queue status above.') }}</p>
                <div class="flex justify-end space-x-3">
                    <button @click="showReprocessConfirm = false" class="px-4 py-2 rounded-lg text-sm font-medium text-gray-300 bg-gray-700 hover:bg-gray-600 border border-gray-600 transition-colors">
                        {{ $t('Cancel') }}
                    </button>
                    <button @click="reprocessAllFailed" class="px-4 py-2 rounded-lg text-sm font-medium text-white bg-orange-600 hover:bg-orange-500 transition-colors">
                        {{ $t('Reprocess All') }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>

    <!-- Upload Info Modal -->
    <Teleport to="body">
        <div v-if="showUploadInfo" class="fixed inset-0 z-[60] flex items-center justify-center" @click.self="showUploadInfo = false">
            <div class="fixed inset-0 bg-black/60"></div>
            <div class="relative bg-gray-800 border border-gray-600/50 rounded-xl shadow-2xl p-6 max-w-sm w-full mx-4">
                <div class="flex items-center space-x-3 mb-4">
                    <div class="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0"
                        :class="{
                            'bg-blue-500/20': uploadInfoType === 'info',
                            'bg-yellow-500/20': uploadInfoType === 'warning',
                            'bg-red-500/20': uploadInfoType === 'error'
                        }"
                    >
                        <svg v-if="uploadInfoType === 'info'" class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <svg v-else-if="uploadInfoType === 'warning'" class="w-5 h-5 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                        <svg v-else class="w-5 h-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-100">{{ uploadInfoTitle }}</h3>
                </div>
                <p class="text-sm text-gray-300 mb-6 whitespace-pre-line">{{ uploadInfoMessage }}</p>
                <div class="flex justify-end">
                    <button @click="showUploadInfo = false" class="px-4 py-2 rounded-lg text-sm font-medium text-white transition-colors"
                        :class="{
                            'bg-blue-600 hover:bg-blue-500': uploadInfoType === 'info',
                            'bg-yellow-600 hover:bg-yellow-500': uploadInfoType === 'warning',
                            'bg-red-600 hover:bg-red-500': uploadInfoType === 'error'
                        }"
                    >
                        {{ $t('OK') }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>

    <!-- Download Limit Popup -->
    <Teleport to="body">
        <div v-if="showDownloadLimitPopup" class="fixed inset-0 z-[9999] flex items-center justify-center" @click.self="showDownloadLimitPopup = false">
            <div class="fixed inset-0 bg-black/60 backdrop-blur-sm"></div>
            <div class="relative bg-gray-900 border border-white/10 rounded-2xl shadow-2xl max-w-md w-full mx-4 p-6">
                <button @click="showDownloadLimitPopup = false" class="absolute top-3 right-3 text-gray-500 hover:text-white transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 rounded-full bg-red-500/20 flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                    </div>
                    <h3 class="text-lg font-bold text-white">{{ $t('Download Limit Reached') }}</h3>
                </div>
                <p class="text-gray-300 text-sm mb-5">{{ downloadLimitPopupMessage }}</p>
                <div v-if="downloadLimitPopupIsGuest" class="flex gap-3">
                    <a href="/login" class="flex-1 text-center px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white font-semibold rounded-lg transition-colors">{{ $t('Login') }}</a>
                    <a href="/register" class="flex-1 text-center px-4 py-2 bg-green-600 hover:bg-green-500 text-white font-semibold rounded-lg transition-colors">{{ $t('Register') }}</a>
                </div>
                <div v-else>
                    <button @click="showDownloadLimitPopup = false" class="w-full px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white font-semibold rounded-lg transition-colors">{{ $t('OK') }}</button>
                </div>
            </div>
        </div>
    </Teleport>
</template>