<script setup>
    import { useForm } from '@inertiajs/vue3';
    import InputError from '@/Components/Laravel/InputError.vue';
    import PrimaryButton from '@/Components/Laravel/PrimaryButton.vue';
    import Modal from '@/Components/Laravel/Modal.vue';
    import PlayerSelectDefrag from '@/Components/Basic/PlayerSelectDefrag2.vue';
    import { ref, computed } from 'vue';

    const props = defineProps({
        players: Array,
        close: Function,
        show: Boolean,
        tournament: Object
    });

    const user_id = ref([]);

    const form = useForm({
        _method: 'POST',
        player_id: []
    });

    const submitForm = () => {
        if (user_id.value.length > 0) {
            form.player_id = user_id.value[0];
        } else {
            return;
        }

        form.post(route('tournaments.teams.invite', props.tournament.id), {
            errorBag: 'submitForm',
            preserveScroll: true,
            onSuccess: () => {
                props.close();
            }
        });
    };

    const selectedUser = computed(() => {
        if (user_id.value.length > 0) {
            return props.players.find(player => player.id === user_id.value[0]).name;
        }

        return '';
    });
</script>

<template>
    <div>
        <Modal :show="show" maxWidth="xl" :closeable="false">
            <div class="p-5">
                <div class="flex justify-between items-center">
                    <h2 class="font-semibold text-xl text-gray-200 leading-tight">
                        Invite Player
                    </h2>

                    <div class="text-gray-200 cursor-pointer rounded-full hover:bg-grayop-700 p-1" @click="close">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </div>
                </div>
                <div class="w-full bg-gray-700 my-2" style="height: 1px;"></div>

                <form @submit.prevent="submitForm">
                    <div class="my-3 mb-5">
                        <PlayerSelectDefrag
                            id="player_id"
                            v-model="user_id"
                            :values="[]"
                            :options="players"
                        />
                        <InputError :message="form.errors.player_id" />
                    </div>

                    <div class="text-gray-500" v-if="user_id.length > 0">
                        Selected Player: <span class="text-gray-300" v-html="q3tohtml(selectedUser)"></span>
                    </div>

                    <div class="flex justify-center">
                        <PrimaryButton type="submit" class="w-32 justify-center">
                            Invite
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>
    </div>
</template>