<script setup>
    import { Head } from '@inertiajs/vue3';
    import Tabs from '@/Components/Tabs.vue';

    import Rules from '@/Pages/Tournaments/Tournament/Rules.vue';
    import Overview from '@/Pages/Tournaments/Tournament/Overview.vue';

    const props = defineProps({
        tournament: Object,
        tab: String
    });

    const tabs = [
        {
            name: 'Overview',
            link: true,
            route: 'tournaments.show',
            params: { tournament: props.tournament.id }
        },
        {
            name: 'Rounds',
            link: true,
            route: 'tournaments.show',
            params: { tournament: props.tournament.id }
        },
        {
            name: 'Standings',
            link: true,
            route: 'tournaments.show',
            params: { tournament: props.tournament.id }
        },
        {
            name: 'Rules',
            link: true,
            route: 'tournaments.rules',
            params: { tournament: props.tournament.id }
        },
        {
            name: 'Teams',
            link: true,
            route: 'tournaments.show',
            params: { tournament: props.tournament.id },
            condition: props.tournament.has_teams
        },
        {
            name: 'Manage Teams',
            link: true,
            route: 'tournaments.show',
            params: { tournament: props.tournament.id },
            condition: props.tournament.has_teams
        },
        {
            name: 'Donations',
            link: true,
            route: 'tournaments.show',
            params: { tournament: props.tournament.id }
        },
        {
            name: 'FAQs',
            link: true,
            route: 'tournaments.show',
            params: { tournament: props.tournament.id }
        },
        {
            name: 'Manage Tournament',
            link: true,
            route: 'tournaments.show',
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
                        <Overview v-if="tab === 'Overview'" :tournament="tournament" />
                        <Rules v-if="tab === 'Rules'" :tournament="tournament" />
                    </div>
                  </div>
                </div>
            </div>
        </div>
</template>
