<script setup>
import { ref, computed, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import MapCard from '@/Components/MapCard.vue';
import DialogModal from '@/Components/Laravel/DialogModal.vue';
import TextInput from '@/Components/Laravel/TextInput.vue';
import InputLabel from '@/Components/Laravel/InputLabel.vue';
import axios from 'axios';
import { t } from '@/utils/i18n';

import EnglishOnlyNotice from '@/Components/EnglishOnlyNotice.vue';
const props = defineProps({
    maplist: Object,
    is_liked: Boolean,
    is_favorited: Boolean,
    is_owner: Boolean,
    servers: {
        type: Array,
        default: () => []
    }
});

const liked = ref(props.is_liked);
const favorited = ref(props.is_favorited);
const likesCount = ref(props.maplist.likes_count);
const favoritesCount = ref(props.maplist.favorites_count);
const processing = ref(false);
const selectedServer = ref(null);
const isReordering = ref(false);
const maps = ref([...props.maplist.maps]);
const draggedIndex = ref(null);
const showDeleteModal = ref(false);
const deleteConfirmPhrase = ref('');
const deleting = ref(false);
const tags = ref([]);
const allTags = ref([]);
const showAllTags = ref(false);
const newTagInput = ref('');
const addingTag = ref(false);
const tagInputContainer = ref(null);
const tagInput = ref(null);
const isEditing = ref(false);
const editForm = ref({
    name: props.maplist.name,
    description: props.maplist.description || '',
    is_public: props.maplist.is_public
});
const saving = ref(false);
const showPublicConfirmation = ref(false);
const showServerDropdown = ref(false);
const copiedMapId = ref(null);

/**
 * Copy the callvote, then open the game. No confirmation step: the server was
 * already chosen deliberately in step one, so there is nothing here to click
 * by mistake - unlike the map page, where the server is picked by the same
 * click that connects you.
 *
 * Copy first, connect second. The clipboard write is asynchronous and
 * navigating away can cancel it, which would drop you onto the server with
 * nothing to paste.
 */
const copyAndConnect = async (map) => {
    const server = selectedServer.value;
    if (!server) {
        return;
    }

    try {
        await navigator.clipboard.writeText(`/cv map ${map.name}`);
        copiedMapId.value = map.id;
    } catch (error) {
        console.error('Failed to copy the callvote command:', error);
    }

    window.location.href = `defrag://${server.ip}:${server.port}`;

    setTimeout(() => {
        copiedMapId.value = null;
    }, 2000);
};

// The grid renders `maps`, a local copy that drag-and-drop reordering needs to
// mutate freely. Without this, a `router.reload()` after removing a map brings
// fresh props in and the copy keeps showing the map that is already gone -
// removal looked like it had done nothing until the page was loaded again.
// Skipped mid-reorder, where the local order is the one being edited.
watch(() => props.maplist.maps, (fresh) => {
    if (!isReordering.value) {
        maps.value = [...(fresh || [])];
    }
});

const isPlayLater = computed(() => props.maplist.is_play_later);

const aggregatedMapTags = computed(() => {
    const counts = new Map();
    for (const map of (props.maplist.maps || [])) {
        for (const tag of (map.tags || [])) {
            const existing = counts.get(tag.id);
            if (existing) existing.count++;
            else counts.set(tag.id, { tag, count: 1 });
        }
    }
    return Array.from(counts.values())
        .sort((a, b) => b.count - a.count || a.tag.display_name.localeCompare(b.tag.display_name))
        .slice(0, 15)
        .map(entry => ({ ...entry.tag, map_count: entry.count }));
});

const emptyServers = computed(() => {
    if (!props.servers) return [];
    return props.servers
        .filter(s => !s.online_players || s.online_players.length === 0)
        .sort((a, b) => (a.plain_name || '').localeCompare(b.plain_name || ''));
});

const showAllServers = ref(false);

const dropdownStyle = computed(() => {
    if (!tagInputContainer.value) return {};
    const rect = tagInputContainer.value.getBoundingClientRect();
    return {
        top: `${rect.bottom + 8}px`,
        left: `${rect.left}px`,
        width: `${rect.width}px`
    };
});

const toggleLike = async () => {
    if (processing.value || isPlayLater.value) return;

    try {
        processing.value = true;
        const response = await axios.post(`/api/maplists/${props.maplist.id}/like`);
        liked.value = response.data.is_liked;
        likesCount.value = response.data.likes_count;
    } catch (error) {
        console.error('Error toggling like:', error);
    } finally {
        processing.value = false;
    }
};

const toggleFavorite = async () => {
    if (processing.value || isPlayLater.value) return;

    try {
        processing.value = true;
        const response = await axios.post(`/api/maplists/${props.maplist.id}/favorite`);
        favorited.value = response.data.is_favorited;
        favoritesCount.value = response.data.favorites_count;
    } catch (error) {
        console.error('Error toggling favorite:', error);
    } finally {
        processing.value = false;
    }
};

const openDeleteModal = () => {
    deleteConfirmPhrase.value = '';
    showDeleteModal.value = true;
};

const deleteMaplist = async () => {
    if (deleteConfirmPhrase.value !== t('delete my maplist')) {
        alert(t('Please type ":phrase" to confirm deletion', { phrase: t('delete my maplist') }));
        return;
    }

    try {
        deleting.value = true;
        await axios.delete(`/api/maplists/${props.maplist.id}`);
        router.visit('/maplists');
    } catch (error) {
        console.error('Error deleting maplist:', error);
        if (error.response?.data?.error) {
            alert(error.response.data.error);
        } else {
            alert(t('Failed to delete maplist'));
        }
    } finally {
        deleting.value = false;
        showDeleteModal.value = false;
    }
};

// Taking a map off the list asks first, in the same modal the rest of the
// page uses rather than the browser's confirm() box. The map being asked
// about is the modal's state: set, the modal is open; null, it is closed.
const mapToRemove = ref(null);
const removing = ref(false);
const removeError = ref('');

const askRemoveMap = (map) => {
    removeError.value = '';
    mapToRemove.value = map;
};

const cancelRemoveMap = () => {
    if (removing.value) return;
    mapToRemove.value = null;
};

const removeMap = async () => {
    if (!mapToRemove.value || removing.value) return;

    try {
        removing.value = true;
        await axios.delete(`/api/maplists/${props.maplist.id}/maps/${mapToRemove.value.id}`);
        mapToRemove.value = null;
        router.reload();
    } catch (error) {
        console.error('Error removing map:', error);
        removeError.value = error.response?.data?.error || t('Failed to remove map');
    } finally {
        removing.value = false;
    }
};

const toggleReordering = () => {
    if (isReordering.value) {
        // Cancel reordering - reset to original order
        maps.value = [...props.maplist.maps];
    }
    isReordering.value = !isReordering.value;
};

const saveOrder = async () => {
    try {
        const mapIds = maps.value.map(m => m.id);
        await axios.post(`/api/maplists/${props.maplist.id}/reorder`, { map_ids: mapIds });
        isReordering.value = false;
        router.reload();
    } catch (error) {
        console.error('Error saving order:', error);
        alert(t('Failed to save order'));
    }
};

const onDragStart = (index) => {
    draggedIndex.value = index;
};

const onDragOver = (e, index) => {
    e.preventDefault();
    if (draggedIndex.value === null || draggedIndex.value === index) return;

    const newMaps = [...maps.value];
    const draggedItem = newMaps[draggedIndex.value];
    newMaps.splice(draggedIndex.value, 1);
    newMaps.splice(index, 0, draggedItem);
    maps.value = newMaps;
    draggedIndex.value = index;
};

const onDragEnd = () => {
    draggedIndex.value = null;
};

// Initialize tags from maplist data and fetch all tags
const initializeTags = async () => {
    if (props.maplist.tags) {
        tags.value = props.maplist.tags;
    }

    // Fetch all tags and sort them alphabetically
    try {
        const response = await axios.get('/api/tags');
        allTags.value = response.data.tags.sort((a, b) =>
            a.display_name.localeCompare(b.display_name)
        );
    } catch (error) {
        console.error('Error fetching tags:', error);
    }
};

initializeTags();

const addTag = async (tagName) => {
    if (!props.is_owner) {
        alert(t('Only the owner can add tags'));
        return;
    }

    if (addingTag.value) return;

    try {
        addingTag.value = true;
        const response = await axios.post(`/api/maplists/${props.maplist.id}/tags`, {
            tag_name: tagName
        });
        tags.value.push(response.data.tag);
        newTagInput.value = '';

        // Refresh all tags list
        const tagsResponse = await axios.get('/api/tags');
        allTags.value = tagsResponse.data.tags.sort((a, b) =>
            a.display_name.localeCompare(b.display_name)
        );
    } catch (error) {
        if (error.response?.data?.error) {
            alert(error.response.data.error);
        } else {
            alert(t('Failed to add tag'));
        }
    } finally {
        addingTag.value = false;
    }
};

const removeTag = async (tagId) => {
    if (!props.is_owner) return;

    try {
        await axios.delete(`/api/maplists/${props.maplist.id}/tags/${tagId}`);
        tags.value = tags.value.filter(tag => tag.id !== tagId);

        // Refresh all tags list
        const tagsResponse = await axios.get('/api/tags');
        allTags.value = tagsResponse.data.tags.sort((a, b) =>
            a.display_name.localeCompare(b.display_name)
        );
    } catch (error) {
        console.error('Error removing tag:', error);
        alert(t('Failed to remove tag'));
    }
};

const addCustomTag = () => {
    if (newTagInput.value.trim()) {
        addTag(newTagInput.value.trim());
    }
};

const handleTagInputBlur = () => {
    setTimeout(() => {
        showAllTags.value = false;
    }, 200);
};

const handlePublicClick = () => {
    // If already public or trying to make private, just toggle
    if (editForm.value.is_public || !props.maplist.is_public) {
        editForm.value.is_public = true;
        showPublicConfirmation.value = true;
    }
};

const confirmMakePublic = () => {
    editForm.value.is_public = true;
    showPublicConfirmation.value = false;
};

const cancelMakePublic = () => {
    editForm.value.is_public = props.maplist.is_public;
    showPublicConfirmation.value = false;
};

const startEditing = () => {
    editForm.value = {
        name: props.maplist.name,
        description: props.maplist.description || '',
        is_public: props.maplist.is_public
    };
    isEditing.value = true;
};

const cancelEditing = () => {
    isEditing.value = false;
    editForm.value = {
        name: props.maplist.name,
        description: props.maplist.description || '',
        is_public: props.maplist.is_public
    };
};

const saveEdits = async () => {
    if (!editForm.value.name.trim()) {
        alert(t('Maplist name is required'));
        return;
    }

    try {
        saving.value = true;
        await axios.put(`/api/maplists/${props.maplist.id}`, {
            name: editForm.value.name,
            description: editForm.value.description,
            is_public: editForm.value.is_public
        });

        isEditing.value = false;
        router.reload({ only: ['maplist'] });
    } catch (error) {
        console.error('Error saving maplist:', error);
        if (error.response?.data?.message) {
            alert(error.response.data.message);
        } else {
            alert(t('Failed to save changes'));
        }
    } finally {
        saving.value = false;
    }
};

const closeServerDropdown = () => {
    showServerDropdown.value = false;
};
</script>

<template>
    <div class="relative" @click="closeServerDropdown">
        <Head :title="maplist.name" />

        <!-- Animated Background Pattern -->
        <div class="fixed inset-0 overflow-hidden pointer-events-none">
            <div class="absolute inset-0 opacity-20">
                <div class="absolute inset-0" style="background-image:
                    repeating-linear-gradient(0deg, transparent, transparent 2px, rgba(59, 130, 246, 0.03) 2px, rgba(59, 130, 246, 0.03) 4px),
                    repeating-linear-gradient(90deg, transparent, transparent 2px, rgba(59, 130, 246, 0.03) 2px, rgba(59, 130, 246, 0.03) 4px),
                    repeating-linear-gradient(45deg, transparent, transparent 10px, rgba(139, 92, 246, 0.02) 10px, rgba(139, 92, 246, 0.02) 20px),
                    repeating-linear-gradient(-45deg, transparent, transparent 10px, rgba(236, 72, 153, 0.02) 10px, rgba(236, 72, 153, 0.02) 20px),
                    radial-gradient(ellipse at 20% 30%, rgba(59, 130, 246, 0.15) 0%, transparent 50%),
                    radial-gradient(ellipse at 80% 70%, rgba(139, 92, 246, 0.15) 0%, transparent 50%);
                    animation: bgShift 20s ease-in-out infinite;">
                </div>
            </div>
            <div class="absolute top-0 left-0 w-full h-full opacity-30">
                <div class="absolute top-1/4 left-1/4 w-96 h-96 bg-blue-500/10 rounded-full blur-3xl animate-pulse" style="animation-duration: 8s;"></div>
                <div class="absolute bottom-1/4 right-1/4 w-96 h-96 bg-purple-500/10 rounded-full blur-3xl animate-pulse" style="animation-duration: 12s; animation-delay: 2s;"></div>
                <div class="absolute top-1/2 left-1/2 w-96 h-96 bg-pink-500/10 rounded-full blur-3xl animate-pulse" style="animation-duration: 10s; animation-delay: 4s;"></div>
            </div>
        </div>

        <!-- Header Section -->
        <div class="relative bg-gradient-to-b from-black/25 via-black/10 to-transparent pt-0 pointer-events-none" :class="isPlayLater ? 'pb-6' : (maplist.description ? 'pb-16' : 'pb-3')">
            <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8 pointer-events-auto">
                <!-- Maplist Header Card -->
                <div class="bg-black/40 rounded-2xl border border-white/5 shadow-2xl" :class="isPlayLater ? 'p-4 md:p-5' : 'p-3 md:p-4'">
                    <!-- Breadcrumb (not for Play Later) -->
                    <div v-if="!isPlayLater" class="flex items-center gap-1.5 text-xs text-gray-400 mb-2">
                        <Link href="/maplists" class="hover:text-white transition">{{ $t('Maplists') }}</Link>
                        <template v-if="is_owner">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                            <Link href="/maplists?view=mine" class="hover:text-white transition">{{ $t('My Maplists') }}</Link>
                        </template>
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                        <span class="text-white">{{ maplist.name }}</span>
                    </div>

                    <!-- Edit Mode -->
                    <div v-if="isEditing">
                        <EnglishOnlyNotice :compact="false" />

                        <div class="mb-6">
                            <label class="block text-sm font-semibold text-blue-400 mb-2 uppercase tracking-wider">{{ $t('Name') }}</label>
                            <input
                                v-model="editForm.name"
                                type="text"
                                class="w-full px-5 py-3 bg-black/30 border border-white/20 rounded-xl text-white text-3xl font-black placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                                :placeholder="$t('Maplist name...')" />
                        </div>
                        <div class="mb-6">
                            <label class="block text-sm font-semibold text-blue-400 mb-2 uppercase tracking-wider">{{ $t('Description') }}</label>
                            <textarea
                                v-model="editForm.description"
                                rows="4"
                                class="w-full px-5 py-3 bg-black/30 border border-white/20 rounded-xl text-white text-lg placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition"
                                :placeholder="$t('Description (optional)...')" />
                        </div>
                        <div class="mb-6">
                            <label class="block text-sm font-semibold text-blue-400 mb-2 uppercase tracking-wider">{{ $t('Visibility') }}</label>
                            <div class="flex gap-4">
                                <button
                                    @click="handlePublicClick"
                                    :class="[
                                        'flex-1 px-5 py-3 rounded-xl font-bold transition-all flex items-center justify-center gap-2',
                                        editForm.is_public
                                            ? 'bg-gradient-to-r from-green-600 to-green-700 text-white border-2 border-green-400'
                                            : 'bg-black/30 text-gray-400 border-2 border-white/20 hover:border-green-400/50'
                                    ]">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    {{ $t('Public') }}
                                </button>
                                <button
                                    @click="editForm.is_public = false"
                                    :class="[
                                        'flex-1 px-5 py-3 rounded-xl font-bold transition-all flex items-center justify-center gap-2',
                                        !editForm.is_public
                                            ? 'bg-gradient-to-r from-gray-700 to-gray-600 text-white border-2 border-gray-400'
                                            : 'bg-black/30 text-gray-400 border-2 border-white/20 hover:border-gray-400/50'
                                    ]">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                    </svg>
                                    {{ $t('Private') }}
                                </button>
                            </div>
                        </div>
                        <div class="flex gap-3">
                            <button
                                @click="saveEdits"
                                :disabled="saving"
                                class="px-6 py-3 bg-gradient-to-r from-green-600 to-green-700 hover:from-green-500 hover:to-green-600 disabled:from-gray-700 disabled:to-gray-800 disabled:cursor-not-allowed text-white rounded-xl font-bold shadow-lg transition-all transform hover:scale-105">
                                {{ saving ? $t('Saving...') : $t('Save Changes') }}
                            </button>
                            <button
                                @click="cancelEditing"
                                :disabled="saving"
                                class="px-6 py-3 bg-white/10 hover:bg-white/20 disabled:cursor-not-allowed text-white rounded-xl font-bold transition-all border border-white/20">
                                {{ $t('Cancel') }}
                            </button>
                        </div>
                    </div>

                    <!-- View Mode -->
                    <div v-else>
                        <!-- Title Row with Stats + Owner Actions -->
                        <div class="flex items-start justify-between gap-6" :class="isPlayLater ? 'mb-0' : ((maplist.description || (is_owner && !isPlayLater)) ? 'mb-6' : 'mb-0')">
                            <!-- Title Left: title, badge, small Like/Fav, map tags -->
                            <div class="flex-1 min-w-0">
                                <div class="flex flex-wrap items-center gap-3">
                                    <Link v-if="!isPlayLater" :href="`/profile/${maplist.user?.id}`" class="flex items-center gap-2 px-3 py-2 bg-gradient-to-br from-purple-600/20 to-purple-700/10 rounded-lg border border-purple-500/30 hover:border-purple-400/50 transition-all group flex-shrink-0">
                                        <div class="relative w-8 h-8 flex-shrink-0">
                                            <div :class="'avatar-effect-' + (maplist.user?.avatar_effect || 'none')" :style="`--effect-color: ${maplist.user?.color || '#ffffff'}; --border-color: ${maplist.user?.avatar_border_color || '#6b7280'}; --orbit-radius: 16px`">
                                                <img
                                                    :src="maplist.user?.profile_photo_path ? `/storage/${maplist.user.profile_photo_path}` : '/images/null.jpg'"
                                                    :alt="maplist.user?.plain_name"
                                                    class="w-8 h-8 rounded-full object-cover border-2 relative"
                                                    :style="`border-color: ${maplist.user?.avatar_border_color || '#6b7280'}`">
                                            </div>
                                        </div>
                                        <div class="min-w-0">
                                            <div class="text-[10px] text-purple-300 font-semibold uppercase tracking-wider leading-none">{{ $t('Created by') }}</div>
                                            <div :class="'name-effect-' + (maplist.user?.name_effect || 'none')" :style="`--effect-color: ${maplist.user?.color || '#ffffff'}`" class="font-bold text-white text-sm group-hover:text-purple-300 transition truncate" v-html="q3tohtml(maplist.user?.name || $t('Unknown'))"></div>
                                        </div>
                                    </Link>
                                    <h1 class="text-2xl md:text-3xl font-black text-transparent bg-clip-text bg-gradient-to-r from-white via-blue-100 to-purple-200 break-words">
                                        {{ maplist.name }}
                                    </h1>
                                    <span v-if="isPlayLater" class="text-sm font-semibold text-gray-500 align-middle">{{ $tc(':count map|:count maps', maplist.maps?.length || 0) }}</span>
                                    <div v-if="!isPlayLater && maplist.is_public" class="px-3 py-1.5 bg-gradient-to-r from-green-600 to-green-500 rounded-lg text-xs font-bold flex items-center gap-1.5 shadow-lg flex-shrink-0">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        {{ $t('Public') }}
                                    </div>
                                    <div v-else-if="!isPlayLater" class="px-3 py-1.5 bg-gradient-to-r from-gray-700 to-gray-600 rounded-lg text-xs font-bold flex items-center gap-1.5 shadow-lg flex-shrink-0">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                        </svg>
                                        {{ $t('Private') }}
                                    </div>
                                </div>

                                <!-- Small Like / Favourite buttons under title -->
                                <div v-if="!isPlayLater && !is_owner && maplist.is_public" class="flex flex-wrap gap-2 mt-3">
                                    <button
                                        @click="toggleLike"
                                        :disabled="processing"
                                        :class="[
                                            'px-3 py-1.5 rounded-lg text-xs font-bold transition-all flex items-center gap-1.5 shadow',
                                            liked ? 'bg-gradient-to-r from-red-600 to-red-500 text-white' : 'bg-white/10 text-gray-300 hover:bg-white/20 border border-white/20'
                                        ]">
                                        <svg class="w-3.5 h-3.5" :fill="liked ? 'currentColor' : 'none'" stroke="currentColor" viewBox="0 0 20 20">
                                            <path stroke-width="2" fill-rule="evenodd" d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z" clip-rule="evenodd" />
                                        </svg>
                                        {{ liked ? $t('Liked') : $t('Like') }}
                                    </button>
                                    <button
                                        @click="toggleFavorite"
                                        :disabled="processing"
                                        :class="[
                                            'px-3 py-1.5 rounded-lg text-xs font-bold transition-all flex items-center gap-1.5 shadow',
                                            favorited ? 'bg-gradient-to-r from-yellow-600 to-yellow-500 text-white' : 'bg-white/10 text-gray-300 hover:bg-white/20 border border-white/20'
                                        ]">
                                        <svg class="w-3.5 h-3.5" :fill="favorited ? 'currentColor' : 'none'" stroke="currentColor" viewBox="0 0 20 20">
                                            <path stroke-width="2" d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                                        </svg>
                                        {{ favorited ? $t('Favorited') : $t('Favorite') }}
                                    </button>
                                </div>

                                <!-- Aggregated tags from maps in this list -->
                                <div v-if="!isPlayLater && aggregatedMapTags.length > 0" class="flex flex-wrap gap-1.5 mt-3">
                                    <span
                                        v-for="tag in aggregatedMapTags"
                                        :key="tag.id"
                                        :title="$tc(':count map with this tag|:count maps with this tag', tag.map_count)"
                                        class="px-2.5 py-1 rounded-full text-xs font-semibold bg-white/10 text-gray-300 border border-white/15 hover:bg-white/15 hover:border-white/25 transition-colors">
                                        {{ tag.display_name }}
                                        <span class="text-gray-500 font-normal ml-0.5">{{ tag.map_count }}</span>
                                    </span>
                                </div>
                            </div>

                            <!-- Right: Stats bubbles + Owner Actions -->
                            <div class="flex flex-col items-end gap-3 flex-shrink-0">
                                <!-- Stats Cards Row -->
                                <div v-if="!isPlayLater" class="flex flex-wrap gap-2 justify-end max-w-md">
                                    <div class="flex items-center gap-2 px-3 py-2 bg-gradient-to-br from-blue-600/20 to-blue-700/10 rounded-lg border border-blue-500/30">
                                        <svg class="w-4 h-4 text-blue-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
                                        </svg>
                                        <div class="text-lg font-black text-white leading-none">{{ maplist.maps?.length || 0 }}</div>
                                        <div class="text-[10px] text-blue-300 font-semibold uppercase tracking-wider">{{ $t('Maps') }}</div>
                                    </div>

                                    <div class="flex items-center gap-2 px-3 py-2 bg-gradient-to-br from-red-600/20 to-red-700/10 rounded-lg border border-red-500/30">
                                        <svg class="w-4 h-4 text-red-400 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z" clip-rule="evenodd" />
                                        </svg>
                                        <div class="text-lg font-black text-white leading-none">{{ likesCount }}</div>
                                        <div class="text-[10px] text-red-300 font-semibold uppercase tracking-wider">{{ $t('Likes') }}</div>
                                    </div>

                                    <div class="flex items-center gap-2 px-3 py-2 bg-gradient-to-br from-yellow-600/20 to-yellow-700/10 rounded-lg border border-yellow-500/30">
                                        <svg class="w-4 h-4 text-yellow-400 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                                        </svg>
                                        <div class="text-lg font-black text-white leading-none">{{ favoritesCount }}</div>
                                        <div class="text-[10px] text-yellow-300 font-semibold uppercase tracking-wider">{{ $t('Favorites') }}</div>
                                    </div>

                                </div>

                                <!-- Owner Actions (Edit, Delete, Reorder) -->
                                <div v-if="is_owner && !isPlayLater" class="flex flex-col gap-2">
                                    <div class="flex gap-2 justify-end">
                                        <button
                                            @click="startEditing"
                                            class="p-2.5 text-blue-400 hover:text-white bg-white/5 hover:bg-white/10 rounded-lg transition-all border border-white/10 hover:border-blue-400/50"
                                            :title="$t('Edit maplist')">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                        </button>
                                        <button
                                            @click="openDeleteModal"
                                            class="p-2.5 text-red-400 hover:text-white bg-white/5 hover:bg-white/10 rounded-lg transition-all border border-white/10 hover:border-red-400/50"
                                            :title="$t('Delete maplist')">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </div>
                                    <button
                                        v-if="!isReordering"
                                        @click="toggleReordering"
                                        class="px-3 py-1.5 bg-gradient-to-r from-gray-700 to-gray-600 hover:from-gray-600 hover:to-gray-500 text-white rounded-lg text-xs font-bold shadow transition-all flex items-center justify-center gap-2">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3.5 h-3.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 7.5L7.5 3m0 0L12 7.5M7.5 3v13.5m13.5 0L16.5 21m0 0L12 16.5m4.5 4.5V7.5" />
                                        </svg>
                                        {{ $t('Reorder') }}
                                    </button>
                                    <template v-else>
                                        <button
                                            @click="saveOrder"
                                            class="px-3 py-1.5 bg-gradient-to-r from-green-600 to-green-500 hover:from-green-500 hover:to-green-400 text-white rounded-lg text-xs font-bold shadow transition-all flex items-center justify-center gap-2">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3.5 h-3.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                            </svg>
                                            {{ $t('Save') }}
                                        </button>
                                        <button
                                            @click="toggleReordering"
                                            class="px-3 py-1.5 bg-white/10 hover:bg-white/20 text-white rounded-lg text-xs font-bold transition-all border border-white/20">
                                            {{ $t('Cancel') }}
                                        </button>
                                    </template>
                                </div>
                            </div>
                        </div>

                        <!-- Description -->
                        <p v-if="maplist.description" :class="isPlayLater ? 'text-gray-500 text-sm mb-2' : 'text-gray-300 text-xl leading-relaxed mb-6'" class="max-w-4xl">
                            {{ maplist.description }}
                        </p>

                        <!-- Tags Section (for owner to manage) -->
                        <div v-if="is_owner && !isPlayLater" class="mt-6 pt-6 border-t border-white/10">
                            <div class="mb-3">
                                <span class="text-gray-400 font-bold text-xs uppercase tracking-wider">{{ $t('Manage Tags') }}</span>
                            </div>

                            <!-- Current tags on this maplist -->
                            <div v-if="tags.length > 0" class="mb-4">
                                <div class="text-xs text-gray-500 uppercase mb-2">{{ $t('Current Tags') }}</div>
                                <div class="flex flex-wrap gap-2">
                                    <span v-for="tag in tags" :key="tag.id" class="px-3 py-1.5 bg-blue-600 text-white rounded-full text-sm font-semibold flex items-center gap-2">
                                        {{ tag.display_name }}
                                        <button
                                            @click="removeTag(tag.id)"
                                            class="hover:bg-blue-700 rounded-full w-5 h-5 flex items-center justify-center transition"
                                            :title="$t('Remove tag')">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                        </button>
                                    </span>
                                </div>
                            </div>

                            <!-- Custom tag input -->
                            <div class="relative" ref="tagInputContainer">
                                <div class="flex gap-2">
                                    <input
                                        ref="tagInput"
                                        v-model="newTagInput"
                                        @keyup.enter="addCustomTag"
                                        @focus="showAllTags = true"
                                        @blur="handleTagInputBlur"
                                        type="text"
                                        :placeholder="$t('Add custom tag...')"
                                        class="flex-1 px-4 py-2 bg-black/40 border border-white/5 rounded-lg text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                        :disabled="addingTag" />
                                    <button
                                        @click="addCustomTag"
                                        :disabled="!newTagInput.trim() || addingTag"
                                        class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-gray-700 disabled:text-gray-500 disabled:cursor-not-allowed text-white rounded-lg font-semibold transition">
                                        {{ addingTag ? $t('Adding...') : $t('Add') }}
                                    </button>
                                </div>

                                <!-- Available tags dropdown (shown only when showAllTags is true) -->
                                <Teleport to="body">
                                    <div
                                        v-if="showAllTags && allTags.length > 0"
                                        :style="dropdownStyle"
                                        class="fixed bg-[#111827] border border-white/20 rounded-lg shadow-[0_10px_40px_rgba(0,0,0,0.8)] p-3 z-[99999]">
                                        <div class="text-xs text-gray-500 uppercase mb-2">{{ $t('Click to add tag') }}</div>
                                        <div class="flex flex-wrap gap-1.5 max-h-64 overflow-y-auto">
                                            <button
                                                v-for="availableTag in allTags.filter(t => !tags.some(tag => tag.id === t.id))"
                                                :key="availableTag.id"
                                                @click="addTag(availableTag.display_name)"
                                                class="px-3 py-1.5 rounded-full text-sm font-semibold transition bg-white/10 text-gray-300 hover:bg-white/20">
                                                {{ availableTag.display_name }}
                                            </button>
                                        </div>
                                    </div>
                                </Teleport>
                            </div>
                        </div>
                        <!-- Play Wizard (for Play Later only) -->
                        <div v-if="isPlayLater && servers && servers.length > 0" class="mt-5 pt-5 border-t border-white/10 relative z-30" @click.stop>
                            <!-- Summary: what is queued and where it can go -->
                            <div class="flex flex-wrap items-center gap-2 mb-4">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-blue-500/15 border border-blue-400/30 text-blue-300 text-xs font-bold">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
                                    </svg>
                                    {{ $tc(':count map queued|:count maps queued', maps.length) }}
                                </span>
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold"
                                    :class="emptyServers.length > 0
                                        ? 'bg-green-500/15 border border-green-400/30 text-green-300'
                                        : 'bg-white/5 border border-white/15 text-gray-400'">
                                    <span class="w-1.5 h-1.5 rounded-full" :class="emptyServers.length > 0 ? 'bg-green-400' : 'bg-gray-500'"></span>
                                    {{ $tc(':count empty server|:count empty servers', emptyServers.length) }}
                                </span>
                            </div>

                            <!-- Step indicators -->
                            <div class="flex items-center gap-3 mb-4">
                                <div class="flex items-center gap-2">
                                    <div class="flex items-center justify-center w-7 h-7 rounded-full text-xs font-black flex-shrink-0"
                                        :class="selectedServer ? 'bg-green-500 text-white' : 'bg-blue-500 text-white ring-2 ring-blue-500/30'">
                                        <svg v-if="selectedServer" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                                        <span v-else>1</span>
                                    </div>
                                    <span class="text-sm" :class="selectedServer ? 'text-green-400 font-semibold' : 'text-white font-semibold'">{{ $t('Choose a server') }}</span>
                                </div>
                                <div class="h-px flex-1 bg-white/10"></div>
                                <div class="flex items-center gap-2">
                                    <div class="flex items-center justify-center w-7 h-7 rounded-full text-xs font-black flex-shrink-0"
                                        :class="selectedServer ? 'bg-blue-500 text-white ring-2 ring-blue-500/30' : 'bg-white/10 text-gray-500'">
                                        2
                                    </div>
                                    <span class="text-sm" :class="selectedServer ? 'text-white font-semibold' : 'text-gray-500'">{{ $t('Click Copy and connect on a map') }}</span>
                                </div>
                                <div class="h-px flex-1 bg-white/10"></div>
                                <div class="flex items-center gap-2">
                                    <div class="flex items-center justify-center w-7 h-7 rounded-full text-xs font-black flex-shrink-0 bg-green-500/20 text-green-400 border border-green-500/40">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0013.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 01-.75.75H9.75a.75.75 0 01-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 01-2.25 2.25H6.75A2.25 2.25 0 014.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 011.927-.184" /></svg>
                                    </div>
                                    <span class="text-sm text-green-400/80 font-medium">{{ $t('Callvote lands on your clipboard, paste it in the console') }}</span>
                                </div>
                            </div>

                            <!-- Server selector -->
                            <div class="relative">
                                <div
                                    @click="showServerDropdown = !showServerDropdown"
                                    class="w-full flex items-center gap-3 border-2 rounded-lg px-4 py-3 cursor-pointer transition"
                                    :class="selectedServer
                                        ? 'bg-green-500/10 border-green-500/40 hover:border-green-400/60'
                                        : 'bg-white/5 border-white/15 hover:border-blue-500/50'">
                                    <template v-if="!selectedServer">
                                        <svg class="w-5 h-5 text-gray-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5.25 14.25h13.5m-13.5 0a3 3 0 01-3-3m3 3a3 3 0 100 6h13.5a3 3 0 100-6m-16.5-3a3 3 0 013-3h13.5a3 3 0 013 3m-19.5 0a4.5 4.5 0 01.9-2.7L5.737 5.1a3.375 3.375 0 012.7-1.35h7.126c1.062 0 2.062.5 2.7 1.35l2.587 3.45a4.5 4.5 0 01.9 2.7m0 0a3 3 0 01-3 3m0 3h.008v.008h-.008v-.008zm0-6h.008v.008h-.008v-.008zm-3 6h.008v.008h-.008v-.008zm0-6h.008v.008h-.008v-.008z" /></svg>
                                        <span class="text-gray-400 text-sm">{{ $t('Select an empty server to play on...') }}</span>
                                        <svg class="w-4 h-4 text-gray-500 flex-shrink-0 ml-auto transition-transform" :class="showServerDropdown ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" /></svg>
                                    </template>
                                    <template v-else>
                                        <svg class="w-5 h-5 text-green-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5.25 14.25h13.5m-13.5 0a3 3 0 01-3-3m3 3a3 3 0 100 6h13.5a3 3 0 100-6m-16.5-3a3 3 0 013-3h13.5a3 3 0 013 3m-19.5 0a4.5 4.5 0 01.9-2.7L5.737 5.1a3.375 3.375 0 012.7-1.35h7.126c1.062 0 2.062.5 2.7 1.35l2.587 3.45a4.5 4.5 0 01.9 2.7m0 0a3 3 0 01-3 3m0 3h.008v.008h-.008v-.008zm0-6h.008v.008h-.008v-.008zm-3 6h.008v.008h-.008v-.008zm0-6h.008v.008h-.008v-.008z" /></svg>
                                        <div class="flex-1 min-w-0">
                                            <span v-html="q3tohtml(selectedServer.name)" class="text-sm font-semibold truncate block"></span>
                                            <span class="text-xs text-gray-500">{{ selectedServer.ip }}:{{ selectedServer.port }}</span>
                                        </div>
                                        <button @click.stop="selectedServer = null" class="text-gray-500 hover:text-red-400 p-1 rounded transition flex-shrink-0" :title="$t('Change server')">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                                        </button>
                                    </template>
                                </div>

                                <!-- Dropdown -->
                                <div v-if="showServerDropdown" class="absolute z-50 left-0 right-0 mt-2 bg-gray-900 border border-white/15 rounded-lg overflow-hidden shadow-2xl shadow-black/60">
                                    <div class="px-4 py-2.5 border-b border-white/10 flex items-center justify-between bg-gray-800/80">
                                        <span class="text-xs font-bold text-gray-300 uppercase tracking-wider">{{ $t('Empty Servers') }}</span>
                                        <button @click.stop="showAllServers = !showAllServers" class="text-xs text-blue-400 hover:text-blue-300 font-medium">
                                            {{ showAllServers ? $t('Empty only') : $t('Show all servers') }}
                                        </button>
                                    </div>
                                    <div class="max-h-64 overflow-y-auto">
                                        <div
                                            v-for="server in (showAllServers ? servers : emptyServers)"
                                            :key="server.id"
                                            @click="selectedServer = server; showServerDropdown = false"
                                            class="px-4 py-3 hover:bg-blue-500/15 cursor-pointer transition border-b border-white/5 last:border-0">
                                            <div class="flex items-center justify-between gap-3">
                                                <div class="min-w-0">
                                                    <span v-html="q3tohtml(server.name)" class="font-semibold truncate block"></span>
                                                    <div class="text-xs text-gray-500 mt-0.5">
                                                        {{ server.ip }}:{{ server.port }}
                                                        <span v-if="server.location"> - {{ server.location }}</span>
                                                    </div>
                                                </div>
                                                <span v-if="server.online_players && server.online_players.length > 0" class="text-yellow-400 text-xs font-bold flex-shrink-0 px-2 py-0.5 bg-yellow-500/15 rounded">{{ $tc(':count playing|:count playing', server.online_players.length) }}</span>
                                                <span v-else class="text-gray-500 text-xs font-bold flex-shrink-0 px-2 py-0.5 bg-white/5 rounded">{{ $t('EMPTY') }}</span>
                                            </div>
                                        </div>
                                        <div v-if="(showAllServers ? servers : emptyServers).length === 0" class="px-4 py-6 text-center text-gray-500 text-sm">
                                            {{ $t('No empty servers available right now') }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- Maps Grid -->
        <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8 py-2" :style="isPlayLater ? '' : (maplist.description ? 'margin-top: -3rem;' : 'margin-top: 0;')">
            <!-- Reordering Instructions -->
            <div v-if="isReordering" class="mb-4 p-4 bg-purple-600/20 border border-purple-500/50 rounded-lg text-purple-200 text-center">
                <p class="font-semibold">{{ $t('Drag and drop maps to reorder them') }}</p>
            </div>

            <div v-if="maps && maps.length > 0" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3">
                <div
                    v-for="(map, index) in maps"
                    :key="map.id"
                    class="relative"
                    :draggable="isReordering"
                    @dragstart="onDragStart(index)"
                    @dragover="onDragOver($event, index)"
                    @dragend="onDragEnd"
                    :class="{ 'cursor-move': isReordering, 'opacity-50': draggedIndex === index }">
                    <MapCard :map="map">
                        <!-- Queue position. Play Later is an ordered list you can
                             drag around, so the order is worth showing. It goes
                             through the card's bottom-right slot, under the item
                             rows, clear of the physics badge in the top corner. -->
                        <template #bottom-right>
                            <div
                                v-if="isPlayLater"
                                class="min-w-[1.5rem] h-6 px-1.5 flex items-center justify-center rounded-md bg-black/70 border border-white/15 text-white text-xs font-black backdrop-blur-sm">
                                {{ index + 1 }}
                            </div>
                        </template>
                    </MapCard>

                    <!-- Play Button (for Play Later with server selected) -->
                    <button
                        v-if="isPlayLater && selectedServer"
                        @click="copyAndConnect(map)"
                        :class="selectedServer.defrag?.toLowerCase().includes('cpm') ? 'connect-button-cpm' : 'connect-button-vq3'"
                        class="connect-button w-full mt-2 flex items-center justify-between px-3 py-2 rounded-lg text-white font-bold text-xs transition-all"
                        :title="$t('Connect to :server and load :map', { server: `${selectedServer.ip}:${selectedServer.port}`, map: map.name })">
                        <div class="flex items-center gap-1.5">
                            <svg v-if="copiedMapId !== map.id" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 0 1 0 1.972l-11.54 6.347a1.125 1.125 0 0 1-1.667-.986V5.653Z" />
                            </svg>
                            <svg v-else class="w-4 h-4 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                            </svg>
                            {{ copiedMapId === map.id ? $t('Copied - connecting...') : $t('Copy and connect') }}
                        </div>
                        <span :class="selectedServer.defrag?.toLowerCase().includes('cpm') ? 'bg-purple-500/30 border-purple-400/50 text-purple-300' : 'bg-blue-500/30 border-blue-400/50 text-blue-300'" class="px-2 py-0.5 text-[10px] font-black uppercase tracking-wider rounded border">
                            {{ selectedServer.defrag?.toLowerCase().includes('cpm') ? 'CPM' : 'VQ3' }}
                        </span>
                    </button>

                    <!-- Remove Button (for owner) -->
                    <button
                        v-if="is_owner"
                        @click="askRemoveMap(map)"
                        class="absolute top-2 left-2 bg-red-600/90 hover:bg-red-700 text-white p-2 rounded-lg shadow-lg transition z-10">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Empty State -->
            <div v-else class="flex flex-col items-center justify-center py-20 text-gray-400">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-16 h-16 mb-4 opacity-40">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 010 3.75H5.625a1.875 1.875 0 010-3.75z" />
                </svg>
                <template v-if="isPlayLater">
                    <p class="font-semibold text-lg mb-2">{{ $t('Nothing queued yet') }}</p>
                    <p class="text-sm mb-4">{{ $t('Add maps from any map page and they land here, ready to load onto a server.') }}</p>
                </template>
                <template v-else>
                    <p class="font-semibold text-lg mb-2">{{ $t('No maps in this maplist yet') }}</p>
                    <p class="text-sm mb-4" v-if="is_owner">{{ $t('Start adding maps to build your collection!') }}</p>
                </template>
                <Link v-if="is_owner || isPlayLater" :href="'/maps'" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-semibold transition">
                    {{ $t('Browse Maps') }}
                </Link>
            </div>
        </div>

        <!-- Make Public Confirmation Modal -->
        <DialogModal :show="showPublicConfirmation" @close="cancelMakePublic">
            <template #title>
                {{ $t('Make Maplist Public') }}
            </template>

            <template #content>
                <div class="space-y-4">
                    <div class="bg-yellow-600/20 border border-yellow-500/50 rounded-lg p-4">
                        <div class="flex items-start gap-3">
                            <svg class="w-6 h-6 text-yellow-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                            <div>
                                <p class="font-bold text-yellow-300 mb-2">{{ $t('Warning: This action is permanent!') }}</p>
                                <p class="text-sm text-yellow-200 [&_span]:font-bold"
                                   v-html="$t('Once you make this maplist public, it <span>cannot be made private again</span>. Everyone will be able to view and use this maplist.')"></p>
                            </div>
                        </div>
                    </div>

                    <p class="text-gray-300">
                        {{ $t('Are you sure you want to make ":name" public?', { name: maplist.name }) }}
                    </p>
                </div>
            </template>

            <template #footer>
                <button
                    @click="cancelMakePublic"
                    class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg font-semibold transition">
                    {{ $t('Cancel') }}
                </button>

                <button
                    @click="confirmMakePublic"
                    class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg font-semibold transition">
                    {{ $t('Make Public') }}
                </button>
            </template>
        </DialogModal>

        <!-- Delete Confirmation Modal -->
        <DialogModal :show="showDeleteModal" @close="showDeleteModal = false">
            <template #title>
                {{ $t('Delete Maplist') }}
            </template>

            <template #content>
                <div class="space-y-4">
                    <p class="text-gray-300">
                        {{ $t('Are you sure you want to permanently delete ":name"?', { name: maplist.name }) }}
                    </p>

                    <p class="text-sm text-gray-400">
                        {{ $t('This action cannot be undone. All maps in this maplist will be removed.') }}
                    </p>

                    <div class="bg-red-600/20 border border-red-500/50 rounded-lg p-4">
                        <p class="text-sm text-red-300 mb-3">
                            {{ $t('To confirm deletion, please type') }} <span class="font-mono font-bold text-red-200">{{ $t('delete my maplist') }}</span> {{ $t('below:') }}
                        </p>
                        <TextInput
                            v-model="deleteConfirmPhrase"
                            type="text"
                            class="w-full font-mono"
                            :placeholder="$t('delete my maplist')"
                            @keyup.enter="deleteMaplist"
                        />
                    </div>
                </div>
            </template>

            <template #footer>
                <button
                    @click="showDeleteModal = false"
                    class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg font-semibold transition">
                    {{ $t('Cancel') }}
                </button>

                <button
                    @click="deleteMaplist"
                    :disabled="deleting || deleteConfirmPhrase !== $t('delete my maplist')"
                    class="px-4 py-2 bg-red-600 hover:bg-red-700 disabled:bg-gray-600 disabled:cursor-not-allowed text-white rounded-lg font-semibold transition">
                    {{ deleting ? $t('Deleting...') : $t('Delete Maplist') }}
                </button>
            </template>
        </DialogModal>

        <!-- Remove Map Confirmation Modal -->
        <DialogModal :show="mapToRemove !== null" max-width="md" @close="cancelRemoveMap">
            <template #title>
                {{ $t('Remove map') }}
            </template>

            <template #content>
                <div class="space-y-3">
                    <p class="text-gray-300">
                        {{ $t('Remove ":map" from ":name"?', { map: mapToRemove?.name, name: maplist.name }) }}
                    </p>

                    <p v-if="removeError" class="text-sm text-red-300 bg-red-600/20 border border-red-500/50 rounded-lg px-3 py-2">
                        {{ removeError }}
                    </p>
                </div>
            </template>

            <template #footer>
                <button
                    @click="cancelRemoveMap"
                    :disabled="removing"
                    class="px-4 py-2 bg-gray-600 hover:bg-gray-700 disabled:cursor-not-allowed text-white rounded-lg font-semibold transition">
                    {{ $t('Cancel') }}
                </button>

                <button
                    @click="removeMap"
                    :disabled="removing"
                    class="ml-2 px-4 py-2 bg-red-600 hover:bg-red-700 disabled:bg-gray-600 disabled:cursor-not-allowed text-white rounded-lg font-semibold transition">
                    {{ removing ? $t('Removing...') : $t('Remove') }}
                </button>
            </template>
        </DialogModal>

    </div>
</template>

<style scoped>
/* Background Animation */
@keyframes bgShift {
    0%, 100% {
        transform: translate(0, 0) scale(1);
        opacity: 0.2;
    }
    33% {
        transform: translate(-10px, 10px) scale(1.05);
        opacity: 0.25;
    }
    66% {
        transform: translate(10px, -10px) scale(0.95);
        opacity: 0.15;
    }
}

/* Connect Button - same as Servers page */
.connect-button {
    position: relative;
    overflow: hidden;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.4), inset 0 1px 0 rgba(255, 255, 255, 0.1);
}

.connect-button::before {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(135deg,
        rgba(255, 255, 255, 0.15) 0%,
        rgba(255, 255, 255, 0.05) 25%,
        transparent 50%,
        rgba(0, 0, 0, 0.05) 75%,
        rgba(0, 0, 0, 0.1) 100%
    );
    pointer-events: none;
}

.connect-button::after {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
    transition: left 0.5s ease;
}

.connect-button:hover::after {
    left: 100%;
}

.connect-button-vq3 {
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.25), rgba(37, 99, 235, 0.15));
    border: 2px solid rgba(96, 165, 250, 0.4);
}

.connect-button-vq3:hover {
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.35), rgba(37, 99, 235, 0.25));
    border-color: rgba(96, 165, 250, 0.6);
    box-shadow: 0 6px 20px rgba(59, 130, 246, 0.3), inset 0 1px 0 rgba(255, 255, 255, 0.2);
}

.connect-button-cpm {
    background: linear-gradient(135deg, rgba(168, 85, 247, 0.25), rgba(147, 51, 234, 0.15));
    border: 2px solid rgba(192, 132, 252, 0.4);
}

.connect-button-cpm:hover {
    background: linear-gradient(135deg, rgba(168, 85, 247, 0.35), rgba(147, 51, 234, 0.25));
    border-color: rgba(192, 132, 252, 0.6);
    box-shadow: 0 6px 20px rgba(168, 85, 247, 0.3), inset 0 1px 0 rgba(255, 255, 255, 0.2);
}
</style>
