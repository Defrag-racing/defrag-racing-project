<script setup>
    import { Head, Link } from '@inertiajs/vue3';
    import { ref } from 'vue';
    import TournamentCard from '@/Components/TournamentCard.vue';
    import TextInput from '@/Components/Laravel/TextInput.vue';
    import Tabs from '@/Components/Tabs.vue';

    const props = defineProps({
        tournaments: Object,
        activeTournaments: Object,
        upcomingTournaments: Object,
        pastTournaments: Object,
        records: Number
    });

    const filteredTournaments = ref([]);

    const searchInput = ref('');

    const tabs = [
        {
            name: 'Active Tournaments',
            label: 'Active Tournaments',
            link: false,
        },
        {
            name: 'Upcoming Tournaments',
            label: 'Upcoming Tournaments',
            link: false,
        },
        {
            name: 'Historical Tournaments',
            label: 'Historical Tournaments',
            link: false,
        },
        {
            name: 'Search Tournaments',
            label: 'Search Tournaments',
            link: false,
        }
    ];

    const activeTab = ref('Active Tournaments');

    const toggleTab = (tab) => {
        activeTab.value = tab;
    };

    const onSearchTournament = () => {
        if (searchInput.value.length > 0) {
            filteredTournaments.value = props.tournaments.filter((tournament) => {
                return tournament.name.toLowerCase().includes(searchInput.value.toLowerCase());
            });
        } else {
            filteredTournaments.value = [];
        }
    };
</script>

<template>
    <div>
        <Head title="Tournaments" />

        <div class="py-12">
            <div class="max-w-8xl mx-auto sm:px-6 lg:px-8">
                <div class="flex justify-between items-center flex-wrap">
                    <h2 class="font-semibold text-3xl text-gray-200 leading-tight">
                        Tournaments
                    </h2>
    
                    <Link v-if="records >= 200" :href="route('tournaments.create')" class="flex items-center text-white bg-grayop-700 py-2 px-4 rounded-md font-bold cursor-pointer bg-grayop-700 hover:bg-gray-600">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 mr-2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
    
                        <div>Create Tournaments</div>
                    </Link>

                    <div v-else class="flex items-center text-gray-500 bg-grayop-700 py-2 px-4 rounded-md font-bold bg-grayop-700 cursor-default" title="You cannot create tournaments because you have less than 200 records.">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 mr-2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>

                        <div>Create Tournaments</div>
                    </div>
                </div>

                <div class="flex justify-end mt-2" v-if="records < 200">
                    <div class="text-yellow-500 text-sm">
                        You cannot create tournaments because you have less than 200 records. You now have {{ records }} records.
                    </div>
                </div>

                <div class="mb-12 pb-5 pt-1 rounded-lg flex flex-col items-center md:items-stretch md:flex-row mt-10">
                    <Tabs :tabs="tabs" :onClick="toggleTab" :activeTab="activeTab" />

                    <div class="rounded-lg p-5 w-full bg-grayop-800">
                      <div>
                        <div v-show="activeTab === 'Active Tournaments'" id="active-tab">
                            <div v-for="(tournament, id) in activeTournaments">
                                <TournamentCard :key="id" :tournament="tournament"></TournamentCard>
                            </div>

                            <div v-if="activeTournaments.length === 0" class="text-center text-gray-400">
                                No active tournaments available.
                            </div>
                        </div>
            
                        <div v-show="activeTab === 'Upcoming Tournaments'" id="upcoming-tab">
                            <div v-for="(tournament, id) in upcomingTournaments">
                                <TournamentCard :key="id" :tournament="tournament"></TournamentCard>
                            </div>

                            <div v-if="upcomingTournaments.length === 0" class="text-center text-gray-400">
                                No upcoming tournaments available.
                            </div>
                        </div>
            
                        <div v-show="activeTab === 'Historical Tournaments'" id="historical-tab">
                            <div class="text-sm mb-5 text-center text-gray-400">
                                If you want to submit a historical tournament to be available on Defrag.Racing, please contact <span><a class="hover:underline" :href="route('profile.index', 8)" v-html="q3tohtml('^4[^7gt^4]^7neiT^7.')"></a></span> or <span><a class="hover:underline" :href="route('profile.index', 170)" v-html="q3tohtml('^9Jan^8Hejazi')"></a></span> on discord.
                            </div>

                            <div v-for="(tournament, id) in pastTournaments">
                                <TournamentCard :key="id" :tournament="tournament"></TournamentCard>
                            </div>

                            <div v-if="pastTournaments.length === 0" class="text-center text-gray-400">
                                No historical tournaments available.
                            </div>
                        </div>
            
                        <div v-show="activeTab === 'Search Tournaments'" id="search-tab">
                            <div class="my-5 flex items-center justify-center">
                                <TextInput @input="onSearchTournament" v-model="searchInput" placeholder="Search tournament" type="text" class="w-96" />
                            </div>
            
                            <div v-for="tournament in filteredTournaments">
                                <TournamentCard :key="tournament.id" :tournament="tournament"></TournamentCard>
                            </div>

                            <div v-if="filteredTournaments.length === 0 && searchInput.length > 0" class="text-center text-gray-400">
                                No tournaments found.
                            </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
            </div>
        </div>
</template>
