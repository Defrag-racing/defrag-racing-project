<script setup>
    import OnlinePlayerData from '@/Components/OnlinePlayerData.vue';

    const props = defineProps({
        player: Object,
    });
</script>

<template>
    <div>
        <div class="mb-2" v-if="player.follow_num == -1">
            <div class="flex justify-between">
                <OnlinePlayerData :player="player" :spectator="false" />
                <div class="font-bold online-time-text" v-if="player.time != 0">
                    {{  formatTime(player.time) }}
                </div>
            </div>

            <div v-for="spectator in player.spectators" :key="spectator.id">
                <div class="mt-2" v-if="spectator.server_id == player.server_id">
                    <div class="flex text-sm items-center text-gray-400">
                        <OnlinePlayerData :spectator="true" :player="spectator" class="flex-grow ml-2" />
        
                        <div class="font-bold online-time-text" v-if="spectator.time != 0">
                            {{  formatTime(spectator.time) }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.online-time-text{
    font-size: 14px;
}
</style>