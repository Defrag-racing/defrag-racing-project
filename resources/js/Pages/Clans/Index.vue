<script setup>
    import { Head } from '@inertiajs/vue3';
    import Pagination from '@/Components/Basic/Pagination.vue';
    import Clan from './Clan.vue';

    const props = defineProps({
        clans: Object,
    });
</script>

<template>
    <div>
        <Head title="Clans" />

        <div class="py-12">
            <div class="max-w-8xl mx-auto sm:px-6 lg:px-8">
                <h2 class="font-semibold text-3xl text-gray-200 leading-tight">
                    Clans
                </h2>

                <div class="mt-5">
                    <Clan v-for="clan in clans.data" :key="clan.id" :clan="clan" />
                    
                    <div class="flex justify-center" v-if="clans.total > clans.per_page">
                        <Pagination :current_page="clans.current_page" :last_page="clans.last_page" :link="clans.first_page_url" />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
