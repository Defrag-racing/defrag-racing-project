<script setup>
    import { Head } from '@inertiajs/vue3';
    import Tabs from '@/Components/Tabs.vue';

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
            route: 'tournaments.show',
            params: { tournament: props.tournament.id }
        },
        {
            name: 'FAQs',
            label: 'FAQs',
            link: true,
            route: 'tournaments.show',
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
                    <Tabs :tabs="tabs" :activeTab="tab" />

                    <div class="rounded-lg p-5 w-full bg-grayop-800">
                        <slot></slot>
                    </div>
                  </div>
                </div>
            </div>
        </div>
</template>
