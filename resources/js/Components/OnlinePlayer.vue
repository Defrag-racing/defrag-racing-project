<script setup>
    import OnlinePlayerData from '@/Components/OnlinePlayerData.vue';

    const props = defineProps({
        player: Object,
    });
</script>

<template>
    <div>
        <div class="p-2 bg-blackop-30 rounded-md mb-1 mr-1" v-if="player.follow_num == -1">
            <div class="flex justify-between">
                <OnlinePlayerData :player="player" :spectator="false" />
                <div class="font-bold" v-if="player.time != 0">
                    {{  formatTime(player.time) }}
                </div>
            </div>

            <div v-for="spectator in player.spectators" :key="spectator.id">
                <div class="mt-2" v-if="spectator.server_id == player.server_id">
                    <div class="flex text-sm items-center text-gray-400">
                        <div title="Spectator">
                            <!-- <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class=" ml-3 w-6 h-6">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17.25 8.25 21 12m0 0-3.75 3.75M21 12H3" />
                            </svg> -->
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="ml-3 w-5 h-5 text-gray-500">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                            </svg>
                        </div>

                        <OnlinePlayerData :spectator="true" :player="spectator" class="flex-grow ml-2" />
        
                        <div class="font-bold" v-if="spectator.time != 0">
                            {{  formatTime(spectator.time) }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>