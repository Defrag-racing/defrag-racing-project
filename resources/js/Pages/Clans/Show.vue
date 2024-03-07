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

    const props = defineProps({
        clan: Object,
        players: Array,
    });
</script>

<template>
    <div>
        <Head title="Clans" />

        <div class="py-12">
            <div class="max-w-8xl mx-auto sm:px-6 lg:px-8">
                <div class="flex justify-between items-center mb-5 flex-wrap">
                    <h2 class="font-semibold text-3xl text-gray-200 leading-tight">
                        <img class="h-20 w-20 rounded-full object-cover mr-3" :src="'/storage/' + clan.image">
            
                        <h2 class="text-xl text-gray-300 hover:underline" v-html="q3tohtml(clan.name)"></h2>
                    </h2>
                </div>
            </div>
        </div>
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