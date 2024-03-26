<script setup>
    import { Link } from '@inertiajs/vue3';
    import Tournament from '@/Pages/Tournaments/Tournament.vue';

    const props = defineProps({
        tournament: Object,
        news: Array
    });
</script>

<template>
    <Tournament :tournament="tournament" tab="ManageTournament">
        <div class="flex justify-center items-center">
            <Link :href="route('tournaments.news.create', tournament.id)" class="text-gray-300 font-bold bg-grayop-700 cursor-pointer hover:bg-grayop-600 text-center rounded-lg p-3 mr-4 mb-3">
                Add News
            </Link>
        </div>

        <div v-for="item in news" :key="item.id">
            <div class="mb-5 p-5 rounded-md mx-auto bg-grayop-900">
                <div class="text-white font-bold text-xl">
                    {{ item.title }}
                </div>
        
                <div class="my-3" style="width: 100%; height: 1px; background-color: #72727244"></div>
        
                <div class="flex justify-center p-5" v-if="item.image">
                    <img :src="`/storage/${item.image}`" class="rounded-md border-2 border-gray-700">
                </div>
        
                <div class="text-gray-400">
                    {{ item.content }}
                </div>

                <div class="flex justify-end">
                    <Link :href="route('tournaments.news.edit', [tournament.id, item.id])" class="text-gray-300 font-bold bg-grayop-700 cursor-pointer hover:bg-grayop-600 text-center rounded-lg p-3 mr-4 mb-3">
                        Edit
                    </Link>
                    <Link :href="route('tournaments.news.destroy', [tournament.id, item.id])" class="text-gray-300 font-bold bg-grayop-700 cursor-pointer hover:bg-grayop-600 text-center rounded-lg p-3 mr-4 mb-3">
                        Delete
                    </Link>
                </div>
            </div>
        </div>

        <div class="flex justify-center mt-5 text-md text-gray-500" v-if="news.length === 0">
            There are no news yet.
        </div>
    </Tournament>
</template>
