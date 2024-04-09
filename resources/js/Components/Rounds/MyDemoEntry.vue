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
            </div>
        </div>
    </div>
</template>