<script setup>
    import { Head, usePage } from '@inertiajs/vue3';
    import Tabs2 from '@/Components/Tabs2.vue';
    import { onMounted, getCurrentInstance, ref } from 'vue';
    import OverviewNew from '@/Components/Tournament/OverviewNew.vue';
    import PrimaryButton from '@/Components/Laravel/PrimaryButton.vue';

    const props = defineProps({
        tournament: Object,
        tab: String
    });

    const page = usePage()

    const pinnedNews = ref(page.props.pinnedNews)

    const tabs = [
        {
            name: 'Overview',
            label: 'Overview',
            link: true,
            route: 'tournaments.show',
            params: { tournament: props.tournament.id },
            condition: true
        },
        {
            name: 'Rounds',
            label: 'Rounds',
            link: true,
            route: 'tournaments.rounds.index',
            params: { tournament: props.tournament.id },
            condition: true
        },
        {
            name: 'News',
            label: 'News',
            link: true,
            route: 'tournaments.news.index',
            params: { tournament: props.tournament.id },
            condition: true
        },
        {
            name: 'Standings',
            label: 'Standings',
            link: true,
            route: 'tournaments.standings.index',
            params: { tournament: props.tournament.id },
            condition: true
        },
        {
            name: 'Rules',
            label: 'Rules',
            link: true,
            route: 'tournaments.rules',
            params: { tournament: props.tournament.id },
            condition: true
        },
        {
            name: 'Teams',
            label: 'Teams',
            link: true,
            route: 'tournaments.teams.index',
            params: { tournament: props.tournament.id },
            condition: props.tournament.has_teams
        },
        {
            name: 'ManageTeams',
            label: 'Manage Team',
            link: true,
            route: 'tournaments.teams.manage',
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
            params: { tournament: props.tournament.id },
            condition: true
        },
        {
            name: 'ManageTournament',
            label: 'Manage Tournament',
            link: true,
            route: 'tournaments.manage',
            params: { tournament: props.tournament.id },
            condition: props.tournament.creator === page.props?.auth?.user?.id
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
                        <div class="flex flex-col md:flex-row md:gap-0 gap-2 justify-between items-center flex-wrap mb-2">
                            <div class="h-1 w-40"></div>
                            <h2 class="font-semibold text-3xl text-gray-200 leading-tight">
                                {{ tournament.name }}
                            </h2>
                            <div class="w-40">
                                <a v-if="tournament.donation_link" :href="tournament.donation_link" target="_blank" class="flex justify-center">
                                    <PrimaryButton class="flex w-full justify-center bg-yellow-500 hover:bg-yellow-400 text-black" style="color: black;">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 mr-3">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                        </svg>
                                        Donate
                                    </PrimaryButton>
                                </a>
                            </div>
                        </div>

                        <Tabs2 :tabs="tabs" :activeTab="tab" style="z-index: 3;" />

                        <OverviewNew v-if="pinnedNews" :comments="false" :key="pinnedNews.id" :item="pinnedNews" :tournament="tournament" />

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