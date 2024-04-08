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
        <div v-for="team in teams" class="mb-5">
            <div class="grid grid-cols-1 md:grid-cols-3 mx-5 lg:mx-0">
                <div class="col-span-1 mt-3 lg:mt-0 order-1 md:order-1">
                    <div class="tech-line-base mb-1"></div>
                    <div class="text-xl text-white" v-html="q3tohtml(team.name)"></div>
                </div>

                <div class="col-span-1 lg:col-span-1 mt-3 lg:mt-0 order-1 md:order-0">
                    <div class="tech-line-cpm mb-1"></div>

                    <Link :href="route('profile.index', team.cpm_player.id)" class="text-gray-400 flex justify-start items-center" v-if="team.cpm_player">
                        <img style="z-index: 2;" class="h-8 w-8 rounded-full object-cover mr-4" :src="team.cpm_player.profile_photo_path ? '/storage/' + team.cpm_player.profile_photo_path : '/images/null.jpg'">
                        <img :src="`/images/flags/${team.cpm_player.country}.png`" :title="team.cpm_player.country" class="w-5 inline mr-2">
                        <div v-html="q3tohtml(team.cpm_player.name)"></div>
                    </Link>
                </div>
    
                <div class="col-span-1 lg:col-span-1 mt-3 lg:mt-0 order-2 md:order-2">
                    <div class="tech-line-vq3 mb-1"></div>

                    <Link :href="route('profile.index', team.vq3_player.id)" class="text-gray-400 flex justify-start items-center" v-if="team.vq3_player">
                        <img style="z-index: 2;" class="h-8 w-8 rounded-full object-cover mr-4" :src="team.vq3_player.profile_photo_path ? '/storage/' + team.vq3_player.profile_photo_path : '/images/null.jpg'">
                        <img :src="`/images/flags/${team.vq3_player.country}.png`" :title="team.vq3_player.country" class="w-5 inline mr-2">
                        <div v-html="q3tohtml(team.vq3_player.name)"></div>
                    </Link>
                </div>
            </div>

            <div class="ruler mt-1 px-5 md:hidden"></div>
        </div>
    </Tournament>
</template>

<style scoped>
    .tech-line-cpm {
        background: linear-gradient(90deg,#ffffff42,#7dff88,#ffffff42);
        box-shadow: 0 0 10px 1px #0aff336f;
        height: 1px;
        max-width: 100%;
    }

    .tech-line-vq3 {
        background: linear-gradient(90deg,#ffffff42,#5171ff,#ffffff42);
        box-shadow: 0 0 10px 1px #2225ff6f;
        height: 1px;
        max-width: 100%;
    }

    .tech-line-base {
        background: linear-gradient(90deg,#ffffff42,#949494,#ffffff42);
        box-shadow: 0 0 10px 1px #c5c5c56f;
        height: 1px;
        max-width: 100%;
    }

    .ruler {
        height: 1px;
        width: 100%;
        background: linear-gradient(90deg,#27272742,#3f3f3f,#27272742);
    }
</style>