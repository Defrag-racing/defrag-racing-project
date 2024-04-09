<script setup>
    import Tournament from '@/Pages/Tournaments/Tournament.vue';
    import TextInput from '@/Components/Laravel/TextInput.vue';
    import PrimaryButton from '@/Components/Laravel/PrimaryButton.vue';
    import { useForm } from '@inertiajs/vue3';
    import { ref } from 'vue';

    const error = ref('');

    const props = defineProps({
        tournament: Object
    });

    const form = useForm({
        _method: 'POST',
        name: ''
    });

    const deleteTournament = () => {
        if (form.name !== props.tournament.name) {
            error.value = 'The tournament name does not match';
            return;
        }

        error.value = '';

        form.post(route('tournaments.delete.destroy', props.tournament.id));
    }
</script>

<template>
    <Tournament :tournament="tournament" tab="Delete Tournament">
        <div class="text-center text-red-600 text-xl">
            Deleting a tournament is permanent and cannot be undone. Are you sure you want to delete this tournament?
        </div>

        <div class="mt-10">
            <div class="text-gray-400">Type the tournament name: <span class="font-bold font-italic text-white">{{ tournament.name }}</span></div>

            <TextInput
                id="name"
                v-model="form.name"
                type="text"
                class="mt-1 block w-full"
            />
            <div class="text-red-500">
                <span v-if="error">{{ error }}</span>
            </div>
            

            <div class="flex justify-center mt-5">
                <PrimaryButton @click="deleteTournament" class="bg-red-600 hover:bg-red-500">
                    Delete Tournament
                </PrimaryButton>
            </div>
        </div>
    </Tournament>
</template>