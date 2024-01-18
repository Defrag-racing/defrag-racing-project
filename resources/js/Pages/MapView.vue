<script setup>
    import MainLayout from '@/Layouts/MainLayout.vue';
    import MapCard from '@/Components/MapCard.vue';

    const props = defineProps({
        map: Object
    });
</script>

<template>
    <MainLayout :title="map.name">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-200 leading-tight">
                Map details
            </h2>
        </template>

        <div class="max-w-8xl mx-auto py-10 sm:px-6 lg:px-8">
            <div class="flex flex-wrap justify-center">
                <MapCard :map="map" />

                <div class="bg-blackop-30 rounded-md p-3 flex-grow">
                    test
                </div>
            </div>
        </div>
    </MainLayout>
</template>
