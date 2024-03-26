<script setup>
    import { computed, ref } from 'vue';
    import RoundPart from '@/Components/Rounds/RoundPart.vue';
    import MySubmissions from '@/Components/Rounds/MySubmissions.vue';
    import CommentSection from '@/Components/Tournament/CommentSection.vue';

    defineProps({
        tournament: Object,
        title: String,
        active: Boolean,
        listing: Object
    });

    const uploadFileName = ref('Select Demo to upload...')

    const changeUploadFileName = (e) => {
        uploadFileName.value = e.target.files[0].name;
    }
</script>

<template>
    <div class="mb-5 p-5 rounded-md mx-auto bg-blackop-30">
        <RoundPart :round="listing.round" :title="title">
            <template #additional>
                <div class="flex items-center" v-if="active">
                    <div scope="row" class="text-lg text-gray-400" style="min-width: 150px;">
                        Upload
                    </div>
    
                    <form method="POST" enctype="multipart/form-data" id="demo-upload-form" class="flex justify-between text-gray-900 dark:text-white flex-grow rounded-md" action="#">
                        <input accept=".dm_68" type="file" name="file" id="demo" class="hidden" @change="changeUploadFileName">
                        <label for="demo" class="rounded-md cursor-pointer flex-grow flex items-center bg-blackop-30">
                            <div class="flex justify-between items-center w-full rounded-md px-4">
                                <div class="truncate" style="max-width: 250px;" :title="uploadFileName">
                                    {{ uploadFileName }}
                                </div>
                            </div>
                        </label>
    
                        <button type="button" onclick="submitForm()" class="rounded-md px-4 ml-2 cursor-pointer bg-blackop-30 hover:bg-blackop-50 py-2">
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
                    </form>
                </div>
            </template>
        </RoundPart>

        <MySubmissions :round="listing.round" v-if="active" />

        <CommentSection :comments="listing.comments" :url="route('tournaments.rounds.comment', {tournament: tournament.id, listing: listing.id})" />
    </div>
</template>