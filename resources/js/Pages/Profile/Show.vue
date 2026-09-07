<script setup>
import { Head, useForm, router, usePage } from '@inertiajs/vue3';
import { ref, computed, onMounted, nextTick } from 'vue';
import CountrySelect from '@/Components/Basic/CountrySelect.vue';
import DeleteUserForm from '@/Pages/Profile/Partials/DeleteUserForm.vue';
import LogoutOtherBrowserSessionsForm from '@/Pages/Profile/Partials/LogoutOtherBrowserSessionsForm.vue';
import TwoFactorAuthenticationForm from '@/Pages/Profile/Partials/TwoFactorAuthenticationForm.vue';
import UpdatePasswordForm from '@/Pages/Profile/Partials/UpdatePasswordForm.vue';
import UpdateSocialMediaForm from '@/Pages/Profile/Partials/UpdateSocialMediaForm.vue';
import LauncherTokensForm from '@/Pages/Profile/Partials/LauncherTokensForm.vue';
import ApiTokensForm from '@/Pages/Profile/Partials/ApiTokensForm.vue';

import ManageAliasesForm from '@/Pages/Profile/Partials/ManageAliasesForm.vue';
import StreamerWidgetForm from '@/Pages/Profile/Partials/StreamerWidgetForm.vue';
import ProfileLayoutForm from '@/Pages/Profile/Partials/ProfileLayoutForm.vue';
import EffectsIntensityForm from '@/Pages/Profile/Partials/EffectsIntensityForm.vue';
import InputLabel from '@/Components/Laravel/InputLabel.vue';
import InputError from '@/Components/Laravel/InputError.vue';
import TextInput from '@/Components/Laravel/TextInput.vue';
import PrimaryButton from '@/Components/Laravel/PrimaryButton.vue';
import SecondaryButton from '@/Components/Laravel/SecondaryButton.vue';
import { Cropper } from 'vue-advanced-cropper';
import draggable from 'vuedraggable';
import 'vue-advanced-cropper/dist/style.css';
import { t } from '@/utils/i18n';

import EnglishOnlyNotice from '@/Components/EnglishOnlyNotice.vue';
const props = defineProps({
    confirmsTwoFactorAuthentication: Boolean,
    sessions: Array,
});

const page = usePage();
const user = computed(() => page.props.auth.user);
const isVerified = computed(() => !!user.value.email_verified_at);

// Profile Information Form
const profileForm = useForm({
    _method: 'PUT',
    name: user.value.name || '',
    email: user.value.email || '',
    photo: null,
    country: user.value.country || 'US'
});

const photoInput = ref(null);
const photoPreview = ref(null);

const selectNewPhoto = () => photoInput.value.click();
const updatePhotoPreview = () => {
    const photo = photoInput.value.files[0];
    if (!photo) return;
    // 1MB size limit for profile photo
    const maxSize = 1 * 1024 * 1024;
    if (photo.size > maxSize) {
        profileForm.errors.photo = 'The profile photo must be smaller than 1MB.';
        photoInput.value.value = '';
        return;
    }
    profileForm.errors.photo = '';
    const reader = new FileReader();
    reader.onload = (e) => { photoPreview.value = e.target.result; };
    reader.readAsDataURL(photo);

    // Auto-save avatar immediately
    profileForm.photo = photo;
    profileForm.post(route('user-profile-information.update'), {
        errorBag: 'updateProfileInformation',
        preserveScroll: true,
        onSuccess: () => {
            if (photoInput.value?.value) photoInput.value.value = null;
        },
    });
};

const deletePhoto = () => {
    router.delete(route('current-user-photo.destroy'), {
        preserveScroll: true,
        onSuccess: () => {
            photoPreview.value = null;
            if (photoInput.value?.value) photoInput.value.value = null;
        },
    });
};

const updateProfile = () => {
    if (photoInput.value) profileForm.photo = photoInput.value.files[0];
    profileForm.post(route('user-profile-information.update'), {
        errorBag: 'updateProfileInformation',
        preserveScroll: true,
        onSuccess: () => {
            if (photoInput.value?.value) photoInput.value.value = null;
        },
    });
};

const setCountry = (country) => { profileForm.country = country; };

// Background Upload Form
const backgroundForm = useForm({
    _method: 'POST',
    background: null
});

const backgroundInput = ref(null);
const backgroundPreview = ref(null);
const showBackgroundCropper = ref(false);
const tempBackgroundSrc = ref(null);
const backgroundCropper = ref(null);

const selectNewBackground = () => backgroundInput.value.click();

const updateBackgroundPreview = () => {
    const background = backgroundInput.value.files[0];
    if (!background) return;
    if (!background.type.startsWith('image/')) {
        backgroundForm.errors.background = 'The file must be an Image.';
        backgroundInput.value.value = '';
        return;
    }
    // 5MB size limit for profile background
    const maxSize = 5 * 1024 * 1024;
    if (background.size > maxSize) {
        backgroundForm.errors.background = 'The background image must be smaller than 5MB.';
        backgroundInput.value.value = '';
        return;
    }
    backgroundForm.errors.background = '';
    const reader = new FileReader();
    reader.onload = (e) => {
        tempBackgroundSrc.value = e.target.result;
        showBackgroundCropper.value = true;
    };
    reader.readAsDataURL(background);
};

const handleBackgroundCrop = () => {
    if (!backgroundCropper.value) return;

    const { canvas } = backgroundCropper.value.getResult();

    canvas.toBlob((blob) => {
        const reader = new FileReader();
        reader.onload = (e) => {
            backgroundPreview.value = e.target.result;
        };
        reader.readAsDataURL(blob);

        backgroundForm.background = new File([blob], 'background.jpg', { type: 'image/jpeg' });
        showBackgroundCropper.value = false;
        tempBackgroundSrc.value = null;

        backgroundForm.post(route('settings.background'), {
            preserveScroll: true,
            onSuccess: () => {
                backgroundInput.value.value = '';
                backgroundPreview.value = null;
            }
        });
    }, 'image/jpeg', 0.92);
};

const cancelBackgroundCrop = () => {
    showBackgroundCropper.value = false;
    tempBackgroundSrc.value = null;
    backgroundInput.value.value = '';
};

const deleteBackground = () => {
    router.delete(route('settings.background.destroy'), {
        preserveScroll: true,
        onSuccess: () => {
            if (backgroundInput.value?.value) backgroundInput.value.value = null;
        }
    });
};

// Preferences Form
const prefsForm = useForm({
    _method: 'POST',
    color: user.value.color || '#1F2937',
    avatar_effect: user.value.avatar_effect || 'none',
    name_effect: user.value.name_effect || 'none',
    avatar_border_color: user.value.avatar_border_color || '#6b7280'
});

const updatePreferences = () => {
    prefsForm.post(route('settings.preferences'), { preserveScroll: true });
};

// Map View Preferences Form
const mapViewForm = useForm({
    default_show_oldtop: user.value.default_show_oldtop || false,
    default_show_offline: user.value.default_show_offline || false,
});

const updateMapViewPreferences = () => {
    mapViewForm.post(route('settings.map-view-preferences'), { preserveScroll: true });
};

// Physics Order Preference Form
const physicsOrderForm = useForm({
    default_physics_order: user.value.default_physics_order || 'vq3_first',
});

const updatePhysicsOrder = () => {
    physicsOrderForm.post(route('settings.physics-order'), { preserveScroll: true });
};

// Global Profile Preferences Form
const profileSections = computed(() => [
    { id: 'activity_history', label: t('Activity History') },
    { id: 'records', label: t('Records') },
    { id: 'rendered_videos', label: t('Rendered Videos') },
    { id: 'similar_skill_rivals', label: t('Similar Skill Rivals') },
    { id: 'competitor_comparison', label: t('Competitor Comparison') },
    { id: 'known_aliases', label: t('Known Aliases') },
    { id: 'featured_maplists', label: t('Featured Maplists') },
    { id: 'map_completionist', label: t('Map Completionist') },
]);

const statBoxItems = computed(() => [
    { id: 'performance', label: t('Performance') },
    { id: 'activity', label: t('Activity') },
    { id: 'record_types', label: t('Record Types') },
    { id: 'map_features', label: t('Map Features') },
    { id: 'renders', label: t('Renders') },
]);

const aboutMeForm = useForm({ content: user.value.about_me || '' });
const submitAboutMeSettings = () => {
    aboutMeForm.post(route('profile.about-me.submit', user.value.id), { preserveScroll: true });
};

const savedHidden = user.value.global_profile_preferences?.hidden_sections || [];
const savedHiddenStatBoxes = user.value.global_profile_preferences?.hidden_stat_boxes || [];
const savedDateFormat = user.value.global_profile_preferences?.date_format || 'dmY';
const savedTimeFormat = user.value.global_profile_preferences?.time_format || 'dot';
const globalProfileForm = useForm({
    hidden_sections: [...savedHidden],
    hidden_stat_boxes: [...savedHiddenStatBoxes],
    date_format: savedDateFormat,
    time_format: savedTimeFormat,
});

const toggleGlobalSection = (id) => {
    const idx = globalProfileForm.hidden_sections.indexOf(id);
    if (idx >= 0) {
        globalProfileForm.hidden_sections.splice(idx, 1);
    } else {
        globalProfileForm.hidden_sections.push(id);
    }
    saveGlobalPreferencesDebounced();
};

const toggleGlobalStatBox = (id) => {
    const idx = globalProfileForm.hidden_stat_boxes.indexOf(id);
    if (idx >= 0) {
        globalProfileForm.hidden_stat_boxes.splice(idx, 1);
    } else {
        globalProfileForm.hidden_stat_boxes.push(id);
    }
    saveGlobalPreferencesDebounced();
};

let globalPrefTimeout = null;
const saveGlobalPreferencesDebounced = () => {
    clearTimeout(globalPrefTimeout);
    globalPrefTimeout = setTimeout(() => {
        globalProfileForm.post(route('settings.global-profile-preferences'), { preserveScroll: true });
    }, 500);
};

const updateGlobalProfilePreferences = () => {
    clearTimeout(globalPrefTimeout);
    globalProfileForm.post(route('settings.global-profile-preferences'), { preserveScroll: true });
};

const avatarEffects = computed(() => [
    { id: 'none', name: t('None'), icon: '⭕' },
    { id: 'glow', name: t('Glow'), icon: '✨' },
    { id: 'pulse', name: t('Pulse'), icon: '💓' },
    { id: 'spin', name: t('Spin'), icon: '🌀' },
    { id: 'bounce', name: t('Bounce'), icon: '⬆️' },
    { id: 'shake', name: t('Shake'), icon: '📳' },
    { id: 'ring', name: t('Ring'), icon: '💍' },
    { id: 'fire', name: t('Fire'), icon: '🔥' },
    { id: 'electric', name: t('Electric'), icon: '⚡' },
    { id: 'rainbow', name: t('Rainbow'), icon: '🌈' },
    { id: 'portal', name: t('Portal'), icon: '🌌' },
    { id: 'hologram', name: t('Hologram'), icon: '👾' },
    { id: 'glitch', name: t('Glitch'), icon: '📺' },
    { id: 'frost', name: t('Frost'), icon: '❄️' },
    { id: 'neon', name: t('Neon'), icon: '💡' },
    { id: 'orbit', name: t('Orbit'), icon: '🪐' },
    { id: 'matrix', name: t('Matrix'), icon: '🟢' },
    { id: 'vortex', name: t('Vortex'), icon: '🌪️' },
    { id: 'quantum', name: t('Quantum'), icon: '⚛️' },
    { id: 'nebula', name: t('Nebula'), icon: '☁️' },
    { id: 'supernova', name: t('Supernova'), icon: '💥' },
    { id: 'digital', name: t('Digital'), icon: '💾' },
    { id: 'cosmic', name: t('Cosmic'), icon: '🌠' },
    { id: 'plasma', name: t('Plasma'), icon: '🔮' }
]);

const nameEffects = computed(() => [
    { id: 'none', name: t('None'), icon: '⭕' },
    { id: 'wave', name: t('Wave'), icon: '🌊' },
    { id: 'bounce', name: t('Bounce'), icon: '⬆️' },
    { id: 'shake', name: t('Shake'), icon: '📳' },
    { id: 'glitch', name: t('Glitch'), icon: '📺' },
    { id: 'rainbow', name: t('Rainbow'), icon: '🌈' },
    { id: 'neon', name: t('Neon'), icon: '💡' },
    { id: 'typewriter', name: t('Typewriter'), icon: '⌨️' },
    { id: 'slide', name: t('Slide'), icon: '➡️' },
    { id: 'fade', name: t('Fade'), icon: '👻' },
    { id: 'zoom', name: t('Zoom'), icon: '🔍' },
    { id: 'flip', name: t('Flip'), icon: '🔄' },
    { id: 'shadow', name: t('Shadow'), icon: '🌑' },
    { id: 'gradient', name: t('Gradient'), icon: '🎨' },
    { id: 'electric', name: t('Electric'), icon: '⚡' },
    { id: 'fire', name: t('Fire Text'), icon: '🔥' },
    { id: 'matrix', name: t('Matrix'), icon: '🟢' },
    { id: 'cyber', name: t('Cyber'), icon: '🤖' },
    { id: 'vortex', name: t('Vortex'), icon: '🌪️' },
    { id: 'quantum', name: t('Quantum'), icon: '⚛️' },
    { id: 'nebula', name: t('Nebula'), icon: '☁️' },
    { id: 'wave-distort', name: t('Wave Distort'), icon: '〰️' },
    { id: 'plasma', name: t('Plasma'), icon: '🔮' },
    { id: 'cosmic', name: t('Cosmic'), icon: '🌠' },
    { id: 'shockwave', name: t('Shockwave'), icon: '💥' },
    { id: 'fractal', name: t('Fractal'), icon: '🔺' }
]);

// Notifications Form
const notifsForm = useForm({
    _method: 'POST',
    tournament_news: user.value.tournament_news ? true : false,
    map_news: user.value.map_news ? true : false,
    clan_notifications: user.value.clan_notifications ? true : false,
    invitations: true,
    records_vq3: user.value.records_vq3 || 'all',
    records_cpm: user.value.records_cpm || 'all',
    preview_records: user.value.preview_records || 'all',
    preview_system: user.value.preview_system || ['announcement', 'clan', 'tournament', 'map']
});

const togglePreviewSystem = (type) => {
    if (type === 'announcement') return; // Announcement is mandatory

    const index = notifsForm.preview_system.indexOf(type);
    if (index > -1) {
        notifsForm.preview_system.splice(index, 1);
    } else {
        notifsForm.preview_system.push(type);
    }
};

const updateNotifications = () => {
    notifsForm.post(route('settings.notifications'), { preserveScroll: true });
};

// Resolve initial tab from URL query param ?tab=
const urlParams = new URLSearchParams(window.location.search);
const hasTwitch = computed(() => !!user.value.twitch_id);
const validTabs = ['profile', 'creator', 'marketplace', 'customize', 'global-customize', 'notifications', 'security', ...(user.value.twitch_id ? ['streamer'] : [])];
const initialTab = validTabs.includes(urlParams.get('tab')) ? urlParams.get('tab') : 'profile';
const activeTab = ref(initialTab);

// Creator sub-sections (for left nav scroll)
const creatorSections = computed(() => [
    { id: 'creator-names', label: t('Creator Names') },
    { id: 'creator-maps', label: t('Map Selector') },
    { id: 'creator-pinned', label: t('Pinned Models') },
    { id: 'creator-order', label: t('Model Order') },
]);

const customizeSections = computed(() => [
    { id: 'customize-effects', label: t('Effects') },
    { id: 'customize-intensity', label: t('Intensity') },
    { id: 'customize-layout', label: t('Profile Layout') },
]);

const globalCustomizeSections = computed(() => [
    { id: 'gc-map-view', label: t('Map View Defaults') },
    { id: 'gc-physics-order', label: t('Physics Order') },
    { id: 'gc-date-format', label: t('Date Format') },
    { id: 'gc-time-format', label: t('Time Format') },
    { id: 'gc-hide-stats', label: t('Hide Stat Boxes') },
    { id: 'gc-hide-sections', label: t('Hide Sections') },
]);

const switchTab = (tabId) => {
    activeTab.value = tabId;
    const url = new URL(window.location.href);
    url.searchParams.set('tab', tabId);
    window.history.replaceState({}, '', url.toString());
    nextTick(() => { globalThis.scrollTo({ top: 0, behavior: 'smooth' }); });
};

const scrollToSection = (sectionId) => {
    nextTick(() => {
        const el = document.getElementById(sectionId);
        if (!el) return;
        const navHeight = 120; // navbar height + padding
        const y = el.getBoundingClientRect().top + globalThis.scrollY - navHeight;
        globalThis.scrollTo({ top: y, behavior: 'smooth' });
    });
};

// Mapper Claims
const mapperClaims = ref([]);
const loadingMapperClaims = ref(true);
const savingMapperClaims = ref(false);
const mapperClaimsSaved = ref(false);
const newClaimName = ref('');
const newClaimType = ref('map');
const claimPreview = ref(null);
const loadingPreview = ref(false);
let previewTimeout = null;

const fetchMapperClaims = async () => {
    loadingMapperClaims.value = true;
    try {
        const res = await fetch(route('settings.mapper-claims.get'));
        mapperClaims.value = await res.json();
        // Auto-fetch maps for all claims
        await fetchAllClaimMaps();
    } catch (e) {
        console.error('Error loading mapper claims:', e);
    } finally {
        loadingMapperClaims.value = false;
    }
};

const previewClaim = () => {
    if (previewTimeout) clearTimeout(previewTimeout);
    const name = newClaimName.value.trim();
    if (name.length < 2) {
        claimPreview.value = null;
        return;
    }
    previewTimeout = setTimeout(async () => {
        loadingPreview.value = true;
        try {
            const res = await fetch(route('settings.mapper-claims.preview'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ name, type: newClaimType.value }),
            });
            claimPreview.value = await res.json();
        } catch (e) {
            claimPreview.value = null;
        } finally {
            loadingPreview.value = false;
        }
    }, 300);
};

// Exclusion management
const expandedClaims = ref(new Set());
let claimMapsSearchTimeout = null;

// Store maps per claim
const claimMapsById = ref({});
const loadingClaimMapsById = ref({});

const fetchClaimMaps = async (claimId, search = '') => {
    loadingClaimMapsById.value[claimId] = true;
    try {
        const url = `/settings/mapper-claims/${claimId}/maps` + (search ? `?search=${encodeURIComponent(search)}` : '');
        const res = await fetch(url, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        const data = await res.json();
        claimMapsById.value[claimId] = data.maps;
    } catch (e) {
        claimMapsById.value[claimId] = [];
    } finally {
        loadingClaimMapsById.value[claimId] = false;
    }
};

const fetchAllClaimMaps = async () => {
    for (const claim of mapperClaims.value) {
        if (claim.id) {
            await fetchClaimMaps(claim.id);
        }
    }
};

const claimSearches = ref({});
const searchClaimMaps = (claimId) => {
    if (claimMapsSearchTimeout) clearTimeout(claimMapsSearchTimeout);
    claimMapsSearchTimeout = setTimeout(() => {
        fetchClaimMaps(claimId, claimSearches.value[claimId] || '');
    }, 300);
};

const toggleExclusion = async (claimId, mapId) => {
    const maps = claimMapsById.value[claimId] || [];
    const map = maps.find(m => m.id === mapId);
    if (map) map.excluded = !map.excluded; // optimistic

    try {
        const res = await fetch(`/settings/mapper-claims/${claimId}/exclusions/toggle`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ map_id: mapId }),
        });
        const data = await res.json();
        if (map) map.excluded = data.excluded;
    } catch (e) {
        if (map) map.excluded = !map.excluded; // revert
    }
};

// Claim dispute reporting
const reportingClaim = ref(false);
const reportSent = ref(false);
const reportReason = ref('');
const showReportForm = ref(false);

const reportError = ref('');

const reportClaim = async () => {
    if (!claimPreview.value?.claimed_by) return;
    reportingClaim.value = true;
    reportError.value = '';
    try {
        const res = await fetch('/settings/mapper-claims/report', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                mapper_claim_id: claimPreview.value.claimed_by.claim_id,
                reason: reportReason.value,
            }),
        });
        const data = await res.json();
        if (data.success) {
            reportSent.value = true;
            showReportForm.value = false;
        } else if (data.error) {
            reportError.value = data.error;
        }
    } catch (e) {
        reportError.value = 'Failed to submit dispute. Please try again.';
    } finally {
        reportingClaim.value = false;
    }
};

const addMapperClaim = async () => {
    const name = newClaimName.value.trim();
    if (!name) return;
    if (claimPreview.value?.claimed_by) return;
    if (mapperClaims.value.some(c => c.name.toLowerCase() === name.toLowerCase() && c.type === newClaimType.value)) return;
    mapperClaims.value.push({ name, type: newClaimType.value, matching_count: claimPreview.value?.count || 0, matching_samples: claimPreview.value?.maps?.map(m => m.name) || [] });
    newClaimName.value = '';
    claimPreview.value = null;
    // Auto-save
    await saveMapperClaims();
};

const removeMapperClaim = async (index) => {
    mapperClaims.value.splice(index, 1);
    // Auto-save
    await saveMapperClaims();
};

const saveMapperClaims = async () => {
    savingMapperClaims.value = true;
    try {
        await fetch(route('settings.mapper-claims'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ claims: mapperClaims.value }),
        });
        mapperClaimsSaved.value = true;
        setTimeout(() => { mapperClaimsSaved.value = false; }, 3000);
        // Refresh to get updated matching counts
        await fetchMapperClaims();
    } catch (e) {
        console.error('Error saving mapper claims:', e);
    } finally {
        savingMapperClaims.value = false;
    }
};

// Pinned Models
const pinnedModelIds = ref([]);
const allCreatorModels = ref([]);
const loadingCreatorModels = ref(false);
const savingPinnedModels = ref(false);
const pinnedModelsSaved = ref(false);

const modelGroupOrder = ref([]);
const savingGroupOrder = ref(false);
const groupOrderSaved = ref(false);

const draggableGroupList = ref([]);

const buildGroupList = () => {
    const groups = {};
    for (const model of allCreatorModels.value) {
        const base = model.base_model || model.name;
        if (!groups[base]) groups[base] = 0;
        groups[base]++;
    }
    const order = modelGroupOrder.value;
    draggableGroupList.value = Object.entries(groups)
        .map(([name, count]) => ({ name, count }))
        .sort((a, b) => {
            if (order.length) {
                const ai = order.indexOf(a.name);
                const bi = order.indexOf(b.name);
                if (ai !== -1 && bi !== -1) return ai - bi;
                if (ai !== -1) return -1;
                if (bi !== -1) return 1;
            }
            return b.count - a.count;
        });
};

const onGroupDragEnd = async () => {
    modelGroupOrder.value = draggableGroupList.value.map(g => g.name);
    await saveModelGroupOrder();
};

const saveModelGroupOrder = async () => {
    savingGroupOrder.value = true;
    try {
        await fetch('/settings/model-group-order', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ model_group_order: modelGroupOrder.value }),
        });
        groupOrderSaved.value = true;
        setTimeout(() => { groupOrderSaved.value = false; }, 3000);
    } catch (e) {
        console.error('Error saving group order:', e);
    } finally {
        savingGroupOrder.value = false;
    }
};

const loadPinnedModels = async () => {
    const claims = mapperClaims.value.filter(c => c.type === 'model');
    if (claims.length === 0) return;

    loadingCreatorModels.value = true;
    try {
        const res = await fetch(`/api/profile/${page.props.auth?.user?.id}/mapper/models`);
        const data = await res.json();
        allCreatorModels.value = data.models || [];
        pinnedModelIds.value = (data.pinned || []).map(m => m.id);
        modelGroupOrder.value = data.group_order || [];
        buildGroupList();
    } catch (e) {
        console.error('Error loading pinned models:', e);
    } finally {
        loadingCreatorModels.value = false;
    }
};

const pinnedLeft = computed(() => pinnedModelIds.value[0] || null);
const pinnedRight = computed(() => pinnedModelIds.value[1] || null);

const pinModelAs = async (modelId, side) => {
    // side: 'left' (index 0) or 'right' (index 1)
    const idx = side === 'left' ? 0 : 1;
    const current = [...pinnedModelIds.value];

    // If already pinned on this side, unpin
    if (current[idx] === modelId) {
        current.splice(idx, 1);
        pinnedModelIds.value = current;
        await savePinnedModels();
        return;
    }

    // If pinned on the other side, remove from there first
    const otherIdx = current.indexOf(modelId);
    if (otherIdx !== -1) current.splice(otherIdx, 1);

    // Set at position
    if (idx === 0) {
        current[0] = modelId;
    } else {
        // Ensure index 0 exists
        if (current.length === 0) current.push(null);
        current[1] = modelId;
    }

    // Clean nulls
    pinnedModelIds.value = current.filter(id => id !== null);
    await savePinnedModels();
};

const unpinModel = async (modelId) => {
    pinnedModelIds.value = pinnedModelIds.value.filter(id => id !== modelId);
    await savePinnedModels();
};

const isModelPinned = (modelId) => pinnedModelIds.value.includes(modelId);
const getPinSide = (modelId) => {
    if (pinnedModelIds.value[0] === modelId) return 'L';
    if (pinnedModelIds.value[1] === modelId) return 'R';
    return null;
};

const savePinnedModels = async () => {
    savingPinnedModels.value = true;
    try {
        await fetch('/settings/pinned-models', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ pinned_models: pinnedModelIds.value }),
        });
        pinnedModelsSaved.value = true;
        setTimeout(() => { pinnedModelsSaved.value = false; }, 3000);
    } catch (e) {
        console.error('Error saving pinned models:', e);
    } finally {
        savingPinnedModels.value = false;
    }
};

// Marketplace Creator Profile
const mpProfile = ref({
    is_listed: false,
    accepting_commissions: true,
    specialties: [],
    bio: '',
    rate_maps: '',
    rate_models: '',
    featured_map_ids: [],
    portfolio_urls: [],
    featured_maps: [],
    // Filled in by the same request that loads the profile: the specialties a
    // creator can tick are the marketplace's work types, and those live in the
    // database now.
    work_types: [],
});
const mpLoading = ref(false);
const mpSaving = ref(false);
const mpSuccess = ref('');
const mpMapSearch = ref('');
const mpMapResults = ref([]);
const mpSearchTimeout = ref(null);

const fetchCreatorProfile = async () => {
    mpLoading.value = true;
    try {
        const res = await fetch(route('settings.creator-profile.get'), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        const data = await res.json();
        mpProfile.value = { ...mpProfile.value, ...data };
    } catch (e) {
        console.error('Failed to load creator profile', e);
    } finally {
        mpLoading.value = false;
    }
};

const saveCreatorProfile = async () => {
    mpSaving.value = true;
    mpSuccess.value = '';
    try {
        const res = await fetch(route('settings.creator-profile'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                is_listed: mpProfile.value.is_listed,
                accepting_commissions: mpProfile.value.accepting_commissions,
                specialties: mpProfile.value.specialties,
                bio: mpProfile.value.bio,
                rate_maps: mpProfile.value.rate_maps,
                rate_models: mpProfile.value.rate_models,
                featured_map_ids: mpProfile.value.featured_map_ids,
                portfolio_urls: mpProfile.value.portfolio_urls,
            }),
        });
        if (res.ok) {
            mpSuccess.value = 'Saved!';
            setTimeout(() => mpSuccess.value = '', 3000);
        }
    } catch (e) {
        console.error('Failed to save creator profile', e);
    } finally {
        mpSaving.value = false;
    }
};

const toggleMpSpecialty = (spec) => {
    const idx = mpProfile.value.specialties.indexOf(spec);
    if (idx === -1) {
        mpProfile.value.specialties.push(spec);
    } else {
        mpProfile.value.specialties.splice(idx, 1);
    }
};

const searchMapsForFeatured = () => {
    clearTimeout(mpSearchTimeout.value);
    if (mpMapSearch.value.length < 2) {
        mpMapResults.value = [];
        return;
    }
    mpSearchTimeout.value = setTimeout(async () => {
        try {
            const res = await fetch(route('settings.creator-profile.search-maps') + `?search=${encodeURIComponent(mpMapSearch.value)}`, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            mpMapResults.value = await res.json();
        } catch (e) {
            mpMapResults.value = [];
        }
    }, 300);
};

const addFeaturedMap = (map) => {
    if (mpProfile.value.featured_map_ids.length >= 5) return;
    if (mpProfile.value.featured_map_ids.includes(map.id)) return;
    mpProfile.value.featured_map_ids.push(map.id);
    mpProfile.value.featured_maps.push(map);
    mpMapSearch.value = '';
    mpMapResults.value = [];
};

const removeFeaturedMap = (mapId) => {
    mpProfile.value.featured_map_ids = mpProfile.value.featured_map_ids.filter(id => id !== mapId);
    mpProfile.value.featured_maps = mpProfile.value.featured_maps.filter(m => m.id !== mapId);
};

onMounted(() => {
    fetchMapperClaims().then(() => loadPinnedModels());
    fetchCreatorProfile();
});

const tabs = computed(() => [
    { id: 'profile', label: t('Profile'), icon: 'M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z' },
    { id: 'global-customize', label: t('Global Customize'), icon: 'M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75' },
    { id: 'marketplace', label: t('Marketplace'), icon: 'M13.5 21v-7.5a.75.75 0 0 1 .75-.75h3a.75.75 0 0 1 .75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349M3.75 21V9.349m0 0a3.001 3.001 0 0 0 3.75-.615A2.993 2.993 0 0 0 9.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 0 0 2.25 1.016c.896 0 1.7-.393 2.25-1.015a3.001 3.001 0 0 0 3.75.614m-16.5 0a3.004 3.004 0 0 1-.621-4.72l1.189-1.19A1.5 1.5 0 0 1 5.378 3h13.243a1.5 1.5 0 0 1 1.06.44l1.19 1.189a3 3 0 0 1-.621 4.72M6.75 18h3.75a.75.75 0 0 0 .75-.75V13.5a.75.75 0 0 0-.75-.75H6.75a.75.75 0 0 0-.75.75v3.75c0 .414.336.75.75.75Z' },
    { id: 'notifications', label: t('Notifications'), icon: 'M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0' },
    { id: 'security', label: t('Security'), icon: 'M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z' },
    { id: 'streamer', label: t('Streamer'), icon: 'm15.75 10.5 4.72-4.72a.75.75 0 0 1 1.28.53v11.38a.75.75 0 0 1-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 0 0 2.25-2.25v-9a2.25 2.25 0 0 0-2.25-2.25h-9A2.25 2.25 0 0 0 2.25 7.5v9a2.25 2.25 0 0 0 2.25 2.25Z', requiresTwitch: true },
]);

const profileSubTabs = computed(() => [
    { id: 'creator', label: t('Creator'), icon: 'M9 6.75V15m6-6v8.25m.503 3.498l4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934c-.317.159-.69.159-1.006 0L9.503 3.252a1.125 1.125 0 00-1.006 0L3.622 5.689C3.24 5.88 3 6.27 3 6.695V19.18c0 .836.88 1.38 1.628 1.006l3.869-1.934c.317-.159.69-.159 1.006 0l4.994 2.497c.317.158.69.158 1.006 0z' },
    { id: 'customize', label: t('Customize'), icon: 'M9.53 16.122a3 3 0 0 0-5.78 1.128 2.25 2.25 0 0 1-2.4 2.245 4.5 4.5 0 0 0 8.4-2.245c0-.399-.078-.78-.22-1.128Zm0 0a15.998 15.998 0 0 0 3.388-1.62m-5.043-.025a15.994 15.994 0 0 1 1.622-3.395m3.42 3.42a15.995 15.995 0 0 0 4.764-4.648l3.876-5.814a1.151 1.151 0 0 0-1.597-1.597L14.146 6.32a15.996 15.996 0 0 0-4.649 4.763m3.42 3.42a6.776 6.776 0 0 0-3.42-3.42' },
]);

const isProfileGroup = (tabId) => ['profile', 'creator', 'customize'].includes(tabId);
const filteredTabs = computed(() => {
    const visible = isVerified.value ? tabs.value : tabs.value.filter(tab => ['profile', 'security'].includes(tab.id));
    return visible.filter(tab => !tab.requiresTwitch || hasTwitch.value);
});
const filteredProfileSubTabs = computed(() => isVerified.value ? profileSubTabs.value : []);
</script>

<template>
    <div class="min-h-screen pb-4 relative">
        <Head :title="$t('Settings')" />

        <!-- Fade shadow at top (absolute positioned behind everything) -->
        <div class="absolute top-0 left-0 right-0 bg-gradient-to-b from-black/25 via-black/10 to-transparent pointer-events-none" style="height: 600px; z-index: 0;"></div>

        <!-- Header Section -->
        <div class="relative pt-6 pb-8" style="z-index: 10;">
            <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8">
                <div>
                    <h1 class="text-2xl md:text-3xl font-black text-gray-300/90 mb-2">{{ $t('Settings') }}</h1>
                    <p class="text-sm text-gray-400">{{ $t('Customize your profile and preferences') }}</p>
                </div>
            </div>
        </div>

        <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8 pb-6 relative" style="z-index: 10;">
            <div class="flex gap-6">
                <!-- Left Sidebar Navigation -->
                <div class="hidden lg:block w-48 shrink-0 sticky top-[120px] self-start">
                    <nav class="bg-black/40 backdrop-blur-sm rounded-xl border border-white/10 p-2 space-y-1">
                        <template v-for="tab in filteredTabs" :key="tab.id">
                            <!-- Profile tab with nested Creator & Customize -->
                            <template v-if="tab.id === 'profile'">
                                <div :class="[
                                    'rounded-lg transition-all',
                                    isProfileGroup(activeTab) || activeTab === 'profile'
                                        ? 'bg-white/[0.07] border border-white/10'
                                        : ''
                                ]">
                                    <button
                                        @click="switchTab('profile')"
                                        :class="[
                                            'w-full flex items-center gap-2.5 px-3 py-2 rounded-lg text-sm font-medium transition-all text-left',
                                            activeTab === 'profile' || isProfileGroup(activeTab)
                                                ? 'bg-blue-500/20 text-white'
                                                : 'text-gray-400 hover:text-white hover:bg-white/5'
                                        ]"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                                            <path stroke-linecap="round" stroke-linejoin="round" :d="tab.icon" />
                                        </svg>
                                        {{ tab.label }}
                                    </button>

                                    <!-- Profile sub-tabs: Creator & Customize (always expanded) -->
                                    <div class="ml-3 pl-3 border-l border-white/15 space-y-0.5 pb-1">
                                    <template v-for="subTab in filteredProfileSubTabs" :key="subTab.id">
                                        <button
                                            @click="switchTab(subTab.id)"
                                            :class="[
                                                'w-full flex items-center gap-2 px-2.5 py-1.5 rounded-md text-sm font-medium transition-all text-left',
                                                activeTab === subTab.id
                                                    ? 'bg-white/10 text-white'
                                                    : 'text-gray-300 hover:text-white hover:bg-white/5'
                                            ]"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3.5 h-3.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" :d="subTab.icon" />
                                            </svg>
                                            {{ subTab.label }}
                                        </button>
                                        <!-- Creator sub-sections (always visible) -->
                                        <template v-if="subTab.id === 'creator'">
                                            <button v-for="sub in creatorSections" :key="sub.id"
                                                @click="switchTab('creator'); nextTick(() => scrollToSection(sub.id))"
                                                :class="[
                                                    'w-full text-left pl-8 pr-3 py-1 rounded-md text-xs transition-all',
                                                    activeTab === 'creator' ? 'text-gray-300 hover:text-white hover:bg-white/5' : 'text-gray-400 hover:text-gray-200 hover:bg-white/5'
                                                ]">
                                                {{ sub.label }}
                                            </button>
                                        </template>
                                        <div v-if="subTab.id === 'creator'" class="my-1.5 border-t border-white/10 mx-1"></div>
                                        <!-- Customize sub-sections (always visible) -->
                                        <template v-if="subTab.id === 'customize'">
                                            <button v-for="sub in customizeSections" :key="sub.id"
                                                @click="switchTab('customize'); nextTick(() => scrollToSection(sub.id))"
                                                :class="[
                                                    'w-full text-left pl-8 pr-3 py-1 rounded-md text-xs transition-all',
                                                    activeTab === 'customize' ? 'text-gray-300 hover:text-white hover:bg-white/5' : 'text-gray-400 hover:text-gray-200 hover:bg-white/5'
                                                ]">
                                                {{ sub.label }}
                                            </button>
                                        </template>
                                        <div v-if="subTab.id === 'customize'" class="my-1.5 border-t border-white/10 mx-1"></div>
                                    </template>
                                </div>
                                </div>
                            </template>

                            <!-- Global Customize with sub-sections -->
                            <template v-else-if="tab.id === 'global-customize'">
                                <div :class="['rounded-lg transition-all', activeTab === 'global-customize' ? 'bg-white/[0.07] border border-white/10' : '']">
                                    <button
                                        @click="switchTab('global-customize')"
                                        :class="[
                                            'w-full flex items-center gap-2.5 px-3 py-2 rounded-lg text-sm font-medium transition-all text-left',
                                            activeTab === 'global-customize'
                                                ? 'bg-blue-500/20 text-white'
                                                : 'text-gray-400 hover:text-white hover:bg-white/5'
                                        ]"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                                            <path stroke-linecap="round" stroke-linejoin="round" :d="tab.icon" />
                                        </svg>
                                        {{ tab.label }}
                                    </button>
                                    <div v-if="activeTab === 'global-customize'" class="ml-3 pl-3 border-l border-white/15 space-y-0.5 pb-1">
                                        <button v-for="sub in globalCustomizeSections" :key="sub.id"
                                            @click="scrollToSection(sub.id)"
                                            class="w-full text-left pl-5 pr-3 py-1 rounded-md text-xs transition-all text-gray-300 hover:text-white hover:bg-white/5">
                                            {{ sub.label }}
                                        </button>
                                    </div>
                                </div>
                            </template>

                            <!-- Regular tabs (Marketplace, Notifications, Security) -->
                            <template v-else>
                                <button
                                    @click="switchTab(tab.id)"
                                    :class="[
                                        'w-full flex items-center gap-2.5 px-3 py-2 rounded-lg text-sm font-medium transition-all text-left',
                                        activeTab === tab.id
                                            ? 'bg-blue-500/20 text-white'
                                            : 'text-gray-400 hover:text-white hover:bg-white/5'
                                    ]"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                                        <path stroke-linecap="round" stroke-linejoin="round" :d="tab.icon" />
                                    </svg>
                                    {{ tab.label }}
                                </button>
                            </template>
                        </template>
                    </nav>
                </div>

                <!-- Right Content Area -->
                <div class="flex-1 space-y-4">

                <!-- ==================== PROFILE TAB ==================== -->
                <template v-if="activeTab === 'profile'">

            <!-- Link MDD Account Banner -->
            <a v-if="isVerified && !user.mdd_id" href="/link-account" class="group block rounded-xl overflow-hidden mb-4 relative">
                <div class="absolute inset-0 rounded-xl bg-gradient-to-r from-blue-500 via-purple-500 to-blue-500 animate-gradient-shift"></div>
                <div class="relative m-[2px] rounded-[10px] bg-gray-900/95 px-5 py-4 flex items-center gap-4 group-hover:bg-gray-900/80 transition-colors">
                    <div class="shrink-0 w-12 h-12 rounded-full bg-gradient-to-br from-blue-500/30 to-purple-500/30 border border-blue-400/40 flex items-center justify-center animate-pulse">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6 text-blue-400">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244" />
                        </svg>
                    </div>
                    <div class="flex-1">
                        <p class="text-white font-bold text-lg">{{ $t('Link your mDd Account') }}</p>
                        <p class="text-gray-400 text-sm">{{ $t('Connect your Q3DF.org profile to unlock records, rankings, tournaments and more.') }}</p>
                    </div>
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-6 h-6 text-gray-400 group-hover:text-white group-hover:translate-x-1 transition-all shrink-0">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                    </svg>
                </div>
            </a>

            <div class="space-y-4">
                <!-- Profile Card (horizontal) -->
                <div class="rounded-xl bg-black/40 backdrop-blur-sm border border-white/10">
                    <div class="p-4">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center gap-2">
                                <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-blue-500/20 to-blue-600/20 border border-blue-500/30 flex items-center justify-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 text-blue-400">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                                    </svg>
                                </div>
                                <h2 class="text-sm font-bold text-white">{{ $t('Profile') }}</h2>
                            </div>
                            <div class="flex items-center gap-2">
                                <div v-if="profileForm.recentlySuccessful" class="flex items-center gap-1.5 px-2 py-1 rounded bg-green-500/10 border border-green-500/20">
                                    <svg class="w-3 h-3 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                    <span class="text-xs font-medium text-green-400">{{ $t('Saved') }}</span>
                                </div>
                                <PrimaryButton type="button" @click="updateProfile" :disabled="profileForm.processing">
                                    {{ $t('Save') }}
                                </PrimaryButton>
                            </div>
                        </div>

                        <form @submit.prevent="updateProfile" class="grid grid-cols-2 md:grid-cols-4 gap-3" data-lpignore="true" data-1p-ignore data-bwignore>
                            <div>
                                <InputLabel for="username" :value="$t('Username')" />
                                <TextInput
                                    id="username"
                                    type="text"
                                    :value="user.username"
                                    class="mt-1 block w-full"
                                    disabled
                                />
                            </div>

                            <div>
                                <InputLabel for="email" :value="$t('Email')" />
                                <TextInput
                                    id="email"
                                    v-model="profileForm.email"
                                    type="email"
                                    class="mt-1 block w-full"
                                />
                            </div>

                            <div>
                                <InputLabel for="name">
                                    {{ $t('Display Name') }} <span v-html="'(' + q3tohtml(profileForm.name) + ')'"></span>
                                </InputLabel>
                                <TextInput
                                    id="name"
                                    v-model="profileForm.name"
                                    type="text"
                                    class="mt-1 block w-full"
                                    placeholder="^1Red^2Green"
                                />
                            </div>

                            <div>
                                <InputLabel for="country" :value="$t('Country')" />
                                <CountrySelect :setCountry="setCountry" :selectedCountry="profileForm.country" class="mt-1" />
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Connections (full width) -->
                <div v-if="isVerified" class="rounded-xl bg-black/40 backdrop-blur-sm border border-white/10 overflow-hidden">
                    <UpdateSocialMediaForm :user="user" />
                </div>
            </div>

            <!-- Profile Images Card -->
            <div v-if="isVerified" class="rounded-xl bg-black/40 backdrop-blur-sm border border-white/10">
                <div class="p-4">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-cyan-500/20 to-cyan-600/20 border border-cyan-500/30 flex items-center justify-center">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 text-cyan-400">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                                </svg>
                            </div>
                            <h2 class="text-sm font-bold text-white">{{ $t('Profile Images') }}</h2>
                        </div>
                        <div class="flex items-center gap-2">
                            <div v-if="profileForm.recentlySuccessful || backgroundForm.recentlySuccessful" class="flex items-center gap-1.5 px-2 py-1 rounded bg-green-500/10 border border-green-500/20">
                                <svg class="w-3 h-3 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                <span class="text-xs font-medium text-green-400">{{ $t('Saved') }}</span>
                            </div>
                            <PrimaryButton type="button" @click="updateProfile" :disabled="profileForm.processing">
                                {{ $t('Save') }}
                            </PrimaryButton>
                        </div>
                    </div>

                    <div v-if="isVerified" class="grid grid-cols-2 gap-6">
                        <!-- Avatar Upload -->
                        <div>
                            <input ref="photoInput" type="file" class="hidden" @change="updatePhotoPreview" accept="image/*" />
                            <InputLabel :value="$t('Avatar')" class="text-sm font-medium mb-2" />
                            <div class="text-xs text-gray-400 mb-4">{{ $t('Max 1MB. GIF supported.') }}</div>
                            <div class="flex flex-col items-center gap-4">
                                <div v-if="photoPreview" class="shrink-0">
                                    <img :src="photoPreview" class="rounded-full h-32 w-32 object-cover border-2 border-white/20 shadow-lg">
                                </div>
                                <div v-else-if="user.profile_photo_path" class="shrink-0">
                                    <img :src="'/storage/' + user.profile_photo_path" class="rounded-full h-32 w-32 object-cover border-2 border-white/20 shadow-lg">
                                </div>
                                <div v-else class="shrink-0 w-32 h-32 rounded-full bg-gray-700 border-2 border-dashed border-white/20 flex items-center justify-center">
                                    <span class="text-xs text-gray-500">{{ $t('No avatar') }}</span>
                                </div>
                                <div class="flex gap-2">
                                    <button type="button" @click="selectNewPhoto" class="px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white text-sm font-semibold rounded-md transition">
                                        {{ $t('Change') }}
                                    </button>
                                    <button v-if="user.profile_photo_path" type="button" @click="deletePhoto" class="px-4 py-2 bg-red-600 hover:bg-red-500 text-white text-sm font-semibold rounded-md transition">
                                        {{ $t('Remove') }}
                                    </button>
                                </div>
                            </div>
                            <InputError :message="profileForm.errors.photo" class="mt-2" />
                        </div>

                        <!-- Background Upload -->
                        <div>
                            <input ref="backgroundInput" type="file" class="hidden" @change="updateBackgroundPreview" accept="image/*" />
                            <InputLabel :value="$t('Background')" class="text-sm font-medium mb-2" />
                            <div class="text-xs text-gray-400 mb-4">{{ $t('Recommended: 1920x400px. Max 5MB. GIF supported.') }}</div>
                            <div class="space-y-4">
                                <div v-if="backgroundPreview || user.profile_background_path" class="w-full h-32 rounded-lg overflow-hidden border-2 border-white/20 shadow-lg">
                                    <img :src="backgroundPreview || '/storage/' + user.profile_background_path" class="w-full h-full object-cover" />
                                </div>
                                <div v-else class="w-full h-32 rounded-lg bg-gray-700 border-2 border-dashed border-white/20 flex items-center justify-center">
                                    <span class="text-xs text-gray-500">{{ $t('No background image') }}</span>
                                </div>
                                <div class="flex gap-2">
                                    <button type="button" @click="selectNewBackground" class="px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white text-sm font-semibold rounded-md transition">
                                        {{ $t('Change') }}
                                    </button>
                                    <button v-if="user.profile_background_path" type="button" @click="deleteBackground" class="px-4 py-2 bg-red-600 hover:bg-red-500 text-white text-sm font-semibold rounded-md transition">
                                        {{ $t('Remove') }}
                                    </button>
                                </div>
                            </div>
                            <InputError :message="backgroundForm.errors.background" class="mt-2" />
                        </div>
                    </div>

                    <!-- Background Cropper -->
                    <div v-if="showBackgroundCropper" class="mt-6 pt-6 border-t border-white/10">
                        <div class="bg-black rounded-lg overflow-hidden border border-white/10" style="height: 400px;">
                            <Cropper
                                ref="backgroundCropper"
                                :src="tempBackgroundSrc"
                                :stencil-props="{
                                    aspectRatio: 4.8,
                                    movable: true,
                                    resizable: true
                                }"
                                :canvas="{
                                    maxWidth: 1920,
                                    maxHeight: 400
                                }"
                                class="cropper"
                            />
                        </div>
                        <div class="flex gap-2 mt-4">
                            <PrimaryButton type="button" @click="handleBackgroundCrop">
                                {{ $t('Crop & Upload') }}
                            </PrimaryButton>
                            <SecondaryButton type="button" @click="cancelBackgroundCrop">
                                {{ $t('Cancel') }}
                            </SecondaryButton>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Player Aliases -->
            <div v-if="isVerified" class="rounded-xl bg-black/40 backdrop-blur-sm border border-white/10 overflow-hidden max-h-[400px] overflow-y-auto mt-4">
                <ManageAliasesForm :user="user" />
            </div>

            <!-- About Me Card -->
            <div class="rounded-xl bg-black/40 backdrop-blur-sm border border-white/10 mt-4">
                <div class="p-4">
                    <div class="flex items-center gap-2 mb-3">
                        <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-amber-500/20 to-yellow-600/20 border border-amber-500/30 flex items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 text-amber-400">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 0 1 .865-.501 48.172 48.172 0 0 0 3.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0 0 12 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018Z" />
                            </svg>
                        </div>
                        <div>
                            <h2 class="text-sm font-bold text-white">{{ $t('About Me') }}</h2>
                            <p class="text-xs text-gray-500">{{ $t('Visible on hover over your name on your profile. All changes reviewed by moderators.') }}</p>
                        </div>
                    </div>
                    <div>
                        <EnglishOnlyNotice />
                        <textarea v-model="aboutMeForm.content"
                            class="w-full bg-gray-800/60 border border-gray-700/50 rounded-lg p-3 text-sm text-white placeholder-gray-600 focus:border-amber-500/50 focus:outline-none resize-none"
                            rows="3" maxlength="500"
                            :placeholder="$t('Write something about yourself...')"></textarea>
                        <div class="flex items-center justify-between mt-2">
                            <span class="text-[10px] text-gray-600">{{ aboutMeForm.content?.length || 0 }}/500</span>
                            <button @click="submitAboutMeSettings" :disabled="aboutMeForm.processing || !aboutMeForm.content?.trim()"
                                class="px-4 py-1.5 bg-amber-600 hover:bg-amber-500 disabled:bg-gray-700 disabled:text-gray-500 text-white text-xs font-bold rounded-lg transition-colors">
                                {{ aboutMeForm.processing ? $t('Submitting...') : $t('Submit for Review') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

                </template>

                <!-- ==================== CREATOR TAB ==================== -->
                <template v-if="activeTab === 'creator'">

            <!-- Intro -->
            <div class="rounded-xl bg-gradient-to-br from-green-500/5 via-green-600/10 to-green-500/5 backdrop-blur-sm border border-green-500/20 mb-4">
                <div class="p-5">
                    <div class="flex items-start gap-4">
                        <div class="w-12 h-12 rounded-xl bg-green-500/20 border border-green-500/30 flex items-center justify-center flex-shrink-0">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-green-400">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 6.75V15m6-6v8.25m.503 3.498l4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934c-.317.159-.69.159-1.006 0L9.503 3.252a1.125 1.125 0 00-1.006 0L3.622 5.689C3.24 5.88 3 6.27 3 6.695V19.18c0 .836.88 1.38 1.628 1.006l3.869-1.934c.317-.159.69-.159 1.006 0l4.994 2.497c.317.158.69.158 1.006 0z" />
                            </svg>
                        </div>
                        <div>
                            <h2 class="text-lg font-black text-white mb-1">{{ $t('Creator Profile') }}</h2>
                            <p class="text-sm text-gray-400 leading-relaxed">
                                {{ $t('Link your mapper or model author names to your account. A') }} <span class="text-green-400 font-bold">{{ $t('Mapper') }}</span> {{ $t('and/or') }} <span class="text-blue-400 font-bold">{{ $t('Modeler') }}</span> {{ $t('tab will appear on your profile showcasing your work.') }}
                            </p>
                            <div class="mt-3 space-y-1.5 text-xs text-gray-500">
                                <div class="flex items-start gap-2">
                                    <svg class="w-4 h-4 text-green-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                    <span>{{ $t('Your claimed name matches maps where the author field contains your name as a') }} <span class="text-gray-300">{{ $t('standalone word') }}</span>{{ $t('. Collaboration separators (spaces, &, -, +, @, commas, etc.) are recognized - e.g. claiming') }} <span class="text-orange-400">"Foo"</span> <span class="text-gray-300">{{ $t('will match "Foo", "Foo & Bar", or "Foo@Bar", but') }}</span> <span class="text-red-400">{{ $t('NOT') }}</span> <span class="text-gray-300">{{ $t('"SgtFoo" or "Foobar"') }}</span></span>
                                </div>
                                <div class="flex items-start gap-2">
                                    <svg class="w-4 h-4 text-green-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                    <span>{{ $t('If another user claims a more specific name (e.g.') }} <span class="text-orange-400">"Foo@Bar"</span>{{ $t('), those maps') }} <span class="text-gray-300">{{ $t('automatically move') }}</span> {{ $t("to their profile since it's a more precise match") }}</span>
                                </div>
                                <div class="flex items-start gap-2">
                                    <svg class="w-4 h-4 text-green-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                    <span>{{ $t('New maps added to the database are') }} <span class="text-gray-300">{{ $t('automatically included') }}</span> {{ $t('- no need to re-claim') }}</span>
                                </div>
                                <div class="flex items-start gap-2">
                                    <svg class="w-4 h-4 text-green-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                    <span>{{ $t("You can claim multiple names if you've published under different aliases") }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ===== YOUR CREATOR NAMES ===== -->
            <div id="creator-names">

                <!-- Claims list + Add form -->
                <div class="rounded-xl bg-black/40 backdrop-blur-sm border border-white/10">
                    <div class="p-4">
                        <!-- Add New Claim (at the top) -->
                        <div class="bg-black/20 rounded-lg border border-white/5 p-4 mb-4">
                            <div class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3">{{ $t('Add Creator Name') }}</div>
                            <div class="flex items-center gap-1 mb-3">
                                <button @click="newClaimType = 'map'; previewClaim()" type="button"
                                    class="px-4 py-1.5 rounded-lg text-xs font-bold transition-all"
                                    :class="newClaimType === 'map' ? 'bg-green-600/30 text-green-400 border border-green-500/40' : 'bg-black/30 text-gray-500 border border-white/5 hover:text-gray-300'">
                                    {{ $t('Map Author') }}
                                </button>
                                <button @click="newClaimType = 'model'; previewClaim()" type="button"
                                    class="px-4 py-1.5 rounded-lg text-xs font-bold transition-all"
                                    :class="newClaimType === 'model' ? 'bg-blue-600/30 text-blue-400 border border-blue-500/40' : 'bg-black/30 text-gray-500 border border-white/5 hover:text-gray-300'">
                                    {{ $t('Model Author') }}
                                </button>
                            </div>
                            <div class="flex items-center gap-2">
                                <input v-model="newClaimName" type="text" :placeholder="$t('Type author name to search...')"
                                    @input="previewClaim"
                                    @keyup.enter="addMapperClaim"
                                    class="flex-1 px-3 py-2.5 rounded-lg bg-black/40 border border-white/10 text-sm text-white placeholder-gray-500 focus:border-green-500/50 focus:outline-none">
                                <button @click="addMapperClaim"
                                    :disabled="!newClaimName.trim() || (claimPreview && claimPreview.count === 0) || claimPreview?.claimed_by"
                                    class="px-5 py-2.5 rounded-lg text-sm font-bold transition disabled:opacity-30 disabled:cursor-not-allowed"
                                    :class="claimPreview && claimPreview.count > 0 && !claimPreview.claimed_by ? 'bg-green-600 hover:bg-green-500 text-white' : 'bg-gray-700 text-gray-400'">
                                    {{ $t('Add') }}
                                </button>
                            </div>
                            <div v-if="loadingPreview" class="mt-3 flex items-center gap-2 text-xs text-gray-500">
                                <div class="animate-spin rounded-full h-3 w-3 border-t border-b border-green-500"></div>
                                {{ $t('Searching...') }}
                            </div>
                            <div v-else-if="claimPreview" class="mt-3">
                                <div v-if="claimPreview.claimed_by" class="mb-2 p-3 bg-yellow-500/10 border border-yellow-500/20 rounded-lg">
                                    <div class="flex items-center justify-between gap-2">
                                        <div class="flex items-center gap-2 text-sm text-yellow-400">
                                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" /></svg>
                                            <span>{{ $t('Already claimed by') }}
                                                <a :href="`/profile/${claimPreview.claimed_by.user_id}`" class="font-bold hover:underline inline" v-html="q3tohtml(claimPreview.claimed_by.user_name)"></a>
                                            </span>
                                        </div>
                                        <button v-if="!reportSent" @click="showReportForm = !showReportForm"
                                            class="text-xs px-3 py-1.5 rounded-lg bg-red-500/20 hover:bg-red-500/30 text-red-400 font-medium transition shrink-0 border border-red-500/20">
                                            {{ $t('Dispute Claim') }}
                                        </button>
                                        <span v-else class="text-xs text-green-400 font-medium shrink-0">{{ $t('Reported') }}</span>
                                    </div>
                                    <div v-if="showReportForm && !reportSent" class="mt-3 pt-3 border-t border-yellow-500/20">
                                        <p class="text-xs text-gray-400 mb-2">{{ $t('If you believe this name was claimed incorrectly, describe why you are the rightful author:') }}</p>
                                        <textarea v-model="reportReason" rows="2" :placeholder="$t('e.g. I am the original author...')"
                                            class="w-full bg-black/30 border border-white/10 text-white rounded-lg px-3 py-2 text-xs focus:outline-none focus:ring-1 focus:ring-red-500 placeholder-gray-500 mb-2"></textarea>
                                        <div v-if="reportError" class="text-xs text-red-400 mb-2">{{ reportError }}</div>
                                        <div class="flex items-center gap-2">
                                            <button @click="reportClaim" :disabled="reportingClaim"
                                                class="px-3 py-1.5 bg-red-600 hover:bg-red-500 disabled:bg-gray-700 text-white text-xs font-medium rounded-lg transition">
                                                {{ reportingClaim ? $t('Submitting...') : $t('Submit Dispute') }}
                                            </button>
                                            <button @click="showReportForm = false" class="px-3 py-1.5 bg-white/5 hover:bg-white/10 text-gray-400 text-xs rounded-lg transition">{{ $t('Cancel') }}</button>
                                        </div>
                                    </div>
                                </div>
                                <div v-if="claimPreview.count === 0" class="text-xs text-red-400 flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                    {{ newClaimType === 'model' ? $t('No models found with this author name') : $t('No maps found with this author name') }}
                                </div>
                                <div v-else>
                                    <div class="text-xs font-bold mb-2 flex items-center gap-1.5" :class="claimPreview.claimed_by ? 'text-gray-400' : 'text-green-400'">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                        {{ newClaimType === 'model' ? $tc(':count model found|:count models found', claimPreview.count) : $tc(':count map found|:count maps found', claimPreview.count) }}
                                    </div>
                                    <!-- Map previews -->
                                    <div v-if="claimPreview.maps?.length" class="grid grid-cols-4 gap-2">
                                        <div v-for="map in claimPreview.maps" :key="map.name"
                                            class="bg-black/30 rounded overflow-hidden border border-white/5">
                                            <div class="h-16 bg-cover bg-center" :style="`background-image: url('/storage/${map.thumbnail}')`"></div>
                                            <div class="px-2 py-1.5">
                                                <div class="text-[11px] font-bold text-white truncate">{{ map.name }}</div>
                                                <div class="text-[10px] text-gray-500 truncate">{{ $t('by :author', { author: map.author }) }}</div>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Model previews -->
                                    <div v-if="claimPreview.models?.length" class="grid grid-cols-4 gap-2">
                                        <div v-for="model in claimPreview.models" :key="model.name"
                                            class="bg-black/30 rounded overflow-hidden border border-white/5">
                                            <div class="h-16 bg-cover bg-center" :style="`background-image: url('/storage/${model.thumbnail}')`"></div>
                                            <div class="px-2 py-1.5">
                                                <div class="text-[11px] font-bold text-white truncate">{{ model.name }}</div>
                                                <div class="text-[10px] text-gray-500 truncate">{{ $t('by :author', { author: model.author }) }}</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div v-if="claimPreview.count > 8" class="text-[10px] text-gray-500 mt-1.5">
                                        {{ $t('...and :count more', { count: claimPreview.count - 8 }) }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Existing Claims -->
                        <div v-if="loadingMapperClaims" class="flex items-center justify-center py-4">
                            <div class="animate-spin rounded-full h-6 w-6 border-t-2 border-b-2 border-green-500"></div>
                        </div>
                        <div v-else-if="mapperClaims.length" class="space-y-2 mt-4">
                            <div class="flex items-center justify-between mb-2">
                                <h4 class="text-xs font-bold text-gray-400 uppercase tracking-wider">{{ $t('Your Claims') }}</h4>
                                <div class="flex items-center gap-2">
                                    <div v-if="mapperClaimsSaved" class="flex items-center gap-1.5 px-2 py-1 rounded bg-green-500/10 border border-green-500/20">
                                        <svg class="w-3 h-3 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                        <span class="text-xs font-medium text-green-400">{{ $t('Saved') }}</span>
                                    </div>
                                    <PrimaryButton type="button" @click="saveMapperClaims" :disabled="savingMapperClaims" class="text-xs">{{ $t('Save') }}</PrimaryButton>
                                </div>
                            </div>
                            <div v-for="(claim, idx) in mapperClaims" :key="idx"
                                class="flex items-center gap-3 px-4 py-3 bg-black/30 rounded-lg border border-white/5">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm font-bold text-white">{{ claim.name }}</span>
                                        <span class="text-xs font-bold px-2 py-0.5 rounded"
                                            :class="claim.type === 'map' ? 'bg-green-500/20 text-green-400 border border-green-500/30' : 'bg-blue-500/20 text-blue-400 border border-blue-500/30'">
                                            {{ claim.type }}
                                        </span>
                                    </div>
                                    <div v-if="claim.matching_count !== undefined" class="text-xs text-gray-500 mt-0.5">
                                        <span class="text-green-400 font-bold">{{ claim.matching_count }}</span> {{ claim.type === 'model' ? $t('matching models') : $t('matching maps') }}
                                        <span v-if="claim.exclusions_count" class="text-yellow-400 ml-1">({{ $t(':count excluded', { count: claim.exclusions_count }) }})</span>
                                    </div>
                                </div>
                                <button @click="removeMapperClaim(idx)" class="text-red-400 hover:text-red-300 transition p-1.5 rounded hover:bg-red-500/10 flex-shrink-0">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                        <div v-else-if="!loadingMapperClaims" class="text-center py-4 text-sm text-gray-500 mt-4">
                            {{ $t('No creator names claimed yet') }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- ===== MAP SELECTOR ===== -->
            <div id="creator-maps" class="mt-4">
                <div class="rounded-xl bg-black/40 backdrop-blur-sm border border-white/10">
                    <div class="p-4">
                        <h3 class="text-sm font-bold text-white mb-1">{{ $t('Map Selector') }}</h3>
                        <p class="text-xs text-gray-500 mb-4">{{ $t('Include or exclude specific maps from your Mapper profile tab. Click a map to toggle it.') }}</p>

                        <div v-if="!mapperClaims.filter(c => c.type === 'map').length" class="text-center py-8 text-sm text-gray-500">
                            {{ $t('No map author claims yet. Add one in the "Your Creator Names" tab first.') }}
                        </div>

                        <div v-for="(claim, idx) in mapperClaims.filter(c => c.type === 'map')" :key="idx" class="mb-4 last:mb-0">
                            <div class="flex items-center gap-2 mb-3">
                                <span class="text-xs font-bold text-green-400">{{ claim.name }}</span>
                                <div v-if="claim.id && claimMapsById[claim.id]" class="text-xs text-gray-400">
                                    <span class="text-green-400 font-bold">{{ (claimMapsById[claim.id] || []).filter(m => !m.excluded).length }}</span> {{ $t('included,') }}
                                    <span class="text-red-400 font-bold">{{ (claimMapsById[claim.id] || []).filter(m => m.excluded).length }}</span> {{ $t('excluded') }}
                                </div>
                                <input v-if="claim.id" v-model="claimSearches[claim.id]" @input="searchClaimMaps(claim.id)" type="text" :placeholder="$t('Search...')"
                                    class="ml-auto px-3 py-1.5 rounded-lg bg-black/40 border border-white/10 text-xs text-white placeholder-gray-500 focus:border-blue-500/50 focus:outline-none w-48">
                            </div>

                            <div v-if="claim.id && loadingClaimMapsById[claim.id]" class="flex items-center justify-center py-8">
                                <div class="animate-spin rounded-full h-5 w-5 border-t-2 border-b-2 border-blue-500"></div>
                            </div>

                            <div v-else-if="claim.id && claimMapsById[claim.id]" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-2 max-h-[500px] overflow-y-auto pr-1">
                                <div v-for="map in claimMapsById[claim.id]" :key="map.id"
                                    @click="toggleExclusion(claim.id, map.id)"
                                    class="relative rounded-lg overflow-hidden cursor-pointer transition-all group"
                                    :class="map.excluded
                                        ? 'ring-2 ring-red-500/60 opacity-50 hover:opacity-70'
                                        : 'ring-2 ring-green-500/40 hover:ring-green-400/60'">
                                    <div class="h-20 bg-cover bg-center" :style="`background-image: url('/storage/${map.thumbnail}')`">
                                        <div class="absolute top-1.5 right-1.5">
                                            <div v-if="map.excluded" class="w-6 h-6 rounded-full bg-red-500 flex items-center justify-center shadow-lg">
                                                <svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12" /></svg>
                                            </div>
                                            <div v-else class="w-6 h-6 rounded-full bg-green-500 flex items-center justify-center shadow-lg">
                                                <svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg>
                                            </div>
                                        </div>
                                        <div class="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 transition"
                                            :class="map.excluded ? 'bg-green-900/60' : 'bg-red-900/60'">
                                            <span v-if="map.excluded" class="text-xs font-bold text-green-300 bg-black/50 px-3 py-1 rounded-full">{{ $t('Click to Include') }}</span>
                                            <span v-else class="text-xs font-bold text-red-300 bg-black/50 px-3 py-1 rounded-full">{{ $t('Click to Exclude') }}</span>
                                        </div>
                                    </div>
                                    <div class="px-2 py-1.5" :class="map.excluded ? 'bg-red-950/40' : 'bg-black/60'">
                                        <div class="text-[11px] font-bold truncate" :class="map.excluded ? 'text-red-300 line-through' : 'text-white'">{{ map.name }}</div>
                                        <div class="text-[10px] text-gray-500 truncate">{{ map.author }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ===== PINNED MODELS ===== -->
            <div id="creator-pinned" class="mt-4">
                <div class="rounded-xl bg-black/40 backdrop-blur-sm border border-white/10">
                    <div class="p-4">
                        <div class="mb-3">
                            <div class="flex items-center justify-between">
                                <h3 class="text-sm font-bold text-white">{{ $t('Pinned Models') }}</h3>
                                <div class="flex items-center gap-2 flex-shrink-0">
                                    <div v-if="pinnedModelsSaved" class="flex items-center gap-1 px-2 py-0.5 rounded bg-green-500/10 border border-green-500/20">
                                        <svg class="w-3 h-3 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                        <span class="text-[10px] text-green-400">{{ $t('Saved') }}</span>
                                    </div>
                                    <span class="text-xs text-gray-400"><span class="text-blue-400 font-bold">{{ pinnedModelIds.length }}</span>{{ $t('/2 pinned') }}</span>
                                </div>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">{{ $t('Select up to 2 models to feature in an interactive 3D viewer at the top of your Modeler profile tab. The first pinned model appears on the left, the second on the right.') }}</p>
                        </div>

                        <div v-if="!mapperClaims.some(c => c.type === 'model')" class="text-center py-8 text-sm text-gray-500">
                            {{ $t('No model author claims yet. Add one in the "Your Creator Names" tab first.') }}
                        </div>

                        <template v-else-if="allCreatorModels.length > 0">
                            <!-- Current pins summary -->
                            <div class="flex items-center gap-4 mb-3 p-2 rounded-lg bg-blue-950/20 border border-blue-500/10" style="min-height: 36px;">
                                <span v-if="pinnedModelIds.length === 0" class="text-[10px] text-gray-600 mx-auto">{{ $t('No models pinned yet. Hover left/right on a model to pin it.') }}</span>
                                <div v-for="(id, idx) in pinnedModelIds" :key="id" class="flex items-center gap-1.5">
                                    <span class="text-[10px] font-bold uppercase" :class="idx === 0 ? 'text-cyan-400' : 'text-orange-400'">{{ idx === 0 ? $t('Left') : $t('Right') }}:</span>
                                    <span class="text-[10px] font-bold text-blue-300">{{ allCreatorModels.find(m => m.id === id)?.name || '?' }}</span>
                                    <button @click.stop="unpinModel(id)" class="text-red-400 hover:text-red-300 ml-0.5" :title="$t('Unpin')">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                    </button>
                                </div>
                                <button v-if="pinnedModelIds.length === 2" @click.stop="pinnedModelIds.reverse(); savePinnedModels()" class="text-gray-400 hover:text-white ml-auto text-[10px] font-bold flex items-center gap-1 px-2 py-1 rounded bg-white/5 hover:bg-white/10 transition" :title="$t('Swap positions')">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4" /></svg>
                                    {{ $t('Swap') }}
                                </button>
                            </div>

                            <!-- Single grid with split-hover per model -->
                            <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-6 gap-2 max-h-[400px] overflow-y-auto pr-1">
                                <div v-for="model in allCreatorModels" :key="model.id"
                                    class="relative rounded-lg overflow-hidden transition-all"
                                    :class="[
                                        getPinSide(model.id) === 'L' ? 'ring-2 ring-cyan-500/60' :
                                        getPinSide(model.id) === 'R' ? 'ring-2 ring-orange-500/60' :
                                        'ring-1 ring-white/5'
                                    ]">
                                    <div class="aspect-square bg-gradient-to-br from-blue-500/10 to-purple-500/10 flex items-center justify-center overflow-hidden relative">
                                        <img v-if="model.idle_gif || model.thumbnail"
                                            :src="`/storage/${model.idle_gif || model.thumbnail}`"
                                            :alt="model.name"
                                            class="w-full h-full object-cover"
                                            loading="lazy">
                                        <span v-else class="text-2xl">{{ model.category === 'player' ? '&#x1F3C3;' : '&#x1F52B;' }}</span>

                                        <!-- Left half hover zone -->
                                        <div @click="pinModelAs(model.id, 'left')"
                                            class="absolute inset-y-0 left-0 w-1/2 cursor-pointer group/left z-10">
                                            <div class="absolute inset-0 flex items-center justify-center opacity-0 group-hover/left:opacity-100 transition"
                                                :class="getPinSide(model.id) === 'L' ? 'bg-red-900/60' : 'bg-cyan-900/60'">
                                                <span class="text-[10px] font-black px-1.5 py-0.5 rounded-full bg-black/50"
                                                    :class="getPinSide(model.id) === 'L' ? 'text-red-300' : 'text-cyan-300'">
                                                    {{ getPinSide(model.id) === 'L' ? $t('UNPIN') : $t('LEFT') }}
                                                </span>
                                            </div>
                                        </div>

                                        <!-- Right half hover zone -->
                                        <div @click="pinModelAs(model.id, 'right')"
                                            class="absolute inset-y-0 right-0 w-1/2 cursor-pointer group/right z-10">
                                            <div class="absolute inset-0 flex items-center justify-center opacity-0 group-hover/right:opacity-100 transition"
                                                :class="getPinSide(model.id) === 'R' ? 'bg-red-900/60' : 'bg-orange-900/60'">
                                                <span class="text-[10px] font-black px-1.5 py-0.5 rounded-full bg-black/50"
                                                    :class="getPinSide(model.id) === 'R' ? 'text-red-300' : 'text-orange-300'">
                                                    {{ getPinSide(model.id) === 'R' ? $t('UNPIN') : $t('RIGHT') }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Pin badge -->
                                    <div v-if="getPinSide(model.id)" class="absolute top-1 right-1 z-20">
                                        <div class="w-5 h-5 rounded-full flex items-center justify-center shadow-lg text-[9px] font-black text-white"
                                            :class="getPinSide(model.id) === 'L' ? 'bg-cyan-500' : 'bg-orange-500'">
                                            {{ getPinSide(model.id) }}
                                        </div>
                                    </div>

                                    <div class="px-1.5 py-1" :class="getPinSide(model.id) === 'L' ? 'bg-cyan-950/40' : getPinSide(model.id) === 'R' ? 'bg-orange-950/40' : 'bg-black/60'">
                                        <div class="text-[10px] font-bold truncate" :class="getPinSide(model.id) === 'L' ? 'text-cyan-300' : getPinSide(model.id) === 'R' ? 'text-orange-300' : 'text-white'">{{ model.name }}</div>
                                        <div class="text-[9px] text-gray-600">{{ $t(':count dl', { count: model.downloads }) }}</div>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <div v-else-if="loadingCreatorModels" class="flex items-center justify-center py-8">
                            <div class="animate-spin rounded-full h-5 w-5 border-t-2 border-b-2 border-blue-500"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ===== MODEL ORDER ===== -->
            <div id="creator-order" class="mt-4">
                <div class="rounded-xl bg-black/40 backdrop-blur-sm border border-white/10">
                    <div class="p-4">
                        <div class="flex items-center justify-between mb-3">
                            <div>
                                <h3 class="text-sm font-bold text-white mb-1">{{ $t('Model Group Order') }}</h3>
                                <p class="text-xs text-gray-500">{{ $t('Drag to reorder how model groups appear on your Modeler profile tab. Groups are organized by base model name.') }}</p>
                            </div>
                            <div v-if="groupOrderSaved" class="flex items-center gap-1 px-2 py-0.5 rounded bg-green-500/10 border border-green-500/20">
                                <svg class="w-3 h-3 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                <span class="text-[10px] text-green-400">{{ $t('Saved') }}</span>
                            </div>
                        </div>

                        <div v-if="!mapperClaims.some(c => c.type === 'model')" class="text-center py-8 text-sm text-gray-500">
                            {{ $t('No model author claims yet. Add one in the "Your Creator Names" tab first.') }}
                        </div>

                        <div v-else-if="draggableGroupList.length > 0">
                            <draggable v-model="draggableGroupList" item-key="name" handle=".drag-handle" @end="onGroupDragEnd" class="space-y-1">
                                <template #item="{ element, index }">
                                    <div class="flex items-center gap-2 px-3 py-2 rounded-lg bg-black/30 border border-white/5 hover:border-white/15 transition">
                                        <div class="drag-handle cursor-grab active:cursor-grabbing text-gray-600 hover:text-gray-400 transition">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16" /></svg>
                                        </div>
                                        <span class="text-[10px] font-bold text-gray-600 w-4 text-right">{{ index + 1 }}</span>
                                        <span class="text-xs font-bold text-white flex-1">{{ element.name }}</span>
                                        <span class="text-[10px] text-gray-500">{{ $tc(':count model|:count models', element.count) }}</span>
                                    </div>
                                </template>
                            </draggable>
                        </div>

                        <div v-else-if="loadingCreatorModels" class="flex items-center justify-center py-8">
                            <div class="animate-spin rounded-full h-5 w-5 border-t-2 border-b-2 border-blue-500"></div>
                        </div>
                    </div>
                </div>
            </div>


                </template>

                <!-- ==================== MARKETPLACE TAB ==================== -->
                <template v-if="activeTab === 'marketplace'">
            <div class="rounded-xl bg-black/40 backdrop-blur-sm border border-white/10">
                <div class="p-4">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-blue-500/20 to-blue-600/20 border border-blue-500/30 flex items-center justify-center">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 text-blue-400">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 0 1 .75-.75h3a.75.75 0 0 1 .75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349M3.75 21V9.349m0 0a3.001 3.001 0 0 0 3.75-.615A2.993 2.993 0 0 0 9.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 0 0 2.25 1.016c.896 0 1.7-.393 2.25-1.015a3.001 3.001 0 0 0 3.75.614m-16.5 0a3.004 3.004 0 0 1-.621-4.72l1.189-1.19A1.5 1.5 0 0 1 5.378 3h13.243a1.5 1.5 0 0 1 1.06.44l1.19 1.189a3 3 0 0 1-.621 4.72M6.75 18h3.75a.75.75 0 0 0 .75-.75V13.5a.75.75 0 0 0-.75-.75H6.75a.75.75 0 0 0-.75.75v3.75c0 .414.336.75.75.75Z" />
                                </svg>
                            </div>
                            <h2 class="text-sm font-bold text-white">{{ $t('Marketplace Creator Profile') }}</h2>
                        </div>
                        <div v-if="mpSuccess" class="flex items-center gap-1.5 px-2 py-1 rounded bg-green-500/10 border border-green-500/20">
                            <svg class="w-3 h-3 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                            <span class="text-xs font-medium text-green-400">{{ mpSuccess }}</span>
                        </div>
                    </div>

                    <div class="text-xs text-gray-400 mb-6">
                        {{ $t('Set up your creator profile to appear in the Creator Directory. Players can browse creators and commission work directly.') }}
                    </div>

                    <div v-if="mpLoading" class="text-center py-8 text-gray-500">{{ $t('Loading...') }}</div>

                    <div v-else class="space-y-5">
                        <!-- Toggles -->
                        <div class="grid grid-cols-2 gap-4">
                            <label class="flex items-center gap-3 p-3 rounded-lg bg-black/20 border border-white/5 cursor-pointer hover:border-white/10 transition">
                                <input type="checkbox" v-model="mpProfile.is_listed" class="rounded border-gray-600 bg-black/40 text-blue-600 focus:ring-blue-500/50" />
                                <div>
                                    <div class="text-sm font-semibold text-white">{{ $t('List in Directory') }}</div>
                                    <div class="text-xs text-gray-500">{{ $t('Show your profile in the Creator Directory') }}</div>
                                </div>
                            </label>
                            <label class="flex items-center gap-3 p-3 rounded-lg bg-black/20 border border-white/5 cursor-pointer hover:border-white/10 transition">
                                <input type="checkbox" v-model="mpProfile.accepting_commissions" class="rounded border-gray-600 bg-black/40 text-green-600 focus:ring-green-500/50" />
                                <div>
                                    <div class="text-sm font-semibold text-white">{{ $t('Accepting Commissions') }}</div>
                                    <div class="text-xs text-gray-500">{{ $t('Show as available for work') }}</div>
                                </div>
                            </label>
                        </div>

                        <!-- Specialties -->
                        <div>
                            <label class="block text-sm font-bold text-white mb-2">{{ $t('Specialties') }}</label>
                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-for="spec in mpProfile.work_types"
                                    :key="spec.value"
                                    type="button"
                                    @click="toggleMpSpecialty(spec.value)"
                                    :class="mpProfile.specialties.includes(spec.value) ? 'bg-blue-600/20 border-blue-500/50 text-blue-300' : 'bg-black/20 border-white/10 text-gray-400 hover:border-white/20'"
                                    class="px-3 py-1.5 text-sm font-medium rounded-lg border transition"
                                >
                                    {{ spec.plural }}
                                </button>
                            </div>
                        </div>

                        <!-- Rates -->
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-bold text-gray-400 mb-1">{{ $t('Rate for Maps') }}</label>
                                <input
                                    v-model="mpProfile.rate_maps"
                                    type="text"
                                    :placeholder="$t('e.g., 15 EUR/hour')"
                                    class="w-full bg-black/40 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-gray-500 focus:border-blue-500/50 focus:ring-1 focus:ring-blue-500/50"
                                />
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-400 mb-1">{{ $t('Rate for Models') }}</label>
                                <input
                                    v-model="mpProfile.rate_models"
                                    type="text"
                                    :placeholder="$t('e.g., 10 EUR/hour')"
                                    class="w-full bg-black/40 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-gray-500 focus:border-blue-500/50 focus:ring-1 focus:ring-blue-500/50"
                                />
                            </div>
                        </div>

                        <!-- Bio -->
                        <div>
                            <label class="block text-xs font-bold text-gray-400 mb-1">{{ $t('Bio / Portfolio Description') }}</label>
                            <EnglishOnlyNotice />
                            <textarea
                                v-model="mpProfile.bio"
                                rows="4"
                                :placeholder="$t('Tell potential clients about your experience and work...')"
                                maxlength="2000"
                                class="w-full bg-black/40 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-gray-500 focus:border-blue-500/50 focus:ring-1 focus:ring-blue-500/50 resize-none"
                            ></textarea>
                            <div class="text-xs text-gray-500 text-right">{{ (mpProfile.bio || '').length }}/2000</div>
                        </div>

                        <!-- Featured Maps -->
                        <div>
                            <label class="block text-xs font-bold text-gray-400 mb-2">{{ $t('Featured Maps (up to 5)') }}</label>
                            <div v-if="mpProfile.featured_maps && mpProfile.featured_maps.length > 0" class="flex flex-wrap gap-2 mb-3">
                                <div
                                    v-for="map in mpProfile.featured_maps"
                                    :key="map.id"
                                    class="flex items-center gap-2 px-2 py-1 bg-black/30 border border-white/10 rounded-lg"
                                >
                                    <img
                                        :src="map.thumbnail ? `/images/thumbnails/${map.thumbnail}` : '/images/thumbnails/noimage.jpg'"
                                        class="w-8 h-5 rounded object-cover"
                                    />
                                    <span class="text-xs text-white font-medium">{{ map.name }}</span>
                                    <button @click="removeFeaturedMap(map.id)" class="text-red-400 hover:text-red-300 transition">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                    </button>
                                </div>
                            </div>
                            <div v-if="mpProfile.featured_map_ids.length < 5" class="relative">
                                <input
                                    v-model="mpMapSearch"
                                    @input="searchMapsForFeatured"
                                    type="text"
                                    :placeholder="$t('Search maps to feature...')"
                                    class="w-full bg-black/40 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-gray-500 focus:border-blue-500/50 focus:ring-1 focus:ring-blue-500/50"
                                />
                                <div v-if="mpMapResults.length > 0" class="absolute top-full left-0 right-0 mt-1 bg-gray-900 border border-white/10 rounded-lg max-h-48 overflow-y-auto z-50 shadow-2xl">
                                    <button
                                        v-for="map in mpMapResults"
                                        :key="map.id"
                                        type="button"
                                        @click="addFeaturedMap(map)"
                                        class="w-full flex items-center gap-2 px-3 py-2 text-left text-sm text-gray-300 hover:bg-white/10 transition"
                                    >
                                        <img :src="map.thumbnail ? `/images/thumbnails/${map.thumbnail}` : '/images/thumbnails/noimage.jpg'" class="w-10 h-6 rounded object-cover" />
                                        <div>
                                            <div class="text-white font-medium">{{ map.name }}</div>
                                            <div class="text-xs text-gray-500">{{ map.author }}</div>
                                        </div>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Portfolio URLs -->
                        <div>
                            <label class="block text-xs font-bold text-gray-400 mb-2">{{ $t('Portfolio Links (optional)') }}</label>
                            <div class="space-y-2">
                                <div v-for="(url, i) in (mpProfile.portfolio_urls || [])" :key="i" class="flex gap-2">
                                    <input
                                        v-model="mpProfile.portfolio_urls[i]"
                                        type="url"
                                        :placeholder="$t('https://...')"
                                        class="flex-1 bg-black/40 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-gray-500 focus:border-blue-500/50 focus:ring-1 focus:ring-blue-500/50"
                                    />
                                    <button @click="mpProfile.portfolio_urls.splice(i, 1)" class="text-red-400 hover:text-red-300 px-2">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                    </button>
                                </div>
                            </div>
                            <button
                                v-if="!mpProfile.portfolio_urls || mpProfile.portfolio_urls.length < 5"
                                @click="if (!mpProfile.portfolio_urls) mpProfile.portfolio_urls = []; mpProfile.portfolio_urls.push('')"
                                type="button"
                                class="mt-2 text-sm text-blue-400 hover:text-blue-300 transition"
                            >
                                {{ $t('+ Add link') }}
                            </button>
                        </div>

                        <!-- Save -->
                        <div class="flex justify-end pt-2">
                            <button
                                @click="saveCreatorProfile"
                                :disabled="mpSaving"
                                class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 disabled:bg-gray-700 disabled:text-gray-500 text-white text-sm font-bold rounded-lg transition"
                            >
                                {{ mpSaving ? $t('Saving...') : $t('Save Marketplace Profile') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
                </template>

                <!-- ==================== CUSTOMIZE TAB ==================== -->
                <template v-if="activeTab === 'customize'">
            <!-- Preferences Card -->
            <div id="customize-effects" class="rounded-xl bg-black/40 backdrop-blur-sm border border-white/10">
                <div class="p-4">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-purple-500/20 to-purple-600/20 border border-purple-500/30 flex items-center justify-center">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 text-purple-400">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.53 16.122a3 3 0 0 0-5.78 1.128 2.25 2.25 0 0 1-2.4 2.245 4.5 4.5 0 0 0 8.4-2.245c0-.399-.078-.78-.22-1.128Zm0 0a15.998 15.998 0 0 0 3.388-1.62m-5.043-.025a15.994 15.994 0 0 1 1.622-3.395m3.42 3.42a15.995 15.995 0 0 0 4.764-4.648l3.876-5.814a1.151 1.151 0 0 0-1.597-1.597L14.146 6.32a15.996 15.996 0 0 0-4.649 4.763m3.42 3.42a6.776 6.776 0 0 0-3.42-3.42" />
                                </svg>
                            </div>
                            <h2 class="text-sm font-bold text-white">{{ $t('Avatar & Name Effects') }}</h2>
                        </div>
                        <div class="flex items-center gap-2">
                            <div v-if="prefsForm.recentlySuccessful" class="flex items-center gap-1.5 px-2 py-1 rounded bg-green-500/10 border border-green-500/20">
                                <svg class="w-3 h-3 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                <span class="text-xs font-medium text-green-400">{{ $t('Saved') }}</span>
                            </div>
                            <PrimaryButton type="button" @click="updatePreferences" :disabled="prefsForm.processing">
                                {{ $t('Save') }}
                            </PrimaryButton>
                        </div>
                    </div>

                    <form @submit.prevent="updatePreferences" class="space-y-3">
                        <div class="grid grid-cols-2 gap-3">
                            <!-- Left: Effects & Colors -->
                            <div class="space-y-3">
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <InputLabel for="effect_color" :value="$t('Effect Color')" />
                                        <input v-model="prefsForm.color" id="effect_color" type="color" class="mt-1 w-full h-8 rounded border-2 border-grayop-700 bg-grayop-900 cursor-pointer" />
                                    </div>

                                    <div>
                                        <InputLabel for="border_color" :value="$t('Border Color')" />
                                        <input v-model="prefsForm.avatar_border_color" id="border_color" type="color" class="mt-1 w-full h-8 rounded border-2 border-grayop-700 bg-grayop-900 cursor-pointer" />
                                    </div>
                                </div>

                                <div>
                                    <InputLabel for="avatar_effect">
                                        {{ $t('Avatar Effect') }} <span class="text-xs text-purple-400">({{ avatarEffects.find(e => e.id === prefsForm.avatar_effect)?.name }})</span>
                                    </InputLabel>
                                    <div class="mt-1 grid grid-cols-8 gap-1">
                                        <button
                                            v-for="effect in avatarEffects"
                                            :key="effect.id"
                                            type="button"
                                            @click="prefsForm.avatar_effect = effect.id"
                                            :class="[
                                                'w-12 h-12 rounded border transition-all hover:scale-105',
                                                prefsForm.avatar_effect === effect.id
                                                    ? 'border-purple-500 bg-purple-500/20 shadow-md shadow-purple-500/40'
                                                    : 'border-white/10 bg-black/20 hover:border-purple-500/50'
                                            ]"
                                            :title="effect.name"
                                        >
                                            <span class="flex items-center justify-center text-2xl">{{ effect.icon }}</span>
                                        </button>
                                    </div>
                                </div>

                                <div>
                                    <InputLabel for="name_effect">
                                        {{ $t('Name Effect') }} <span class="text-xs text-pink-400">({{ nameEffects.find(e => e.id === prefsForm.name_effect)?.name }})</span>
                                    </InputLabel>
                                    <div class="mt-1 grid grid-cols-8 gap-1">
                                        <button
                                            v-for="effect in nameEffects"
                                            :key="effect.id"
                                            type="button"
                                            @click="prefsForm.name_effect = effect.id"
                                            :class="[
                                                'w-12 h-12 rounded border transition-all hover:scale-105',
                                                prefsForm.name_effect === effect.id
                                                    ? 'border-pink-500 bg-pink-500/20 shadow-md shadow-pink-500/40'
                                                    : 'border-white/10 bg-black/20 hover:border-pink-500/50'
                                            ]"
                                            :title="effect.name"
                                        >
                                            <span class="flex items-center justify-center text-2xl">{{ effect.icon }}</span>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Right: Preview -->
                            <div>
                                <InputLabel :value="$t('Preview')" />
                                <div class="mt-1 p-4 rounded-lg bg-gradient-to-br from-black/40 to-black/20 border border-white/5 h-full flex items-center justify-center">
                                    <div class="flex flex-col items-center gap-3">
                                        <div :class="'avatar-effect-' + prefsForm.avatar_effect" :style="`--effect-color: ${prefsForm.color}; --border-color: ${prefsForm.avatar_border_color}`">
                                            <img :src="user.profile_photo_path ? '/storage/' + user.profile_photo_path : '/images/null.jpg'" class="w-24 h-24 rounded-full object-cover border-4" :style="`border-color: ${prefsForm.avatar_border_color}`" />
                                        </div>
                                        <div :class="'name-effect-' + prefsForm.name_effect" :style="`--effect-color: ${prefsForm.color}`">
                                            <div class="text-lg font-bold text-white" v-html="q3tohtml(user.name)"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Effects Intensity -->
            <div id="customize-intensity">
                <EffectsIntensityForm />
            </div>

            <!-- Profile Layout Customization -->
            <div id="customize-layout">
                <ProfileLayoutForm />
            </div>

                </template>

                <!-- ==================== GLOBAL CUSTOMIZE TAB ==================== -->
                <template v-if="activeTab === 'global-customize'">
            <p class="text-sm text-gray-400 mb-4">{{ $t('These settings affect how content is displayed across the entire site.') }}</p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- Map View Defaults Card -->
            <div id="gc-map-view" class="rounded-xl bg-black/40 backdrop-blur-sm border border-white/10 transition-all duration-500">
                <div class="p-4">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-teal-500/20 to-teal-600/20 border border-teal-500/30 flex items-center justify-center">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 text-teal-400">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z" />
                                </svg>
                            </div>
                            <h2 class="text-sm font-bold text-white">{{ $t('Map Records Default View') }}</h2>
                        </div>
                        <div class="flex items-center gap-2">
                            <div v-if="mapViewForm.recentlySuccessful" class="flex items-center gap-1.5 px-2 py-1 rounded bg-green-500/10 border border-green-500/20">
                                <svg class="w-3 h-3 text-green-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                <span class="text-xs text-green-400 font-medium">{{ $t('Saved') }}</span>
                            </div>
                            <PrimaryButton type="button" @click="updateMapViewPreferences" :disabled="mapViewForm.processing">
                                <span class="text-xs">{{ $t('Save') }}</span>
                            </PrimaryButton>
                        </div>
                    </div>

                    <p class="text-xs text-gray-500 mb-3">{{ $t('Choose which record sources are shown by default on map detail pages. Online records are always visible.') }}</p>

                    <div class="space-y-2">
                        <label class="flex items-center gap-3 p-2 rounded-lg bg-white/5 border border-white/10 cursor-not-allowed opacity-60">
                            <input type="checkbox" checked disabled class="w-4 h-4 rounded bg-white/10 border-white/20 text-blue-600" />
                            <div>
                                <p class="text-sm font-medium text-gray-300">{{ $t('Online Records') }}</p>
                                <p class="text-xs text-gray-500">{{ $t('Always shown') }}</p>
                            </div>
                        </label>

                        <label class="flex items-center gap-3 p-2 rounded-lg bg-white/5 border border-white/10 hover:bg-white/10 transition-colors cursor-pointer">
                            <input v-model="mapViewForm.default_show_oldtop" type="checkbox" class="w-4 h-4 rounded bg-white/10 border-white/20 text-teal-600" />
                            <div>
                                <p class="text-sm font-medium text-gray-300">{{ $t('Oldtop Records') }}</p>
                                <p class="text-xs text-gray-500">{{ $t('Historical records from q3df.org archive') }}</p>
                            </div>
                        </label>

                        <label class="flex items-center gap-3 p-2 rounded-lg bg-white/5 border border-white/10 hover:bg-white/10 transition-colors cursor-pointer">
                            <input v-model="mapViewForm.default_show_offline" type="checkbox" class="w-4 h-4 rounded bg-white/10 border-white/20 text-teal-600" />
                            <div>
                                <p class="text-sm font-medium text-gray-300">{{ $t('Demos Top') }}</p>
                                <p class="text-xs text-gray-500">{{ $t('Records from uploaded demo files') }}</p>
                            </div>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Physics Column Order Card -->
            <div id="gc-physics-order" class="rounded-xl bg-black/40 backdrop-blur-sm border border-white/10 transition-all duration-500">
                <div class="p-4">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-blue-500/20 to-purple-600/20 border border-blue-500/30 flex items-center justify-center">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 text-blue-400">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
                                </svg>
                            </div>
                            <h2 class="text-sm font-bold text-white">{{ $t('Physics Column Order') }}</h2>
                        </div>
                        <div class="flex items-center gap-2">
                            <div v-if="physicsOrderForm.recentlySuccessful" class="flex items-center gap-1.5 px-2 py-1 rounded bg-green-500/10 border border-green-500/20">
                                <svg class="w-3 h-3 text-green-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                <span class="text-xs text-green-400 font-medium">{{ $t('Saved') }}</span>
                            </div>
                            <PrimaryButton type="button" @click="updatePhysicsOrder" :disabled="physicsOrderForm.processing">
                                <span class="text-xs">{{ $t('Save') }}</span>
                            </PrimaryButton>
                        </div>
                    </div>

                    <p class="text-xs text-gray-500 mb-3">{{ $t('Choose which physics mode appears on the left side across all pages (records, rankings, profile stats).') }}</p>

                    <div class="space-y-2">
                        <label class="flex items-center gap-3 p-2 rounded-lg bg-white/5 border border-white/10 hover:bg-white/10 transition-colors cursor-pointer" :class="physicsOrderForm.default_physics_order === 'vq3_first' ? 'border-blue-500/30 bg-blue-500/10' : ''">
                            <input v-model="physicsOrderForm.default_physics_order" type="radio" value="vq3_first" class="w-4 h-4 bg-white/10 border-white/20 text-blue-600" />
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-black text-blue-400 uppercase bg-blue-400/20 border border-blue-400/30 px-2 py-0.5 rounded">VQ3</span>
                                <span class="text-xs text-gray-500">/</span>
                                <span class="text-xs font-black text-purple-400 uppercase bg-purple-400/20 border border-purple-400/30 px-2 py-0.5 rounded">CPM</span>
                                <span class="text-xs text-gray-500 ml-1">{{ $t('(default)') }}</span>
                            </div>
                        </label>

                        <label class="flex items-center gap-3 p-2 rounded-lg bg-white/5 border border-white/10 hover:bg-white/10 transition-colors cursor-pointer" :class="physicsOrderForm.default_physics_order === 'cpm_first' ? 'border-purple-500/30 bg-purple-500/10' : ''">
                            <input v-model="physicsOrderForm.default_physics_order" type="radio" value="cpm_first" class="w-4 h-4 bg-white/10 border-white/20 text-purple-600" />
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-black text-purple-400 uppercase bg-purple-400/20 border border-purple-400/30 px-2 py-0.5 rounded">CPM</span>
                                <span class="text-xs text-gray-500">/</span>
                                <span class="text-xs font-black text-blue-400 uppercase bg-blue-400/20 border border-blue-400/30 px-2 py-0.5 rounded">VQ3</span>
                            </div>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Date Format Card -->
            <div id="gc-date-format" class="rounded-xl bg-black/40 backdrop-blur-sm border border-white/10 transition-all duration-500">
                <div class="p-4">
                    <div class="flex items-center gap-2 mb-3">
                        <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-cyan-500/20 to-blue-600/20 border border-cyan-500/30 flex items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 text-cyan-400">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                            </svg>
                        </div>
                        <div>
                            <h2 class="text-sm font-bold text-white">{{ $t('Date Format') }}</h2>
                            <p class="text-xs text-gray-500">{{ $t('Choose how dates are displayed in record tables') }}</p>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <button @click="globalProfileForm.date_format = 'ymd'; saveGlobalPreferencesDebounced()"
                            class="px-3 py-2 rounded-lg text-xs font-bold border transition-all"
                            :class="globalProfileForm.date_format === 'ymd' ? 'bg-cyan-600/20 text-cyan-300 border-cyan-500/40' : 'bg-white/5 text-gray-400 border-white/10 hover:bg-white/10'">
                            YY/MM/DD
                            <div class="text-[10px] font-normal mt-0.5 opacity-70">26/04/02</div>
                        </button>
                        <button @click="globalProfileForm.date_format = 'dmy'; saveGlobalPreferencesDebounced()"
                            class="px-3 py-2 rounded-lg text-xs font-bold border transition-all"
                            :class="globalProfileForm.date_format === 'dmy' ? 'bg-cyan-600/20 text-cyan-300 border-cyan-500/40' : 'bg-white/5 text-gray-400 border-white/10 hover:bg-white/10'">
                            DD/MM/YY
                            <div class="text-[10px] font-normal mt-0.5 opacity-70">02/04/26</div>
                        </button>
                        <button @click="globalProfileForm.date_format = 'Ymd'; saveGlobalPreferencesDebounced()"
                            class="px-3 py-2 rounded-lg text-xs font-bold border transition-all"
                            :class="globalProfileForm.date_format === 'Ymd' ? 'bg-cyan-600/20 text-cyan-300 border-cyan-500/40' : 'bg-white/5 text-gray-400 border-white/10 hover:bg-white/10'">
                            YYYY/MM/DD
                            <div class="text-[10px] font-normal mt-0.5 opacity-70">2026/04/02</div>
                        </button>
                        <button @click="globalProfileForm.date_format = 'dmY'; saveGlobalPreferencesDebounced()"
                            class="px-3 py-2 rounded-lg text-xs font-bold border transition-all"
                            :class="globalProfileForm.date_format === 'dmY' ? 'bg-cyan-600/20 text-cyan-300 border-cyan-500/40' : 'bg-white/5 text-gray-400 border-white/10 hover:bg-white/10'">
                            DD/MM/YYYY
                            <div class="text-[10px] font-normal mt-0.5 opacity-70">02/04/2026</div>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Time Format Card -->
            <div id="gc-time-format" class="rounded-xl bg-black/40 backdrop-blur-sm border border-white/10 transition-all duration-500">
                <div class="p-4">
                    <div class="flex items-center gap-2 mb-3">
                        <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-cyan-500/20 to-blue-600/20 border border-cyan-500/30 flex items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 text-cyan-400">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                            </svg>
                        </div>
                        <div>
                            <h2 class="text-sm font-bold text-white">{{ $t('Time Format') }}</h2>
                            <p class="text-xs text-gray-500">{{ $t('What separates the milliseconds in a run time') }}</p>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <button @click="globalProfileForm.time_format = 'colon'; saveGlobalPreferencesDebounced()"
                            class="px-3 py-2 rounded-lg text-xs font-bold border transition-all"
                            :class="globalProfileForm.time_format === 'colon' ? 'bg-cyan-600/20 text-cyan-300 border-cyan-500/40' : 'bg-white/5 text-gray-400 border-white/10 hover:bg-white/10'">
                            {{ $t('Colon') }}
                            <div class="text-[10px] font-normal mt-0.5 opacity-70">12:20:108</div>
                        </button>
                        <button @click="globalProfileForm.time_format = 'dot'; saveGlobalPreferencesDebounced()"
                            class="px-3 py-2 rounded-lg text-xs font-bold border transition-all"
                            :class="globalProfileForm.time_format === 'dot' ? 'bg-cyan-600/20 text-cyan-300 border-cyan-500/40' : 'bg-white/5 text-gray-400 border-white/10 hover:bg-white/10'">
                            {{ $t('Decimal point') }}
                            <div class="text-[10px] font-normal mt-0.5 opacity-70">12:20.108</div>
                        </button>
                    </div>
                    <p class="text-[10px] text-gray-600 mt-2">
                        {{ $t('Colon is what the in-game timer prints. The point reads the milliseconds as a fraction of a second.') }}
                    </p>
                </div>
            </div>

            <!-- Hide Stat Boxes Card -->
            <div id="gc-hide-stats" class="rounded-xl bg-black/40 backdrop-blur-sm border border-white/10 transition-all duration-500 md:col-span-2">
                <div class="p-4">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-orange-500/20 to-red-600/20 border border-orange-500/30 flex items-center justify-center">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 text-orange-400">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />
                                </svg>
                            </div>
                            <div>
                                <h2 class="text-sm font-bold text-white">{{ $t('Hide Stat Boxes') }}</h2>
                                <p class="text-xs text-gray-500">{{ $t("Hide stat boxes on other players' profiles (uncustomized only)") }}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <div v-if="globalProfileForm.recentlySuccessful" class="flex items-center gap-1.5 px-2 py-1 rounded bg-green-500/10 border border-green-500/20">
                                <svg class="w-3 h-3 text-green-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                <span class="text-xs text-green-400 font-medium">{{ $t('Saved') }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 md:grid-cols-5 gap-2">
                        <label v-for="box in statBoxItems" :key="box.id"
                            class="flex items-center gap-2 p-2 rounded-lg border cursor-pointer transition-colors"
                            :class="globalProfileForm.hidden_stat_boxes.includes(box.id) ? 'bg-red-500/10 border-red-500/30' : 'bg-white/5 border-white/10 hover:bg-white/10'"
                        >
                            <input type="checkbox"
                                :checked="!globalProfileForm.hidden_stat_boxes.includes(box.id)"
                                @change="toggleGlobalStatBox(box.id)"
                                class="w-4 h-4 rounded bg-white/10 border-white/20 text-orange-600"
                            />
                            <span class="text-xs font-medium" :class="globalProfileForm.hidden_stat_boxes.includes(box.id) ? 'text-red-400 line-through' : 'text-gray-300'">{{ box.label }}</span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Hide Profile Sections Card -->
            <div id="gc-hide-sections" class="rounded-xl bg-black/40 backdrop-blur-sm border border-white/10 transition-all duration-500 md:col-span-2">
                <div class="p-4">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-purple-500/20 to-pink-600/20 border border-purple-500/30 flex items-center justify-center">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 text-purple-400">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                                </svg>
                            </div>
                            <div>
                                <h2 class="text-sm font-bold text-white">{{ $t('Hide Profile Sections') }}</h2>
                                <p class="text-xs text-gray-500">{{ $t("Hide sections on other players' profiles (uncustomized only)") }}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <div v-if="globalProfileForm.recentlySuccessful" class="flex items-center gap-1.5 px-2 py-1 rounded bg-green-500/10 border border-green-500/20">
                                <svg class="w-3 h-3 text-green-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                <span class="text-xs text-green-400 font-medium">{{ $t('Saved') }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                        <label v-for="section in profileSections" :key="section.id"
                            class="flex items-center gap-2 p-2 rounded-lg border cursor-pointer transition-colors"
                            :class="globalProfileForm.hidden_sections.includes(section.id) ? 'bg-red-500/10 border-red-500/30' : 'bg-white/5 border-white/10 hover:bg-white/10'"
                        >
                            <input type="checkbox"
                                :checked="!globalProfileForm.hidden_sections.includes(section.id)"
                                @change="toggleGlobalSection(section.id)"
                                class="w-4 h-4 rounded bg-white/10 border-white/20 text-purple-600"
                            />
                            <span class="text-xs font-medium" :class="globalProfileForm.hidden_sections.includes(section.id) ? 'text-red-400 line-through' : 'text-gray-300'">{{ section.label }}</span>
                        </label>
                    </div>
                </div>
            </div>
            </div>
                </template>

                <!-- ==================== NOTIFICATIONS TAB ==================== -->
                <template v-if="activeTab === 'notifications'">
            <div class="grid grid-cols-2 gap-4">
                <!-- Email & Site Notifications -->
                <div class="rounded-xl bg-black/40 backdrop-blur-sm border border-white/10">
                    <div class="p-4">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center gap-2">
                                <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-orange-500/20 to-orange-600/20 border border-orange-500/30 flex items-center justify-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 text-orange-400">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
                                    </svg>
                                </div>
                                <h2 class="text-sm font-bold text-white">{{ $t('Notifications') }}</h2>
                            </div>
                            <div class="flex items-center gap-2">
                                <div v-if="notifsForm.recentlySuccessful" class="flex items-center gap-1.5 px-2 py-1 rounded bg-green-500/10 border border-green-500/20">
                                    <svg class="w-3 h-3 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                    </svg>
                                    <span class="text-xs font-medium text-green-400">{{ $t('Saved') }}</span>
                                </div>
                                <PrimaryButton type="button" @click="updateNotifications" :disabled="notifsForm.processing">
                                    {{ $t('Save') }}
                                </PrimaryButton>
                            </div>
                        </div>

                        <p class="text-xs text-gray-400 mb-3">
                            {{ $t('What reaches you at all. Switch one off and that notification is never made - it will not be waiting in the bell later. To keep getting something but stop it appearing in the header, use Header Preview below instead.') }}
                        </p>

                        <form @submit.prevent="updateNotifications" class="space-y-1.5">
                            <!-- Announcements are not a switch. They carry rules,
                                 deadlines and changes to how the site works, and the
                                 header strip already refuses to let one be removed
                                 from it. Shown here anyway, so the list of what
                                 reaches you is complete and nobody has to wonder. -->
                            <div class="flex items-center gap-2 p-2 rounded-lg bg-amber-500/5 border border-amber-500/20">
                                <svg class="w-4 h-4 flex-shrink-0 text-amber-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                </svg>
                                <div class="flex-1">
                                    <p class="text-sm font-medium text-gray-200">
                                        {{ $t('Announcements') }}
                                        <span class="text-amber-400 text-xs font-normal">{{ $t('(always on)') }}</span>
                                    </p>
                                    <p class="text-xs text-gray-400">{{ $t('Site news, rules and changelog. These reach everybody, and an unread one holds the header strip until you have read it.') }}</p>
                                </div>
                            </div>
                            <label class="flex items-center gap-2 p-2 rounded-lg bg-black/20 border border-white/5 hover:border-white/10 cursor-pointer transition-all">
                                <input v-model="notifsForm.tournament_news" type="checkbox" class="w-4 h-4 rounded bg-white/10 border-white/20 text-blue-600" />
                                <div class="flex-1">
                                    <p class="text-sm font-medium text-gray-300">{{ $t('Tournament News') }}</p>
                                    <p class="text-xs text-gray-400">{{ $t('A round opens or closes, and the results when it does.') }}</p>
                                </div>
                            </label>
                            <label class="flex items-center gap-2 p-2 rounded-lg bg-black/20 border border-white/5 hover:border-white/10 cursor-pointer transition-all">
                                <input v-model="notifsForm.map_news" type="checkbox" class="w-4 h-4 rounded bg-white/10 border-white/20 text-blue-600" />
                                <div class="flex-1">
                                    <p class="text-sm font-medium text-gray-300">{{ $t('New Maps') }}</p>
                                    <p class="text-xs text-gray-400">{{ $t('A map is released. Maps published together arrive as one line, not one each.') }}</p>
                                </div>
                            </label>
                            <label class="flex items-center gap-2 p-2 rounded-lg bg-black/20 border border-white/5 hover:border-white/10 cursor-pointer transition-all">
                                <input v-model="notifsForm.clan_notifications" type="checkbox" class="w-4 h-4 rounded bg-white/10 border-white/20 text-blue-600" />
                                <div class="flex-1">
                                    <p class="text-sm font-medium text-gray-300">{{ $t('Clan Notifications') }}</p>
                                    <p class="text-xs text-gray-400">{{ $t('Your clan invites you, removes you, hands over ownership, or someone asks to join.') }}</p>
                                </div>
                            </label>
                            <label class="flex items-center gap-2 p-2 rounded-lg bg-black/20 border border-white/5 opacity-50 cursor-not-allowed">
                                <input type="checkbox" checked disabled class="w-4 h-4 rounded bg-white/10 border-white/20" />
                                <div class="flex-1">
                                    <p class="text-sm font-medium text-gray-300">{{ $t('Team Invitations') }} <span class="text-xs text-yellow-400">{{ $t('(Required)') }}</span></p>
                                    <p class="text-xs text-gray-400">{{ $t('Somebody invites you into their tournament team. There is nothing to answer if you never see it.') }}</p>
                                </div>
                            </label>

                            <div class="pt-3">
                                <p class="text-sm font-medium text-gray-300">{{ $t('Somebody beats one of your times') }}</p>
                                <p class="text-xs text-gray-400 mb-2">
                                    {{ $t('Set per physics.') }} <span class="text-gray-300">{{ $t('All') }}</span> {{ $t('is every time of yours that gets taken back;') }} <span class="text-gray-300">{{ $t('WR Only') }}</span> {{ $t('is just the ones where what they took off you was the world record.') }}
                                </p>
                            </div>

                            <div class="grid grid-cols-2 gap-2">
                                <div class="p-2.5 rounded-lg bg-blue-500/5 border border-blue-500/20">
                                    <p class="text-sm font-bold text-blue-400 mb-2">{{ $t('VQ3 Records') }}</p>
                                    <div class="space-y-1.5">
                                        <label class="flex items-center gap-1.5 cursor-pointer">
                                            <input v-model="notifsForm.records_vq3" type="radio" value="all" class="w-4 h-4 text-blue-600 focus:ring-0 focus:ring-offset-0" />
                                            <span class="text-sm text-gray-300">{{ $t('All') }}</span>
                                        </label>
                                        <label class="flex items-center gap-1.5 cursor-pointer">
                                            <input v-model="notifsForm.records_vq3" type="radio" value="wr" class="w-4 h-4 text-blue-600 focus:ring-0 focus:ring-offset-0" />
                                            <span class="text-sm text-gray-300">{{ $t('WR Only') }}</span>
                                        </label>
                                    </div>
                                </div>
                                <div class="p-2.5 rounded-lg bg-green-500/5 border border-green-500/20">
                                    <p class="text-sm font-bold text-green-400 mb-2">{{ $t('CPM Records') }}</p>
                                    <div class="space-y-1.5">
                                        <label class="flex items-center gap-1.5 cursor-pointer">
                                            <input v-model="notifsForm.records_cpm" type="radio" value="all" class="w-4 h-4 text-green-600 focus:ring-0 focus:ring-offset-0" />
                                            <span class="text-sm text-gray-300">{{ $t('All') }}</span>
                                        </label>
                                        <label class="flex items-center gap-1.5 cursor-pointer">
                                            <input v-model="notifsForm.records_cpm" type="radio" value="wr" class="w-4 h-4 text-green-600 focus:ring-0 focus:ring-offset-0" />
                                            <span class="text-sm text-gray-300">{{ $t('WR Only') }}</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Header Preview Settings -->
                <div class="rounded-xl bg-black/40 backdrop-blur-sm border border-white/10">
                    <div class="p-4">
                        <div class="flex items-center gap-2 mb-4">
                            <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-blue-500/20 to-blue-600/20 border border-blue-500/30 flex items-center justify-center">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 text-blue-400">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                </svg>
                            </div>
                            <h2 class="text-sm font-bold text-white">{{ $t('Header Preview') }}</h2>
                        </div>
                        <p class="text-xs text-gray-400 mb-3">
                            {{ $t('The strip beside the bell that cycles through what is new. Nothing here stops a notification arriving - it only decides what gets shown up there. Whatever you switch off is still in the bell, and unread until you open it.') }}
                        </p>

                        <div class="space-y-3">
                            <div class="p-2.5 rounded-lg bg-blue-500/5 border border-blue-500/20">
                                <p class="text-xs font-bold text-blue-400 mb-1">{{ $t('System Preview') }}</p>
                                <p class="text-[11px] text-gray-500 mb-2">
                                    {{ $t('An unread announcement takes the strip for itself and holds everything else back, including the records ticker, until you have read it.') }}
                                </p>
                                <div class="space-y-1.5">
                                    <label class="flex items-center gap-1.5 opacity-50 cursor-not-allowed">
                                        <input type="checkbox" checked disabled class="w-3.5 h-3.5 rounded focus:ring-0 focus:ring-offset-0" />
                                        <span class="text-xs text-gray-300">{{ $t('Announcements') }} <span class="text-amber-400">{{ $t('(always first)') }}</span></span>
                                    </label>
                                    <label class="flex items-center gap-1.5 cursor-pointer">
                                        <input type="checkbox" :checked="notifsForm.preview_system.includes('clan')" @change="togglePreviewSystem('clan')" class="w-3.5 h-3.5 rounded focus:ring-0 focus:ring-offset-0" />
                                        <span class="text-xs text-gray-300">{{ $t('Clan Notifications') }}</span>
                                    </label>
                                    <label class="flex items-center gap-1.5 cursor-pointer">
                                        <input type="checkbox" :checked="notifsForm.preview_system.includes('tournament')" @change="togglePreviewSystem('tournament')" class="w-3.5 h-3.5 rounded focus:ring-0 focus:ring-offset-0" />
                                        <span class="text-xs text-gray-300">{{ $t('Tournament Notifications') }}</span>
                                    </label>
                                    <label class="flex items-center gap-1.5 cursor-pointer">
                                        <input type="checkbox" :checked="notifsForm.preview_system.includes('map')" @change="togglePreviewSystem('map')" class="w-3.5 h-3.5 rounded focus:ring-0 focus:ring-offset-0" />
                                        <span class="text-xs text-gray-300">{{ $t('New Map Notifications') }}</span>
                                    </label>
                                    <label class="flex items-center gap-1.5 cursor-pointer">
                                        <input type="checkbox" :checked="notifsForm.preview_system.includes('render')" @change="togglePreviewSystem('render')" class="w-3.5 h-3.5 rounded focus:ring-0 focus:ring-offset-0" />
                                        <span class="text-xs text-gray-300">{{ $t('Render Notifications') }} <span class="text-gray-500">{{ $t('(your demo finished rendering)') }}</span></span>
                                    </label>
                                </div>
                            </div>

                            <div class="p-2.5 rounded-lg bg-orange-500/5 border border-orange-500/20">
                                <p class="text-xs font-bold text-orange-400 mb-1">{{ $t('Record Preview') }}</p>
                                <p class="text-[11px] text-gray-500 mb-2">
                                    {{ $t('How much of "somebody beat your time" the strip shows. This is the header only - which ones you get is the Notifications card above.') }}
                                </p>
                                <div class="space-y-1.5">
                                    <label class="flex items-center gap-1.5 cursor-pointer">
                                        <input v-model="notifsForm.preview_records" type="radio" value="all" class="w-3.5 h-3.5 focus:ring-0 focus:ring-offset-0" />
                                        <span class="text-xs text-gray-300">{{ $t('Show All Records') }}</span>
                                    </label>
                                    <label class="flex items-center gap-1.5 cursor-pointer">
                                        <input v-model="notifsForm.preview_records" type="radio" value="wr" class="w-3.5 h-3.5 focus:ring-0 focus:ring-offset-0" />
                                        <span class="text-xs text-gray-300">{{ $t('World Records Only') }}</span>
                                    </label>
                                    <label class="flex items-center gap-1.5 cursor-pointer">
                                        <input v-model="notifsForm.preview_records" type="radio" value="none" class="w-3.5 h-3.5 focus:ring-0 focus:ring-offset-0" />
                                        <span class="text-xs text-gray-300">{{ $t("Don't Show Preview") }}</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
                </template>

                <!-- ==================== SECURITY TAB ==================== -->
                <template v-if="activeTab === 'security'">
            <div v-if="$page.props.jetstream.canUpdatePassword" class="rounded-xl bg-black/40 backdrop-blur-sm border border-white/10 overflow-hidden">
                <UpdatePasswordForm />
            </div>

            <div v-if="$page.props.jetstream.canManageTwoFactorAuthentication" class="rounded-xl bg-black/40 backdrop-blur-sm border border-white/10 overflow-hidden">
                <TwoFactorAuthenticationForm :requires-confirmation="confirmsTwoFactorAuthentication" />
            </div>

            <div class="rounded-xl bg-black/40 backdrop-blur-sm border border-white/10 overflow-hidden">
                <LogoutOtherBrowserSessionsForm :sessions="sessions" />
            </div>

            <div class="rounded-xl bg-black/40 backdrop-blur-sm border border-white/10 overflow-hidden">
                <LauncherTokensForm />
            </div>

            <div class="rounded-xl bg-black/40 backdrop-blur-sm border border-white/10 overflow-hidden">
                <ApiTokensForm />
            </div>

            <div v-if="$page.props.jetstream.hasAccountDeletionFeatures" class="rounded-xl bg-gradient-to-br from-red-500/5 to-red-600/5 border border-red-500/30 overflow-hidden">
                <DeleteUserForm />
            </div>
                </template>

                <!-- ==================== STREAMER TAB ==================== -->
                <template v-if="activeTab === 'streamer'">
                    <StreamerWidgetForm />
                </template>

                </div> <!-- Close Right Content Area -->
            </div> <!-- Close Flex Container -->
        </div>
    </div>
</template>

<style scoped>
.cropper {
    height: 400px;
    width: 100%;
}

@keyframes gradient-shift {
    0% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
    100% { background-position: 0% 50%; }
}

.animate-gradient-shift {
    background-size: 200% 200%;
    animation: gradient-shift 3s ease infinite;
}
</style>
