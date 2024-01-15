<script setup>
    import Popper from "vue3-popper";
    import OnlinePlayerData from '@/Components/OnlinePlayerData.vue';

    const props = defineProps({
        player: Object,
    });
</script>

<template>
    <div>
        <div class="p-2 bg-blackop-30 rounded-md mb-1 mr-1" v-if="player.follow_num == -1">
            <div class="flex justify-between">
                <OnlinePlayerData :player="player" />
                <div class="font-bold" v-if="player.time != 0">
                    {{  formatTime(player.time) }}
                </div>
            </div>

            <div v-for="spectator in player.spectators">
                <div class="mt-2 opacity-70 group-hover:opacity-90" v-if="spectator.server_id == player.server_id">
                    <div class="flex text-sm items-center text-gray-400">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class=" ml-3 w-6 h-6 text-gray-500">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17.25 8.25 21 12m0 0-3.75 3.75M21 12H3" />
                        </svg>

                        <OnlinePlayerData :player="spectator" class="flex-grow ml-2" />
        
                        <div class="font-bold" v-if="spectator.time != 0">
                            {{  formatTime(spectator.time) }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>