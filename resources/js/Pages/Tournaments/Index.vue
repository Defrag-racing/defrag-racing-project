<script setup>
    import { Head, Link } from '@inertiajs/vue3';
    import { ref } from 'vue';
    import TournamentCard from '@/Components/TournamentCard.vue';
    import TextInput from '@/Components/Laravel/TextInput.vue';

    const props = defineProps({
        tournaments: Object,
        activeTournaments: Object,
        upcomingTournaments: Object,
        pastTournaments: Object,
    });

    const filteredTournaments = ref([]);

    const searchInput = ref('');

    const activeTab = ref('active');

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
    
                    <Link :href="route('tournaments.create')" class="flex items-center text-white bg-grayop-700 py-2 px-4 rounded-md font-bold cursor-pointer bg-grayop-700 hover:bg-gray-600">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 mr-2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
    
                        <div>Create Tournaments</div>
                    </Link>
                </div>

                <div class="mb-12 pb-5 pt-1 rounded-lg flex mt-10">
                    <div class="mb-5 text-sm font-medium text-center">
                        <ul class="mr-5 w-60">
                            <li>
                                <div @click="toggleTab('active')" :class="{ 'text-white bg-grayop-300': activeTab === 'active', 'bg-grayop-500': activeTab !== 'active' }" class="cursor-pointer rounded-lg w-full py-3 text-gray-300 mb-4">Active Tournaments</div>
                            </li>
                            <li>
                                <div @click="toggleTab('upcoming')" :class="{ 'text-white bg-grayop-300': activeTab === 'upcoming', 'bg-grayop-500': activeTab !== 'upcoming' }" class="cursor-pointer rounded-lg w-full py-3 text-gray-300 mb-4">Upcoming Tournaments</div>
                            </li>
                            <li>
                                <div @click="toggleTab('historical')" :class="{ 'text-white bg-grayop-300': activeTab === 'historical', 'bg-grayop-500': activeTab !== 'historical' }" class="cursor-pointer rounded-lg w-full py-3 text-gray-300 mb-4">Historical Tournaments</div>
                            </li>
                            <li>
                                <div @click="toggleTab('search')" :class="{ 'text-white bg-grayop-300': activeTab === 'search', 'bg-grayop-500': activeTab !== 'search' }" class="cursor-pointer rounded-lg w-full py-3 text-gray-300 mb-4">Search Tournaments</div>
                            </li>
                        </ul>
                    </div>

                    <div class="rounded-lg p-5 w-full bg-grayop-800">
                      <div>
                        <div v-show="activeTab === 'active'" id="active-tab">
                            <div v-for="(tournament, id) in activeTournaments">
                                <TournamentCard :key="id" :tournament="tournament"></TournamentCard>
                            </div>
                        </div>
            
                        <div v-show="activeTab === 'upcoming'" id="upcoming-tab">
                            <div v-for="(tournament, id) in upcomingTournaments">
                                <TournamentCard :key="id" :tournament="tournament"></TournamentCard>
                            </div>
                        </div>
            
                        <div v-show="activeTab === 'historical'" id="historical-tab">
                            <div class="text-sm mb-5 text-center text-gray-400">
                                If you want to submit a historical tournament to be available on Defrag.Racing, please contact <span><a class="hover:underline" :href="route('profile.index', 8)" v-html="q3tohtml('^4[^7gt^4]^7neiT^7.')"></a></span> or <span><a class="hover:underline" :href="route('profile.index', 170)" v-html="q3tohtml('^9Jan^8Hejazi')"></a></span> on discord.
                            </div>

                            <div v-for="(tournament, id) in pastTournaments">
                                <TournamentCard :key="id" :tournament="tournament"></TournamentCard>
                            </div>
                        </div>
            
                        <div v-show="activeTab === 'search'" id="search-tab">
                            <div class="my-5 flex items-center justify-center">
                                <TextInput @input="onSearchTournament" v-model="searchInput" placeholder="Search tournament" type="text" class="w-96" />
                            </div>
            
                            <div v-for="tournament in filteredTournaments">
                                <TournamentCard :key="tournament.id" :tournament="tournament"></TournamentCard>
                            </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
            </div>
        </div>
</template>
