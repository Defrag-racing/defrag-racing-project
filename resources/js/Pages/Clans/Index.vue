<script setup>
    import { Head } from '@inertiajs/vue3';
    import { Link } from '@inertiajs/vue3';
    import Pagination from '@/Components/Basic/Pagination.vue';
    import ClanCard from './ClanCard.vue';
    import { ref, computed, onMounted, watch } from 'vue';
    import InvitePlayerModal from './InvitePlayerModal.vue';
    import KickPlayerModal from './KickPlayerModal.vue';
    import LeaveClanModal from './LeaveClanModal.vue';
    import TransferOwnershipModal from './TransferOwnershipModal.vue';
    import DismantleClanModal from './DismantleClanModal.vue';
    import InvitationsModal from './InvitationsModal.vue';
    import ConfirmTransferOwnershipModal from './ConfirmTransferOwnershipModal.vue';
    import MemberNoteEditor from './MemberNoteEditor.vue';
    import { Cropper } from 'vue-advanced-cropper';
import 'vue-advanced-cropper/dist/style.css';
import { t } from '@/utils/i18n';
import { useDeferredProps } from '@/utils/deferredProps';
import PlayerSelectDefrag from '@/Components/Basic/PlayerSelectDefrag2.vue';
    import { usePage } from '@inertiajs/vue3';
    import { router } from '@inertiajs/vue3';
    import { useForm } from '@inertiajs/vue3';
    import InputError from '@/Components/Laravel/InputError.vue';
    import PrimaryButton from '@/Components/Laravel/PrimaryButton.vue';
    import InputLabel from '@/Components/Laravel/InputLabel.vue';
    import TextInput from '@/Components/Laravel/TextInput.vue';
    import SecondaryButton from '@/Components/Laravel/SecondaryButton.vue';

    const page = usePage()

    const props = defineProps({
        clans: Object,
        myClan: Object,
        users: Array,
        invitations: Array,
        joinRequests: Array,
        blockedUsers: Array,
        currentSort: String,
        currentDir: String
    });

    const clansLoaded = ref(!!props.clans);

    // The list arrives in a second request, and comes again whenever the
    // page is re-rendered without it - a language switch, say.
    useDeferredProps(() => !props.clans, (done) => {
        const start = Date.now();
        router.reload({
            only: ['clans'],
            onFinish: () => {
                done();
                const remaining = 600 - (Date.now() - start);
                if (remaining > 0) {
                    setTimeout(() => { clansLoaded.value = true; }, remaining);
                } else {
                    clansLoaded.value = true;
                }
            }
        });
    });

    const sortLoading = ref(false);
    const sortClans = (field) => {
        const newDir = props.currentSort === field && props.currentDir === 'desc' ? 'asc' : 'desc';
        sortLoading.value = true;
        router.get(route('clans.index'), { sort: field, dir: newDir }, {
            preserveState: true,
            preserveScroll: true,
            only: ['clans', 'currentSort', 'currentDir'],
            onFinish: () => { sortLoading.value = false; },
        });
    };

    const showInvitiationsModal = ref(false);

    const showInvitePlayer = ref(false);
    const usersLoaded = ref(!!props.users);
    watch(() => showInvitePlayer.value, (val) => {
        if (val && !usersLoaded.value) {
            router.reload({ only: ['users'], onFinish: () => { usersLoaded.value = true; } });
        }
    });
    const showKickPlayer = ref(false);
    const showTransferOwnership = ref(false);
    const showLeaveClan = ref(false);
    const showEditClan = ref(false);
    const showDismantleClan = ref(false);
    const showEditMemberNotes = ref(false);

    // Invite Player Form
    const inviteUserId = ref([]);
    const inviteForm = useForm({
        _method: 'POST',
        player_id: []
    });

    const submitInviteForm = () => {
        if (inviteUserId.value.length > 0) {
            inviteForm.player_id = inviteUserId.value[0];
        } else {
            return;
        }

        inviteForm.post(route('clans.manage.invite'), {
            errorBag: 'submitInviteForm',
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                showInvitePlayer.value = false;
                inviteUserId.value = [];
                router.reload({ only: ['clans', 'myClan', 'joinRequests', 'blockedUsers'] });
            }
        });
    };

    // Kick Player Form
    const kickUserId = ref([]);
    const kickForm = useForm({
        _method: 'POST',
        player_id: []
    });

    const submitKickForm = () => {
        if (kickUserId.value.length > 0) {
            kickForm.player_id = kickUserId.value[0];
        } else {
            return;
        }

        kickForm.post(route('clans.manage.kick'), {
            errorBag: 'submitKickForm',
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                showKickPlayer.value = false;
                kickUserId.value = [];
                router.reload({ only: ['clans', 'myClan'] });
            }
        });
    };

    // Transfer Ownership
    const transferUserId = ref([]);
    const transferForm = useForm({
        _method: 'POST',
        player_id: []
    });
    const showTransferConfirm = ref(false);

    const submitTransferForm = () => {
        if (transferUserId.value.length > 0) {
            transferForm.player_id = transferUserId.value[0];
            showTransferConfirm.value = true;
        }
    };

    const finalTransferSubmit = () => {
        transferForm.post(route('clans.manage.transfer'), {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                showTransferOwnership.value = false;
                showTransferConfirm.value = false;
                transferUserId.value = [];
                router.reload({ only: ['clans', 'myClan'] });
            }
        });
    };

    // Leave Clan
    const leaveForm = useForm({
        _method: 'POST'
    });

    const submitLeaveForm = () => {
        leaveForm.post(route('clans.manage.leave'), {
            errorBag: 'submitLeaveForm',
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                showLeaveClan.value = false;
                router.reload({ only: ['clans', 'myClan'] });
            }
        });
    };

    // Dismantle Clan
    const dismantleForm = useForm({
        _method: 'POST'
    });

    const submitDismantleForm = () => {
        dismantleForm.post(route('clans.manage.dismantle'), {
            errorBag: 'submitDismantleForm',
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                showDismantleClan.value = false;
                router.reload({ only: ['clans', 'myClan'] });
            }
        });
    };

    // Join Requests Management
    const showJoinRequests = ref(false);
    const showBlockedUsers = ref(false);
    const requestForm = useForm({ _method: 'POST' });

    const acceptRequest = (invitation) => {
        requestForm.post(route('clans.manage.request.accept', invitation.id), {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => { router.reload({ only: ['clans', 'myClan', 'joinRequests', 'blockedUsers'] }); }
        });
    };

    const rejectRequest = (invitation) => {
        requestForm.post(route('clans.manage.request.reject', invitation.id), {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => { router.reload({ only: ['joinRequests'] }); }
        });
    };

    const blockRequest = (invitation) => {
        requestForm.post(route('clans.manage.request.block', invitation.id), {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => { router.reload({ only: ['joinRequests', 'blockedUsers'] }); }
        });
    };

    const unblockUser = (userId) => {
        if (!props.myClan) return;
        requestForm.delete(route('clans.manage.unblock', { clan: props.myClan.id, user: userId }), {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => { router.reload({ only: ['blockedUsers'] }); }
        });
    };

    // Create Clan modal
    const showCreateClanModal = ref(false);
    const createClanForm = useForm({ _method: 'POST', name: '', image: null });
    const createClanImagePreview = ref(null);
    const createClanImageInput = ref(null);

    const selectCreateClanImage = () => createClanImageInput.value?.click();
    const updateCreateClanImage = () => {
        const image = createClanImageInput.value?.files[0];
        if (!image || !image.type.startsWith('image/')) return;
        const reader = new FileReader();
        reader.onload = (e) => { createClanImagePreview.value = e.target.result; };
        reader.readAsDataURL(image);
        createClanForm.image = image;
    };
    const submitCreateClan = () => {
        createClanForm.post(route('clans.manage.store'), {
            preserveScroll: true,
            onSuccess: () => {
                showCreateClanModal.value = false;
                createClanForm.reset();
                createClanImagePreview.value = null;
            }
        });
    };

    // Request to Join from Index page
    const showRequestJoinModal = ref(false);
    const requestJoinClanId = ref([]);
    const requestJoinForm = useForm({ _method: 'POST' });

    const submitRequestJoin = () => {
        if (requestJoinClanId.value.length === 0) return;

        // We need to build the route with the selected clan ID
        const clanId = requestJoinClanId.value[0];
        requestJoinForm.post(`/clans/${clanId}/request-join`, {
            preserveScroll: true,
            onSuccess: () => {
                showRequestJoinModal.value = false;
                requestJoinClanId.value = [];
            }
        });
    };

    // Build a list of clans available for requesting (from the paginated clans list)
    const availableClansForRequest = computed(() => {
        if (!props.clans?.data) return [];
        return props.clans.data
            .filter(c => !c.hidden && !c.banned);
    });

    const showInvitations = () => {
        if (page.props.auth?.user) {
            showInvitiationsModal.value = !showInvitiationsModal.value
            return;
        }

        router.get('/login')
    }

    // Edit Clan Form
    const imagePreview = ref(null);
    const imageInput = ref(null);
    const backgroundPreview = ref(null);
    const backgroundInput = ref(null);
    const showImageCropper = ref(false);
    const showBackgroundCropper = ref(false);
    const tempImageSrc = ref(null);
    const tempBackgroundSrc = ref(null);

    const form = useForm({
        _method: 'POST',
        name: props.myClan?.name || '',
        tag: props.myClan?.tag || '',
        image: null,
        background: null,
        name_effect: props.myClan?.name_effect || 'none',
        name_shadow_enabled: Boolean(props.myClan?.name_shadow_enabled),
        name_shadow_color: props.myClan?.name_shadow_color || '#3b82f6',
        avatar_effect: props.myClan?.avatar_effect || 'none',
        effect_color: props.myClan?.effect_color || '#60a5fa',
        avatar_effect_color: props.myClan?.avatar_effect_color || '#60a5fa',
        featured_stats: props.myClan?.featured_stats || []
    });

    const hexToRgba = (hex, alpha = 1) => {
        const r = parseInt(hex.slice(1, 3), 16);
        const g = parseInt(hex.slice(3, 5), 16);
        const b = parseInt(hex.slice(5, 7), 16);
        return `rgba(${r}, ${g}, ${b}, ${alpha})`;
    };

    const previewEffectColor = computed(() => form.effect_color || '#60a5fa');
    const previewAvatarEffectColor = computed(() => form.avatar_effect_color || '#60a5fa');
    const previewShadowStyle = computed(() => {
        // Only show shadow if explicitly enabled
        if (form.name_shadow_enabled === true || form.name_shadow_enabled === 1) {
            const color = form.name_shadow_color || '#3b82f6';
            return `text-shadow: 0 0 40px ${hexToRgba(color, 0.5)}, 0 0 80px ${hexToRgba(color, 0.3)};`;
        }
        return 'text-shadow: none;';
    });

    const submitForm = () => {
        form.post(route('clans.manage.update', props.myClan.id), {
            errorBag: 'submitForm',
            preserveScroll: true,
            preserveState: true,
            onSuccess: (page) => {
                // Update form with fresh data from server
                const updatedClan = page.props.myClan;
                form.name = updatedClan.name;
                form.tag = updatedClan.tag;
                form.name_effect = updatedClan.name_effect || 'none';
                form.name_shadow_enabled = Boolean(updatedClan.name_shadow_enabled);
                form.name_shadow_color = updatedClan.name_shadow_color || '#3b82f6';
                form.avatar_effect = updatedClan.avatar_effect || 'none';
                form.effect_color = updatedClan.effect_color || '#60a5fa';
                form.avatar_effect_color = updatedClan.avatar_effect_color || '#60a5fa';
                form.featured_stats = updatedClan.featured_stats || [];

                // Reset file inputs
                imagePreview.value = null;
                backgroundPreview.value = null;
                form.reset('image', 'background');

                router.reload({ only: ['clans', 'myClan'] });
            }
        });
    };

    const selectNewImage = () => {
        imageInput.value.click();
    };

    const updateimagePreview = () => {
        const image = imageInput.value.files[0];

        if (! image) {
            return;
        };

        if (! image.type.startsWith('image/')) {
            form.errors.image = t('The file must be an Image.');
            imageInput.value = '';
            return;
        }

        // 1MB size limit for avatar
        const maxSize = 1 * 1024 * 1024; // 1MB in bytes
        if (image.size > maxSize) {
            form.errors.image = t('The avatar image must be smaller than 1MB.');
            imageInput.value.value = '';
            return;
        }

        const reader = new FileReader();
        form.errors.image = '';

        reader.onload = (e) => {
            tempImageSrc.value = e.target.result;
            showImageCropper.value = true;
        };

        reader.readAsDataURL(image);
    };

    const imageCropper = ref(null);

    const handleImageCrop = () => {
        if (!imageCropper.value) return;

        const { canvas } = imageCropper.value.getResult();

        canvas.toBlob((blob) => {
            const reader = new FileReader();
            reader.onload = (e) => {
                imagePreview.value = e.target.result;
            };
            reader.readAsDataURL(blob);

            form.image = new File([blob], 'avatar.jpg', { type: 'image/jpeg' });
            showImageCropper.value = false;
            tempImageSrc.value = null;
        }, 'image/jpeg', 0.92);
    };

    const cancelImageCrop = () => {
        showImageCropper.value = false;
        tempImageSrc.value = null;
        imageInput.value.value = '';
    };

    const selectNewBackground = () => {
        backgroundInput.value.click();
    };

    const updateBackgroundPreview = () => {
        const background = backgroundInput.value.files[0];

        if (! background) {
            return;
        };

        if (! background.type.startsWith('image/')) {
            form.errors.background = t('The file must be an Image.');
            backgroundInput.value = '';
            return;
        }

        // 5MB size limit
        const maxSize = 5 * 1024 * 1024; // 5MB in bytes
        if (background.size > maxSize) {
            form.errors.background = t('The image must be smaller than 5MB.');
            backgroundInput.value.value = '';
            return;
        }

        const reader = new FileReader();
        form.errors.background = '';

        reader.onload = (e) => {
            tempBackgroundSrc.value = e.target.result;
            showBackgroundCropper.value = true;
        };

        reader.readAsDataURL(background);
    };

    const backgroundCropper = ref(null);

    const handleBackgroundCrop = () => {
        if (!backgroundCropper.value) return;

        const { canvas } = backgroundCropper.value.getResult();

        canvas.toBlob((blob) => {
            const reader = new FileReader();
            reader.onload = (e) => {
                backgroundPreview.value = e.target.result;
            };
            reader.readAsDataURL(blob);

            form.background = new File([blob], 'background.jpg', { type: 'image/jpeg' });
            showBackgroundCropper.value = false;
            tempBackgroundSrc.value = null;
        }, 'image/jpeg', 0.92);
    };

    const cancelBackgroundCrop = () => {
        showBackgroundCropper.value = false;
        tempBackgroundSrc.value = null;
        backgroundInput.value.value = '';
    };
</script>

<template>
    <div class="pb-4">
        <Head :title="$t('Clans')" />

        <!-- Header Section -->
        <div class="relative bg-gradient-to-b from-black/25 via-black/10 to-transparent pt-6 pb-96 pointer-events-none">
            <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8 pointer-events-auto">
                <div class="flex flex-col md:flex-row justify-between items-center gap-6">
                    <div>
                        <h1 class="text-2xl md:text-3xl font-black text-gray-300/90 mb-2">{{ $t('Clans') }}</h1>
                        <div class="flex items-center gap-2 text-gray-400">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" />
                            </svg>
                            <span class="text-sm font-semibold">{{ $t('Join forces with other players') }}</span>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-3">
                        <template v-if="$page.props.auth.user">
                            <button
                                @click="showInvitations"
                                class="flex items-center gap-2 px-6 py-3 bg-black/40 backdrop-blur-sm hover:bg-gray-900/60 border border-blue-500/30 hover:border-blue-500/50 rounded-xl text-white transition-all duration-300 hover:scale-105"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
                                </svg>
                                <span>{{ $t('Invitations') }}</span>
                                <span v-if="invitations.length > 0" class="px-2 py-0.5 bg-blue-500 rounded-full text-xs font-bold">{{ invitations.length }}</span>
                            </button>

                            <template v-if="!$page.props.isVerified">
                                <Link href="/email/verify" class="flex items-center gap-2 px-6 py-3 bg-red-600/80 hover:bg-red-500 text-white font-bold rounded-xl transition-all duration-300 text-sm">
                                    {{ $t('Verify Email to Join or Create') }}
                                </Link>
                            </template>
                            <template v-else>
                                <button
                                    v-if="! myClan"
                                    @click="showRequestJoinModal = true"
                                    class="flex items-center gap-2 px-6 py-3 bg-gradient-to-r from-green-600/20 to-green-800/20 hover:from-green-600/30 hover:to-green-800/30 border border-green-500/30 hover:border-green-500/50 rounded-xl text-white transition-all duration-300 hover:scale-105"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0ZM4 19.235v-.11a6.375 6.375 0 0 1 12.75 0v.109A12.318 12.318 0 0 1 10.374 21c-2.331 0-4.512-.645-6.374-1.766Z" />
                                    </svg>
                                    <span>{{ $t('Request to Join') }}</span>
                                </button>

                                <button
                                    v-if="! myClan"
                                    @click="showCreateClanModal = true"
                                    class="flex items-center gap-2 px-6 py-3 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-500 hover:to-blue-600 rounded-xl text-white font-bold transition-all duration-300 hover:scale-105 shadow-lg hover:shadow-blue-500/50"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                    </svg>
                                    {{ $t('Create New Clan') }}
                                </button>
                            </template>
                        </template>
                        <Link
                            v-else
                            :href="route('login')"
                            class="flex items-center gap-2 px-6 py-3 bg-gray-700/50 border border-white/10 text-gray-400 font-bold rounded-lg transition-all duration-300 hover:bg-gray-600/50 hover:text-gray-300"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                            </svg>
                            {{ $t('Log in to create or join a clan') }}
                        </Link>
                    </div>
                </div>
            </div>
        </div>

        <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8" style="margin-top: -22rem;">
            <!-- My Clan Section -->
            <div v-if="myClan" class="mb-12 bg-black/40 backdrop-blur-sm rounded-xl border border-white/5 p-6">
                <h2 class="text-2xl font-bold text-white mb-4 flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6 text-blue-400">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.562.562 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5Z" />
                    </svg>
                    {{ $t('My Clan') }}
                </h2>
                <div class="relative">
                    <div class="absolute inset-0 bg-gradient-to-r from-blue-500/5 to-blue-700/5 pointer-events-none"></div>
                    <div class="relative">
                        <ClanCard
                            :clan="myClan"
                            manage
                            highlighted
                            :InvitePlayer="() => { showInvitePlayer = !showInvitePlayer; showKickPlayer = false; showTransferOwnership = false; showLeaveClan = false; showDismantleClan = false; showEditClan = false; showEditMemberNotes = false; showJoinRequests = false; }"
                            :KickPlayer="() => { showKickPlayer = !showKickPlayer; showInvitePlayer = false; showTransferOwnership = false; showLeaveClan = false; showDismantleClan = false; showEditClan = false; showEditMemberNotes = false; showJoinRequests = false; }"
                            :TransferOwnership="() => { showTransferOwnership = !showTransferOwnership; showInvitePlayer = false; showKickPlayer = false; showLeaveClan = false; showDismantleClan = false; showEditClan = false; showEditMemberNotes = false; showJoinRequests = false; }"
                            :LeaveClan="() => { showLeaveClan = !showLeaveClan; showInvitePlayer = false; showKickPlayer = false; showTransferOwnership = false; showDismantleClan = false; showEditClan = false; showEditMemberNotes = false; showJoinRequests = false; }"
                            :EditClan="() => { showEditClan = !showEditClan; showInvitePlayer = false; showKickPlayer = false; showTransferOwnership = false; showLeaveClan = false; showDismantleClan = false; showEditMemberNotes = false; showJoinRequests = false; }"
                            :DismantleClan="() => { showDismantleClan = !showDismantleClan; showInvitePlayer = false; showKickPlayer = false; showTransferOwnership = false; showLeaveClan = false; showEditClan = false; showEditMemberNotes = false; showJoinRequests = false; }"
                            :EditMemberNotes="() => { showEditMemberNotes = !showEditMemberNotes; showInvitePlayer = false; showKickPlayer = false; showTransferOwnership = false; showLeaveClan = false; showDismantleClan = false; showEditClan = false; showJoinRequests = false; }"
                            :JoinRequests="() => { showJoinRequests = !showJoinRequests; showInvitePlayer = false; showKickPlayer = false; showTransferOwnership = false; showLeaveClan = false; showDismantleClan = false; showEditClan = false; showEditMemberNotes = false; showBlockedUsers = false; }"
                            :joinRequestsCount="joinRequests ? joinRequests.length : 0"
                        />

                        <!-- Edit Clan Section -->
                        <div v-if="showEditClan && myClan.admin_id === $page.props.auth.user.id" class="mt-6">
                            <div class="bg-black/40 backdrop-blur-sm rounded-2xl border border-white/10">
                                <div class="px-6 py-4 bg-white/5 flex items-center justify-between">
                                    <h3 class="text-lg font-medium text-white">{{ $t('Edit Clan') }}</h3>
                                    <PrimaryButton @click="submitForm" type="button">
                                        {{ $t('Save Changes') }}
                                    </PrimaryButton>
                                </div>

                                <form @submit.prevent="submitForm" class="p-6 space-y-8">
                                    <!-- Basic Information Section -->
                                    <div class="space-y-4">
                                        <h4 class="text-base font-bold text-white uppercase tracking-wide border-b border-white/20 pb-2">{{ $t('Basic Information') }}</h4>
                                        <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-300 mb-2">
                                                {{ $t('Clan Name:') }} <span v-html="q3tohtml(form.name)"></span>
                                            </label>
                                            <TextInput
                                                id="name"
                                                v-model="form.name"
                                                type="text"
                                                class="w-full"
                                                required
                                                autocomplete="name"
                                            />
                                            <div class="text-xs text-gray-400 mt-2">
                                                {{ $t('Quake3 color codes:') }} ^1Red^2Green
                                            </div>
                                            <InputError class="mt-2" :message="form.errors.name" />
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-300 mb-2">
                                                {{ $t('Clan Tag:') }} <span v-if="form.tag" v-html="q3tohtml(form.tag)"></span>
                                            </label>
                                            <TextInput
                                                id="tag"
                                                v-model="form.tag"
                                                type="text"
                                                class="w-full"
                                                :placeholder="$t('e.g. TRY')"
                                                maxlength="10"
                                            />
                                            <div class="text-xs text-gray-400 mt-2">
                                                {{ $t('Short tag (optional, 2-10 chars). Quake3 color codes:') }} ^1Red^2Green
                                            </div>
                                            <InputError class="mt-2" :message="form.errors.tag" />
                                        </div>
                                        </div>
                                    </div>

                                    <!-- Visual Effects Section -->
                                    <div class="space-y-4">
                                        <h4 class="text-base font-bold text-white uppercase tracking-wide border-b border-white/20 pb-2">{{ $t('Visual Effects') }}</h4>
                                        <div>
                                        <label class="block text-sm font-semibold text-gray-200 mb-2">{{ $t('Clan Name Effect, Color & Shadow') }}</label>
                                        <div class="text-xs text-gray-400 mb-3">
                                            {{ $t('Animated effect and shadow to highlight your clan name on the detail page') }}
                                        </div>

                                        <div class="grid grid-cols-2 gap-4">
                                            <!-- Effect & Color Picker Column -->
                                            <div class="space-y-3">
                                                <select
                                                    v-model="form.name_effect"
                                                    class="w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
                                                >
                                                    <option value="none">{{ $t('None') }}</option>
                                                    <option value="particles">{{ $t('Particles') }}</option>
                                                    <option value="orbs">{{ $t('Orbs') }}</option>
                                                    <option value="lines">{{ $t('Lines') }}</option>
                                                    <option value="matrix">{{ $t('Matrix') }}</option>
                                                    <option value="glitch">{{ $t('Glitch') }}</option>
                                                    <option value="wave">{{ $t('Wave') }}</option>
                                                    <option value="neon">{{ $t('Neon Pulse') }}</option>
                                                    <option value="rgb">{{ $t('RGB Split') }}</option>
                                                    <option value="flicker">{{ $t('Flicker') }}</option>
                                                    <option value="hologram">{{ $t('Hologram') }}</option>
                                                </select>

                                                <div>
                                                    <label class="block text-xs text-gray-400 mb-1">{{ $t('Effect Color') }}</label>
                                                    <div class="flex items-center gap-3">
                                                        <input
                                                            type="color"
                                                            v-model="form.effect_color"
                                                            class="h-10 w-16 rounded-md border border-gray-300 dark:border-gray-700 bg-gray-900 cursor-pointer"
                                                        />
                                                        <input
                                                            type="text"
                                                            v-model="form.effect_color"
                                                            placeholder="#60a5fa"
                                                            maxlength="7"
                                                            class="flex-1 border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
                                                        />
                                                    </div>
                                                </div>

                                                <div>
                                                    <div class="flex items-center justify-between mb-2">
                                                        <label class="block text-xs text-gray-400">{{ $t('Shadow Color') }}</label>
                                                        <label class="flex items-center gap-2 cursor-pointer">
                                                            <input
                                                                type="checkbox"
                                                                v-model="form.name_shadow_enabled"
                                                                class="w-4 h-4 rounded border-gray-300 dark:border-gray-700 bg-gray-900 text-blue-600 focus:ring-blue-500 dark:focus:ring-blue-600 cursor-pointer"
                                                            />
                                                            <span class="text-xs text-gray-400">{{ $t('Enable') }}</span>
                                                        </label>
                                                    </div>
                                                    <div class="flex items-center gap-3" :class="{ 'opacity-50 pointer-events-none': !form.name_shadow_enabled }">
                                                        <input
                                                            type="color"
                                                            v-model="form.name_shadow_color"
                                                            :disabled="!form.name_shadow_enabled"
                                                            class="h-10 w-16 rounded-md border border-gray-300 dark:border-gray-700 bg-gray-900 cursor-pointer"
                                                        />
                                                        <input
                                                            type="text"
                                                            v-model="form.name_shadow_color"
                                                            :disabled="!form.name_shadow_enabled"
                                                            placeholder="#3b82f6"
                                                            maxlength="7"
                                                            class="flex-1 border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
                                                        />
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Effect Preview Column -->
                                            <div class="bg-black/40 backdrop-blur-sm rounded-lg py-12 px-4 relative overflow-hidden border border-white/10">
                                                <div class="relative py-6 px-8">
                                                    <!-- Particles Effect Preview -->
                                                    <div v-if="form.name_effect === 'particles'" class="absolute inset-0 -inset-x-24 -inset-y-12 pointer-events-none">
                                                        <div class="absolute top-[20%] left-[15%] w-3 h-3 rounded-full" :style="`background-color: ${previewEffectColor}; box-shadow: 0 0 20px ${hexToRgba(previewEffectColor, 0.8)}; animation: particle-float 3s ease-in-out infinite;`"></div>
                                                        <div class="absolute top-[70%] left-[25%] w-2 h-2 rounded-full" :style="`background-color: ${previewEffectColor}; box-shadow: 0 0 15px ${hexToRgba(previewEffectColor, 0.8)}; animation: particle-float 2.5s ease-in-out infinite; animation-delay: 0.5s;`"></div>
                                                        <div class="absolute top-[40%] left-[80%] w-3 h-3 rounded-full" :style="`background-color: ${previewEffectColor}; box-shadow: 0 0 20px ${hexToRgba(previewEffectColor, 0.8)}; animation: particle-float 2.8s ease-in-out infinite; animation-delay: 1s;`"></div>
                                                    </div>

                                                    <!-- Orbs Effect Preview -->
                                                    <div v-if="form.name_effect === 'orbs'" class="absolute inset-0 -inset-x-32 -inset-y-16 pointer-events-none overflow-hidden">
                                                        <div class="absolute -left-8 top-1/2 -translate-y-1/2 w-40 h-40 rounded-full" :style="`animation: orb-float 6s ease-in-out infinite; box-shadow: 0 0 60px 30px ${hexToRgba(previewEffectColor, 0.3)};`"></div>
                                                        <div class="absolute -right-8 top-1/2 -translate-y-1/2 w-48 h-48 rounded-full" :style="`animation: orb-float 6s ease-in-out infinite; animation-delay: 1s; box-shadow: 0 0 60px 30px ${hexToRgba(previewEffectColor, 0.3)};`"></div>
                                                    </div>

                                                    <!-- Lines Effect Preview -->
                                                    <div v-if="form.name_effect === 'lines'" class="absolute inset-0 -inset-x-24 -inset-y-4 pointer-events-none">
                                                        <div class="absolute top-0 left-0 right-0 h-[2px]" :style="`background: linear-gradient(to right, transparent, ${hexToRgba(previewEffectColor, 0.6)}, transparent); box-shadow: 0 0 15px ${hexToRgba(previewEffectColor, 0.5)}; animation: line-scan 3s linear infinite;`"></div>
                                                        <div class="absolute bottom-0 left-0 right-0 h-[2px]" :style="`background: linear-gradient(to right, transparent, ${hexToRgba(previewEffectColor, 0.6)}, transparent); box-shadow: 0 0 15px ${hexToRgba(previewEffectColor, 0.5)}; animation: line-scan 3s linear infinite; animation-delay: 1.5s;`"></div>
                                                    </div>

                                                    <!-- Matrix Effect Preview -->
                                                    <div v-if="form.name_effect === 'matrix'" class="absolute inset-0 -inset-x-24 -inset-y-12 pointer-events-none overflow-hidden">
                                                        <div class="absolute top-0 left-[20%] w-[2px] h-full" :style="`background: linear-gradient(to bottom, transparent, ${hexToRgba(previewEffectColor, 0.6)}, transparent); box-shadow: 0 0 8px ${hexToRgba(previewEffectColor, 0.6)}; animation: matrix-fall 2.5s linear infinite;`"></div>
                                                        <div class="absolute top-0 left-[40%] w-[2px] h-full" :style="`background: linear-gradient(to bottom, transparent, ${hexToRgba(previewEffectColor, 0.6)}, transparent); box-shadow: 0 0 8px ${hexToRgba(previewEffectColor, 0.6)}; animation: matrix-fall 2s linear infinite; animation-delay: 0.5s;`"></div>
                                                        <div class="absolute top-0 left-[60%] w-[2px] h-full" :style="`background: linear-gradient(to bottom, transparent, ${hexToRgba(previewEffectColor, 0.6)}, transparent); box-shadow: 0 0 8px ${hexToRgba(previewEffectColor, 0.6)}; animation: matrix-fall 2.8s linear infinite; animation-delay: 1s;`"></div>
                                                        <div class="absolute top-0 left-[80%] w-[2px] h-full" :style="`background: linear-gradient(to bottom, transparent, ${hexToRgba(previewEffectColor, 0.6)}, transparent); box-shadow: 0 0 8px ${hexToRgba(previewEffectColor, 0.6)}; animation: matrix-fall 2.3s linear infinite; animation-delay: 1.5s;`"></div>
                                                    </div>

                                                    <!-- Glitch Effect Preview -->
                                                    <div v-if="form.name_effect === 'glitch'" class="absolute inset-0 pointer-events-none flex items-center justify-center">
                                                        <h1 class="absolute text-4xl font-black" :style="`color: ${hexToRgba(previewEffectColor, 0.3)}; animation: glitch-text 2s infinite; transform: translate(2px, -1px);`" v-html="q3tohtml(form.name)"></h1>
                                                        <h1 class="absolute text-4xl font-black" :style="`color: ${hexToRgba(previewEffectColor, 0.3)}; animation: glitch-text 2s infinite; animation-delay: 0.2s; transform: translate(-2px, 1px);`" v-html="q3tohtml(form.name)"></h1>
                                                    </div>

                                                    <!-- Wave Effect Preview -->
                                                    <div v-if="form.name_effect === 'wave'" class="absolute inset-0 pointer-events-none flex items-center justify-center">
                                                        <h1 class="absolute text-4xl font-black" :style="`color: ${hexToRgba(previewEffectColor, 0.4)}; animation: wave-text 1s ease-in-out infinite;`" v-html="q3tohtml(form.name)"></h1>
                                                        <h1 class="absolute text-4xl font-black" :style="`color: ${hexToRgba(previewEffectColor, 0.4)}; animation: wave-text 1s ease-in-out infinite; animation-delay: 0.1s;`" v-html="q3tohtml(form.name)"></h1>
                                                    </div>

                                                    <!-- Neon Pulse Effect Preview -->
                                                    <div v-if="form.name_effect === 'neon'" class="absolute inset-0 pointer-events-none flex items-center justify-center">
                                                        <h1 class="absolute text-4xl font-black" :style="`color: ${previewEffectColor}; text-shadow: 0 0 20px ${previewEffectColor}, 0 0 40px ${previewEffectColor}; animation: neon-pulse 1.5s ease-in-out infinite;`" v-html="q3tohtml(form.name)"></h1>
                                                    </div>

                                                    <!-- RGB Split Effect Preview -->
                                                    <div v-if="form.name_effect === 'rgb'" class="absolute inset-0 pointer-events-none flex items-center justify-center">
                                                        <h1 class="absolute text-4xl font-black" :style="`color: ${previewEffectColor}; animation: rgb-split 0.8s ease-in-out infinite;`" v-html="q3tohtml(form.name)"></h1>
                                                    </div>

                                                    <!-- Flicker Effect Preview -->
                                                    <div v-if="form.name_effect === 'flicker'" class="absolute inset-0 pointer-events-none flex items-center justify-center">
                                                        <h1 class="absolute text-4xl font-black" :style="`color: ${previewEffectColor}; text-shadow: 0 0 10px ${previewEffectColor}; animation: flicker 3s linear infinite;`" v-html="q3tohtml(form.name)"></h1>
                                                    </div>

                                                    <!-- Hologram Effect Preview -->
                                                    <div v-if="form.name_effect === 'hologram'" class="absolute inset-0 pointer-events-none flex items-center justify-center">
                                                        <h1 class="absolute text-4xl font-black" :style="`color: ${hexToRgba(previewEffectColor, 0.85)}; text-shadow: 0 0 15px ${previewEffectColor}; animation: hologram 2s ease-in-out infinite;`" v-html="q3tohtml(form.name)"></h1>
                                                        <div class="absolute inset-0 pointer-events-none" :style="`background: repeating-linear-gradient(0deg, transparent, transparent 2px, ${hexToRgba(previewEffectColor, 0.05)} 2px, ${hexToRgba(previewEffectColor, 0.05)} 4px);`"></div>
                                                    </div>

                                                    <!-- Clan name preview -->
                                                    <h1 class="relative text-4xl font-black text-white text-center drop-shadow-2xl" :style="previewShadowStyle" v-html="q3tohtml(form.name)"></h1>
                                                </div>
                                            </div>
                                        </div>

                                        <InputError :message="form.errors.name_effect" class="mt-2" />
                                        <InputError :message="form.errors.effect_color" class="mt-2" />
                                        <InputError :message="form.errors.name_shadow_color" class="mt-2" />
                                        </div>
                                    </div>

                                    <!-- Images Section -->
                                    <div class="space-y-4">
                                        <h4 class="text-base font-bold text-white uppercase tracking-wide border-b border-white/20 pb-2">{{ $t('Images') }}</h4>

                                    <!-- Clan Avatar -->
                                    <div>
                                        <input
                                            id="image"
                                            ref="imageInput"
                                            type="file"
                                            class="hidden"
                                            accept="image/*"
                                            @change="updateimagePreview"
                                        >

                                        <label class="block text-sm font-medium text-gray-300 mb-2">{{ $t('Clan Avatar') }}</label>
                                        <div class="text-xs text-gray-400 mb-3">
                                            {{ $t('Max 1MB. GIF supported (no compression).') }}
                                        </div>

                                        <div class="flex items-center gap-4">
                                            <div v-if="imagePreview" class="shrink-0">
                                                <img :src="imagePreview" class="rounded-full h-20 w-20 object-cover border-2 border-white/20">
                                            </div>
                                            <div v-else-if="myClan.image" class="shrink-0">
                                                <img :src="'/storage/' + myClan.image" class="rounded-full h-20 w-20 object-cover border-2 border-white/20">
                                            </div>

                                            <SecondaryButton v-if="!showImageCropper" type="button" @click.prevent="selectNewImage">
                                                {{ $t('Change Avatar') }}
                                            </SecondaryButton>
                                        </div>

                                        <!-- Image Cropper -->
                                        <div v-if="showImageCropper" class="mt-4">
                                            <div class="bg-black rounded-lg overflow-hidden border border-white/10" style="height: 400px;">
                                                <Cropper
                                                    ref="imageCropper"
                                                    :src="tempImageSrc"
                                                    :stencil-props="{
                                                        aspectRatio: 1,
                                                        movable: true,
                                                        resizable: true
                                                    }"
                                                    :canvas="{
                                                        maxWidth: 400,
                                                        maxHeight: 400
                                                    }"
                                                    class="cropper"
                                                />
                                            </div>
                                            <div class="flex gap-3 mt-3">
                                                <PrimaryButton type="button" @click="handleImageCrop">
                                                    {{ $t('Crop & Apply') }}
                                                </PrimaryButton>
                                                <SecondaryButton type="button" @click="cancelImageCrop">
                                                    {{ $t('Cancel') }}
                                                </SecondaryButton>
                                            </div>
                                        </div>

                                        <InputError :message="form.errors.image" class="mt-2" />
                                    </div>

                                    <!-- Avatar Effect -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-300 mb-2">{{ $t('Avatar Effect & Color') }}</label>
                                        <div class="text-xs text-gray-400 mb-3">
                                            {{ $t('Visual effect and color applied to your clan avatar') }}
                                        </div>

                                        <div class="grid grid-cols-2 gap-4">
                                            <!-- Effect Selector & Color Picker -->
                                            <div class="space-y-3">
                                                <select
                                                    v-model="form.avatar_effect"
                                                    class="w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
                                                >
                                                    <option value="none">{{ $t('None') }}</option>
                                                    <option value="glow">{{ $t('Glow') }}</option>
                                                    <option value="pulse">{{ $t('Pulse') }}</option>
                                                    <option value="ring">{{ $t('Rotating Ring') }}</option>
                                                    <option value="shine">{{ $t('Shine') }}</option>
                                                    <option value="border">{{ $t('Animated Border') }}</option>
                                                    <option value="particles">{{ $t('Particle Orbit') }}</option>
                                                    <option value="spin">{{ $t('Spin') }}</option>
                                                </select>

                                                <div class="flex items-center gap-3">
                                                    <input
                                                        type="color"
                                                        v-model="form.avatar_effect_color"
                                                        class="h-10 w-16 rounded-md border border-gray-300 dark:border-gray-700 bg-gray-900 cursor-pointer"
                                                    />
                                                    <input
                                                        type="text"
                                                        v-model="form.avatar_effect_color"
                                                        placeholder="#60a5fa"
                                                        maxlength="7"
                                                        class="flex-1 border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
                                                    />
                                                </div>
                                            </div>

                                            <!-- Avatar Preview -->
                                            <div class="bg-black/40 backdrop-blur-sm rounded-lg p-6 flex items-center justify-center border border-white/10">
                                                <div class="relative">
                                                    <!-- Glow Effect -->
                                                    <div v-if="form.avatar_effect === 'glow'" class="absolute inset-0 rounded-full opacity-50" :style="`box-shadow: 0 0 20px ${previewAvatarEffectColor};`"></div>

                                                    <!-- Pulse Effect -->
                                                    <div v-if="form.avatar_effect === 'pulse'" class="absolute inset-0 rounded-full animate-ping opacity-75" :style="`background-color: ${previewAvatarEffectColor};`"></div>

                                                    <!-- Ring Effect -->
                                                    <div v-if="form.avatar_effect === 'ring'" class="absolute -inset-2">
                                                        <div class="w-full h-full rounded-full border-4 border-t-transparent animate-spin" :style="`border-color: ${previewAvatarEffectColor}; border-top-color: transparent;`"></div>
                                                    </div>

                                                    <!-- Shine Effect -->
                                                    <div v-if="form.avatar_effect === 'shine'" class="absolute inset-0 rounded-full overflow-hidden">
                                                        <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white to-transparent opacity-40 animate-shine"></div>
                                                    </div>

                                                    <!-- Animated Border -->
                                                    <div v-if="form.avatar_effect === 'border'" class="absolute -inset-1 rounded-full opacity-75 animate-pulse" :style="`background: linear-gradient(45deg, ${previewAvatarEffectColor}, transparent, ${previewAvatarEffectColor}); background-size: 200% 200%; animation: gradient-shift 3s ease infinite;`"></div>

                                                    <!-- Particle Orbit -->
                                                    <div v-if="form.avatar_effect === 'particles'" class="absolute -inset-4">
                                                        <div class="absolute top-0 left-1/2 w-2 h-2 rounded-full animate-orbit-1" :style="`background-color: ${previewAvatarEffectColor}; box-shadow: 0 0 10px ${previewAvatarEffectColor};`"></div>
                                                        <div class="absolute top-0 left-1/2 w-2 h-2 rounded-full animate-orbit-2" :style="`background-color: ${previewAvatarEffectColor}; box-shadow: 0 0 10px ${previewAvatarEffectColor};`"></div>
                                                        <div class="absolute top-0 left-1/2 w-2 h-2 rounded-full animate-orbit-3" :style="`background-color: ${previewAvatarEffectColor}; box-shadow: 0 0 10px ${previewAvatarEffectColor};`"></div>
                                                    </div>

                                                    <!-- Spin Effect -->
                                                    <div v-if="form.avatar_effect === 'spin'" class="absolute inset-0 animate-spin-slow"></div>

                                                    <img
                                                        :src="imagePreview || (myClan.image ? '/storage/' + myClan.image : '/images/null.jpg')"
                                                        class="relative h-20 w-20 rounded-full object-cover ring-2 ring-white/20"
                                                        :class="{'animate-spin-slow': form.avatar_effect === 'spin'}"
                                                    />
                                                </div>
                                            </div>
                                        </div>

                                        <InputError :message="form.errors.avatar_effect" class="mt-2" />
                                    </div>

                                    <!-- Clan Background -->
                                    <div>
                                        <input
                                            id="background"
                                            ref="backgroundInput"
                                            type="file"
                                            class="hidden"
                                            accept="image/*"
                                            @change="updateBackgroundPreview"
                                        >

                                        <label class="block text-sm font-medium text-gray-300 mb-2">{{ $t('Clan Background') }}</label>
                                        <div class="text-xs text-gray-400 mb-3">
                                            {{ $t('Recommended size: 1920x400px (wide banner format). Max 5MB. GIF supported (no compression).') }}
                                        </div>

                                        <div v-if="backgroundPreview || myClan.background" class="mb-3">
                                            <div class="grid grid-cols-2 gap-4">
                                                <!-- Detail Page Preview -->
                                                <div>
                                                    <div class="text-xs text-gray-400 mb-2">{{ $t('Detail page preview:') }}</div>
                                                    <img
                                                        :src="backgroundPreview || '/storage/' + myClan.background"
                                                        class="rounded-lg w-full h-40 object-cover border border-white/20"
                                                    >
                                                </div>
                                                <!-- List Card Preview -->
                                                <div>
                                                    <div class="text-xs text-gray-400 mb-2">{{ $t('Card preview:') }}</div>
                                                    <img
                                                        :src="backgroundPreview || '/storage/' + myClan.background"
                                                        class="rounded-lg w-full h-24 object-cover border border-white/20"
                                                    >
                                                </div>
                                            </div>
                                        </div>

                                        <SecondaryButton v-if="!showBackgroundCropper" type="button" @click.prevent="selectNewBackground">
                                            {{ myClan.background ? $t('Change Background') : $t('Add Background') }}
                                        </SecondaryButton>

                                        <!-- Background Cropper -->
                                        <div v-if="showBackgroundCropper" class="mt-4">
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
                                            <div class="flex gap-3 mt-3">
                                                <PrimaryButton type="button" @click="handleBackgroundCrop">
                                                    {{ $t('Crop & Apply') }}
                                                </PrimaryButton>
                                                <SecondaryButton type="button" @click="cancelBackgroundCrop">
                                                    {{ $t('Cancel') }}
                                                </SecondaryButton>
                                            </div>
                                        </div>

                                        <InputError :message="form.errors.background" class="mt-2" />
                                    </div>
                                    </div>

                                    <!-- Other Settings Section -->
                                    <div class="space-y-4">
                                        <h4 class="text-base font-bold text-white uppercase tracking-wide border-b border-white/20 pb-2">{{ $t('Other Settings') }}</h4>

                                    <!-- Featured Stats (Multi-Select) -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-300 mb-2">{{ $t('Featured Stats (Select up to 3)') }}</label>
                                        <div class="text-xs text-gray-400 mb-3">
                                            {{ $t('Choose which stats to display on your clan card. You can select 1-3 stats or none.') }}
                                        </div>

                                        <div class="space-y-2">
                                            <!-- Total Records -->
                                            <label class="flex items-center gap-3 p-3 rounded-lg bg-white/5 hover:bg-white/10 transition-all cursor-pointer">
                                                <input
                                                    type="checkbox"
                                                    value="total_records"
                                                    v-model="form.featured_stats"
                                                    :disabled="!form.featured_stats.includes('total_records') && form.featured_stats.length >= 3"
                                                    class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
                                                />
                                                <div class="flex items-center gap-2 flex-1">
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-blue-400">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />
                                                    </svg>
                                                    <div>
                                                        <div class="text-sm font-medium text-gray-200">{{ $t('Total Records') }}</div>
                                                        <div class="text-xs text-gray-500">{{ $t('Display total number of records') }}</div>
                                                    </div>
                                                </div>
                                            </label>

                                            <!-- World Records -->
                                            <label class="flex items-center gap-3 p-3 rounded-lg bg-white/5 hover:bg-white/10 transition-all cursor-pointer">
                                                <input
                                                    type="checkbox"
                                                    value="world_records"
                                                    v-model="form.featured_stats"
                                                    :disabled="!form.featured_stats.includes('world_records') && form.featured_stats.length >= 3"
                                                    class="rounded border-gray-300 text-yellow-600 shadow-sm focus:ring-yellow-500 disabled:opacity-50 disabled:cursor-not-allowed"
                                                />
                                                <div class="flex items-center gap-2 flex-1">
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-yellow-400">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 18.75h-9m9 0a3 3 0 0 1 3 3h-15a3 3 0 0 1 3-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.007 0H9.497m5.007 0a7.454 7.454 0 0 1-.982-3.172M9.497 14.25a7.454 7.454 0 0 0 .981-3.172M5.25 4.236c-.982.143-1.954.317-2.916.52A6.003 6.003 0 0 0 7.73 9.728M5.25 4.236V4.5c0 2.108.966 3.99 2.48 5.228M5.25 4.236V2.721C7.456 2.41 9.71 2.25 12 2.25c2.291 0 4.545.16 6.75.47v1.516M7.73 9.728a6.726 6.726 0 0 0 2.748 1.35m8.272-6.842V4.5c0 2.108-.966 3.99-2.48 5.228m2.48-5.492a46.32 46.32 0 0 1 2.916.52 6.003 6.003 0 0 1-5.395 4.972m0 0a6.726 6.726 0 0 1-2.749 1.35m0 0a6.772 6.772 0 0 1-3.044 0" />
                                                    </svg>
                                                    <div>
                                                        <div class="text-sm font-medium text-gray-200">{{ $t('World Records') }}</div>
                                                        <div class="text-xs text-gray-500">{{ $t('Display #1 world record positions') }}</div>
                                                    </div>
                                                </div>
                                            </label>

                                            <!-- Podium Finishes -->
                                            <label class="flex items-center gap-3 p-3 rounded-lg bg-white/5 hover:bg-white/10 transition-all cursor-pointer">
                                                <input
                                                    type="checkbox"
                                                    value="podium_finishes"
                                                    v-model="form.featured_stats"
                                                    :disabled="!form.featured_stats.includes('podium_finishes') && form.featured_stats.length >= 3"
                                                    class="rounded border-gray-300 text-purple-600 shadow-sm focus:ring-purple-500 disabled:opacity-50 disabled:cursor-not-allowed"
                                                />
                                                <div class="flex items-center gap-2 flex-1">
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-purple-400">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.562.562 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5Z" />
                                                    </svg>
                                                    <div>
                                                        <div class="text-sm font-medium text-gray-200">{{ $t('Top 3 Positions') }}</div>
                                                        <div class="text-xs text-gray-500">{{ $t('Display podium finishes (1st, 2nd, 3rd)') }}</div>
                                                    </div>
                                                </div>
                                            </label>

                                            <!-- Top 10 Positions -->
                                            <label class="flex items-center gap-3 p-3 rounded-lg bg-white/5 hover:bg-white/10 transition-all cursor-pointer">
                                                <input
                                                    type="checkbox"
                                                    value="top10_positions"
                                                    v-model="form.featured_stats"
                                                    :disabled="!form.featured_stats.includes('top10_positions') && form.featured_stats.length >= 3"
                                                    class="rounded border-gray-300 text-green-600 shadow-sm focus:ring-green-500 disabled:opacity-50 disabled:cursor-not-allowed"
                                                />
                                                <div class="flex items-center gap-2 flex-1">
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-green-400">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 0 0 6 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0 1 18 16.5h-2.25m-7.5 0h7.5m-7.5 0-1 3m8.5-3 1 3m0 0 .5 1.5m-.5-1.5h-9.5m0 0-.5 1.5m.75-9 3-3 2.148 2.148A12.061 12.061 0 0 1 16.5 7.605" />
                                                    </svg>
                                                    <div>
                                                        <div class="text-sm font-medium text-gray-200">{{ $t('Top 10 Positions') }}</div>
                                                        <div class="text-xs text-gray-500">{{ $t('Display top 10 finishes') }}</div>
                                                    </div>
                                                </div>
                                            </label>

                                            <!-- Average WR Age -->
                                            <label class="flex items-center gap-3 p-3 rounded-lg bg-white/5 hover:bg-white/10 transition-all cursor-pointer">
                                                <input
                                                    type="checkbox"
                                                    value="avg_wr_age"
                                                    v-model="form.featured_stats"
                                                    :disabled="!form.featured_stats.includes('avg_wr_age') && form.featured_stats.length >= 3"
                                                    class="rounded border-gray-300 text-orange-600 shadow-sm focus:ring-orange-500 disabled:opacity-50 disabled:cursor-not-allowed"
                                                />
                                                <div class="flex items-center gap-2 flex-1">
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-orange-400">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                                    </svg>
                                                    <div>
                                                        <div class="text-sm font-medium text-gray-200">{{ $t('Average WR Age') }}</div>
                                                        <div class="text-xs text-gray-500">{{ $t('Display average age of world records') }}</div>
                                                    </div>
                                                </div>
                                            </label>

                                            <!-- Map Coverage -->
                                            <label class="flex items-center gap-3 p-3 rounded-lg bg-white/5 hover:bg-white/10 transition-all cursor-pointer">
                                                <input
                                                    type="checkbox"
                                                    value="map_coverage"
                                                    v-model="form.featured_stats"
                                                    :disabled="!form.featured_stats.includes('map_coverage') && form.featured_stats.length >= 3"
                                                    class="rounded border-gray-300 text-cyan-600 shadow-sm focus:ring-cyan-500 disabled:opacity-50 disabled:cursor-not-allowed"
                                                />
                                                <div class="flex items-center gap-2 flex-1">
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-cyan-400">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 6.75V15m6-6v8.25m.503 3.498 4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934c-.317.159-.69.159-1.006 0L9.503 3.252a1.125 1.125 0 0 0-1.006 0L3.622 5.689C3.24 5.88 3 6.27 3 6.695V19.18c0 .836.88 1.38 1.628 1.006l3.869-1.934c.317-.159.69-.159 1.006 0l4.994 2.497c.317.158.69.158 1.006 0Z" />
                                                    </svg>
                                                    <div>
                                                        <div class="text-sm font-medium text-gray-200">{{ $t('Map Coverage') }}</div>
                                                        <div class="text-xs text-gray-500">{{ $t('Display map completion percentage') }}</div>
                                                    </div>
                                                </div>
                                            </label>
                                        </div>

                                        <div class="text-xs text-gray-500 mt-2">
                                            {{ $t('Selected: :count/3', { count: form.featured_stats.length }) }}
                                        </div>

                                        <InputError :message="form.errors.featured_stats" class="mt-2" />
                                    </div>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Member Notes Section -->
                        <div v-if="showEditMemberNotes && myClan.admin_id === $page.props.auth.user.id" class="mt-6">
                            <div class="bg-black/40 backdrop-blur-sm rounded-2xl border border-white/10">
                                <div class="px-6 py-4 bg-white/5">
                                    <h3 class="text-lg font-medium text-white">{{ $t('Member Details') }}</h3>
                                </div>

                                <div class="p-6 space-y-4">
                                    <MemberNoteEditor
                                        v-for="player in myClan.players"
                                        :key="player.id"
                                        :player="player"
                                        :clan="myClan"
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- Invite Player Panel -->
                        <div v-if="showInvitePlayer && myClan.admin_id === $page.props.auth.user.id" class="mt-6 relative z-50">
                            <div class="bg-black/40 backdrop-blur-sm rounded-2xl border border-white/10">
                                <div class="px-6 py-4 bg-white/5">
                                    <h3 class="text-lg font-medium text-white">{{ $t('Invite Player') }}</h3>
                                </div>

                                <form @submit.prevent="submitInviteForm" class="p-6 space-y-4">
                                    <div class="relative z-50">
                                        <label class="block text-sm font-medium text-gray-300 mb-2">{{ $t('Select Player') }}</label>
                                        <PlayerSelectDefrag
                                            id="invite_player_id"
                                            v-model="inviteUserId"
                                            :values="[]"
                                            :options="users"
                                        />
                                        <InputError :message="inviteForm.errors.player_id" class="mt-2" />
                                    </div>

                                    <div v-if="inviteUserId.length > 0" class="text-sm text-gray-400">
                                        {{ $t('Selected:') }} <span class="text-white font-medium" v-html="q3tohtml(users.find(p => p.id === inviteUserId[0])?.name || '')"></span>
                                    </div>

                                    <PrimaryButton type="submit" class="w-full justify-center">
                                        {{ $t('Invite Player') }}
                                    </PrimaryButton>
                                </form>
                            </div>
                        </div>

                        <!-- Kick Player Panel -->
                        <div v-if="showKickPlayer && myClan.admin_id === $page.props.auth.user.id" class="mt-6 relative z-50">
                            <div class="bg-black/40 backdrop-blur-sm rounded-2xl border border-white/10">
                                <div class="px-6 py-4 bg-white/5">
                                    <h3 class="text-lg font-medium text-white">{{ $t('Kick Player') }}</h3>
                                </div>

                                <form @submit.prevent="submitKickForm" class="p-6 space-y-4">
                                    <div class="relative z-50">
                                        <label class="block text-sm font-medium text-gray-300 mb-2">{{ $t('Select Player') }}</label>
                                        <PlayerSelectDefrag
                                            id="kick_player_id"
                                            v-model="kickUserId"
                                            :values="[]"
                                            :options="myClan.players.filter(p => p.user.id !== myClan.admin_id).map(p => p.user)"
                                        />
                                        <InputError :message="kickForm.errors.player_id" class="mt-2" />
                                    </div>

                                    <div v-if="kickUserId.length > 0" class="text-sm text-gray-400">
                                        {{ $t('Selected:') }} <span class="text-white font-medium" v-html="q3tohtml(myClan.players.map(p => p.user).find(p => p.id === kickUserId[0])?.name || '')"></span>
                                    </div>

                                    <PrimaryButton type="submit" class="w-full justify-center bg-orange-600 hover:bg-orange-500">
                                        {{ $t('Kick Player') }}
                                    </PrimaryButton>
                                </form>
                            </div>
                        </div>

                        <!-- Transfer Ownership Panel -->
                        <div v-if="showTransferOwnership && myClan.admin_id === $page.props.auth.user.id" class="mt-6 relative z-50">
                            <div class="bg-black/40 backdrop-blur-sm rounded-2xl border border-white/10">
                                <div class="px-6 py-4 bg-white/5">
                                    <h3 class="text-lg font-medium text-white">{{ $t('Transfer Ownership') }}</h3>
                                </div>

                                <form @submit.prevent="submitTransferForm" class="p-6 space-y-4">
                                    <div class="relative z-50">
                                        <label class="block text-sm font-medium text-gray-300 mb-2">{{ $t('Select New Owner') }}</label>
                                        <PlayerSelectDefrag
                                            id="transfer_player_id"
                                            v-model="transferUserId"
                                            :values="[]"
                                            :options="myClan.players.map(p => p.user)"
                                        />
                                        <InputError :message="transferForm.errors.player_id" class="mt-2" />
                                    </div>

                                    <div v-if="transferUserId.length > 0" class="text-sm text-gray-400">
                                        {{ $t('Selected:') }} <span class="text-white font-medium" v-html="q3tohtml(myClan.players.map(p => p.user).find(p => p.id === transferUserId[0])?.name || '')"></span>
                                    </div>

                                    <PrimaryButton type="submit" class="w-full justify-center bg-sky-600 hover:bg-sky-500">
                                        {{ $t('Transfer Ownership') }}
                                    </PrimaryButton>
                                </form>
                            </div>
                        </div>

                        <!-- Leave Clan Panel -->
                        <div v-if="showLeaveClan && myClan.admin_id !== $page.props.auth.user.id" class="mt-6">
                            <div class="bg-black/40 backdrop-blur-sm rounded-2xl border border-white/10">
                                <div class="px-6 py-4 bg-white/5">
                                    <h3 class="text-lg font-medium text-white">{{ $t('Leave Clan') }}</h3>
                                </div>

                                <div class="p-6 space-y-4">
                                    <p class="text-gray-300">{{ $t("Are you sure you want to leave the clan? To join again you will need to be invited by the clan's admin!") }}</p>
                                    <div class="flex gap-3">
                                        <PrimaryButton @click="submitLeaveForm" class="flex-1 justify-center bg-red-600 hover:bg-red-500">
                                            {{ $t('Leave Clan') }}
                                        </PrimaryButton>
                                        <PrimaryButton @click="showLeaveClan = false" class="flex-1 justify-center bg-gray-600 hover:bg-gray-500">
                                            {{ $t('Cancel') }}
                                        </PrimaryButton>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Dismantle Clan Panel -->
                        <div v-if="showDismantleClan && myClan.admin_id === $page.props.auth.user.id" class="mt-6">
                            <div class="bg-black/40 backdrop-blur-sm rounded-2xl border border-white/10">
                                <div class="px-6 py-4 bg-white/5">
                                    <h3 class="text-lg font-medium text-white">{{ $t('Dismantle Clan') }}</h3>
                                </div>

                                <div class="p-6 space-y-4">
                                    <p class="text-gray-300">{{ $t('Are you sure you want to delete this clan? This action cannot be undone.') }}</p>
                                    <div class="flex gap-3">
                                        <PrimaryButton @click="submitDismantleForm" class="flex-1 justify-center bg-red-600 hover:bg-red-500">
                                            {{ $t('Delete Clan') }}
                                        </PrimaryButton>
                                        <PrimaryButton @click="showDismantleClan = false" class="flex-1 justify-center bg-gray-600 hover:bg-gray-500">
                                            {{ $t('Cancel') }}
                                        </PrimaryButton>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Pending Join Requests Panel (admin only) -->
                        <div v-if="showJoinRequests && myClan.admin_id === $page.props.auth.user.id" class="mt-6">
                            <div class="bg-black/40 backdrop-blur-sm rounded-2xl border border-white/10">
                                <div class="px-6 py-4 bg-white/5 flex items-center justify-between">
                                    <h3 class="text-lg font-medium text-white">{{ $t('Pending Join Requests') }}</h3>
                                    <button
                                        @click="showBlockedUsers = !showBlockedUsers"
                                        class="text-xs px-3 py-1.5 rounded-lg bg-white/5 hover:bg-white/10 text-gray-400 hover:text-white border border-white/10 transition-all"
                                    >
                                        {{ showBlockedUsers ? $t('Show Requests') : $t('Blocked Users') }}
                                        <span v-if="!showBlockedUsers && blockedUsers && blockedUsers.length > 0" class="ml-1 text-red-400">({{ blockedUsers.length }})</span>
                                    </button>
                                </div>

                                <!-- Requests List -->
                                <div v-if="!showBlockedUsers" class="p-6 space-y-3">
                                    <div v-if="!joinRequests || joinRequests.length === 0" class="text-center py-8">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-10 h-10 text-gray-600 mx-auto mb-2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" />
                                        </svg>
                                        <p class="text-gray-500 text-sm">{{ $t('No pending join requests') }}</p>
                                    </div>

                                    <div v-for="request in joinRequests" :key="request.id" class="flex items-center justify-between bg-black/30 rounded-xl p-4 border border-white/5">
                                        <Link :href="route('profile.index', request.user.id)" class="flex items-center gap-3 hover:opacity-80 transition-opacity">
                                            <img
                                                :src="request.user.profile_photo_path ? `/storage/${request.user.profile_photo_path}` : '/images/null.jpg'"
                                                class="h-10 w-10 rounded-full object-cover border border-white/20"
                                                :alt="request.user.plain_name"
                                            />
                                            <div>
                                                <div class="text-white font-semibold" v-html="q3tohtml(request.user.name)"></div>
                                                <div class="flex items-center gap-1.5">
                                                    <img
                                                        v-if="request.user.country"
                                                        :src="`/images/flags/${request.user.country}.png`"
                                                        class="w-4 h-3 opacity-70"
                                                        onerror="this.src='/images/flags/_404.png'"
                                                    />
                                                    <span class="text-xs text-gray-500">{{ new Date(request.created_at).toLocaleDateString() }}</span>
                                                </div>
                                            </div>
                                        </Link>

                                        <div class="flex items-center gap-2">
                                            <button
                                                @click="acceptRequest(request)"
                                                :disabled="requestForm.processing"
                                                :title="$t('Accept')"
                                                class="p-2 rounded-full bg-green-600/20 hover:bg-green-600/40 text-green-400 hover:text-green-300 border border-green-500/30 transition-all"
                                            >
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                                </svg>
                                            </button>
                                            <button
                                                @click="rejectRequest(request)"
                                                :disabled="requestForm.processing"
                                                :title="$t('Reject')"
                                                class="p-2 rounded-full bg-red-600/20 hover:bg-red-600/40 text-red-400 hover:text-red-300 border border-red-500/30 transition-all"
                                            >
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                                </svg>
                                            </button>
                                            <button
                                                @click="blockRequest(request)"
                                                :disabled="requestForm.processing"
                                                :title="$t('Block - reject and permanently prevent future requests')"
                                                class="p-2 rounded-full bg-gray-600/20 hover:bg-gray-600/40 text-gray-400 hover:text-gray-300 border border-gray-500/30 transition-all"
                                            >
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 0 0 5.636 5.636m12.728 12.728A9 9 0 0 1 5.636 5.636m12.728 12.728L5.636 5.636" />
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Blocked Users List -->
                                <div v-else class="p-6 space-y-3">
                                    <div v-if="!blockedUsers || blockedUsers.length === 0" class="text-center py-8">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-10 h-10 text-gray-600 mx-auto mb-2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 0 0 5.636 5.636m12.728 12.728A9 9 0 0 1 5.636 5.636m12.728 12.728L5.636 5.636" />
                                        </svg>
                                        <p class="text-gray-500 text-sm">{{ $t('No blocked users') }}</p>
                                    </div>

                                    <div v-for="blocked in blockedUsers" :key="blocked.id" class="flex items-center justify-between bg-black/30 rounded-xl p-4 border border-white/5">
                                        <Link :href="route('profile.index', blocked.user.id)" class="flex items-center gap-3 hover:opacity-80 transition-opacity">
                                            <img
                                                :src="blocked.user.profile_photo_path ? `/storage/${blocked.user.profile_photo_path}` : '/images/null.jpg'"
                                                class="h-10 w-10 rounded-full object-cover border border-white/20 opacity-50"
                                                :alt="blocked.user.plain_name"
                                            />
                                            <div>
                                                <div class="text-gray-400 font-semibold" v-html="q3tohtml(blocked.user.name)"></div>
                                                <div class="text-xs text-red-400/60">{{ $t('Blocked') }}</div>
                                            </div>
                                        </Link>

                                        <button
                                            @click="unblockUser(blocked.user.id)"
                                            :disabled="requestForm.processing"
                                            class="px-3 py-1.5 text-sm rounded-lg bg-white/5 hover:bg-white/10 text-gray-400 hover:text-white border border-white/10 transition-all"
                                        >
                                            {{ $t('Unblock') }}
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            <!-- Divider -->
            <div v-if="myClan" class="h-px bg-gradient-to-r from-transparent via-blue-500/50 to-transparent mb-12"></div>

            <!-- All Clans Section -->
            <div>
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
                    <h2 class="text-2xl font-bold text-white">{{ $t('All Clans') }}</h2>

                    <!-- Sorting Controls -->
                    <div class="flex flex-wrap gap-2 relative z-10 bg-black/40 backdrop-blur-sm rounded-xl border border-white/5 p-2">
                        <button
                            @click="sortClans('name')"
                            :class="[
                                'flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold transition-all duration-300',
                                currentSort === 'name'
                                    ? 'bg-blue-600 text-white shadow-lg shadow-blue-500/30'
                                    : 'bg-white/5 text-gray-400 hover:bg-white/10 hover:text-white'
                            ]"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25H12" />
                            </svg>
                            {{ $t('Name') }}
                            <span v-if="currentSort === 'name'" class="ml-1">
                                {{ currentDir === 'asc' ? '↑' : '↓' }}
                            </span>
                        </button>

                        <button
                            @click="sortClans('members')"
                            :class="[
                                'flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold transition-all duration-300',
                                currentSort === 'members'
                                    ? 'bg-green-600 text-white shadow-lg shadow-green-500/30'
                                    : 'bg-white/5 text-gray-400 hover:bg-white/10 hover:text-white'
                            ]"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                            </svg>
                            {{ $t('Members') }}
                            <span v-if="currentSort === 'members'" class="ml-1">
                                {{ currentDir === 'asc' ? '↑' : '↓' }}
                            </span>
                        </button>

                        <button
                            @click="sortClans('wrs')"
                            :class="[
                                'flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold transition-all duration-300',
                                currentSort === 'wrs'
                                    ? 'bg-yellow-600 text-white shadow-lg shadow-yellow-500/30'
                                    : 'bg-white/5 text-gray-400 hover:bg-white/10 hover:text-white'
                            ]"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 18.75h-9m9 0a3 3 0 0 1 3 3h-15a3 3 0 0 1 3-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.007 0H9.497m5.007 0a7.454 7.454 0 0 1-.982-3.172M9.497 14.25a7.454 7.454 0 0 0 .981-3.172M5.25 4.236c-.982.143-1.954.317-2.916.52A6.003 6.003 0 0 0 7.73 9.728M5.25 4.236V4.5c0 2.108.966 3.99 2.48 5.228M5.25 4.236V2.721C7.456 2.41 9.71 2.25 12 2.25c2.291 0 4.545.16 6.75.47v1.516M7.73 9.728a6.726 6.726 0 0 0 2.748 1.35m8.272-6.842V4.5c0 2.108-.966 3.99-2.48 5.228m2.48-5.492a46.32 46.32 0 0 1 2.916.52 6.003 6.003 0 0 1-5.395 4.972m0 0a6.726 6.726 0 0 1-2.749 1.35m0 0a6.772 6.772 0 0 1-3.044 0" />
                            </svg>
                            {{ $t('World Records') }}
                            <span v-if="currentSort === 'wrs'" class="ml-1">
                                {{ currentDir === 'asc' ? '↑' : '↓' }}
                            </span>
                        </button>

                        <button
                            @click="sortClans('top3')"
                            :class="[
                                'flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold transition-all duration-300',
                                currentSort === 'top3'
                                    ? 'bg-purple-600 text-white shadow-lg shadow-purple-500/30'
                                    : 'bg-white/5 text-gray-400 hover:bg-white/10 hover:text-white'
                            ]"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.562.562 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5Z" />
                            </svg>
                            {{ $t('Top 3 Positions') }}
                            <span v-if="currentSort === 'top3'" class="ml-1">
                                {{ currentDir === 'asc' ? '↑' : '↓' }}
                            </span>
                        </button>
                    </div>
                </div>

                <!-- Loading skeleton -->
                <div v-if="!clansLoaded || sortLoading" class="grid grid-cols-1 gap-4">
                    <div v-for="i in 6" :key="i" class="bg-black/40 backdrop-blur-sm rounded-xl p-6 border border-white/10 animate-pulse">
                        <div class="flex items-center gap-4">
                            <div class="w-14 h-14 bg-white/10 rounded-full"></div>
                            <div class="flex-1 space-y-3">
                                <div class="h-5 bg-white/15 rounded w-1/3"></div>
                                <div class="h-4 bg-white/10 rounded w-1/2"></div>
                            </div>
                            <div class="hidden sm:flex gap-3">
                                <div class="w-24 h-16 bg-white/10 rounded-xl"></div>
                                <div class="w-24 h-16 bg-white/10 rounded-xl"></div>
                                <div class="w-24 h-16 bg-white/10 rounded-xl"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-else-if="clans" class="grid grid-cols-1 gap-4">
                    <ClanCard v-for="(clan, index) in clans.data" :key="clan.id" :clan="clan" :style="{ animationDelay: `${index * 50}ms` }" class="animate-slideInUp" />

                    <div v-if="clans && clans.total === 0" class="text-center py-20">
                        <div class="inline-flex items-center justify-center w-20 h-20 bg-white/5 rounded-full mb-4">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-10 h-10 text-gray-500">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" />
                            </svg>
                        </div>
                        <p class="text-gray-400 text-lg">{{ $t('No clans to show yet') }}</p>
                        <p class="text-gray-500 text-sm mt-2">{{ $t('Be the first to create one!') }}</p>
                    </div>

                    <div class="border-t border-white/5 bg-transparent p-3" v-if="clans && clans.total > clans.per_page">
                        <Pagination :current_page="clans.current_page" :last_page="clans.last_page" :link="clans.first_page_url" :only="['clans']" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Transfer Confirmation Modal -->
        <ConfirmTransferOwnershipModal
            v-if="myClan && showTransferConfirm && transferUserId.length > 0"
            :show="showTransferConfirm"
            :close="() => showTransferConfirm = false"
            :submit="finalTransferSubmit"
            :player="myClan.players.map(p => p.user).find(p => p.id === transferForm.player_id)"
            :clan="myClan"
        />

        <InvitationsModal
            :show="showInvitiationsModal"
            :close="() => showInvitiationsModal = false"
            :invitations="invitations"
            :myClan="myClan"
        />

        <!-- Create Clan Modal -->
        <Teleport to="body">
            <div v-if="showCreateClanModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4" @click.self="showCreateClanModal = false">
                    <div class="fixed inset-0 bg-black/60"></div>
                    <div class="relative bg-gray-900 border border-white/10 rounded-2xl shadow-2xl max-w-md w-full p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-lg font-bold text-white">{{ $t('Create New Clan') }}</h3>
                            <button @click="showCreateClanModal = false" class="text-gray-400 hover:text-white p-1 rounded-full hover:bg-white/10 transition">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                        <form @submit.prevent="submitCreateClan" class="space-y-4">
                            <div>
                                <label class="block text-sm font-bold text-gray-300 mb-1" v-html="$t('Clan Name') + ' ' + (createClanForm.name ? '(' + q3tohtml(createClanForm.name) + ')' : '')"></label>
                                <input v-model="createClanForm.name" type="text" required autofocus
                                    class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-lg text-white placeholder-gray-500 focus:outline-none focus:border-blue-500/50 transition-all"
                                    :placeholder="$t('Q3 color codes supported:') + ' ^1Red^2Green'" />
                            </div>
                            <div>
                                <input ref="createClanImageInput" type="file" class="hidden" accept="image/*" @change="updateCreateClanImage" />
                                <label class="block text-sm font-bold text-gray-300 mb-1">{{ $t('Clan Avatar') }}</label>
                                <div class="flex items-center gap-3">
                                    <div v-if="createClanImagePreview" class="shrink-0">
                                        <img :src="createClanImagePreview" class="rounded-full h-14 w-14 object-cover border-2 border-white/20">
                                    </div>
                                    <div v-else class="shrink-0 w-14 h-14 rounded-full bg-gray-700 border-2 border-dashed border-white/20 flex items-center justify-center">
                                        <span class="text-[10px] text-gray-500">{{ $t('No image') }}</span>
                                    </div>
                                    <button type="button" @click="selectCreateClanImage" class="px-3 py-1.5 bg-white/5 border border-white/10 hover:bg-white/10 text-gray-300 text-sm rounded-lg transition-all">
                                        {{ $t('Select Image') }}
                                    </button>
                                </div>
                            </div>
                            <div class="flex gap-3 pt-2">
                                <button type="submit" :disabled="createClanForm.processing"
                                    class="flex-1 py-2.5 bg-blue-600 hover:bg-blue-500 disabled:opacity-50 text-white font-bold rounded-lg transition-colors">
                                    {{ createClanForm.processing ? $t('Creating...') : $t('Create Clan') }}
                                </button>
                                <button type="button" @click="showCreateClanModal = false"
                                    class="px-4 py-2.5 bg-gray-700 hover:bg-gray-600 text-gray-300 rounded-lg transition-colors">
                                    {{ $t('Cancel') }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

        </Teleport>

        <!-- Request to Join Clan Modal -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition-opacity duration-200"
                leave-active-class="transition-opacity duration-150"
                enter-from-class="opacity-0"
                leave-to-class="opacity-0"
            >
                <div v-if="showRequestJoinModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4" @click.self="showRequestJoinModal = false">
                    <div class="fixed inset-0 bg-black/60"></div>
                    <div class="relative bg-gray-900 border border-white/10 rounded-2xl shadow-2xl max-w-lg w-full p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-xl font-bold text-white">{{ $t('Request to Join a Clan') }}</h3>
                            <button @click="showRequestJoinModal = false" class="text-gray-400 hover:text-white p-1 rounded-full hover:bg-white/10 transition">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <p class="text-gray-400 text-sm mb-4">{{ $t('Select a clan from the list to send a join request. The clan admin will be notified.') }}</p>

                        <div class="space-y-2 max-h-[400px] overflow-y-auto pr-1">
                            <div v-if="!clans || !clans.data || availableClansForRequest.length === 0" class="text-center py-8 text-gray-500">
                                {{ $t('No clans available. Browse the list first.') }}
                            </div>

                            <button
                                v-for="clan in availableClansForRequest"
                                :key="clan.id"
                                @click="requestJoinClanId = [clan.id]"
                                :class="[
                                    'w-full flex items-center gap-3 p-3 rounded-xl border transition-all text-left',
                                    requestJoinClanId.includes(clan.id)
                                        ? 'bg-green-600/20 border-green-500/50 ring-1 ring-green-500/30'
                                        : 'bg-white/5 border-white/10 hover:bg-white/10'
                                ]"
                            >
                                <img
                                    :src="clan.image ? `/storage/${clan.image}` : '/images/null.jpg'"
                                    class="h-10 w-10 rounded-full object-cover border border-white/20"
                                />
                                <div class="flex-1 min-w-0">
                                    <div class="text-white font-semibold truncate" v-html="q3tohtml(clan.name)"></div>
                                    <div class="text-xs text-gray-500">{{ $tc(':count member|:count members', clan.players_count) }}</div>
                                </div>
                                <svg v-if="requestJoinClanId.includes(clan.id)" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5 text-green-400 shrink-0">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                </svg>
                            </button>
                        </div>

                        <div class="flex gap-3 mt-5">
                            <button
                                @click="submitRequestJoin"
                                :disabled="requestJoinClanId.length === 0 || requestJoinForm.processing"
                                class="flex-1 px-4 py-2.5 bg-green-600 hover:bg-green-500 disabled:opacity-50 disabled:cursor-not-allowed text-white font-semibold rounded-xl transition-colors"
                            >
                                {{ requestJoinForm.processing ? $t('Sending...') : $t('Send Request') }}
                            </button>
                            <button
                                @click="showRequestJoinModal = false"
                                class="flex-1 px-4 py-2.5 bg-white/5 hover:bg-white/10 text-gray-300 font-semibold rounded-xl border border-white/10 transition-colors"
                            >
                                {{ $t('Cancel') }}
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>

<style scoped>
.cropper {
    height: 400px;
    width: 100%;
}

@keyframes slideInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.animate-slideInUp {
    animation: slideInUp 0.5s ease-out forwards;
    opacity: 0;
}

/* Avatar Effect Animations */
@keyframes shine {
    0% {
        transform: translateX(-100%);
    }
    100% {
        transform: translateX(200%);
    }
}

.animate-shine {
    animation: shine 3s ease-in-out infinite;
}

@keyframes spin-slow {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}

.animate-spin-slow {
    animation: spin-slow 8s linear infinite;
}

@keyframes orbit-1 {
    0% {
        transform: rotate(0deg) translateX(2.5rem) rotate(0deg);
    }
    100% {
        transform: rotate(360deg) translateX(2.5rem) rotate(-360deg);
    }
}

@keyframes orbit-2 {
    0% {
        transform: rotate(120deg) translateX(2.5rem) rotate(-120deg);
    }
    100% {
        transform: rotate(480deg) translateX(2.5rem) rotate(-480deg);
    }
}

@keyframes orbit-3 {
    0% {
        transform: rotate(240deg) translateX(2.5rem) rotate(-240deg);
    }
    100% {
        transform: rotate(600deg) translateX(2.5rem) rotate(-600deg);
    }
}

.animate-orbit-1 {
    animation: orbit-1 4s linear infinite;
}

.animate-orbit-2 {
    animation: orbit-2 4s linear infinite;
}

.animate-orbit-3 {
    animation: orbit-3 4s linear infinite;
}

@keyframes gradient-shift {
    0%, 100% {
        background-position: 0% 50%;
    }
    50% {
        background-position: 100% 50%;
    }
}
</style>
