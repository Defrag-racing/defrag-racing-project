<script setup>
    import { Link } from '@inertiajs/vue3';

    const props = defineProps({
        tournament: Object
    });
</script>

<template>
    <div class="w-full">
        <a href="#" class="w-full flex items-end mb-4 mx-auto border border-grayop-500 border-4 rounded-lg" :style="`min-height: 400px; width: 600px; background-size: cover; background-image: url(/storage/${tournament.image});`">
            <div class="h-24 w-full px-5 py-5 rounded-b-lg" style="background-color: #000000b9;">
                <div class="text-2xl font-bold hover:underline text-white">{{ tournament.name }}</div>
    
                <div class="flex justify-between">
                    <div class="text-blue-400">7 Rounds</div>
                    <div class="text-blue-400">Started {{ timeSince(tournament.start_date) }} ago</div>
                </div>
            </div>
        </a>

        <div class="mx-auto mb-10" style="width: 600px;" v-if="tournament.isOrganizer || tournament.isValidator">
            <div class="flex justify-between text-white">
                <Link v-if="tournament.isOrganizer" :href="route('tournaments.edit', tournament.id)" class="font-bold bg-grayop-400 hover:bg-grayop-700 cursor-pointer flex justify-center items-center text-center rounded-lg p-3 w-60">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
                    </svg>                                                                 

                    <span class="ml-2">Edit Tournament</span>
                </Link>

                <a v-if="tournament.isValidator" href="#" class="font-bold bg-grayop-400 hover:bg-grayop-700 cursor-pointer flex justify-center items-center text-center rounded-lg p-3 w-60">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                    </svg>                                                                                                            

                    <span class="ml-2">Demo Validation</span>
                </a>
            </div>
        </div>
    </div>
</template>