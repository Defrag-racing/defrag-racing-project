<script>
import MainLayout from '@/Layouts/MainLayout.vue';

export default {
    layout: MainLayout,
};
</script>

<script setup>
    import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
    import { computed, ref } from 'vue';
    import moment from 'moment';
    import { t } from '@/utils/i18n';
    import { formatTime } from '@/utils/time';
    import { physicsBadge, physicsText, physicsTint } from '@/utils/physics';

    import Popper from 'vue3-popper';

    import BallotCard from '@/Components/Comps/BallotCard.vue';
    import CompsCountdown from '@/Components/Comps/CompsCountdown.vue';
    import CompsDonors from '@/Components/Comps/CompsDonors.vue';
    import CompsPlayer from '@/Components/Comps/CompsPlayer.vue';
    import CompsPayoutText from '@/Components/Comps/CompsPayoutText.vue';
    import CompsLeaderboard from '@/Components/Comps/CompsLeaderboard.vue';
    import DemoSettingsCheck from '@/Components/DemoSettingsCheck.vue';
    import ConfigModal from '@/Components/Comps/ConfigModal.vue';

    // The comps hub.
    //
    // Two things run side by side and always have: the week being played, and
    // the ballot for the week after it. That overlap is the whole design - it
    // is why there is never a gap where there is nothing to do, and it is why
    // this page has two halves rather than one.
    const props = defineProps({
        playing: { type: Object, default: null },
        voting: { type: Object, default: null },
        history: { type: Array, default: () => [] },
        leaderboard: { type: Object, default: () => ({ periods: [], rows: {} }) },
        me: { type: Object, default: null },
        pointsTable: { type: Array, default: () => [] },
        pointsForFinishing: { type: Number, default: 1 },
        winsPerWildcard: { type: Number, default: 5 },
        prize: { type: Object, default: null },
        funders: { type: Object, default: null },
        myNotices: { type: Array, default: () => [] },
    });

    const page = usePage();
    const user = computed(() => page.props.auth?.user);

    const PHYSICS = ['cpm', 'vq3'];

    // Gold, silver, bronze for the three rows of a past comp.
    const RANK_STYLE = {
        1: 'bg-amber-400/20 border-amber-400/40 text-amber-300',
        2: 'bg-slate-300/15 border-slate-300/40 text-slate-200',
        3: 'bg-orange-700/25 border-orange-500/40 text-orange-300',
    };

    const historyDates = (comp) => {
        if (!comp.starts_at || !comp.ends_at) return '';
        const a = new Date(comp.starts_at), b = new Date(comp.ends_at);
        const opts = { day: 'numeric', month: 'short' };
        return `${a.toLocaleDateString(undefined, opts)} - ${b.toLocaleDateString(undefined, { ...opts, year: 'numeric' })}`;
    };

    const CATEGORY_LABELS = {
        strafe: () => t('Strafe'),
        weapon: () => t('Weapon'),
        combo: () => t('Combo'),
    };

    const categoryLabel = (category) => (CATEGORY_LABELS[category] ?? (() => category))();

    // When a held demo shows up on the site. The sentence next to it already
    // says "once the round is over", which is the reason; this is the date,
    // which is the thing somebody actually wants.
    const appearsAt = (iso) => moment(iso).format('D MMM, HH:mm');


    // Who pays is no longer a sentence. It is the list of donors below the
    // number, which says the same thing as a fact and stops saying it on its
    // own when somebody's weeks run out.
    // Both options, and what to write for the second one. Telling somebody
    // their money can go to the prize pool without telling them the pool is
    // paid out over a stretch of weeks leaves the admin guessing how long a
    // donation was meant to last - which is the one thing that cannot be
    // worked out from the amount.
    const donateLine = computed(() =>
        t('Donations keep the site running. Write "comps" in the note to put all or part of yours into the prize pool instead - say how much, and over how many weeklies it should be spread.'),
    );

    // Totals per ballot, so each bar can show a share rather than a bare count.
    // A candidate carries no counts while the ballot is open - the server
    // leaves them out so nobody can read off the map that is going to win -
    // and there is nothing to total until it closes.
    const totals = computed(() => {
        const out = { cpm: 0, vq3: 0 };
        for (const c of props.voting?.candidates ?? []) {
            if (! c.votes) continue;
            out.cpm += c.votes.cpm;
            out.vq3 += c.votes.vq3;
        }
        return out;
    });

    // The ballot that chose the maps being played. One panel for the week
    // rather than one per physics: it is a single vote and it reads as one.
    const showBallot = ref(false);


    // Null when the round carries no ballot - an admin-made round, or one whose
    // candidates are gone - so the whole line stays off rather than printing
    // "won the vote, 0 of 0".
    const playedBallot = computed(() => {
        const b = props.playing?.ballot;
        return b && b.rows?.length ? b : null;
    });

    const winningVotes = (physics) =>
        playedBallot.value?.rows.find((r) => r.won?.[physics])?.votes?.[physics] ?? 0;

    const ballotTotal = (physics) => playedBallot.value?.totals?.[physics] ?? 0;

    const ballotShare = (physics, row) => {
        const total = ballotTotal(physics);
        const votes = row.votes?.[physics];
        return total && votes ? Math.round((votes / total) * 100) : 0;
    };

    const castVote = ({ candidate, physics }) => {
        router.post(route('comps.vote', props.voting.round_id), {
            candidate_id: candidate,
            physics,
        }, { preserveScroll: true });
    };

    // Spending a wildcard ends the argument for that physics, so it asks first
    // and says exactly what it will do.
    const wildcardTarget = ref(null);

    const confirmWildcard = () => {
        router.post(route('comps.wildcard', props.voting.round_id), {
            candidate_id: wildcardTarget.value.candidate,
            physics: wildcardTarget.value.physics,
        }, {
            preserveScroll: true,
            onFinish: () => (wildcardTarget.value = null),
        });
    };

    // "My demo did not go through." Raised from a demo comps is holding without
    // having entered it, and from your own entry the site refused - both of
    // which leave somebody with a file and no explanation they can act on.
    //
    // It points at the demo rather than at the entry, because the commonest
    // case by far - a file the parser could not read - has no entry at all.
    const demoReport = ref(null);
    const demoReportForm = useForm({ reason: '' });

    const askAboutDemo = (demoId, filename) => {
        demoReportForm.reset();
        demoReportForm.clearErrors();
        demoReport.value = { demoId, filename };
    };

    const sendDemoReport = () => {
        demoReportForm.post(route('comps.report-own-demo', demoReport.value.demoId), {
            preserveScroll: true,
            onSuccess: () => (demoReport.value = null),
        });
    };

    const mapReport = ref(null);

    const confirmMapReport = () => {
        router.post(route('comps.report-map', props.voting.round_id), {
            map_id: mapReport.value.map_id,
            physics: mapReport.value.physics,
        }, {
            preserveScroll: true,
            onFinish: () => (mapReport.value = null),
        });
    };

    // Wildcards, at a glance.
    const totalHeld = computed(() =>
        PHYSICS.reduce((n, p) => n + (props.me?.held?.[p] ?? 0), 0));

    const totalSpent = computed(() =>
        PHYSICS.reduce((n, p) => n + (props.me?.spent?.[p] ?? 0), 0));

    // Uploading an entry. No physics field: the demo says which it is.
    const uploadForm = useForm({
        demo: null,
        is_highlight: false,
        // Unix seconds, filled in when the file is picked: a run has to have
        // been made after this round's ballot opened, and the upload itself
        // carries no date at all.
        file_mtime: null,
    });

    // Ticking the highlight box is easy to do by accident and impossible to
    // undo after the deadline: the run is off the leaderboard, and by the time
    // somebody notices, the round is over. So it asks, once, before sending.
    const highlightConfirm = ref(false);

    // Closed by default: it answers a question most people do not have, and an
    // open drop zone under the upload is one more thing to mistake for the
    // upload itself.
    const showCheck = ref(false);

    // What a run has to be recorded with. It sits beside Rules rather than
    // inside them: the rules page says what is not allowed, this says what to
    // set, and the second question is the one that gets asked.
    const showConfig = ref(false);

    // Keep the file and its date together: the date is read off the File
    // object at pick time and travels with the upload.
    // Shown on the picker itself. The native file input renders as an OS
    // button labelled in the OS language, which put "Vybrat soubor" in the
    // middle of an English page - so it is hidden behind a label of ours, and
    // the chosen name has to be tracked by hand.
    const pickedDemoName = ref(null);

    const onPickDemo = (event) => {
        const file = event.target.files[0] ?? null;
        uploadForm.demo = file;
        uploadForm.file_mtime = file ? Math.floor((file.lastModified || 0) / 1000) : null;
        pickedDemoName.value = file?.name ?? null;
    };

    const send = () => {
        uploadForm.post(route('comps.submit', props.playing.round_id), {
            preserveScroll: true,
            onSuccess: () => {
                uploadForm.reset('demo', 'is_highlight', 'file_mtime');
                pickedDemoName.value = null;
            },
            onFinish: () => (highlightConfirm.value = false),
        });
    };

    const submitEntry = () => {
        if (uploadForm.is_highlight) {
            highlightConfirm.value = true;

            return;
        }

        send();
    };

    const myEntriesIn = (physics) => (props.playing?.my_entries ?? []).filter((e) => e.physics === physics);

    const bestOf = (physics) => {
        const valid = myEntriesIn(physics).filter((e) => e.status === 'valid' && !e.is_highlight);
        return valid.length ? Math.min(...valid.map((e) => e.time)) : null;
    };

    // The two panels fold up once you are done with them: the ballot when
    // you have voted in both physics, the round when you have a run in both.
    // What is left to do stays open; what is done becomes one line saying
    // what you did, with a button to unfold it. A click is remembered per
    // round in this browser, so the fold does not fight you every visit.
    const foldKey = (what, id) => `comps.fold.${what}.${id}`;
    const storedFold = (what, id) => {
        try { const v = localStorage.getItem(foldKey(what, id)); return v === null ? null : v === '1'; } catch { return null; }
    };
    const rememberFold = (what, id, folded) => {
        try { localStorage.setItem(foldKey(what, id), folded ? '1' : '0'); } catch {}
    };

    const votedBoth = computed(() => !!props.voting?.is_open && PHYSICS.every((p) => props.voting?.my_votes?.[p]));
    const ranBoth = computed(() => PHYSICS.every((p) => bestOf(p) !== null));

    const votingFolded = ref(storedFold('vote', props.voting?.round_id) ?? votedBoth.value);
    const playingFolded = ref(storedFold('play', props.playing?.round_id) ?? ranBoth.value);

    const toggleVoting = () => { votingFolded.value = !votingFolded.value; rememberFold('vote', props.voting?.round_id, votingFolded.value); };
    const togglePlaying = () => { playingFolded.value = !playingFolded.value; rememberFold('play', props.playing?.round_id, playingFolded.value); };

    const candidateName = (id) => (props.voting?.candidates ?? []).find((c) => c.id === id)?.map ?? '?';

    // Withdrawing is not undoable: the entry goes, and the same file cannot be
    // uploaded again because the site already holds its hash. So it asks.
    const withdrawTarget = ref(null);

    const confirmWithdraw = () => {
        const id = withdrawTarget.value?.id;
        withdrawTarget.value = null;

        if (id) router.delete(route('comps.submission.destroy', id), { preserveScroll: true });
    };

    const errors = computed(() => page.props.errors ?? {});
</script>

<template>
    <Head :title="$t('Comps')" />

    <div class="">
        <!-- Header Section - same shape as Servers, Records, Ranking and
             Wishlist: the gradient block holds the heading, the content is
             pulled up under it. A page of ours that starts differently reads
             as a page from somewhere else. -->
        <div class="relative bg-gradient-to-b from-black/25 via-black/10 to-transparent pt-6 pb-96 pointer-events-none">
            <!-- Above the panels below it, which are pulled up under this
                 block and paint later. Both hover bubbles up here live inside
                 an element with backdrop-blur, and a backdrop filter starts its
                 own stacking context - so their z-index counts only against
                 their neighbours in here, and without this the wildcard bubble
                 came out behind the prize panel. -->
            <div class="relative z-20 max-w-8xl mx-auto px-4 md:px-6 lg:px-8 pointer-events-auto">
                <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4 lg:gap-8">
                    <div class="min-w-0">
                        <!-- The warning rides on the title's line rather than
                             opposite it. It is a caveat about the page, so it
                             belongs to the heading, and the space it used to
                             take on the right is worth more to your own
                             standing. -->
                        <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
                            <h1 class="text-2xl md:text-3xl font-black text-gray-300/90">
                                {{ $t('Comps') }}
                            </h1>

                        </div>

                        <!-- Comps invents no ruleset of its own, and the two
                             things people keep asking about are answered by
                             name. "No special rules" is not an answer to
                             "is an overbounce allowed" - it just sends them to
                             read nine sections and guess. -->
                        <div class="mt-3 flex flex-wrap items-center gap-2 max-w-5xl">
                            <!-- What comps is and the four things that surprise
                                 people, behind one mark rather than as a panel.
                                 They are worth saying and worth saying once:
                                 printed in full they pushed the competition
                                 itself down the page, and somebody who has read
                                 them has to scroll past them every week after.
                                 Same "?" as the server cards, so it behaves the
                                 way the rest of the site does. -->
                            <Popper arrow hover placement="bottom-start" class="comps-popper" style="z-index: 1000;">
                                <button type="button"
                                        class="inline-flex items-center gap-1.5 h-7 flex-shrink-0 rounded-lg px-2.5 text-xs leading-none transition-colors cursor-help border border-dashed border-white/25 bg-white/[0.04] hover:bg-white/10 hover:border-white/40 font-bold text-gray-200">
                                    <svg class="w-3.5 h-3.5 flex-shrink-0 text-gray-300" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 5.25h.008v.008H12v-.008Z" />
                                    </svg>
                                    {{ $t('How comps works') }}
                                </button>
                                <template #content>
                                    <div class="px-4 py-3 max-w-md">
                                        <div class="text-[10px] font-black uppercase tracking-wider text-gray-300 mb-2">{{ $t('How comps works') }}</div>
                                        <p class="text-sm text-gray-300 mb-2 leading-snug">
                                            {{ $t('Every week the site draws five maps, everyone votes, and the map winners for both physics are played for a week. Nobody organises it and nobody can forget to.') }}
                                        </p>
                                        <ul class="space-y-1.5 text-sm text-gray-300">
                                            <li class="flex gap-2">
                                                <span class="text-gray-400">1.</span>
                                                <span>
                                                    {{ $t('You need a Q3DF.org profile linked to your account to enter a run or to vote.') }}
                                                    <Link v-if="user" :href="route('settings.show')" class="font-bold text-amber-300 underline decoration-amber-400/40 hover:text-amber-100">{{ $t('Open settings') }}</Link>
                                                </span>
                                            </li>
                                            <li class="flex gap-2">
                                                <span class="text-gray-400">2.</span>
                                                <span>{{ $t('There is no sign-up. Record a run on the map being played and it enters by itself.') }}</span>
                                            </li>
                                            <li class="flex gap-2">
                                                <span class="text-gray-400">3.</span>
                                                <span>{{ $t('A demo of the map being played, in the physics it is being played in, stays hidden until the round is over. It appears then, together with everyone else\'s.') }}</span>
                                            </li>
                                            <li class="flex gap-2">
                                                <span class="text-gray-400">4.</span>
                                                <span>{{ $t('A run made before the vote for the round opened does not count in it.') }}</span>
                                            </li>
                                        </ul>
                                    </div>
                                </template>
                            </Popper>

                            <!-- Where a problem goes. It used to be a grey
                                 "comps is brand new, tell the admin" line
                                 under the title; a chip in the row people
                                 already read, pointing at the board, is
                                 shorter and does not call the page unfinished. -->
                            <Link :href="route('wishlist.index')"
                                  class="inline-flex items-center gap-1.5 h-7 flex-shrink-0 rounded-lg px-2.5 text-xs leading-none transition-colors cursor-pointer border border-dashed border-white/25 bg-white/[0.04] hover:bg-white/10 hover:border-white/40 font-bold text-gray-200">
                                <svg class="w-3.5 h-3.5 flex-shrink-0 text-gray-300" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 12.75c1.148 0 2.278.08 3.383.237 1.037.146 1.866.966 1.866 2.013 0 3.728-2.35 6.75-5.25 6.75S6.75 18.728 6.75 15c0-1.046.83-1.867 1.866-2.013A24.204 24.204 0 0 1 12 12.75Zm0 0c2.883 0 5.647.508 8.207 1.44a23.91 23.91 0 0 1-1.152 6.06M12 12.75c-2.883 0-5.647.508-8.208 1.44.125 2.104.52 4.136 1.153 6.06M12 12.75a2.25 2.25 0 0 0 2.248-2.354M12 12.75a2.25 2.25 0 0 1-2.248-2.354M12 8.25c.995 0 1.971-.08 2.922-.236.403-.066.74-.358.795-.762a3.778 3.778 0 0 0-.399-2.25M12 8.25c-.995 0-1.97-.08-2.922-.236-.402-.066-.74-.358-.795-.762a3.734 3.734 0 0 1 .4-2.253M12 8.25a2.25 2.25 0 0 0-2.248 2.146M12 8.25a2.25 2.25 0 0 1 2.248 2.146M8.683 5a3.75 3.75 0 0 1 6.634 0" />
                                </svg>
                                {{ $t('Report a bug') }}
                            </Link>

                            <Link :href="route('rules')"
                                  class="inline-flex items-center gap-1.5 h-7 flex-shrink-0 rounded-lg px-2.5 text-xs leading-none transition-colors cursor-pointer border border-amber-400/50 bg-amber-500/15 hover:bg-amber-500/25 hover:border-amber-300/70 font-bold text-amber-200">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v17.25m0 0c-1.472 0-2.882.265-4.185.75M12 20.25c1.472 0 2.882.265 4.185.75M18.75 4.97A48.416 48.416 0 0 0 12 4.5c-2.291 0-4.545.16-6.75.47m13.5 0c1.01.143 2.01.317 3 .52m-3-.52 2.62 10.726c.122.499-.106 1.028-.589 1.202a5.988 5.988 0 0 1-2.031.352 5.988 5.988 0 0 1-2.031-.352c-.483-.174-.711-.703-.59-1.202L18.75 4.971Zm-16.5.52c.99-.203 1.99-.377 3-.52m0 0 2.62 10.726c.122.499-.106 1.028-.589 1.202a5.989 5.989 0 0 1-2.031.352 5.989 5.989 0 0 1-2.031-.352c-.483-.174-.711-.703-.59-1.202L5.25 4.971Z" />
                                </svg>
                                {{ $t('Rules') }}
                            </Link>

                            <!-- Offline is the only case where the player has
                                 to do anything, and it is the case nobody is
                                 told about until a run is refused. -->
                            <button type="button"
                                    @click="showConfig = true"
                                    class="inline-flex items-center gap-1.5 h-7 flex-shrink-0 rounded-lg px-2.5 text-xs leading-none transition-colors cursor-pointer border border-blue-400/50 bg-blue-500/15 hover:bg-blue-500/25 hover:border-blue-300/70 font-bold text-blue-200">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.343 3.94c.09-.542.56-.94 1.11-.94h1.093c.55 0 1.02.398 1.11.94l.149.894c.07.424.384.764.78.93.398.164.855.142 1.205-.108l.737-.527a1.125 1.125 0 0 1 1.45.12l.773.774c.39.389.44 1.002.12 1.45l-.527.737c-.25.35-.272.806-.107 1.204.165.397.505.71.93.78l.893.15c.543.09.94.559.94 1.109v1.094c0 .55-.397 1.02-.94 1.11l-.894.149c-.424.07-.764.383-.929.78-.165.398-.143.854.107 1.204l.527.738c.32.447.269 1.06-.12 1.45l-.774.773a1.125 1.125 0 0 1-1.449.12l-.738-.527c-.35-.25-.806-.272-1.203-.107-.398.165-.71.505-.781.929l-.149.894c-.09.542-.56.94-1.11.94h-1.094c-.55 0-1.019-.398-1.11-.94l-.148-.894c-.071-.424-.384-.764-.782-.93-.398-.164-.854-.142-1.204.108l-.738.527c-.447.32-1.06.269-1.45-.12l-.773-.774a1.125 1.125 0 0 1-.12-1.45l.527-.737c.25-.35.272-.806.108-1.204-.165-.397-.506-.71-.93-.78l-.894-.15c-.542-.09-.94-.56-.94-1.109v-1.094c0-.55.398-1.02.94-1.11l.894-.149c.424-.07.765-.383.93-.78.165-.398.143-.854-.108-1.204l-.526-.738a1.125 1.125 0 0 1 .12-1.45l.773-.773a1.125 1.125 0 0 1 1.45-.12l.737.527c.35.25.807.272 1.204.107.397-.165.71-.505.78-.929l.15-.894Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                </svg>
                                {{ $t('Config') }}
                            </button>

                            <!-- The two questions that get asked in Discord
                                 every week. "Same rules as the servers" is a
                                 true answer to neither, so both are answered
                                 by name and where they will be seen.

                                 Not green: green is money on this page - the
                                 pool, the donors, the donate button - and a
                                 rules note has nothing to do with any of it.

                                 Both are written short on purpose. They are
                                 chips, not sentences: the pair has to sit on
                                 one line beside the buttons before it, and a
                                 full sentence wrapped the row. -->
                            <span class="inline-flex items-center gap-1.5 h-7 flex-shrink-0 rounded-lg px-2.5 text-xs leading-none transition-colors cursor-default bg-sky-500/10 text-sky-100/90">
                                <svg class="w-3.5 h-3.5 flex-shrink-0 text-sky-400" viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.2 4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4L9 16.2z" /></svg>
                                {{ $t('OverBounces & Time Resets are allowed.') }}
                            </span>

                            <!-- The second question after overbounces. Comps
                                 reads nothing off the servers, so a run made
                                 alone counts exactly as much as one made in
                                 front of people. -->
                            <span class="inline-flex items-center gap-1.5 h-7 flex-shrink-0 rounded-lg px-2.5 text-xs leading-none transition-colors cursor-default bg-sky-500/10 text-sky-100/90">
                                <svg class="w-3.5 h-3.5 flex-shrink-0 text-sky-400" viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.2 4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4L9 16.2z" /></svg>
                                {{ $t('Online & Offline demos allowed.') }}
                            </span>
                        </div>
                    </div>

                    <!-- ============================== YOU ============================== -->
                    <!-- Opposite the title, in the slot the beta warning used
                         to have.
                         Four figures on a row with their labels underneath,
                         rather than one long sentence of number-word pairs. It
                         was written as a strip when it ran the width of the
                         page; in a column that shape wraps into an unreadable
                         run of small grey text, and it is the one panel here
                         that is about the person reading it. -->
                    <section v-if="me" class="flex-shrink-0 w-full lg:w-[30rem] rounded-xl border border-white/15 border-l-4 border-l-blue-400/70 bg-gradient-to-br from-white/[0.10] to-white/[0.03] backdrop-blur-sm px-4 py-2.5">
                        <!-- No heading. The four figures are labelled one
                             by one and sit opposite the page title, so a row
                             spent saying "Your comps" said nothing the panel
                             was not already saying. -->
                        <div class="grid grid-cols-5 gap-2 text-center">
                            <!-- Wildcards. Gold only when there is one to
                                 spend, so it stays quiet the rest of the time.
                                 What one actually does hangs off it on hover:
                                 it is a rule you read once, and printed out it
                                 took a line of the page away from the
                                 competition every week after that. -->
                            <Popper arrow hover placement="bottom" class="comps-popper" style="z-index: 1000;">
                                <div class="cursor-help">
                                    <div class="h-6 flex items-center justify-center gap-1">
                                        <svg class="w-3.5 h-3.5" :class="totalHeld > 0 ? 'text-amber-400' : 'text-gray-400'" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l2.4 7.4H22l-6.2 4.5 2.4 7.4-6.2-4.6-6.2 4.6 2.4-7.4L2 9.4h7.6z" /></svg>
                                        <span class="text-lg font-black tabular-nums leading-none" :class="totalHeld > 0 ? 'text-amber-300' : 'text-gray-300'">{{ totalHeld }}</span>
                                    </div>
                                    <div class="mt-0.5 text-[10px] uppercase tracking-wider text-gray-300 decoration-dotted underline decoration-white/25 underline-offset-2">
                                        <!-- Never split by physics any more.
                                             The label used to read "CPM 1",
                                             which was true when a wildcard
                                             could only be spent where it was
                                             won; now that it goes on either
                                             ballot, naming the physics states
                                             a restriction that no longer
                                             exists. -->
                                        {{ $t('Wildcards') }}
                                    </div>
                                </div>
                                <template #content>
                                    <div class="px-4 py-3 max-w-sm">
                                        <p class="text-sm text-gray-300 leading-snug">
                                            {{ $t('A wildcard picks next week\'s map outright and overrules the vote. You earn one for every :count weekly wins in a physics, and it can be spent on either ballot. Whoever spends one first decides that round - everyone else keeps theirs.', { count: winsPerWildcard }) }}
                                        </p>
                                        <p v-if="totalSpent > 0" class="mt-1.5 text-xs text-gray-300">
                                            {{ $t('Already spent: :count', { count: totalSpent }) }}
                                        </p>
                                    </div>
                                </template>
                            </Popper>

                            <div>
                                <div class="h-6 flex items-center justify-center text-lg font-black tabular-nums leading-none text-white">{{ me.wins.cpm + me.wins.vq3 }}</div>
                                <div class="mt-0.5 text-[10px] uppercase tracking-wider text-gray-300">{{ $t('Weeks won') }}</div>
                            </div>

                            <div>
                                <div class="h-6 flex items-center justify-center text-lg font-black tabular-nums leading-none" :class="me.rounds_entered ? 'text-white' : 'text-gray-400'">{{ me.average_rank ?? '-' }}</div>
                                <div class="mt-0.5 text-[10px] uppercase tracking-wider text-gray-300">{{ $t('Average rank') }}</div>
                            </div>

                            <div>
                                <div class="h-6 flex items-center justify-center text-lg font-black tabular-nums leading-none" :class="me.best_rank ? 'text-white' : 'text-gray-400'">{{ me.best_rank ?? '-' }}</div>
                                <div class="mt-0.5 text-[10px] uppercase tracking-wider text-gray-300">{{ $t('Best rank') }}</div>
                            </div>

                            <!-- A cell like the rest of them. It was a sentence
                                 under the panel, which cost a whole row to say
                                 one number that belongs in the row above it. -->
                            <div>
                                <div class="h-6 flex items-center justify-center text-lg font-black tabular-nums leading-none" :class="me.rounds_entered ? 'text-white' : 'text-gray-400'">{{ me.rounds_entered }}</div>
                                <div class="mt-0.5 text-[10px] uppercase tracking-wider text-gray-300">{{ $t('Participated') }}</div>
                            </div>
                        </div>

                        <!-- Progress to the next one, as one bar per physics. -->
                        <div class="mt-2 pt-2 border-t border-white/10 flex flex-wrap items-center gap-x-4 gap-y-1">
                            <span class="text-[10px] uppercase tracking-wider text-gray-300">{{ $t('Next wildcard') }}</span>
                            <span v-for="physics in PHYSICS" :key="physics" class="inline-flex items-center gap-1.5">
                                <span class="text-[10px] font-black uppercase tracking-wider text-gray-300">{{ physics }}</span>
                                <span class="w-14 h-1.5 rounded-full bg-black/60 overflow-hidden">
                                    <span class="block h-full rounded-full bg-amber-400"
                                          :style="{ width: (((me.wins[physics] % winsPerWildcard) / winsPerWildcard) * 100) + '%' }"></span>
                                </span>
                                <span class="text-[11px] tabular-nums text-gray-300">{{ me.wins[physics] % winsPerWildcard }}/{{ winsPerWildcard }}</span>
                            </span>
                        </div>
                    </section>

                </div>
            </div>
        </div>

    <!-- Six units between sections, not ten. Ten was set when the page was
         three big boxes; with the header block above them it left a hole
         between the status strip and the ballot that read as a gap rather
         than a break. -->
    <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8 pb-12 space-y-4" style="margin-top: -23.5rem;">

        <!-- Who paid, as a banner in the page's right margin.
             It belongs beside the prize rather than under it: it is the answer
             to "who is funding this", which somebody wants once and then never
             again, while the panel it used to sit in is read every week. Only
             rendered where the margin is genuinely wide enough to hold it -
             below that width the same list appears inline under the prize. -->
        <aside v-if="funders?.donors?.length" class="donors-rail">
            <div class="rounded-xl border border-emerald-400/25 bg-gradient-to-b from-emerald-500/[0.12] to-black/60 backdrop-blur-sm p-3 shadow-[0_0_30px_-12px_rgba(16,185,129,0.35)]">
                <CompsDonors :funders="funders" rail />
            </div>
        </aside>

        <!-- Your own standing and the prize are one header block, spaced
             tightly against each other. The page's 10-unit rhythm is for
             actual sections; giving a two-line status strip the same air
             around it as the competition itself reads as a gap, not a break.

             Yours comes first. The prize is the same number every week and is
             read once; what changed for you since last week is the reason to
             open the page at all. -->
        <div class="space-y-3">

        <!-- What a week pays and who is paying for it. Sits above everything
             else because it is the answer to the first question anybody asks
             about a competition. -->
        <!-- Tinted rather than plain glass. This is the one panel on the page
             that has to catch the eye on the way past, and a page where every
             box is the same shade of black says nothing is more important than
             anything else. Kept to a wash and a border, not a bright block. -->
        <!-- Shown when there is money anywhere: a week paying nothing while
             donors have funded later ones is exactly when the pool needs
             saying, and keying the whole panel to the weekly figure would
             hide the donors along with it. -->
        <section v-if="prize?.eur > 0 || funders?.total"
                 class="relative overflow-hidden rounded-2xl border border-emerald-400/25 bg-gradient-to-br from-emerald-500/[0.12] via-black/40 to-black/40 backdrop-blur-sm p-4 md:px-5 md:py-4 shadow-[0_0_40px_-12px_rgba(16,185,129,0.35)]">
            <!-- A soft bloom behind the number, so the corner it sits in is
                 lit rather than outlined. -->
            <div class="pointer-events-none absolute -top-24 -left-16 w-72 h-72 rounded-full bg-emerald-400/10 blur-3xl"></div>

            <div class="relative flex flex-col md:flex-row md:flex-wrap md:items-center gap-4 md:gap-6">

                <!-- The whole pool, not what one week pays.
                     A weekly figure answers "what do I win" and nothing else;
                     the total is what somebody repeats to a friend, and it is
                     the number the donors actually handed over. The week is
                     still here, beside it, because a competitor needs it. -->
                <!-- Every figure on one side of the divider, all the prose on
                     the other. The two small lines sit BESIDE the big number
                     rather than under it: a 4xl numeral is already two lines
                     tall, so the space next to it is free and stacking them
                     underneath cost the panel a row for nothing.

                     A floor under its width, but only where there is width to
                     spare: it is the part of the panel people look at, and a
                     divider sitting tight against the longest figure gave the
                     money the narrow half of a very wide box. Below xl the
                     floor comes off entirely - held at every breakpoint it left
                     the prose about forty pixels wide on a laptop and printed
                     it one word per line. -->
                <div class="flex-shrink-0 xl:min-w-[33rem]">
                    <div class="inline-flex items-center gap-1.5 text-[11px] font-black uppercase tracking-widest text-emerald-400/90 mb-1">
                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="currentColor"><path d="M18 2H6v2H2v3a5 5 0 0 0 4.6 5A5.5 5.5 0 0 0 11 15.9V19H7v3h10v-3h-4v-3.1a5.5 5.5 0 0 0 4.4-3.9A5 5 0 0 0 22 7V4h-4V2zM4 7V6h2v3.9A3 3 0 0 1 4 7zm16 0a3 3 0 0 1-2 2.9V6h2v1z" /></svg>
                        {{ funders?.total ? $t('Total prize pool') : $t('Prize pool') }}
                    </div>

                    <div class="flex items-center gap-6">
                        <div class="flex items-baseline gap-2">
                            <span class="text-4xl md:text-5xl font-black text-emerald-300 tabular-nums leading-none">{{ funders?.total ?? prize.total }}</span>
                            <span class="text-xl md:text-2xl font-black text-emerald-300/70 leading-none">EUR</span>
                        </div>

                        <div class="min-w-0 leading-snug">
                            <!-- Says nothing about where the donors are
                                 printed: they are below on a narrow screen and
                                 out in the right margin on a wide one. -->
                            <div class="text-base text-emerald-100/60 whitespace-nowrap">
                                <template v-if="funders?.weeks">{{ $t('distributed over :count weekly comps', { count: funders.weeks }) }}</template>
                                <template v-else>{{ $t('every week, :amount EUR per physics', { amount: prize.eur }) }}</template>
                            </div>

                            <!-- What this week itself pays. Split out of the
                                 headline once that became the whole pool: the
                                 two numbers answer different questions and one
                                 of them is the one you play for. -->
                            <div v-if="funders?.total" class="text-base whitespace-nowrap">
                                <span class="font-bold text-emerald-200">{{ $t('This week: :total EUR', { total: prize.total }) }}</span>
                                <span class="text-gray-300">{{ ' ' }}{{ $t('(:amount EUR per physics)', { amount: prize.eur }) }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="hidden md:block w-px self-stretch bg-emerald-400/15"></div>

                <div class="min-w-0 flex-1 md:min-w-[20rem]">
                    <p class="text-sm text-gray-300 leading-snug">
                        <span>{{ donateLine }}</span>
                        <!-- The space is written out: Vue drops whitespace
                             between elements when it contains a newline, which
                             ran this straight into the sentence above it.

                             Split off rather than linked mid-sentence: the
                             clause lands in a different place in every
                             language, and a link glued into one word order
                             breaks in the other eight. -->
                        {{ ' ' }}
                        <Link :href="route('donations.index')" class="text-gray-300 underline decoration-white/20 hover:text-gray-200">{{ $t('See everything donations pay for.') }}</Link>
                    </p>
                </div>

                <Link :href="route('donations.index')"
                      class="flex-shrink-0 inline-flex items-center justify-center gap-2 rounded-xl border border-emerald-400/40 bg-emerald-500/15 px-5 py-3 text-sm font-bold text-emerald-200 hover:bg-emerald-500/25 hover:border-emerald-400/60 transition-colors">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M12 21s-8-4.9-8-10.4A4.6 4.6 0 0 1 12 7a4.6 4.6 0 0 1 8 3.6C20 16.1 12 21 12 21z" /></svg>
                    {{ $t('Donate to the pool') }}
                </Link>
            </div>

            <!-- Only where the floating banner has nowhere to float. On a
                 screen wide enough it lives in the right margin instead, so
                 the prize panel itself stays two lines tall. -->
            <div v-if="funders?.donors?.length"
                 class="donors-inline relative mt-5 pt-4 border-t border-emerald-400/15">
                <CompsDonors :funders="funders" />
            </div>
        </section>

        </div>

        <!-- ============================ VOTING ============================= -->
        <!-- Same panel as Playing now on purpose: the two halves of this page
             are the two halves of the same week, and giving one a box and the
             other a bare heading made them read as unrelated. -->
        <!-- Tinted blue, the way the prize pool is tinted green, but lighter.
             The ballot is the half of the page with a deadline on it, so it
             should read as more urgent than the round being played and less
             loud than the money. -->
        <section v-if="voting"
                 class="rounded-2xl border border-blue-400/25 bg-black/40 backdrop-blur-sm overflow-hidden shadow-[0_0_35px_-16px_rgba(96,165,250,0.4)]">
            <!-- Everything the ballot is about, on one line: what you are
                 doing, what it is a ballot of, what it pays, when it shuts.
                 Then the one sentence explaining how voting works underneath
                 it, inside the same header.

                 It was a pill, a heading, two bordered chips, a money box and
                 a stacked countdown across two rows, with the explanation
                 floating loose above the cards - six shapes at five heights to
                 carry four facts. Nothing here is boxed now: the category is
                 simply painted in the panel's own colour and the week after it
                 is a grey aside, so the row reads left to right as a sentence
                 rather than as a strip of badges. -->
            <div class="border-b border-blue-400/20 bg-gradient-to-r from-blue-500/[0.12] to-white/5 backdrop-blur-sm px-5 py-3">
                <div class="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-1.5">
                    <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1 min-w-0">
                        <!-- Once the ballot has shut, the panel stops being a
                             ballot and becomes the announcement of the round
                             that is about to run - so it names that round
                             instead of describing what happened to the vote.
                             "Next week's maps are decided" said neither what
                             was starting nor when; paired with the countdown
                             beside it, the comp's own name answers both. -->
                        <span class="text-lg font-black text-white">
                            {{ voting.is_open
                                ? $t('Vote on the next map')
                                : $t('Up next: :comp', { comp: voting.comp_title }) }}
                        </span>
                        <span class="text-sm font-black uppercase tracking-wider text-blue-300">
                            {{ categoryLabel(voting.category) }}<template v-if="voting.weapon"> · {{ voting.weapon }}</template>
                        </span>
                        <!-- The rotation is fixed years ahead, so there is no
                             reason to keep the week after it a surprise. Said
                             as a sentence rather than as "then STRAFE": the
                             two words next to a category being voted on right
                             now do not make it obvious which ballot they mean. -->
                        <span v-if="voting.next_category" class="text-xs text-gray-300">
                            {{ $t("Next week's vote will be :category", { category: categoryLabel(voting.next_category) }) }}
                        </span>
                    </div>

                    <!-- Labelled the same as the round being played, and no
                         longer "for next week". This panel already says it is
                         about the next map, so dating the figure only invited
                         the reading that the money was for the voting itself.
                         The per-physics split is a title rather than a third
                         number on the row - the pool panel above prints it. -->
                    <div class="flex flex-wrap items-baseline gap-x-5 gap-y-1">
                        <span v-if="voting.prize?.eur > 0" class="inline-flex items-baseline gap-2">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-300/60">{{ $t('Playing for') }}</span>
                            <span class="text-base font-black tabular-nums text-emerald-300">{{ voting.prize.total }} EUR</span>
                            <!-- Printed, not a tooltip. The total covers both physics, so a
                                 winner takes half of it, and a number nobody can act on
                                 until they hover is a number most people read wrong. -->
                            <span class="text-[11px] tabular-nums text-emerald-100/50">{{ $t('(:amount EUR per physics)', { amount: voting.prize.eur }) }}</span>
                        </span>

                        <!-- Two different clocks, because this panel lives
                             through two states and the gap between them is a
                             whole day. While the ballot is open the deadline
                             is what matters; once it has closed the only
                             question left is when the thing actually starts,
                             and a panel that answered neither was the most
                             confusing screen on the site: voting visibly over,
                             nothing saying so, and no date anywhere. -->
                        <CompsCountdown v-if="voting.is_open"
                                        :until="voting.closes_at" :label="$t('Voting closes in')" emphasis inline />
                        <CompsCountdown v-else
                                        :until="voting.starts_at" :label="$t('Starts in')" emphasis inline />
                        <button type="button" @click="toggleVoting"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-blue-400/50 bg-blue-500/30 px-3 py-1.5 text-xs font-black text-white shadow-[0_0_16px_-4px_rgba(96,165,250,0.7)] hover:bg-blue-500/50 transition-colors"
                                :title="votingFolded ? $t('Show') : $t('Hide')">
                            <svg class="w-4 h-4 transition-transform" :class="votingFolded ? '' : 'rotate-180'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M6 9l6 6 6-6" /></svg>
                            {{ votingFolded ? $t('Show') : $t('Hide') }}
                        </button>
                    </div>
                </div>

                <!-- Belongs to the header, not to the cards. It explains the
                     panel as a whole, and sitting above the grid it read as a
                     caption on the first row of maps. Folded, the line says
                     what you voted for instead. -->
                <!-- The folded line is the button too: the whole width
                     unfolds the panel, not only the small button on the
                     right. -->
                <button v-if="votingFolded && voting.is_open" type="button" @click="toggleVoting"
                        class="mt-1.5 -mx-2 flex w-[calc(100%+1rem)] flex-wrap items-baseline gap-x-2 rounded-lg px-2 py-1 text-left text-sm text-gray-300 hover:bg-white/5 transition-colors">
                    <span class="text-gray-300">{{ $t('Your votes') }}:</span>
                    <span v-for="physics in PHYSICS" :key="physics" class="inline-flex items-baseline gap-1.5 mr-3">
                        <span class="font-bold uppercase" :class="physicsText(physics)">{{ physics }}</span>
                        <span class="font-bold text-white">{{ voting.my_votes?.[physics] ? candidateName(voting.my_votes[physics]) : '-' }}</span>
                    </span>
                    <span class="ml-auto text-xs text-blue-300">{{ $t('Show') }} ▾</span>
                </button>
                <p v-else class="mt-1.5 text-sm text-gray-300">
                    <template v-if="voting.is_open">{{ $t('CPM and VQ3 vote separately, so each physics gets the map its own players picked. You have one vote in each and can move it until the deadline.') }}</template>
                    <template v-else>{{ $t('Voting is over. These are the maps, and the round starts when the countdown runs out.') }}</template>
                </p>
            </div>

            <div v-show="!votingFolded" class="px-5 pt-4 pb-5">

            <div v-if="user && !voting.may_vote" class="mb-4 rounded-lg border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm text-amber-200">
                {{ $t('Link your mDd profile to vote in comps.') }}
            </div>
            <div v-else-if="!user" class="mb-4 rounded-lg border border-white/10 bg-black/40 backdrop-blur-sm px-4 py-3 text-sm text-gray-300">
                {{ $t('Sign in to vote.') }}
            </div>

            <div v-if="errors.wildcard" class="mb-4 rounded-lg border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-300">
                {{ errors.wildcard }}
            </div>

            <!-- Once decided, say so and by what. Given its own heading:
                 with the ballot closed these two boxes are the answer people
                 came for, and unlabelled they read as another pair of cards.
                 It labels, it does not date - the countdown above already says
                 when, and "next week" reads as a contradiction next to a clock
                 counting down to tomorrow evening. The pairing with "final
                 votes" below is the point: two maps that get played, five that
                 were on the ballot. -->
            <div v-if="!voting.is_open" class="mb-1.5 text-[11px] font-black uppercase tracking-widest text-blue-300/70">
                {{ $t('What gets played') }}
            </div>
            <div v-if="!voting.is_open" class="mb-5 grid gap-3 sm:grid-cols-2">
                <div
                    v-for="physics in PHYSICS"
                    :key="physics"
                    class="rounded-lg border border-white/10 bg-black/40 backdrop-blur-sm px-4 py-3"
                >
                    <div class="text-[10px] font-black uppercase tracking-wider text-gray-300">{{ physics }}</div>
                    <div class="font-bold text-white">{{ voting.decided?.[physics]?.map ?? '-' }}</div>
                    <div class="text-[11px] text-gray-300">
                        <template v-if="voting.decided?.[physics]?.decided_by === 'wildcard'">{{ $t('Chosen with a wildcard') }}</template>
                        <template v-else-if="voting.decided?.[physics]?.decided_by === 'carried'">{{ $t('Nobody voted in this physics, so it took the other one\'s map') }}</template>
                        <template v-else-if="voting.decided?.[physics]?.decided_by === 'random'">{{ $t('Nobody voted at all, so it was drawn at random') }}</template>
                        <template v-else>{{ $t('Chosen by vote') }}</template>
                    </div>
                </div>
            </div>

            <!-- The ballot below is history once it has closed, so it says
                 so rather than sitting there looking like it still takes
                 clicks. -->
            <div v-if="!voting.is_open" class="mb-1.5 text-[11px] font-black uppercase tracking-widest text-gray-300">
                {{ $t('Final votes') }}
            </div>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
                <BallotCard
                    v-for="candidate in voting.candidates"
                    :key="candidate.id"
                    :candidate="candidate"
                    :my-votes="voting.my_votes"
                    :wildcards-held="voting.wildcards_held"
                    :may-vote="voting.may_vote"
                    :is-open="voting.is_open"
                    :totals="totals"
                    @vote="castVote"
                    @wildcard="wildcardTarget = $event"
                    @report="mapReport = { ...$event, physics: 'cpm' }"
                />
            </div>
            </div>
        </section>

        <!-- ============================ PLAYING ============================ -->
        <section v-if="playing" class="rounded-2xl border border-white/10 bg-black/40 backdrop-blur-sm overflow-hidden">
            <!-- The same row as the ballot's, in green: what it is, what it
                 pays, when it stops. The countdown used to stack its wall
                 clock under itself and push this header to two lines for the
                 sake of one date. -->
            <div class="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-1.5 border-b border-white/10 bg-white/[0.04] backdrop-blur-sm px-5 py-3">
                <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1 min-w-0">
                    <span class="text-xs font-black uppercase tracking-wider text-green-300">
                        {{ $t('Playing now') }}
                    </span>
                    <span class="text-lg font-black text-white">{{ playing.comp_title }}</span>
                    <span class="text-sm font-black uppercase tracking-wider text-gray-300">
                        {{ categoryLabel(playing.category) }}<template v-if="playing.weapon"> · {{ playing.weapon }}</template>
                    </span>
                </div>

                <div class="flex flex-wrap items-baseline gap-x-5 gap-y-1">
                    <span v-if="playing.prize?.eur > 0" class="inline-flex items-baseline gap-2">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-300/60">{{ $t('Playing for') }}</span>
                        <span class="text-base font-black tabular-nums text-emerald-300">{{ playing.prize.total }} EUR</span>
                        <!-- Printed, not a tooltip. The total covers both physics, so a
                             winner takes half of it, and a number nobody can act on
                             until they hover is a number most people read wrong. -->
                        <span class="text-[11px] tabular-nums text-emerald-100/50">{{ $t('(:amount EUR per physics)', { amount: playing.prize.eur }) }}</span>
                    </span>

                    <CompsCountdown :until="playing.ends_at" :label="$t('Ends in')" inline />
                    <button type="button" @click="togglePlaying"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-white/20 bg-white/10 px-3 py-1.5 text-xs font-black text-white hover:bg-white/20 transition-colors"
                            :title="playingFolded ? $t('Show') : $t('Hide')">
                        <svg class="w-4 h-4 transition-transform" :class="playingFolded ? '' : 'rotate-180'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M6 9l6 6 6-6" /></svg>
                        {{ playingFolded ? $t('Show') : $t('Hide') }}
                    </button>
                </div>
            </div>

            <!-- Folded: one line with your best in each physics, and the maps
                 by name so the fold still says what is being played. -->
            <button v-if="playingFolded" type="button" @click="togglePlaying"
                    class="flex w-full flex-wrap items-baseline gap-x-6 gap-y-1 px-5 py-3 text-left text-sm text-gray-300 hover:bg-white/5 transition-colors">
                <span v-for="physics in PHYSICS" :key="physics" class="inline-flex items-baseline gap-2">
                    <span class="font-bold uppercase" :class="physicsText(physics)">{{ physics }}</span>
                    <span class="font-bold text-white">{{ playing.maps?.[physics]?.name ?? '-' }}</span>
                    <span v-if="bestOf(physics) !== null" class="text-gray-300">{{ $t('Your best') }} <span class="font-bold text-white tabular-nums">{{ formatTime(bestOf(physics)) }}</span></span>
                </span>
                <span class="ml-auto text-xs text-gray-300">{{ $t('Show') }} ▾</span>
            </button>

            <!-- One box, split down the middle, rather than two cards with
                 a gap. The two halves are the same week and the ballot below
                 them chose both, so a border around each said they were
                 separate things.

                 The line about times staying hidden used to be printed inside
                 each half, so it was on screen twice saying the same thing. It
                 is the footer of the box now. -->
            <!-- Maps on the left, entering on the right, half and half on a
                 wide screen. Under each other the upload sat below the maps
                 and the two physics side by side left the maps half empty;
                 one above the other they fill their column. -->
            <div v-show="!playingFolded" class="grid lg:grid-cols-2">
            <div class="p-5">
                <div class="rounded-xl border border-white/10 bg-black/30 backdrop-blur-sm overflow-hidden">
                    <div class="divide-y divide-white/10">
                        <div v-for="physics in PHYSICS" :key="physics" class="p-4">
                            <div class="flex items-center justify-between gap-3 mb-3">
                                <span class="rounded-md bg-white/[0.07] px-2 py-0.5 text-[11px] font-black uppercase tracking-widest text-gray-300">
                                    {{ physics }}
                                </span>
                                <span v-if="bestOf(physics) !== null" class="text-xs text-gray-300">
                                    {{ $t('Your best') }}
                                    <span class="font-bold text-white tabular-nums ml-1">{{ formatTime(bestOf(physics)) }}</span>
                                </span>
                            </div>

                            <!-- Thumbnail on the left, everything else beside
                                 it. The entrants used to sit under the picture
                                 with the whole width of the card empty to its
                                 right, which made a round with two names in it
                                 look like a card that had failed to load. -->
                            <div v-if="playing.maps?.[physics]" class="flex gap-3.5">
                                <Link
                                    :href="route('maps.map', playing.maps[physics].name)"
                                    class="group flex-shrink-0 block w-36 h-[6.5rem] rounded-lg overflow-hidden border border-white/10 hover:border-blue-400/50 transition-colors"
                                >
                                    <img
                                        v-if="playing.maps[physics].thumbnail"
                                        :src="`/storage/${playing.maps[physics].thumbnail}`"
                                        :alt="playing.maps[physics].name"
                                        class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                                    />
                                    <span v-else class="block w-full h-full bg-white/[0.03]"></span>
                                </Link>

                                <div class="min-w-0 flex-1">
                                    <Link
                                        :href="route('maps.map', playing.maps[physics].name)"
                                        class="block font-bold text-white hover:text-blue-300 transition-colors truncate"
                                    >
                                        {{ playing.maps[physics].name }}
                                    </Link>
                                    <div v-if="playing.maps[physics].author" class="text-xs text-gray-300 truncate">
                                        {{ playing.maps[physics].author }}
                                    </div>

                                    <div v-if="playing.maps[physics].decided_by === 'wildcard'" class="mt-1.5 inline-flex items-center gap-1 rounded-md border border-amber-500/30 bg-amber-500/10 px-2 py-1 text-[10px] font-bold uppercase tracking-wider text-amber-300">
                                        <svg class="w-3 h-3 flex-shrink-0" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l2.4 7.4H22l-6.2 4.5 2.4 7.4-6.2-4.6-6.2 4.6 2.4-7.4L2 9.4h7.6z" /></svg>
                                        {{ $t('Chosen with a wildcard') }}
                                    </div>

                                    <!-- How this map got here. It used to be
                                         readable only in the window between the
                                         ballot closing and play starting, so
                                         whoever missed it never found out - and
                                         with no lead set there is no window.
                                         Voting is long over by now, so the count
                                         gives nothing away. Both halves open the
                                         same panel: it was one ballot. -->
                                    <button
                                        v-else-if="playedBallot"
                                        type="button"
                                        @click="showBallot = !showBallot"
                                        class="mt-1.5 inline-flex items-center gap-1 rounded-md border px-2 py-1 text-[10px] font-bold uppercase tracking-wider transition-colors"
                                        :class="showBallot
                                            ? 'border-blue-400/40 bg-blue-500/15 text-blue-200'
                                            : 'border-white/10 bg-white/[0.04] text-gray-300 hover:text-gray-200 hover:border-white/25'"
                                    >
                                        <svg class="w-3 h-3 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                        </svg>
                                        <template v-if="playing.maps[physics].decided_by === 'random'">{{ $t('Nobody voted at all, so it was drawn at random') }}</template>
                                        <template v-else-if="playing.maps[physics].decided_by === 'carried'">{{ $t('Nobody voted in this physics, so it took the other one\'s map') }}</template>
                                        <template v-else>{{ $t('Won the vote, :votes of :total', { votes: winningVotes(physics), total: ballotTotal(physics) }) }}</template>
                                        <svg class="w-3 h-3 flex-shrink-0 transition-transform" :class="showBallot ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            <!-- Who has entered. Deliberately not how fast: a
                                 live leaderboard would hand everyone else the
                                 answer. -->
                            <div class="mt-3.5 pt-3 border-t border-white/10">
                                <div class="mb-1.5 text-[10px] font-bold uppercase tracking-wider text-gray-300">
                                    {{ $t('Already uploaded a run') }} ({{ playing.entrants?.[physics]?.length ?? 0 }})
                                </div>
                                <div v-if="playing.entrants?.[physics]?.length" class="flex flex-wrap gap-x-3 gap-y-1.5">
                                    <CompsPlayer
                                        v-for="p in playing.entrants[physics]"
                                        :key="p.id"
                                        :player="p"
                                        size="sm"
                                    />
                                </div>
                                <div v-else class="text-xs text-gray-400">{{ $t('Nobody yet.') }}</div>

                                <!-- Runs an admin took out. Kept in the list
                                     rather than deleted from it: dropping them
                                     silently would make the round read as
                                     though they never turned up. -->
                                <div v-if="playing.removed_entrants?.[physics]?.length" class="mt-2.5">
                                    <div class="mb-1.5 text-[10px] font-bold uppercase tracking-wider text-red-400/70">
                                        {{ $t('Removed') }} ({{ playing.removed_entrants[physics].length }})
                                    </div>
                                    <div class="flex flex-wrap gap-x-3 gap-y-1.5">
                                        <CompsPlayer
                                            v-for="p in playing.removed_entrants[physics]"
                                            :key="'x' + p.id"
                                            :player="p"
                                            size="sm"
                                            struck
                                            :title="p.reason || $t('Removed from this round.')"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- The whole ballot, in the shape it was voted on. Small,
                         because it is a record rather than a thing to act on,
                         and the counts sit over the picture the way they sat
                         under it while voting - one card per map, both physics
                         on each, so a map that lost one and won the other says
                         so in one place.

                         In the same box as the maps it chose, spanning both
                         halves, because one ballot decided both. -->
                    <div v-if="showBallot && playedBallot" class="border-t border-white/10 bg-black/25 px-4 py-3">
                        <div class="mb-2.5 text-[10px] font-black uppercase tracking-widest text-gray-300">
                            {{ $t('Final votes') }}
                        </div>

                        <div class="grid gap-2 grid-cols-2 sm:grid-cols-3 lg:grid-cols-5">
                            <Link
                                v-for="row in playedBallot.rows"
                                :key="row.map"
                                :href="route('maps.map', row.map)"
                                class="group relative block overflow-hidden rounded-lg border transition-colors"
                                :class="(row.won?.cpm || row.won?.vq3)
                                    ? 'border-blue-400/50 hover:border-blue-300/70'
                                    : 'border-white/10 hover:border-white/25'"
                            >
                                <img
                                    v-if="row.thumbnail"
                                    :src="`/storage/${row.thumbnail}`"
                                    :alt="row.map"
                                    class="w-full h-24 object-cover"
                                />
                                <div v-else class="w-full h-24 bg-white/[0.03]"></div>

                                <div class="absolute inset-x-0 top-0 px-2 py-1 bg-gradient-to-b from-black/85 to-transparent">
                                    <div class="truncate text-[11px] font-bold text-white group-hover:text-blue-200 transition-colors">
                                        {{ row.map }}
                                    </div>
                                </div>

                                <!-- Over the bottom half of the picture, both physics. -->
                                <div class="absolute inset-x-0 bottom-0 h-1/2 flex flex-col justify-end gap-1 px-2 pb-1.5 pt-4 bg-gradient-to-t from-black/90 via-black/70 to-transparent">
                                    <div v-for="physics in ['vq3', 'cpm']" :key="physics"
                                         class="flex items-center gap-1.5">
                                        <span class="w-6 flex-shrink-0 text-[9px] font-black uppercase tracking-wider"
                                              :class="row.won?.[physics] ? 'text-blue-300' : 'text-gray-300'">
                                            {{ physics }}
                                        </span>

                                        <template v-if="row.votes?.[physics] === null">
                                            <span class="flex-1 text-[9px] text-gray-400 truncate">{{ $t('Not on this ballot') }}</span>
                                        </template>
                                        <template v-else>
                                            <span class="flex-1 h-1 rounded-full bg-white/15 overflow-hidden">
                                                <span class="block h-full rounded-full"
                                                      :class="row.won?.[physics] ? 'bg-blue-400' : 'bg-gray-400'"
                                                      :style="{ width: ballotShare(physics, row) + '%' }"></span>
                                            </span>
                                            <span class="w-4 flex-shrink-0 text-right text-[10px] font-black tabular-nums"
                                                  :class="row.won?.[physics] ? 'text-white' : 'text-gray-300'">
                                                {{ row.votes[physics] }}
                                            </span>
                                        </template>
                                    </div>
                                </div>
                            </Link>
                        </div>
                    </div>

                    <!-- Said once for the box. It was printed inside each half,
                         which put the same sentence on screen twice. -->
                    <div class="border-t border-white/10 px-4 py-2.5 text-[11px] text-gray-300 leading-snug">
                        {{ $t('Times stay hidden until the round closes, so nobody can be handed the time to beat.') }}
                    </div>
                </div>
            </div>

            <!-- Entering -->
            <!-- Centred, on its own surface. This is the one thing on the page
                 somebody came here to DO, and it was a bare file input on a
                 flat panel, hugging the left edge like a field nobody had
                 finished designing. -->
            <div class="border-t border-white/10 lg:border-t-0 lg:border-l bg-black/30 backdrop-blur-sm px-5 py-6 lg:flex lg:flex-col lg:justify-center">
                <div class="mx-auto max-w-3xl text-center">
                    <h3 class="text-lg font-black text-white">{{ $t('Enter your run') }}</h3>
                    <p class="mt-1 text-sm text-gray-300">
                        {{ $t('Upload your demo, online or offline. The physics is read from the file, and the demo stays private until the round ends.') }}
                    </p>
                </div>

                <!-- Who may enter, decided by the server and printed here. The
                     sentence says what is missing; the link says where to fix
                     it, which is the half a person cannot guess. -->
                <div v-if="!playing.entry_gate?.may" class="mx-auto mt-4 max-w-3xl rounded-lg border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-center text-sm text-amber-200">
                    {{ playing.entry_gate?.reason }}
                    <Link
                        v-if="playing.entry_gate?.needs === 'mdd'"
                        :href="route('settings.show')"
                        class="ml-1 font-bold underline decoration-amber-400/40 hover:text-amber-50"
                    >
                        {{ $t('Open settings') }}
                    </Link>
                    <Link
                        v-else-if="playing.entry_gate?.needs === 'verify'"
                        :href="route('verification.notice')"
                        class="ml-1 font-bold underline decoration-amber-400/40 hover:text-amber-50"
                    >
                        {{ $t('Confirm your email') }}
                    </Link>
                </div>

                <form v-else @submit.prevent="submitEntry" class="mx-auto mt-4 max-w-3xl">
                    <div class="rounded-xl border border-white/10 bg-black/40 p-4">
                        <div class="flex flex-wrap items-center justify-center gap-3">
                            <label class="inline-flex min-w-0 max-w-full cursor-pointer items-center gap-2 rounded-lg border border-white/15 bg-white/[0.06] px-4 py-2.5 text-sm text-gray-200 transition-colors hover:border-white/25 hover:bg-white/10">
                                <svg class="w-3.5 h-3.5 flex-shrink-0 text-gray-300" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V9.75m0 0 3 3m-3-3-3 3M6.75 19.5a4.5 4.5 0 0 1-1.41-8.775 5.25 5.25 0 0 1 10.233-2.33 3 3 0 0 1 3.758 3.848A3.752 3.752 0 0 1 18 19.5H6.75Z" />
                                </svg>
                                <span class="truncate">{{ pickedDemoName || $t('Choose a demo') }}</span>
                                <input type="file" accept=".dm_68" @input="onPickDemo($event)" class="hidden" />
                            </label>

                            <button
                                type="submit"
                                :disabled="uploadForm.processing || !uploadForm.demo"
                                class="rounded-lg bg-blue-600 px-6 py-2.5 text-sm font-bold text-white shadow-[0_0_24px_-8px_rgba(59,130,246,0.9)] transition-colors hover:bg-blue-500 disabled:cursor-not-allowed disabled:opacity-40 disabled:shadow-none"
                            >
                                {{ uploadForm.processing ? $t('Uploading...') : $t('Upload') }}
                            </button>

                            <!-- Beside the button, not under it. It is an option
                                 on this upload, and as a paragraph below the
                                 form it read as one more rule to get through
                                 before pressing anything. -->
                            <label class="inline-flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-2.5 text-sm transition-colors"
                                   :class="uploadForm.is_highlight
                                       ? 'border-purple-400/50 bg-purple-500/15 text-purple-200'
                                       : 'border-white/10 bg-white/[0.03] text-gray-300 hover:border-white/20 hover:text-gray-300'">
                                <input type="checkbox" v-model="uploadForm.is_highlight" class="rounded border-white/20 bg-black/40 text-purple-500 focus:ring-0" />
                                {{ $t('Upload as a highlight') }}
                            </label>
                        </div>

                        <p class="mt-3 text-center text-xs text-gray-300">
                            {{ $t('A highlight is shown as a curiosity and is left out of the leaderboard entirely. Use it for a run worth watching rather than a run worth scoring.') }}
                        </p>

                        <div v-if="uploadForm.errors.demo" class="mt-3 text-center text-sm text-red-400">{{ uploadForm.errors.demo }}</div>
                    </div>
                </form>

                <!-- Unrolls in place rather than linking away. Somebody is
                     standing at the upload with a file in their hand; sending
                     them to another page to ask one question about it, and back
                     again to act on the answer, is three navigations for a
                     thing that fits under the form. -->
                <div class="mx-auto mt-4 max-w-3xl">
                    <button
                        type="button"
                        @click="showCheck = !showCheck"
                        class="flex w-full items-center justify-center gap-2 rounded-lg border border-white/10 bg-white/[0.04] backdrop-blur-sm px-4 py-2.5 text-xs text-gray-300 transition-colors hover:border-white/20 hover:bg-white/[0.07]"
                    >
                        <svg class="h-4 w-4 flex-shrink-0 text-gray-300 transition-transform" :class="showCheck ? 'rotate-90' : ''" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                        </svg>
                        <span class="text-sm font-black text-gray-100">{{ $t('Demo validator') }}</span>
                        <span class="hidden text-gray-300 sm:inline">{{ $t('Check your demo settings') }} &middot; {{ $t('Nothing is uploaded.') }}</span>
                    </button>

                    <div v-if="showCheck" class="mt-3">
                        <DemoSettingsCheck />
                    </div>
                </div>

                <!-- Your own entries, times and all. Yours are never a secret
                     from you. -->
                <div v-if="playing.my_entries?.length" class="mt-5">
                    <div class="mb-2 text-[10px] font-bold uppercase tracking-wider text-gray-300">{{ $t('Your entries') }}</div>
                    <div class="space-y-1.5">
                        <div
                            v-for="entry in playing.my_entries"
                            :key="entry.id"
                            class="flex flex-wrap items-center gap-3 rounded-lg border border-white/5 bg-black/40 backdrop-blur-sm px-3 py-2"
                        >
                            <span class="text-[10px] font-black uppercase tracking-wider text-gray-300 w-8">{{ entry.physics || '-' }}</span>

                            <span v-if="entry.status === 'valid'" class="font-bold tabular-nums text-white">{{ formatTime(entry.time) }}</span>
                            <span v-else-if="entry.status === 'pending'" class="text-sm text-gray-300">{{ $t('Reading the demo...') }}</span>
                            <span v-else class="text-sm text-red-400" :title="entry.reason">{{ $t('Rejected') }}</span>

                            <!-- Online or offline, read off the demo's gametype
                                 rather than guessed from whether we paired it
                                 with a record. -->
                            <span v-if="entry.status === 'valid'"
                                  class="rounded px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wider"
                                  :class="entry.is_online ? 'bg-green-500/15 text-green-300' : 'bg-sky-500/15 text-sky-300'"
                                  :title="entry.gametype">
                                {{ entry.is_online ? $t('Online') : $t('Offline') }}
                            </span>

                            <!-- Pairing with a scraped record is a bonus for the
                                 site and changes nothing about the entry, so it
                                 is stated quietly. -->
                            <span v-if="entry.matched_record"
                                  class="rounded bg-white/5 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-gray-300"
                                  :title="$t('This demo was paired with your record on the map.')">
                                {{ $t('Matched to a record') }}
                            </span>

                            <span v-if="entry.is_highlight" class="rounded bg-purple-500/20 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-purple-300">
                                {{ $t('Highlight') }}
                            </span>

                            <span v-if="entry.filename" class="hidden md:block truncate text-[11px] text-gray-400 max-w-[16rem]">{{ entry.filename }}</span>

                            <!-- A refused entry says why in one sentence, and
                                 the sentence is not always the end of it: a
                                 validity note is a cvar we saw, not a verdict.
                                 This is how somebody disagrees with it. -->
                            <template v-if="entry.status !== 'valid' && entry.status !== 'pending' && entry.demo_id">
                                <span v-if="entry.reported" class="text-xs text-gray-300">{{ $t('Sent to an admin') }}</span>
                                <button
                                    v-else
                                    type="button"
                                    @click="askAboutDemo(entry.demo_id, entry.filename)"
                                    class="text-xs font-bold text-amber-300 underline decoration-amber-400/40 hover:text-amber-100"
                                >
                                    {{ $t('Ask an admin about this demo') }}
                                </button>
                            </template>

                            <button
                                type="button"
                                @click="withdrawTarget = entry"
                                class="ml-auto text-xs text-gray-400 hover:text-red-400 transition-colors"
                            >
                                {{ $t('Withdraw') }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Runs of theirs comps is keeping back without having entered
                     them: a file that could not be read, a run older than the
                     round, one they withdrew themselves. Inside the entry
                     panel and directly under the entries, because it answers
                     the same question they do - what happened to my demo -
                     and a separate section further down the page read as a
                     different subject. -->
                <div v-if="myNotices.length" class="mt-5">
                    <div class="mb-2 text-[10px] font-bold uppercase tracking-wider text-gray-300">{{ $t('Demos of yours on hold') }}</div>
                    <div class="space-y-1.5">
                        <div
                            v-for="notice in myNotices"
                            :key="notice.id"
                            class="flex flex-wrap items-center gap-x-3 gap-y-1 rounded-lg border border-white/5 bg-black/40 backdrop-blur-sm px-3 py-2"
                        >
                            <span class="max-w-full truncate text-[11px] text-gray-400 md:max-w-[16rem]">{{ notice.filename }}</span>

                            <span class="text-sm" :class="notice.kind === 'unreadable' ? 'text-red-400' : 'text-gray-300'">{{ notice.note }}</span>

                            <!-- An unreadable demo is the one case where the site
                                 cannot say what went wrong, so it hands over the person
                                 who can look at the file. -->
                            <span v-if="notice.reported" class="text-xs text-gray-300">{{ $t('Sent to an admin') }}</span>
                            <button
                                v-else
                                type="button"
                                @click="askAboutDemo(notice.id, notice.filename)"
                                class="text-xs font-bold text-amber-300 underline decoration-amber-400/40 hover:text-amber-100"
                            >
                                {{ $t('Ask an admin about this demo') }}
                            </button>

                            <span v-if="notice.appears_at" class="ml-auto text-[11px] text-gray-400">
                                {{ $t('Appears :when', { when: appearsAt(notice.appears_at) }) }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            </div>
        </section>

        <!-- =========================== HISTORY ============================ -->
        <!-- Past comps on the left, the leaderboard beside them on the right
             and staying put while the list scrolls. Under each other the
             leaderboard sat below twelve weeks of history and nobody found
             it. A week is one row of the left column, its two physics one
             above the other, which is what leaves room on the right. -->
        <div v-if="history.length" class="grid gap-6 items-start lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
        <section class="rounded-2xl border border-white/10 bg-black/40 backdrop-blur-sm overflow-hidden min-w-0">
            <div class="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-1 border-b border-white/10 bg-white/[0.04] px-5 py-3">
                <h2 class="text-lg font-black text-white">{{ $t('Past comps') }}</h2>
                <span class="text-xs text-gray-300">{{ $tc(':count comp|:count comps', history.length) }}</span>
            </div>

            <!-- One row per week, the full width, both physics side by side
                 in it. Each half is the map's picture as a square on the
                 left and everything said about it on the right: the map,
                 who won and in what time, and what became of the prize. A
                 picture above the text made each half tall and narrow and
                 the two weeks next to each other read as four cards. -->
            <div class="p-4 md:p-5 space-y-6">
                <!-- Each week gets a header in the ballot's blue and a
                     visible edge, or twelve weeks of grey rows run into one
                     long table. -->
                <Link v-for="comp in history" :key="comp.id" :href="route('comps.show', comp.id)"
                      class="group block rounded-xl border border-blue-400/25 bg-black/30 overflow-hidden shadow-[0_0_24px_-14px_rgba(96,165,250,0.5)] transition-all hover:border-blue-400/60 hover:shadow-[0_0_30px_-10px_rgba(59,130,246,0.6)]">
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1 border-b border-blue-400/20 bg-gradient-to-r from-blue-500/[0.18] to-blue-500/[0.04] px-4 py-3">
                        <span class="text-lg font-black text-white group-hover:text-blue-200 transition-colors">{{ comp.title }}</span>
                        <span v-if="comp.category" class="text-[10px] font-black uppercase tracking-wider text-blue-300/80">
                            {{ categoryLabel(comp.category) }}<template v-if="comp.weapon"> · {{ comp.weapon }}</template>
                        </span>
                        <span class="ml-auto text-xs font-bold tabular-nums text-gray-200">{{ historyDates(comp) }}</span>
                        <span class="rounded-lg border border-blue-400/30 bg-blue-500/15 px-3 py-1 text-xs font-black text-blue-200 transition-colors group-hover:border-blue-400/60 group-hover:bg-blue-500/30 group-hover:text-white">{{ $t('Details') }} &rsaquo;</span>
                    </div>

                    <div class="divide-y divide-white/10">
                        <div v-for="physics in PHYSICS" :key="physics" class="flex gap-4 p-4 min-w-0" :class="physicsTint(physics)">
                            <div class="relative w-28 h-28 sm:w-32 sm:h-32 shrink-0 rounded-lg overflow-hidden border border-white/10 bg-white/[0.03]">
                                <img v-if="comp.map_by_physics?.[physics]?.thumbnail"
                                     :src="`/storage/${comp.map_by_physics[physics].thumbnail}`"
                                     :alt="comp.map_by_physics[physics].name"
                                     class="absolute inset-0 w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" loading="lazy" />
                            </div>

                            <div class="flex-1 min-w-0 flex flex-col">
                                <!-- Map on the left, that physics' prize on the right. -->
                                <div class="flex items-center gap-2 min-w-0 pb-2 border-b border-white/10">
                                    <span class="shrink-0 rounded-md border px-1.5 py-0.5 text-[10px] font-black uppercase tracking-widest" :class="physicsBadge(physics)">{{ physics }}</span>
                                    <span class="text-sm font-black text-white truncate">{{ comp.map_by_physics?.[physics]?.name ?? '-' }}</span>
                                    <span class="shrink-0 text-xs text-gray-300">· {{ $tc(':count player entered|:count players entered', comp.entrants_by_physics?.[physics] ?? 0) }}</span>
                                    <span v-if="comp.prize_eur > 0" class="ml-auto shrink-0 text-sm font-black text-emerald-300">{{ comp.prize_eur }} EUR</span>
                                </div>

                                <!-- The three best, one line each, nothing between the lines. -->
                                <div v-if="comp.winners?.[physics]?.length" class="py-2 space-y-1">
                                    <div v-for="w in comp.winners[physics]" :key="w.id" class="flex items-center gap-2 min-w-0" :class="w.rank > 1 && 'opacity-80'">
                                        <span class="inline-flex w-5 h-5 shrink-0 items-center justify-center rounded-full border text-[10px] font-black" :class="RANK_STYLE[w.rank] ?? RANK_STYLE[3]">{{ w.rank }}</span>
                                        <CompsPlayer :player="w" size="sm" />
                                        <span class="ml-auto text-sm font-black tabular-nums text-white shrink-0">{{ formatTime(w.time) }}</span>
                                    </div>
                                </div>
                                <div v-else class="py-2 text-xs text-gray-400">{{ $t('Nobody entered.') }}</div>

                                <!-- What became of the prize. -->
                                <div v-if="comp.winners?.[physics]?.some((w) => w.payout)" class="mt-auto flex items-center gap-3 pt-2 border-t border-white/10">
                                    <!-- Whose prize it was and how much, or the badge
                                         reads as if everybody in the table gave it away. -->
                                    <template v-for="w in comp.winners?.[physics] ?? []" :key="'p' + w.id">
                                        <span v-if="w.payout" class="inline-flex flex-wrap items-center gap-x-1.5 gap-y-0.5 min-w-0 text-xs">
                                            <CompsPlayer :player="w" size="sm" />
                                            <span class="text-gray-400">·</span>
                                            <CompsPayoutText :payout="w.payout" />
                                        </span>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>
                </Link>
            </div>
        </section>

        <CompsLeaderboard v-if="leaderboard.periods.length" :periods="leaderboard.periods" :rows="leaderboard.rows" class="min-w-0" />
        </div>

        <div v-if="!playing && !voting && !history.length" class="rounded-xl border border-white/10 bg-black/40 backdrop-blur-sm px-6 py-12 text-center">
            <p class="text-gray-300">{{ $t('No comps have run yet. The first one starts as soon as it is switched on.') }}</p>
        </div>
        </div>

        <!-- Highlight confirmation -->
        <Teleport to="body">
            <div v-if="highlightConfirm" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/80 backdrop-blur-sm p-4" @click.self="highlightConfirm = false">
                <div class="w-full max-w-md rounded-xl border border-purple-500/30 bg-black/70 backdrop-blur-xl p-6">
                    <h3 class="flex items-center gap-2 text-lg font-black text-white">
                        <svg class="w-5 h-5 text-purple-400" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l2.4 7.4H22l-6.2 4.5 2.4 7.4-6.2-4.6-6.2 4.6 2.4-7.4L2 9.4h7.6z" /></svg>
                        {{ $t('Upload this as a highlight?') }}
                    </h3>
                    <p class="mt-3 text-sm text-gray-300">
                        {{ $t('This run will NOT count towards the leaderboard. It is shown as a curiosity and nothing else.') }}
                    </p>
                    <p class="mt-2 text-sm text-gray-300">
                        {{ $t('Use it only for a run that does not belong on the leaderboard. If you want this time scored, go back and untick the box.') }}
                    </p>
                    <div class="mt-5 flex justify-end gap-2">
                        <button type="button" @click="highlightConfirm = false" class="rounded-lg border border-white/10 px-4 py-2 text-sm text-gray-300 hover:bg-white/5">
                            {{ $t('Cancel') }}
                        </button>
                        <button type="button" @click="send" :disabled="uploadForm.processing" class="rounded-lg bg-purple-500 px-4 py-2 text-sm font-bold text-white hover:bg-purple-400 disabled:opacity-40">
                            {{ $t('Yes, upload as a highlight') }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Wildcard confirmation -->
        <Teleport to="body">
            <div v-if="wildcardTarget" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/80 backdrop-blur-sm p-4" @click.self="wildcardTarget = null">
                <div class="w-full max-w-md rounded-xl border border-amber-500/30 bg-black/70 backdrop-blur-xl p-6">
                    <h3 class="flex items-center gap-2 text-lg font-black text-white">
                        <svg class="w-5 h-5 text-amber-400" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l2.4 7.4H22l-6.2 4.5 2.4 7.4-6.2-4.6-6.2 4.6 2.4-7.4L2 9.4h7.6z" /></svg>
                        {{ $t('Use your wildcard?') }}
                    </h3>
                    <p class="mt-3 text-sm text-gray-300">
                        {{ $t('This makes :map the :physics map for this round, whatever the vote says.', { map: wildcardTarget.map, physics: wildcardTarget.physics.toUpperCase() }) }}
                    </p>
                    <p class="mt-2 text-sm text-gray-300">
                        {{ $t('You hold one wildcard and spending it uses it up. Whoever spends one first decides the round, so if somebody beats you to it yours stays unspent for another week.') }}
                    </p>
                    <div class="mt-5 flex justify-end gap-2">
                        <button type="button" @click="wildcardTarget = null" class="rounded-lg border border-white/10 px-4 py-2 text-sm text-gray-300 hover:bg-white/5">
                            {{ $t('Cancel') }}
                        </button>
                        <button type="button" @click="confirmWildcard" class="rounded-lg bg-amber-500 px-4 py-2 text-sm font-bold text-black hover:bg-amber-400">
                            {{ $t('Use it') }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Withdrawing is final, so it says so before doing it. -->
        <Teleport to="body">
            <div v-if="withdrawTarget" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/80 backdrop-blur-sm p-4" @click.self="withdrawTarget = null">
                <div class="w-full max-w-md rounded-xl border border-white/10 bg-black/70 backdrop-blur-xl p-6">
                    <h3 class="text-lg font-black text-white">{{ $t('Withdraw this run?') }}</h3>
                    <p class="mt-2 truncate text-[11px] text-gray-400">{{ withdrawTarget.filename }}</p>
                    <p class="mt-3 text-sm text-gray-300">
                        {{ $t('It leaves the round and stops counting, and the same file cannot be entered again. The demo itself stays on the site and appears once the round is over.') }}
                    </p>

                    <div class="mt-5 flex justify-end gap-2">
                        <button type="button" @click="withdrawTarget = null" class="rounded-lg border border-white/10 px-4 py-2 text-sm text-gray-300 hover:bg-white/5">
                            {{ $t('Cancel') }}
                        </button>
                        <button type="button" @click="confirmWithdraw" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-bold text-white hover:bg-red-500">
                            {{ $t('Withdraw') }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- "Look at my demo": the one way out of a refusal the site cannot
             explain any further by itself. -->
        <Teleport to="body">
            <div v-if="demoReport" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/80 backdrop-blur-sm p-4" @click.self="demoReport = null">
                <div class="w-full max-w-md rounded-xl border border-white/10 bg-black/70 backdrop-blur-xl p-6">
                    <h3 class="text-lg font-black text-white">{{ $t('Ask an admin about this demo') }}</h3>
                    <p class="mt-2 truncate text-[11px] text-gray-400">{{ demoReport.filename }}</p>
                    <p class="mt-2 text-sm text-gray-300">
                        {{ $t('Say what you expected to happen. An admin opens the file itself and answers you.') }}
                    </p>

                    <textarea
                        v-model="demoReportForm.reason"
                        rows="4"
                        class="mt-4 w-full rounded-lg border border-white/10 bg-black/40 px-3 py-2 text-sm text-white placeholder-gray-600 focus:border-amber-500/50 focus:outline-none"
                        :placeholder="$t('What happened?')"
                    ></textarea>

                    <div v-if="demoReportForm.errors.reason" class="mt-2 text-sm text-red-400">{{ demoReportForm.errors.reason }}</div>

                    <div class="mt-5 flex justify-end gap-2">
                        <button type="button" @click="demoReport = null" class="rounded-lg border border-white/10 px-4 py-2 text-sm text-gray-300 hover:bg-white/5">
                            {{ $t('Cancel') }}
                        </button>
                        <button
                            type="button"
                            @click="sendDemoReport"
                            :disabled="demoReportForm.processing"
                            class="rounded-lg bg-amber-500 px-4 py-2 text-sm font-bold text-black hover:bg-amber-400 disabled:opacity-50"
                        >
                            {{ $t('Send report') }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Impossible-map report -->
        <Teleport to="body">
            <div v-if="mapReport" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/80 backdrop-blur-sm p-4" @click.self="mapReport = null">
                <div class="w-full max-w-md rounded-xl border border-white/10 bg-black/70 backdrop-blur-xl p-6">
                    <h3 class="text-lg font-black text-white">{{ $t('Report an impossible map') }}</h3>
                    <p class="mt-2 text-sm text-gray-300">
                        {{ $t('Tell an admin that :map cannot be finished in one of the physics. If it stands, the map leaves that ballot and never enters that pool again.', { map: mapReport.map }) }}
                    </p>
                    <div class="mt-4 flex gap-2">
                        <button
                            v-for="physics in PHYSICS"
                            :key="physics"
                            type="button"
                            @click="mapReport.physics = physics"
                            class="flex-1 rounded-lg border px-3 py-2 text-xs font-black uppercase tracking-wider transition-colors"
                            :class="mapReport.physics === physics ? 'border-blue-500/50 bg-blue-600/25 text-white' : 'border-white/10 bg-black/40 backdrop-blur-sm text-gray-300 hover:text-white'"
                        >
                            {{ $t('Impossible in :physics', { physics: physics.toUpperCase() }) }}
                        </button>
                    </div>
                    <div class="mt-5 flex justify-end gap-2">
                        <button type="button" @click="mapReport = null" class="rounded-lg border border-white/10 px-4 py-2 text-sm text-gray-300 hover:bg-white/5">
                            {{ $t('Cancel') }}
                        </button>
                        <button type="button" @click="confirmMapReport" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-bold text-white hover:bg-blue-500">
                            {{ $t('Send report') }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <ConfigModal :show="showConfig" @close="showConfig = false" />
    </div>
</template>

<style scoped>
/* vue3-popper draws its bubble from CSS variables and ships no defaults worth
 * having: with none set the panel comes out transparent and the page reads
 * straight through the text. The site's values live in a plain <style> block
 * inside OnlinePlayerData.vue, so they only exist on a page that happens to
 * render that component - this one does not. Set here on the trigger, which
 * the bubble inherits from, rather than globally: a second page-level :root
 * block would fight the first one over whichever loaded last.
 */
.comps-popper {
    --popper-theme-background-color: #161d2b;
    --popper-theme-background-color-hover: #161d2b;
    --popper-theme-text-color: #e5e7eb;
    --popper-theme-border-width: 1px;
    --popper-theme-border-style: solid;
    --popper-theme-border-color: rgba(255, 255, 255, 0.12);
    --popper-theme-border-radius: 10px;
    --popper-theme-padding: 0;
    --popper-theme-box-shadow: 0 12px 40px -8px rgba(0, 0, 0, 0.8);
}

/* The donor banner floats in the page's right margin.
 *
 * It is fixed rather than sticky on purpose: the panel it belongs to scrolls
 * out of view within a screen, and a donor list that vanishes with it answers
 * "who paid for this" only for somebody who happens to be at the top of the
 * page.
 *
 * The breakpoint is a measurement, not a guess. The content column is
 * max-w-8xl (90rem = 1440px), centred, so each margin is (100vw - 1440) / 2.
 * The banner is 190px plus 12px of clearance from the edge, so it needs a
 * 202px margin, so it needs 1440 + 404 = 1844px of viewport. Below that it is
 * not shown at all and the same list renders inline under the prize instead -
 * a banner that overlaps the content it sits beside is worse than one that
 * waits for the room.
 */
.donors-rail {
    display: none;
}

@media (min-width: 1844px) {
    .donors-rail {
        display: block;
        position: fixed;
        top: 7rem;
        right: 12px;
        width: 190px;
        /* Never taller than the screen: a dozen donors would otherwise run off
           the bottom with no way to reach them. */
        max-height: calc(100vh - 9rem);
        overflow-y: auto;
        z-index: 30;
    }

    /* One of the two, never both. */
    .donors-inline {
        display: none;
    }
}
</style>
