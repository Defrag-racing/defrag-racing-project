<script setup>
    import { Link } from '@inertiajs/vue3';

    const props = defineProps({
        active: String,
        tournament: Object,
        round: Object
    })

    const getClasses = (type) => {
        let results = {
            'bg-grayop-700 hover:bg-grayop-700': props.active === type, 
            'bg-grayop-900 hover:bg-grayop-800': props.active !== type,
        }

        if (type === 'clans') {
            results['rounded-r-md'] = ! props.tournament.has_teams
        }

        return results
    }

</script>

<template>
    <div class="my-2 w-full flex">
        <Link :href="active === 'single' ? '#' : route('tournaments.rounds.index', tournament.id, round.id)" class="p-2 rounded-l-md flex-1 text-gray-300 text-lg font-bold text-center cursor-pointer" :class="getClasses('single')">
            Individual Results
        </Link>

        <Link :href="active === 'clans' ? '#' : route('tournaments.rounds.clans', tournament.id, round.id)" class="p-2 flex-1 text-gray-300 text-lg font-bold text-center cursor-pointer" :class="getClasses('clans')">
            Clan Results
        </Link>

        <Link :href="active === 'teams' ? '#' : route('tournaments.rounds.index', tournament.id, round.id)" v-if="tournament.has_teams" class="p-2 rounded-r-md flex-1 text-gray-300 text-lg font-bold text-center cursor-pointer" :class="getClasses('teams')">
            Team Results
        </Link>
    </div>
</template>