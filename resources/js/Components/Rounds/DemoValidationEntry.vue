<script setup>
    import { Link } from '@inertiajs/vue3'
    import { useForm } from '@inertiajs/vue3';
    import TextInput from '@/Components/Laravel/TextInput.vue';

    const props = defineProps({
        demo: Object,
        tournament: Object,
        round: Object
    });

    const form = useForm({
        reason: ''
    })

    const approve = () => {
        const url = route('tournaments.validation.approve', {
            tournament: props.tournament.id,
            round: props.round.id,
            demo: props.demo.id
        })

        form.post(url, {
            preserveScroll: true
        });
    }

    const reject = () => {
        const url = route('tournaments.validation.reject', {
            tournament: props.tournament.id,
            round: props.round.id,
            demo: props.demo.id
        })

        form.post(url, {
            preserveScroll: true
        });
    }

</script>

<template>
    <div class="px-2 py-1 mx-auto bg-blackop-30 my-4 rounded-md">
        <div class="w-full px-5 py-2 mb-1 flex items-center">
            <div class="flex w-full items-center truncate">
                <div>
                    <div class="w-full flex items-center justify-between">
                        <div class="text-xl text-white" v-html="q3tohtml(demo.user.name)"></div>
                    </div>
                    <div class="text-sm text-green-500" v-if="demo.approved">
                        Approved
                    </div>

                    <div class="text-sm text-red-500" v-else-if="demo.rejected">
                        Rejected
                    </div>

                    <div class="text-sm text-gray-500" v-else>
                        Unvalidated
                    </div>
                </div>
            </div>

            <a :href="route('tournaments.demos.download', demo.id)" download class="flex items-center text-xl text-gray-300 hover:text-white">
                {{ formatTime(demo.time) }}
                <span>
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 ml-2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                    </svg>
                </span>
            </a>
        </div>


        <div class="flex items-center justify-between px-5 mb-2">
            <TextInput
                id="reason"
                v-model="form.reason"
                placeholder="Reason"
                type="text"
                class="w-full"
            />

            <div class="ml-5 flex gap-3">
                <button @click="approve" class="hover:bg-grayop-700 text-white font-bold rounded-full w-8 h-8 flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                </button>
    
                <button @click="reject" class="hover:bg-grayop-700 text-white font-bold rounded-full w-8 h-8 flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>
</template>