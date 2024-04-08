<script setup>
    import { Link } from '@inertiajs/vue3';
    import Popper from "vue3-popper";
    import { ref } from 'vue';

    const props = defineProps({
        clan: Object,
        manage: {
            type: Boolean,
            default: false
        },
        InvitePlayer: Function,
        KickPlayer: Function,
        TransferOwnership: Function,
        LeaveClan: Function,
        EditClan: Function,
        DismantleClan: Function
    });

    const showClanManagement = ref(false);
</script>

<template>
    <div class="rounded-md bg-blackop-30 px-5 py-2 mb-5">
        <div class="md:flex items-center justify-between">
            <div>
                <Link :href="route('clans.show', clan.id)" class="flex items-center">
                    <img class="h-8 w-8 rounded-full object-cover mr-3" :src="'/storage/' + clan.image">
    
                    <h2 class="text-xl text-gray-300 hover:underline" v-html="q3tohtml(clan.name)"></h2>
                </Link>
    
                <div class="mt-3 flex text-sm">
                    <div class="text-gray-500 mr-1">Admin: </div>
                    <Link class="text-gray-300" :href="route('profile.index', clan.admin.id)" v-html="q3tohtml(clan.admin.name)"></Link>
                </div>
            </div>

            <div class="flex items-center md:justify-none justify-between">
                <div class="flex items-center">
                    <div v-for="(player, index) in clan.players" :key="player.id">
                        <Popper v-if="index < 5" placement="bottom" arrow hover style="z-index: 100;">
                            <Link class="cursor-pointer relative" :href="route('profile.index', player.user_id)" :style="`left: -${index * 10}px; z-index: ${10 - index}`">
                                <img
                                    class="h-8 w-8 rounded-full object-cover border-2 border-gray-500"
                                    :src="player.user?.profile_photo_path ? '/storage/' + player.user.profile_photo_path : '/images/null.jpg'"
                                >
                            </Link>
                            <template #content>
                                <div class="py-2 px-3 bg-grayop-500 rounded-md">
                                    <div class="text-gray-400" v-html="q3tohtml(player.user?.name)"></div>
                                </div>
                            </template>
                        </Popper>
                    </div>

                    <div v-for="(item, index) in Math.max(0, 5 - clan.players.length)" :key="'it_' + item">
                        <div class="relative h-8 w-8" :style="`left: -${index * 10}px; z-index: ${50 - index}`">
                        </div>
                    </div>
    
                    <div class="text-gray-400 text-sm font-bold relative w-20">
                        <div v-if="clan.players_count > 5">+ {{ clan.players_count - 5 }} More</div>
                    </div>
                </div>

                <div v-if="manage" @click="showClanManagement = !showClanManagement" class="text-white bg-blackop-30 cursor-pointer hover:bg-blackop-20 text-center rounded-lg px-3 py-2 mr-2 flex items-center ml-4">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                    </svg>
                </div>
            </div>
        </div>

        <div v-if="showClanManagement && manage" class="mt-2">
            <div class="w-full bg-gray-700 mb-2" style="height: 1px;"></div>

            <div class="flex items-center justify-center flex-wrap">
                <div v-if="clan.admin_id === $page.props.auth.user.id" @click="InvitePlayer" class="text-gray-100 bg-green-700 cursor-pointer hover:bg-green-600 text-center rounded-lg px-3 py-2 mr-2 flex items-center mb-3">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 mr-2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M18 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0ZM3 19.235v-.11a6.375 6.375 0 0 1 12.75 0v.109A12.318 12.318 0 0 1 9.374 21c-2.331 0-4.512-.645-6.374-1.766Z" />
                    </svg>

                    Invite Player
                </div>

                <div v-if="clan.admin_id === $page.props.auth.user.id" @click="KickPlayer" class="text-gray-100 bg-orange-700 cursor-pointer hover:bg-orange-600 text-center rounded-lg px-3 py-2 mr-2 flex items-center mb-3">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 mr-2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M22 10.5h-6m-2.25-4.125a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0ZM4 19.235v-.11a6.375 6.375 0 0 1 12.75 0v.109A12.318 12.318 0 0 1 10.374 21c-2.331 0-4.512-.645-6.374-1.766Z" />
                    </svg>

                    Kick Player
                </div>

                <div v-if="clan.admin_id === $page.props.auth.user.id" @click="EditClan" class="text-gray-100 bg-gray-700 cursor-pointer hover:bg-gray-600 text-center rounded-lg px-3 py-2 mr-2 flex items-center mb-3">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 mr-2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                    </svg>

                    Edit Clan
                </div>

                <div v-if="clan.admin_id === $page.props.auth.user.id" @click="TransferOwnership" class="text-gray-100 bg-sky-700 cursor-pointer hover:bg-sky-600 text-center rounded-lg px-3 py-2 mr-2 flex items-center text-sm mb-3">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 mr-2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 7.5h-.75A2.25 2.25 0 0 0 4.5 9.75v7.5a2.25 2.25 0 0 0 2.25 2.25h7.5a2.25 2.25 0 0 0 2.25-2.25v-7.5a2.25 2.25 0 0 0-2.25-2.25h-.75m0-3-3-3m0 0-3 3m3-3v11.25m6-2.25h.75a2.25 2.25 0 0 1 2.25 2.25v7.5a2.25 2.25 0 0 1-2.25 2.25h-7.5a2.25 2.25 0 0 1-2.25-2.25v-.75" />
                    </svg>

                    Transfer Ownership
                </div>

                <div v-if="clan.admin_id !== $page.props.auth.user.id" @click="LeaveClan" class="text-gray-100 bg-red-700 cursor-pointer hover:bg-red-600 text-center rounded-lg px-3 py-2 mr-2 flex items-center text-sm mb-3">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 mr-2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9" />
                    </svg>

                    Leave Clan
                </div>

                <div v-if="clan.admin_id === $page.props.auth.user.id" @click="DismantleClan" class="text-gray-100 bg-red-700 cursor-pointer hover:bg-red-600 text-center rounded-lg px-3 py-2 mr-2 flex items-center text-sm mb-3">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 mr-2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9" />
                    </svg>

                    Dismantle Clan
                </div>
            </div>
        </div>
    </div>
</template>
