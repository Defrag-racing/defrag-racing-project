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
                <div class="flex justify-between items-center">
                    <h2 class="font-semibold text-3xl text-gray-200 leading-tight">
                        Clans
                    </h2>

                    <div class="flex">
                        <Link v-if="! myClan" href="#" class="text-gray-300 bg-grayop-700 cursor-pointer hover:bg-grayop-600 text-center rounded-lg px-3 py-2 mr-2 flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 mr-2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                            </svg>

                            Create New Clan
                        </Link>

                        <Link v-if="myClan" href="#" class="text-gray-300 bg-grayop-700 cursor-pointer hover:bg-grayop-600 text-center rounded-lg px-3 py-2 mr-2 flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 mr-2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                              </svg>

                            Manage Clan
                        </Link>
                    </div>
                </div>

                <div class="p-5" v-if="myClan">
                    <h2 class="font-semibold text-xl text-gray-200 leading-tight">
                        My Clan
                    </h2>
                    <div class="my-5 rounded-md my-clan">
                        <Clan :clan="myClan" />
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