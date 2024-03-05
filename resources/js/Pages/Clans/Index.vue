<script setup>
    import { Head } from '@inertiajs/vue3';
    import { Link } from '@inertiajs/vue3';
    import Pagination from '@/Components/Basic/Pagination.vue';
    import Clan from './Clan.vue';

    const props = defineProps({
        clans: Object,
        myClan: Object,
    });
</script>

<template>
    <div>
        <Head title="Clans" />

        <div class="py-12">
            <div class="max-w-8xl mx-auto sm:px-6 lg:px-8">
                <div class="flex justify-between items-center mb-5">
                    <h2 class="font-semibold text-3xl text-gray-200 leading-tight">
                        Clans
                    </h2>

                    <div class="flex">
                        <Link v-if="! myClan" :href="route('clans.create')" class="text-gray-300 bg-grayop-700 cursor-pointer hover:bg-grayop-600 text-center rounded-lg px-3 py-2 mr-2 flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 mr-2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                            </svg>

                            Create New Clan
                        </Link>
                    </div>
                </div>

                <div class="p-5" v-if="myClan">
                    <h2 class="font-semibold text-xl text-gray-200 leading-tight">
                        My Clan
                    </h2>
                    <div class="my-5 rounded-md my-clan">
                        <Clan :clan="myClan" manage />
                    </div>
                </div>

                <div class="tech-line-clan"></div>

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

<style>
    .my-clan {
        background-color: #5398ff34;
    }

    .tech-line-clan {
        background: linear-gradient(90deg,#ffffff42,#2f90ff,#ffffff42);
        box-shadow: 0 0 50px 4px #0a7cffb2;
        height: 1px;
        margin-top: 5px;
        max-width: 100%;
    }
</style>