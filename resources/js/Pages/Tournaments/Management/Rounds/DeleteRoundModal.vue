<script setup>
    import { useForm } from '@inertiajs/vue3';
    import PrimaryButton from '@/Components/Laravel/PrimaryButton.vue';
    import ConfirmationModal from '@/Components/Laravel/ConfirmationModal.vue';

    const props = defineProps({
        close: Function,
        show: Boolean,
        tournament: Object,
        round: Object
    });

    const form = useForm({
        _method: 'POST'
    });

    const submitForm = () => {
        form.post(route('tournaments.rounds.destroy', {tournament: props.tournament.id, round: props.round.id}), {
            errorBag: 'submitForm',
            preserveScroll: true,
            onSuccess: () => {
                props.close();
            }
        });
    };
</script>

<template>
    <div>
        <ConfirmationModal :show="show" maxWidth="xl" :closeable="true" @close="close">
            <template #title>
                <div class="flex justify-between items-center">
                    <div>Delete Round ?</div>
                </div>
            </template>

            <template #content>
                Are you sure you want to delete this round ?
            </template>
                
            <template #footer>
                <div class="flex w-full justify-between">
                    <PrimaryButton @click="submitForm" class="bg-red-600 hover:bg-red-500">
                        Delete Round
                    </PrimaryButton>
    
                    <PrimaryButton @click="props.close" class="bg-gray-600 hover:bg-gray-500">
                        Cancel
                    </PrimaryButton>
                </div>
            </template>
        </ConfirmationModal>
    </div>
</template>