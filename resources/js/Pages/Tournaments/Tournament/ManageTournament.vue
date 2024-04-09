<script setup>
    import { Link } from '@inertiajs/vue3';
    import Tournament from '@/Pages/Tournaments/Tournament.vue';

    const props = defineProps({
        tournament: Object,
        donations: Number,
        suggestions: Number,
        myroles: Array
    });

    const buttons = [
        {
            text: 'Edit Tournament',
            route: route('tournaments.edit', props.tournament.id),
            condition: props.myroles.includes('admin') || props.myroles.includes('organizer')
        },
        {
            text: 'Manage Donations',
            route: route('tournaments.donations.manage', props.tournament.id),
            condition: props.myroles.includes('admin') || props.myroles.includes('organizer')
        },
        {
            text: 'Manage Faqs',
            route: route('tournaments.faqs.manage', props.tournament.id),
            condition: props.myroles.includes('admin') || props.myroles.includes('organizer')
        },
        {
            text: 'Manage Streamers',
            route: route('tournaments.streamers.manage', props.tournament.id),
            condition: props.myroles.includes('admin') || props.myroles.includes('organizer')
        },
        {
            text: 'Manage Related Tournaments',
            route: route('tournaments.related.manage', props.tournament.id),
            condition: props.myroles.includes('admin') || props.myroles.includes('organizer')
        },
        {
            text: 'View Suggestions',
            route: route('tournaments.suggestions.index', props.tournament.id),
            condition: props.myroles.includes('admin') || props.myroles.includes('organizer')
        },
        {
            text: 'Manage Rounds',
            route: route('tournaments.rounds.manage', props.tournament.id),
            condition: props.myroles.includes('admin') || props.myroles.includes('organizer')
        },
        {
            text: 'Manage News',
            route: route('tournaments.news.manage', props.tournament.id),
            condition: props.myroles.includes('admin') || props.myroles.includes('organizer')
        },
        {
            text: 'Manage Organizers',
            route: route('tournaments.organizers.manage', props.tournament.id),
            condition: props.myroles.includes('admin') || props.myroles.includes('organizer')
        },
        {
            text: 'Validate Demos',
            route: route('tournaments.validation.index', props.tournament.id),
            condition: props.myroles.includes('admin') || props.myroles.includes('validator')
        },
        {
            text: 'Delete Tournament',
            route: route('tournaments.delete.index', props.tournament.id),
            condition: props.myroles.includes('admin'),
            del: true
        }
    ];

</script>

<template>
    <Tournament :tournament="tournament" tab="ManageTournament">
        <div class="grid grid-cols-1 md:grid-cols-3 xl:grid-cols-4 justify-between gap-3">
            <div v-for="button in buttons" :key="button.text" class="text-gray-300 font-bold bg-grayop-700 cursor-pointer hover:bg-grayop-600 text-center rounded-lg" :class="{'text-red-500': button.del}">
                <Link v-if="button.condition" :href="button.route" class="block p-3">
                    {{ button.text }}
                </Link>
            </div>
        </div>
    </Tournament>
</template>
