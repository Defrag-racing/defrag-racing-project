<script setup>
    import MainLayout from '@/Layouts/MainLayout.vue';
    import Pagination from '@/Components/Basic/Pagination.vue';

    const props = defineProps({
        maps: Object,
    });
</script>

<template>
    <MainLayout title="Maps">
        <template #header>
            <div class="flex justify-between">
                <h2 class="font-semibold text-3xl text-gray-800 dark:text-gray-200 leading-tight">
                    Maps
                </h2>
            </div>

            <div class="text-sm text-blue-400 flex items-center mt-3">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 6.75V15m6-6v8.25m.503 3.498 4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934c-.317.159-.69.159-1.006 0L9.503 3.252a1.125 1.125 0 0 0-1.006 0L3.622 5.689C3.24 5.88 3 6.27 3 6.695V19.18c0 .836.88 1.38 1.628 1.006l3.869-1.934c.317-.159.69-.159 1.006 0l4.994 2.497c.317.158.69.158 1.006 0Z" />
                </svg>

                <div class="ml-2 mr-5">{{ maps.total }} Maps Found</div>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-8xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg p-4 flex justify-center flex-wrap">
                    <Pagination :current_page="maps.current_page" :last_page="maps.last_page" :link="maps.first_page_url" />
                </div>
            </div>
        </div>
    </MainLayout>
</template>
