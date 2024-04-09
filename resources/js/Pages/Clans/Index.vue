<script setup>
    import { Head } from '@inertiajs/vue3';
    import { Link } from '@inertiajs/vue3';
    import Pagination from '@/Components/Basic/Pagination.vue';
    import ClanCard from './ClanCard.vue';
    import { ref } from 'vue';
    import InvitePlayerModal from './InvitePlayerModal.vue';
    import KickPlayerModal from './KickPlayerModal.vue';
    import LeaveClanModal from './LeaveClanModal.vue';
    import TransferOwnershipModal from './TransferOwnershipModal.vue';
    import EditClanModal from './EditClanModal.vue';
    import DismantleClanModal from './DismantleClanModal.vue';
    import InvitationsModal from './InvitationsModal.vue';
    import { usePage } from '@inertiajs/vue3';
    import { router } from '@inertiajs/vue3';

    const page = usePage()

    const props = defineProps({
        clans: Object,
        myClan: Object,
        users: Array,
        invitations: Array
    });

    const showInvitiationsModal = ref(false);

    const showInvitePlayer = ref(false);
    const showKickPlayer = ref(false);
    const showTransferOwnership = ref(false);
    const showLeaveClan = ref(false);
    const showEditClan = ref(false);
    const showDismantleClan = ref(false);

    const showInvitations = () => {
        if (page.props.auth?.user) {
            showInvitiationsModal.value = !showInvitiationsModal.value
            return;
        }
        
        router.get('/login')
    }
</script>

<template>
    <div>
        <Head title="Clans" />

        <div class="py-12">
            <div class="max-w-8xl mx-auto sm:px-6 lg:px-8">
                <div class="flex justify-between items-center mb-5 flex-wrap">
                    <h2 class="font-semibold text-3xl text-gray-200 leading-tight">
                        Clans
                    </h2>

                    <div class="flex flex-wrap justify-center">
                        <div @click="showInvitations" class="text-gray-300 bg-grayop-700 cursor-pointer hover:bg-grayop-600 text-center rounded-lg px-3 py-2 mr-2 flex items-center mb-2">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 mr-2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M18 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0ZM3 19.235v-.11a6.375 6.375 0 0 1 12.75 0v.109A12.318 12.318 0 0 1 9.374 21c-2.331 0-4.512-.645-6.374-1.766Z" />
                            </svg>

                            Invitations: {{ invitations.length }}
                        </div>

                        <Link v-if="! myClan" :href="route('clans.manage.create')" class="text-gray-300 bg-grayop-700 cursor-pointer hover:bg-grayop-600 text-center rounded-lg px-3 py-2 mr-2 flex items-center mb-2">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 mr-2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                            </svg>

                            Create New Clan
                        </Link>
                    </div>
                </div>

                <div v-if="myClan">
                    <h2 class="font-semibold text-xl text-gray-200 leading-tight">
                        My Clan
                    </h2>
                    <div class="my-5 rounded-md my-clan">
                        <ClanCard
                            :clan="myClan"
                            manage
                            :InvitePlayer="() => showInvitePlayer = !showInvitePlayer"
                            :KickPlayer="() => showKickPlayer = !showKickPlayer"
                            :TransferOwnership="() => showTransferOwnership = !showTransferOwnership"
                            :LeaveClan="() => showLeaveClan = !showLeaveClan"
                            :EditClan="() => showEditClan = !showEditClan"
                            :DismantleClan="() => showDismantleClan = !showDismantleClan"
                        />
                    </div>
                </div>

                <div class="tech-line-clan"></div>

                <div class="mt-5">
                    <ClanCard v-for="clan in clans.data" :key="clan.id" :clan="clan" />

                    <div v-if="clans.total === 0" class="text-gray-300 text-center text-lg">
                        There are no clans to show yet.
                    </div>
                    
                    <div class="flex justify-center" v-if="clans.total > clans.per_page">
                        <Pagination :current_page="clans.current_page" :last_page="clans.last_page" :link="clans.first_page_url" />
                    </div>
                </div>
            </div>
        </div>

        <div v-if="myClan">
            <InvitePlayerModal v-if="myClan.admin_id === $page.props.auth.user.id" :show="showInvitePlayer" :close="() => showInvitePlayer = false" :players="users" />

            <KickPlayerModal v-if="myClan.admin_id === $page.props.auth.user.id" :show="showKickPlayer" :close="() => showKickPlayer = false" :users="myClan.players" />
    
            <LeaveClanModal v-if="myClan.admin_id !== $page.props.auth.user.id" :show="showLeaveClan" :close="() => showLeaveClan = false" />

            <TransferOwnershipModal v-if="myClan.admin_id === $page.props.auth.user.id" :show="showTransferOwnership" :close="() => showTransferOwnership = false" :users="myClan.players" :clan="myClan" />

            <EditClanModal v-if="myClan.admin_id === $page.props.auth.user.id" :show="showEditClan" :close="() => showEditClan = false" :clan="myClan" />

            <DismantleClanModal v-if="myClan.admin_id === $page.props.auth.user.id" :show="showDismantleClan" :close="() => showDismantleClan = false" />
        </div>

        <InvitationsModal
            :show="showInvitiationsModal"
            :close="() => showInvitiationsModal = false"
            :invitations="invitations"
            :myClan="myClan"
        />
    </div>
</template>

<style>
    .my-clan {
        background-color: #5398ff34;
    }

    .tech-line-clan {
        background: linear-gradient(90deg,#ffffff42,#2f90ff,#ffffff42);
        box-shadow: 0 0 50px 4px #0a7cffb2;
        height: 1px;
        margin-top: 5px;
        max-width: 100%;
    }
</style>