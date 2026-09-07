<script setup>
    import { computed } from 'vue';
    import OnlinePlayerData from '@/Components/OnlinePlayerData.vue';
    import { isStreaming } from '@/utils/twitch';

    const props = defineProps({
        player: Object,
        /**
         * Everyone else on the same server. Needed to tell a spectator whose
         * target is on the list from one whose target is not - see below.
         * Optional so a caller that has no list still renders something.
         */
        siblings: {
            type: Array,
            default: () => [],
        },
    });

    /**
     * A spectator is drawn nested under the player they are watching, so their
     * own row is skipped. That only works while the player they are watching
     * is drawn at all - and there is a window, a few seconds long, where they
     * are not.
     *
     * getdfstatus reports a client from the moment it reaches CS_CONNECTED,
     * but reads who it is watching out of the game module's playerState, which
     * is not filled in until the client goes active. In between it holds zero,
     * or whatever the previous occupant of that slot left there, and oDFe
     * publishes it as `spectating` anyway (sv_main.c, SVC_DFStatus). So a
     * player joining a server is announced as watching client 0, or somebody
     * who left ten minutes ago.
     *
     * We stored that verbatim, skipped their own row because they "are a
     * spectator", and had nowhere to nest them because the client they name is
     * not there. They vanished, leaving an empty box behind, until the next
     * scrape found them active. The launcher never showed this because it does
     * not nest spectators at all.
     *
     * So: a spectator counts as one only when the player they name is present
     * AND is themselves drawn. Otherwise they are shown in their own right,
     * which is what they are.
     */
    const isNestedElsewhere = computed(() => {
        if (props.player.follow_num === -1) {
            return false;
        }

        return props.siblings.some((other) =>
            other.client_id === props.player.follow_num
            && other.server_id === props.player.server_id
            && other.follow_num === -1);
    });
</script>

<template>
    <!-- The condition sits on the outermost element on purpose. It used to be
         one level in, so a player who was not drawn still left their wrapper
         behind - the empty row on the server cards was this. -->
    <div class="mb-2" v-if="!isNestedElsewhere">
        <!-- A streamer's row is tinted Twitch purple with a bar down its left
             edge, so the eye lands on it before it reads a single name. -->
        <div class="flex justify-between"
             :class="isStreaming(player) ? 'rounded-md border-l-2 border-purple-400 bg-purple-500/15 pl-2 pr-1 py-0.5 -ml-1' : ''">
            <OnlinePlayerData :player="player" :spectator="false" />
            <div class="font-bold online-time-text text-white drop-shadow-[0_2px_6px_rgba(0,0,0,0.9)]" v-if="player.time != 0">
                {{  formatTime(player.time) }}
            </div>
        </div>

        <div v-for="spectator in player.spectators" :key="spectator.id">
            <div class="mt-2" v-if="spectator.server_id == player.server_id">
                <div class="flex text-sm items-center text-gray-400"
                     :class="isStreaming(spectator) ? 'rounded-md border-l-2 border-purple-400 bg-purple-500/15 pl-1 pr-1 py-0.5' : ''">
                    <OnlinePlayerData :spectator="true" :player="spectator" class="flex-grow ml-2" />

                    <div class="font-bold online-time-text text-gray-300 drop-shadow-[0_2px_6px_rgba(0,0,0,0.9)]" v-if="spectator.time != 0">
                        {{  formatTime(spectator.time) }}
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
