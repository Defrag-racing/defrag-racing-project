<script setup>
    import { ref } from 'vue';
    import { useForm } from '@inertiajs/vue3';
    import PrimaryButton from '@/Components/Laravel/PrimaryButton.vue';
    import ConfirmationModal from '@/Components/Laravel/ConfirmationModal.vue';

    const props = defineProps({
        demo: Object,
        tournament: Object
    });

    const form = useForm({
        _method: 'POST'
    });

    const showConfirmation = ref(false);

    const deleteDemo = () => {
        showConfirmation.value = true;
    }

    const submitForm = () => {
        form.post(route('tournaments.demos.delete', {
            tournament: props.tournament.id,
            demo: props.demo.id
        }), {
            preserveScroll: true
        });
    
    }
</script>

<template>
    <div class="px-2 py-1 mx-auto bg-blackop-30">
        <div class="w-full px-5 py-2 rounded-md mb-1 flex items-center">
            <div class="w-10/12 inline-flex items-center truncate">
                <div>
                    <div class="w-full flex items-center flex-nowrap">
                        <div class="text-xl text-white">{{ formatTime(demo.time) }}</div>
                    </div>
                    <div>
                        <div class="text-sm text-gray-500">Uploaded <b>{{ timeSince(demo.created_at) }} ago</b></div>
                        <div class="text-red-500" v-if="demo.rejected">Rejected: {{ demo.reason }}</div>
                    </div>
                </div>
            </div>

            <div class="flex-1 justify-center flex-wrap items-center flex">
                <a :href="route('tournaments.demos.download', demo.id)" download class="font-black text-lg leading-none mb-1 text-indigo-100 hover:underline cursor-pointer">
                    Download
                </a>
                <div @click="deleteDemo" class="mt-3 font-black text-xs leading-none mb-1 text-red-500 hover:underline cursor-pointer">
                    Delete
                </div>
            </div>
        </div>

        <ConfirmationModal :show="showConfirmation" maxWidth="xl" :closeable="true" @close="showConfirmation = false">
            <template #title>
                <div class="flex justify-between items-center">
                    <div>Delete your demo submission ?</div>

                    <div class="text-gray-200 cursor-pointer rounded-full hover:bg-grayop-700 p-1" @click="showConfirmation = false">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </div>
                </div>
            </template>

            <template #content>
                Are you sure you want to delete this demo submission ? This action cannot be undone.
            </template>
                
            <template #footer>
                <div class="flex w-full justify-between">
                    <PrimaryButton @click="submitForm" class="bg-red-600 hover:bg-red-500">
                        Delete Demo
                    </PrimaryButton>
    
                    <PrimaryButton @click="showConfirmation = false" class="bg-gray-600 hover:bg-gray-500">
                        Cancel
                    </PrimaryButton>
                </div>
            </template>
        </ConfirmationModal>
    </div>
</template>