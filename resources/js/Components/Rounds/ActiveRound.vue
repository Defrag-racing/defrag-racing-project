<script setup>
    import { ref } from 'vue';
    import RoundPart from '@/Components/Rounds/RoundPart.vue';
    import RoundResults from '@/Components/Rounds/RoundResults.vue';
    import RoundResultsClans from '@/Components/Rounds/RoundResultsClans.vue';
    import RoundResultsTeams from '@/Components/Rounds/RoundResultsTeams.vue';
    import MySubmissions from '@/Components/Rounds/MySubmissions.vue';
    import CommentSection from '@/Components/Tournament/CommentSection.vue';
    import { useForm } from '@inertiajs/vue3';


    const props = defineProps({
        tournament: Object,
        active: Boolean,
        round: Object,
        type: {
            type: String,
            default: 'single'
        },
        teams: {
            type: Array,
            default: []
        }
    });

    const form = useForm({
        demo: null
    })

    const uploadFileName = ref('Select Demo to upload...')

    const changeUploadFileName = (e) => {
        uploadFileName.value = e.target.files[0].name;

        form.demo = e.target.files[0];
    }

    const submitForm = () => {
        if (! form.demo) {
            return;
        }

        const url = route('tournaments.rounds.submit', {
            tournament: props.tournament.id,
            round: props.round.id
        })

        form.post(url, {}, {
            forceFormData: true,
        });
    }
</script>

<template>
    <div class="mb-5 p-5 rounded-md mx-auto bg-blackop-30">
        <RoundPart :round="round">
            <template #additional>
                <div class="flex items-center" v-if="active">
                    <div scope="row" class="text-lg text-gray-400" style="min-width: 150px;">
                        Upload
                    </div>
    
                    <form @submit.prevent="submitForm" class="text-white flex-grow rounded-md">
                        <div class="flex">
                            <input accept=".dm_68" type="file" name="file" id="demo" class="hidden" @change="changeUploadFileName">
                            <label for="demo" class="rounded-md cursor-pointer flex-grow flex items-center bg-blackop-30">
                                <div class="flex justify-between items-center w-full rounded-md px-4">
                                    <div class="break-all" :title="uploadFileName">
                                        {{ uploadFileName }}
                                    </div>
                                </div>
                            </label>
        
                            <button type="submit" class="rounded-md px-4 ml-2 cursor-pointer bg-blackop-30 hover:bg-blackop-50 py-2">
                                <svg width="20px" height="20px" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <title/>
                                    <g id="Complete">
                                        <g id="upload">
                                            <g>
                                                <path d="M3,12.3v7a2,2,0,0,0,2,2H19a2,2,0,0,0,2-2v-7" fill="none" stroke="#ffffff" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"/>
                                                <g>
                                                    <polyline data-name="Right" fill="none" id="Right-2" points="7.9 6.7 12 2.7 16.1 6.7" stroke="#ffffff" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"/>
                                                    <line fill="none" stroke="#ffffff" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" x1="12" x2="12" y1="16.3" y2="4.8"/>
                                                </g>
                                            </g>
                                        </g>
                                    </g>
                                </svg>
                            </button>
                        </div>

                        <div class="text-gray-400 ml-2" v-if="form.errors.demo">
                            <small>{{ form.errors.demo }}</small>
                        </div>
                    </form>
                </div>
            </template>
        </RoundPart>

        <RoundResults
            :tournament="tournament"
            :round="round"
            v-if="round.finished && round.results && type === 'single'"
        />

        <RoundResultsClans
            :round="round"
            :tournament="tournament"
            v-if="round.finished && round.results && type === 'clans'"
        />

        <RoundResultsTeams
            :round="round"
            :tournament="tournament"
            :teams="teams"
            v-if="round.finished && round.results && type === 'teams'"
        />

        <MySubmissions :round="round" :tournament="tournament" />

        <CommentSection :tournament="tournament" :comments="round.comments" :url="route('tournaments.rounds.comment', {tournament: tournament.id, round: round.id})" />
    </div>
</template>