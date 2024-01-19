<script setup>
    import { Head } from '@inertiajs/vue3';
    import MapCard from '@/Components/MapCard.vue';

    const props = defineProps({
        map: Object
    });
</script>

<template>
    <div>
        <Head :title="map.name" />

        <div class="max-w-8xl mx-auto pt-6 px-4 md:px-6 lg:px-8">
            <h2 class="font-semibold text-xl text-gray-200 leading-tight">
                Map details
            </h2>
        </div>

        <div class="max-w-8xl mx-auto py-10 sm:px-6 lg:px-8">
            <div class="flex flex-wrap justify-center">
                <MapCard :map="map" />

                <div class="bg-blackop-30 rounded-md p-3 flex-grow">
                    test
                </div>
            </div>
        </div>
    </div>
</template>
