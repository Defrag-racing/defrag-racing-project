<script setup>
    import { Head, Link } from '@inertiajs/vue3';
    import { ref } from 'vue';
    import Tabs from '@/Components/Tabs.vue';

    const props = defineProps({
        tournament: Object
    });

    const activeTab = ref('overview');

    const toggleTab = (tab) => {
        activeTab.value = tab;
    };

    const tabs = [
        {
            name: 'Overview',
            link: true,
            route: 'tournaments.show',
            params: { tournament: props.tournament.id }
        }
    ];
</script>

<template>
    <div>
        <Head title="Tournaments" />

        <div class="py-12">
            <div class="max-w-8xl mx-auto sm:px-6 lg:px-8">
                <div class="flex justify-between items-center flex-wrap">
                    <h2 class="font-semibold text-3xl text-gray-200 leading-tight">
                        {{ tournament.name }}
                    </h2>
                </div>

                <div class="mb-12 pb-5 pt-1 rounded-lg flex mt-10">
                    <Tabs :tabs="tabs" :activeTab="activeTab" :onClick="toggleTab" />

                    <div class="rounded-lg p-5 w-full bg-grayop-800">
                      <div>

                      </div>
                    </div>
                  </div>
                </div>
            </div>
        </div>
</template>
