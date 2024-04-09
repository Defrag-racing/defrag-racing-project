<script setup>
    import { Head, Link } from '@inertiajs/vue3';

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
                <div class="flex justify-center items-center mb-5 flex-wrap">
                    <div class="font-semibold text-3xl text-gray-200 leading-tight flex flex-col items-center justify-center">
                        <img class="h-20 w-20 rounded-full object-cover shadow-lg" :src="'/storage/' + clan.image">
            
                        <h2 class="text-2xl text-gray-300 text-center mt-1" v-html="q3tohtml(clan.name)"></h2>
                    </div>
                </div>

                <div class="tech-line-clan"></div>

                <div class="text-xl text-gray-300 mt-3 text-center">Players</div>

                <div class="flex flex-wrap justify-center my-5">
                    <Link v-for="player in players" class="m-2" :href="route('profile.index', player.id)">
                        <div class="cursor-pointer group w-full z-10 relative overflow-hidden rounded-md bg-gray-700 bg-opacity-30 hover:bg-opacity-50 py-3 px-10 py-3">
                            <div class="flex items-center transition-transform group-hover:scale-110">
                                <img style="z-index: 2;" class="h-8 w-8 rounded-full object-cover mr-4" :src="player.profile_photo_path ? '/storage/' + player.profile_photo_path : '/images/null.jpg'">

                                <div class="font-semibold w-full text-center text-white" v-html="q3tohtml(player.name)"></div>
                            </div>

                            <div class="absolute -left-1 -top-1 w-24 h-24 z-0 opacity-15 transition-transform group-hover:scale-150">
                                <svg class="text-black fill-current w-full h-full" viewBox="0 0 20 20"><use :xlink:href="`/images/svg/icons.svg#icon-human`"></use></svg>
                            </div>
                        </div>
                    </Link>
                </div>

                <div class="tech-line-clan"></div>
                <div class="text-xl text-gray-300 mt-3 text-center">Achievements</div>
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