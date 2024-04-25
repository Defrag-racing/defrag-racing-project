<script setup>
    import { Link } from '@inertiajs/vue3';
    import Tournament from '@/Pages/Tournaments/Tournament.vue';
    import { useForm } from '@inertiajs/vue3';

    const props = defineProps({
        tournament: Object,
        donations: Number,
        suggestions: Number,
        myroles: Array,
        unvalidatedDemos: Number
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
            condition: props.myroles.includes('admin') || props.myroles.includes('organizer'),
            number: props.donations
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
            condition: props.myroles.includes('admin') || props.myroles.includes('organizer'),
            number: props.suggestions
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
            condition: props.myroles.includes('admin') || props.myroles.includes('organizer') || props.myroles.includes('validator'),
            number: props.unvalidatedDemos
        }
    ];

    const form = useForm({
        _method: 'POST'
    });

    const publishTournament = () => {
        form.post(route('tournaments.publish', props.tournament.id));
    }

</script>

<template>
    <Tournament :tournament="tournament" tab="ManageTournament">
        <div class="text-center w-full mb-5 text-lg text-white">
            The tournament is <span v-if="tournament.published" class="text-green-500">published</span><span v-else class="text-red-500">not published</span>.
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 xl:grid-cols-4 justify-between gap-3">
            <div v-for="button in buttons" :key="button.text">
                <Link v-if="button.condition" :href="button.route" class="p-3 relative block text-gray-300 font-bold bg-grayop-700 cursor-pointer hover:bg-grayop-600 text-center rounded-lg" :class="{'text-red-500': button.del}">
                    {{ button.text }}
                    <span v-if="button.number" class="absolute top-0 right-0 bg-red-500 text-white rounded-full w-5 h-5 flex items-center justify-center text-xs">
                        {{ button.number }}
                    </span>
                </Link>
            </div>
        </div>

        <div style="height: 1px; width: 100%;" class="bg-gray-600 mt-10"></div>

        <div class="flex gap-2 justify-center mt-10">
            <div class="text-red-500 font-bold bg-grayop-700 cursor-pointer hover:bg-grayop-600 text-center rounded-lg">
                <Link v-if="myroles.includes('admin')" :href="route('tournaments.delete.index', tournament.id)" class="block p-3">
                    Delete Tournament
                </Link>
            </div>

            <div class="font-bold bg-grayop-700 cursor-pointer hover:bg-grayop-600 text-center rounded-lg" :class="{'text-green-500': ! tournament.published, 'text-yellow-500': tournament.published}">
                <span v-if="props.myroles.includes('admin')" @click="publishTournament" class="block p-3">
                    {{ tournament.published ? 'Unpublish Tournament' : 'Publish Tournament' }}
                </span>
            </div>
        </div>
    </Tournament>
</template>
