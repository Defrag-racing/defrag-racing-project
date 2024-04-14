<script setup>
    import { Head } from '@inertiajs/vue3';
    import TournamentForm from './TournamentForm.vue';

    const props = defineProps({
        error: {
            type: String,
            default: '',
        }
    });
</script>

<template>
    <div>
        <Head title="Create Tournament" />

        <div class="max-w-8xl mx-auto pt-6 px-4 md:px-6 lg:px-8">
            <h2 class="font-semibold text-3xl text-gray-200 leading-tight">
                Create Tournament
            </h2>
        </div>

        <div>
            <div class="max-w-8xl mx-auto py-10 sm:px-6 lg:px-8 mb-10">
                <v-alert
                    v-if="error"
                    color="error"
                    icon="error"
                    title="An Error Occurred!"
                    :text="error"
                    class="mb-5"
                />

                <TournamentForm :tournament="{}" url="tournaments.store" />
            </div>
        </div>
    </div>
</template>
