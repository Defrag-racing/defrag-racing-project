<script setup>
    import { useForm, router } from '@inertiajs/vue3';
    import InputError from '@/Components/Laravel/InputError.vue';
    import PrimaryButton from '@/Components/Laravel/PrimaryButton.vue';
    import Modal from '@/Components/Laravel/Modal.vue';
    import PlayerSelectDefrag from '@/Components/Basic/PlayerSelectDefrag2.vue';
    import { ref, watch } from 'vue';
    import ConfirmTransferOwnershipModal from './ConfirmTransferOwnershipModal.vue';

    const props = defineProps({
        users: Array,
        close: Function,
        show: Boolean,
        clan: Object
    });

    const showConfirmation = ref(false);

    const players = ref(props.users.map(player => {
        return player.user
    }));

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

        showConfirmation.value = true;
    };

    const finalSubmit = () => {
        if (user_id.value.length > 0) {
            form.player_id = user_id.value[0];
        } else {
            return;
        }

        form.post(route('clans.manage.transfer'), {
            preserveScroll: true,
            onSuccess: () => {
                showConfirmation.value = false;
                close();
            }
        });
    };

    const selectedUser = () => {
        if (user_id.value.length > 0) {
            return players.value.find(player => player.id === user_id.value[0]).name;
        }

        return '';
    };

    watch(() => props.users, (newVal) => {
        players.value = newVal.map(player => {
            return player.user
        });
    });
</script>

<template>
    <div>
        <Modal :show="show" maxWidth="xl" :closeable="false">
            <div class="p-5">
                <div class="flex justify-between items-center">
                    <h2 class="font-semibold text-xl text-gray-200 leading-tight">
                        Transfer Ownership
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
                            :class="{ 'border-red-500': form.errors.player_id }"
                        />
                        <InputError :message="form.errors.answer" />
                    </div>

                    <div class="text-gray-500 mb-3" v-if="user_id.length > 0">
                        Selected Player: <span class="text-gray-300" v-html="q3tohtml(selectedUser())"></span>
                    </div>

                    <div class="flex justify-center">
                        <button type="submit" class="font-semibold text-xs text-white uppercase tracking-widest text-gray-300 bg-red-700 cursor-pointer hover:bg-red-600 text-center rounded-lg px-3 py-2 mr-2 flex items-center text-sm">
                            Transfer Ownership
                        </button>
                    </div>
                </form>
            </div>
        </Modal>

        <ConfirmTransferOwnershipModal
            v-if="showConfirmation && user_id.length > 0"
            :show="showConfirmation"
            :close="() => showConfirmation = false"
            :submit="finalSubmit"
            :player="players.find(player => player.id === form.player_id)"
            :clan="clan"
        />
    </div>
</template>