<script setup>
    import { useForm } from '@inertiajs/vue3';
    import Tournament from '@/Pages/Tournaments/Tournament.vue';
    import { Link } from '@inertiajs/vue3';
    import { ref } from 'vue';
    import InvitePlayerModal from './InvitePlayerModal.vue';

    const props = defineProps({
        tournament: Object,
        team: Object,
        invitations: Array,
        users: Array
    });

    const form = useForm({
        _method: 'POST'
    });

    const acceptInvitation = (id) => {
        form.post(route('tournaments.teams.accept', {
            tournament: props.tournament.id,
            invitation: id
        }), {
            errorBag: 'submitForm',
            preserveScroll: true
        });
    };

    const rejectInvitation = (id) => {
        form.post(route('tournaments.teams.reject', {
            tournament: props.tournament.id,
            invitation: id
        }), {
            errorBag: 'submitForm',
            preserveScroll: true
        });
    };

    const showInvitationModal = ref(false);
</script>

<template>
    <Tournament :tournament="tournament" tab="ManageTeams">
        <div v-if="! team" class="text-center">
            <div class="text-gray-400 mb-5">You are not a part of any team.</div>
            <Link :href="route('tournaments.teams.create', tournament.id)" class="mt-5 text-gray-300 font-bold bg-grayop-700 cursor-pointer hover:bg-grayop-600 text-center rounded-lg p-3">
                Create a new Team
            </Link>
        </div>

        <div v-else>
            <div class="flex justify-center items-center justify-between">
                <div class="text-2xl text-center text-white" v-html="q3tohtml(team.name)"></div>

                <Link :href="route('tournaments.teams.leave', tournament.id)" class="text-gray-100 text-sm bg-grayop-700 cursor-pointer hover:bg-gray-600 text-center rounded-lg px-2 py-1 flex items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 mr-2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 9V5.25A2.25 2.25 0 0 1 10.5 3h6a2.25 2.25 0 0 1 2.25 2.25v13.5A2.25 2.25 0 0 1 16.5 21h-6a2.25 2.25 0 0 1-2.25-2.25V15M12 9l3 3m0 0-3 3m3-3H2.25" />
                    </svg>

                    Leave Team
                </Link>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 mx-5 lg:mx-0">
                <div class="col-span-1 lg:col-span-1 mt-3 lg:mt-0">
                    <div class="text-center text-xl font-medium text-gray-900 dark:text-gray-100">CPM</div>
                    <div class="tech-line-cpm mb-4"></div>
    
                    <Link :href="route('profile.index', team.cpm_player.id)" class="text-gray-400 flex justify-center items-center" v-if="team.cpm_player">
                        <img style="z-index: 2;" class="h-8 w-8 rounded-full object-cover mr-4" :src="team.cpm_player.profile_photo_path ? '/storage/' + team.cpm_player.profile_photo_path : '/images/null.jpg'">
                        <img :src="`/images/flags/${team.cpm_player.country}.png`" :title="team.cpm_player.country" class="w-5 inline mr-2">
                        <div v-html="q3tohtml(team.cpm_player.name)"></div>
                    </Link>

                    <div v-else class="flex justify-center">
                        <span @click="showInvitationModal = true" class="text-gray-300 font-bold bg-grayop-700 cursor-pointer hover:bg-grayop-600 text-center rounded-lg p-3">
                            Invite a VQ3 Player
                        </span>
                    </div>
                </div>
    
                <div class="col-span-1 lg:col-span-1 mt-3 lg:mt-0">
                    <div class="text-center text-xl font-medium text-gray-900 dark:text-gray-100">VQ3</div>
                    <div class="tech-line-vq3 mb-4"></div>
    
                    <Link :href="route('profile.index', team.vq3_player.id)" class="text-gray-400 flex justify-center items-center" v-if="team.vq3_player">
                        <img style="z-index: 2;" class="h-8 w-8 rounded-full object-cover mr-4" :src="team.vq3_player.profile_photo_path ? '/storage/' + team.vq3_player.profile_photo_path : '/images/null.jpg'">
                        <img :src="`/images/flags/${team.vq3_player.country}.png`" :title="team.vq3_player.country" class="w-5 inline mr-2">
                        <div v-html="q3tohtml(team.vq3_player.name)"></div>
                    </Link>

                    <div v-else class="flex justify-center">
                        <span @click="showInvitationModal = true" class="text-gray-300 font-bold bg-grayop-700 cursor-pointer hover:bg-grayop-600 text-center rounded-lg p-3">
                            Invite a VQ3 Player
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div>
            <div class="text-2xl text-center text-gray-300 mt-10">Invitations</div>

            <div class="text-center text-gray-500" v-if="invitations.length === 0">
                You have no team invitations yet.
            </div>

            <div v-else class="mt-2">
                <div v-for="invitation in invitations" class="flex justify-between flex-wrap items-center p-3 rounded-md bg-grayop-700 text-gray-400 mb-3">
                    <div>
                        <span>You are invited to join</span>
                        <span v-html="q3tohtml(invitation.team.name)" class="mx-2 bg-blackop-30 px-3 py-1 rounded-md"></span>
                        <span>As a </span>
                        <span v-if="invitation.cpm" class="text-green-300">CPM</span>
                        <span v-else class="text-blue-500">VQ3</span>
                        <span> Player</span>
                    </div>

                    <div class="flex">
                        <div v-if="!team" @click="acceptInvitation(invitation.id)" title="Accept" class="text-white bg-green-700 cursor-pointer hover:bg-green-600 text-center rounded-full flex items-center p-1 mr-2">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                            </svg>
                        </div>

                        <div @click="rejectInvitation(invitation.id)" title="Reject" class="text-white bg-red-700 cursor-pointer hover:bg-red-600 text-center rounded-full flex items-center p-1">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                            </svg>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <InvitePlayerModal :tournament="tournament" :show="showInvitationModal" :close="() => showInvitationModal = false" :players="users" />
    </Tournament>
</template>

<style scoped>
    .tech-line-cpm {
        background: linear-gradient(90deg,#ffffff42,#7dff88,#ffffff42);
        box-shadow: 0 0 50px 4px #0aff336f;
        height: 1px;
        margin-top: 5px;
        max-width: 100%;
    }

    .tech-line-vq3 {
        background: linear-gradient(90deg,#ffffff42,#5171ff,#ffffff42);
        box-shadow: 0 0 50px 4px #0a0eff6f;
        height: 1px;
        margin-top: 5px;
        max-width: 100%;
    }
</style>