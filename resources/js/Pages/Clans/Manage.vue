<script setup>
    import { Head } from '@inertiajs/vue3';
    import { Link } from '@inertiajs/vue3';
    import Pagination from '@/Components/Basic/Pagination.vue';

    const props = defineProps({
        clan: Object,
    });
</script>

<template>
    <div>
        <Head title="Manage Clans" />

        <div class="py-12">
            <div class="max-w-8xl mx-auto sm:px-6 lg:px-8">
                <div class="flex justify-between items-center mb-5">
                    <h2 class="font-semibold text-3xl text-gray-200 leading-tight">
                        Manage Clan: <span class="text-gray-500" v-html="q3tohtml(clan.name)"></span>
                    </h2>
                </div>

                <div class="tech-line-clan"></div>

                <div class="mt-5">
                    <div class="text-gray-400 text-lg">Players</div>

                    <div class="flex flex-wrap justify-center">
                        <div v-for="player in clan.players" :key="player.id" class="rounded-md bg-grayop-300 px-3 py-2 w-60 justify-center m-2 overflow-hidden">
                            <Link :href="route('profile.index', player.user_id)" class="flex items-center">
                                <div>
                                    <img :src="`/images/flags/${player.user.country}.png`" :title="player.user.country" class="w-7 inline mr-2 mb-0.5">
                                </div>
                                <div class="font-medium text-gray-100" v-html="q3tohtml(player.user.name)"></div>
                            </Link>
                        </div>
                    </div>
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