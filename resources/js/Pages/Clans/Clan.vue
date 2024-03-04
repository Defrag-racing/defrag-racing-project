<script setup>
    import { Link } from '@inertiajs/vue3';
    import Popper from "vue3-popper";

    const props = defineProps({
        clan: Object
    });
</script>

<template>
    <div class="mb-5">
        <div class="rounded-md bg-blackop-30 px-5 py-2 flex items-center justify-between">
            <div>
                <div class="flex items-center">
                    <img class="h-8 w-8 rounded-full object-cover mr-3" :src="'/storage/' + clan.image">
    
                    <h2 class="text-xl text-gray-300" v-html="q3tohtml(clan.name)"></h2>
                </div>
    
                <div class="mt-3 flex text-sm">
                    <div class="text-gray-500 mr-1">Admin: </div>
                    <Link class="text-gray-300" :href="route('profile.index', clan.admin.id)" v-html="q3tohtml(clan.admin.name)"></Link>
                </div>
            </div>

            <div class="flex items-center">
                <div v-for="(player, index) in clan.players" :key="player.id">
                    <Popper v-if="index < 5" placement="bottom" arrow hover style="z-index: 100;">
                        <Link class="cursor-pointer relative" :href="route('profile.index', player.user_id)" :style="`left: -${index * 10}px; z-index: ${index}`">
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
                <div class="text-gray-400 text-sm font-bold relative" v-if="clan.players_count > 5">
                    + {{ clan.players_count - 5 }} More
                </div>
            </div>
        </div>
    </div>
</template>