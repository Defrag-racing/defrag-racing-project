<script setup>
    import { Link } from '@inertiajs/vue3';

    const props = defineProps({
        faq: Object,
        management: {
            type: Boolean,
            default: false
        }
    });
</script>

<template>
    <div class="mb-5">
        <div class="rounded-md bg-blackop-30 px-5 py-2 mx-2">
            <div class="flex justify-between">
                <h2 class="text-lg text-gray-300">
                    {{ faq.question }}
                </h2>
    
                <div class="flex" v-if="management">
                    <Link :href="route('tournaments.faqs.edit', {tournament: faq.tournament_id, faq: faq.id})" class="text-gray-300 font-bold bg-grayop-700 cursor-pointer hover:bg-grayop-600 text-center rounded-lg p-3 mr-4 mb-3">
                        Edit
                    </Link>
    
                    <Link :href="route('tournaments.faqs.destroy', {tournament: faq.tournament_id, faq: faq.id})" class="text-gray-300 font-bold bg-grayop-700 cursor-pointer hover:bg-grayop-600 text-center rounded-lg p-3 mr-4 mb-3">
                        Delete
                    </Link>
                </div>
            </div>
        </div>
        <div class="rounded-md bg-blackop-20 px-5 py-3 mx-2 my-2 text-sm text-gray-500">
            {{ faq.answer }}
        </div>
    </div>
</template>