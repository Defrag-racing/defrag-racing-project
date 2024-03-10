<script setup>
    import { Head } from '@inertiajs/vue3';
    import Tabs2 from '@/Components/Tabs2.vue';
    import Tabs3 from '@/Components/Tabs3.vue';
    import { onMounted, getCurrentInstance } from 'vue';

    const props = defineProps({
        tournament: Object,
        tab: String
    });

    const tabs = [
        {
            name: 'Overview',
            label: 'Overview',
            link: true,
            route: 'tournaments.show',
            params: { tournament: props.tournament.id }
        },
        {
            name: 'Rounds',
            label: 'Rounds',
            link: true,
            route: 'tournaments.show',
            params: { tournament: props.tournament.id }
        },
        {
            name: 'Standings',
            label: 'Standings',
            link: true,
            route: 'tournaments.show',
            params: { tournament: props.tournament.id }
        },
        {
            name: 'Rules',
            label: 'Rules',
            link: true,
            route: 'tournaments.rules',
            params: { tournament: props.tournament.id }
        },
        {
            name: 'Teams',
            label: 'Teams',
            link: true,
            route: 'tournaments.show',
            params: { tournament: props.tournament.id },
            condition: props.tournament.has_teams
        },
        {
            name: 'ManageTeams',
            label: 'Manage Teams',
            link: true,
            route: 'tournaments.show',
            params: { tournament: props.tournament.id },
            condition: props.tournament.has_teams
        },
        {
            name: 'Donations',
            label: 'Donations',
            link: true,
            route: 'tournaments.donations',
            params: { tournament: props.tournament.id },
            condition: props.tournament.has_donations
        },
        {
            name: 'FAQs',
            label: 'FAQs',
            link: true,
            route: 'tournaments.faqs',
            params: { tournament: props.tournament.id }
        },
        {
            name: 'ManageTournament',
            label: 'Manage Tournament',
            link: true,
            route: 'tournaments.manage',
            params: { tournament: props.tournament.id }
        },

    ];

    const instance = getCurrentInstance();
    const globalProperties = instance.appContext.config.globalProperties;

    onMounted(() => {
        // globalProperties.$state.globalBackgroundImage = '/storage/' + props.tournament.image;
    });
</script>

<template>
    <div>
        <Head title="Tournaments" />

        <div class="pb-12">
            <div class="max-w-8xl mx-auto sm:px-6 lg:px-8">
                <div class="mb-12 pb-5 pt-1 rounded-lg mt-5 flex" style="z-index: 2;">

                    <div class="rounded-lg p-5 w-full bg-grayop-800">
                        <div class="flex justify-center items-center flex-wrap mb-2">
                            <h2 class="font-semibold text-3xl text-gray-200 leading-tight">
                                {{ tournament.name }}
                            </h2>
                        </div>

                        <Tabs2 :tabs="tabs" :activeTab="tab" style="z-index: 3;" />
                        <slot></slot>
                    </div>
                  </div>
                </div>
            </div>
        </div>
</template>

<style>
    .image-hover-effect {
        background: radial-gradient(circle, #ff6347, #ffd700);
        opacity: 0.5
    }
</style>