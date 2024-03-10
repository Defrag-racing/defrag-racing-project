<script setup>
    import { useForm } from '@inertiajs/vue3';
    import Tournament from '@/Pages/Tournaments/Tournament.vue';
    import { Link } from '@inertiajs/vue3';
    import { ref } from 'vue';
    import InvitePlayerModal from './InvitePlayerModal.vue';

    const props = defineProps({
        tournament: Object,
        teams: Array,
        myTeam: Object
    });
</script>

<template>
    <Tournament :tournament="tournament" tab="Teams">
        <div v-for="team in teams">
            <div class="flex justify-center items-center justify-between">
                <div class="text-2xl text-center" v-html="q3tohtml(team.name)"></div>
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
    </Tournament>
</template>

<style>
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