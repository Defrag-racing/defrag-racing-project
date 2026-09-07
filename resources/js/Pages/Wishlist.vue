<script>
import MainLayout from '@/Layouts/MainLayout.vue';

export default {
    layout: MainLayout,
};
// The reply thread. One wish open at a time: two textareas on a board of
// three hundred is a way to lose what you were writing.
const replyOpen = ref(null);
const replyBody = ref('');
const replySending = ref(false);

const openReply = (wishId) => {
    replyOpen.value = wishId;
    replyBody.value = '';
};

const submitReply = (wishId) => {
    const body = replyBody.value.trim();
    if (! body || replySending.value) return;

    replySending.value = true;
    router.post(route('wishlist.reply', wishId), { body }, {
        preserveScroll: true,
        onSuccess: () => { replyOpen.value = null; replyBody.value = ''; },
        onFinish: () => { replySending.value = false; },
    });
};

</script>

<script setup>
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { currentLocale, t } from '@/utils/i18n';
import { timeAgo } from '@/utils/time';

import EnglishOnlyNotice from '@/Components/EnglishOnlyNotice.vue';
const props = defineProps({
    wishes: { type: Array, default: () => [] },
    statuses: { type: Object, default: () => ({}) },
    projects: { type: Object, default: () => ({}) },
    projectCounts: { type: Object, default: () => ({}) },
    filter: { type: String, default: null },
    projectFilter: { type: String, default: null },
    budget: { type: Object, default: null },
});

const page = usePage();
const user = computed(() => page.props.auth?.user);

// The vote budget, always on screen. Running out is a rule people should have
// seen coming, not a button that suddenly refuses.
const votesLeft = computed(() => props.budget?.left ?? 0);
const voteError = computed(() => page.props.errors?.vote);

// Taking your own vote back is always allowed, and your own wish is free -
// only a fresh upvote on somebody else's costs anything.
const canUpvote = (wish) => !!user.value
    && !wish.pending
    && (wish.my_vote === 1 || wish.mine || votesLeft.value > 0);

const showForm = ref(false);

const form = useForm({
    project: 'web',
    title: '',
    body: '',
    image: null,
    youtube_url: '',
});

// Shown before the wish is posted, so the author sees what everyone else will.
const imagePreview = ref(null);

const pickImage = (event) => {
    const file = event.target.files?.[0] ?? null;
    form.image = file;
    if (imagePreview.value) URL.revokeObjectURL(imagePreview.value);
    imagePreview.value = file ? URL.createObjectURL(file) : null;
};

const clearImage = () => {
    form.image = null;
    if (imagePreview.value) URL.revokeObjectURL(imagePreview.value);
    imagePreview.value = null;
    if (imageInput.value) imageInput.value.value = '';
};

const imageInput = ref(null);

const submit = () => {
    // forceFormData: a file cannot go up as JSON, and Inertia only switches
    // to multipart on its own when a File is present - which it is not when
    // the author attached nothing.
    form.post('/wishlist', {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            form.reset();
            clearImage();
            showForm.value = false;
        },
    });
};

// Clicking a thumbnail opens the full picture. One at a time, and Escape or a
// click anywhere closes it - it is a preview, not a page.
const lightbox = ref(null);

const openLightbox = (wish) => {
    lightbox.value = wish;
};

const closeLightbox = () => {
    lightbox.value = null;
};

const onKeydown = (event) => {
    if (event.key === 'Escape') closeLightbox();
};

onMounted(() => window.addEventListener('keydown', onKeydown));

// One wish, pointed at. A notification about a finished wish sends people to
// /wishlist/<id>, which lands here with ?highlight=<id> and the right tab
// already chosen - see WishlistController::show. Without the scroll and the
// ring the link drops somebody on a list of three hundred and leaves them to
// find their own wish, which is the whole reason a link to one was needed.
//
// The ring times out rather than staying: it says "here", and a wish left
// permanently ringed reads as a wish with something wrong with it.
const highlightId = ref(null);

onMounted(() => {
    const id = parseInt(new URLSearchParams(window.location.search).get('highlight'));

    if (!id) return;

    highlightId.value = id;

    nextTick(() => {
        document.getElementById('wish-' + id)?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });

    setTimeout(() => { highlightId.value = null; }, 4000);
});
onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKeydown);
    if (imagePreview.value) URL.revokeObjectURL(imagePreview.value);
});

// Voting is a plain POST rather than an optimistic local update: the score
// decides the order of the list, and a number that moves before the server
// agrees is a number people stop trusting.
const vote = (wish, value) => {
    if (!user.value || wish.pending) return;
    if (value === 1 && !canUpvote(wish)) return;
    router.post(`/wishlist/${wish.id}/vote`, { value }, { preserveScroll: true, preserveState: true });
};

// Asking, not deleting. Other people have voted on this by now, so taking it
// down is the admin's call and the wish stays up meanwhile.
const askRemoval = (wish) => {
    const reason = prompt(t('Ask an admin to remove this wish. Why? (optional)'));
    if (reason === null) return;

    router.post(`/wishlist/${wish.id}/request-removal`, { reason }, { preserveScroll: true });
};

// Both filters go through the same call so picking one never silently drops
// the other.
const applyFilters = (status, project) => {
    const query = {};
    if (status && status !== 'open') query.status = status;
    if (project) query.project = project;

    router.get('/wishlist', query, { preserveScroll: true, preserveState: true, replace: true });
};

const setFilter = (status) => applyFilters(status, props.projectFilter);
const setProject = (project) => applyFilters(props.filter, project);

// Two groups of tabs: what is still being decided, and what has been answered.
// Same switch, but a wish that is Done is a different kind of thing from one
// that is waiting, and the gap between them says so.
const OPEN_STATUSES = ['considering', 'planned'];

const openStatuses = computed(() => Object.entries(props.statuses)
    .filter(([key]) => OPEN_STATUSES.includes(key)));

const closedStatuses = computed(() => Object.entries(props.statuses)
    .filter(([key]) => ! OPEN_STATUSES.includes(key)));

const totalWishes = computed(() => Object.values(props.projectCounts || {})
    .reduce((sum, count) => sum + Number(count || 0), 0));

const statusClass = (status) => ({
    considering: 'bg-white/5 border-white/15 text-gray-300',
    planned: 'bg-blue-500/15 border-blue-400/30 text-blue-300',
    done: 'bg-green-500/15 border-green-400/30 text-green-300',
    rejected: 'bg-red-500/15 border-red-400/30 text-red-300',
}[status] || 'bg-white/5 border-white/15 text-gray-300');

const fmtDate = (iso) => iso ? new Date(iso).toLocaleDateString(currentLocale()) : '';
</script>

<template>
    <Head :title="$t('Bugs & Wishlist')" />

    <div class="">
        <!-- Header Section - same shape as Servers, Records and Ranking: the
             gradient block holds the heading, the content is pulled up under
             it. A page of ours that starts differently reads as a page from
             somewhere else. -->
        <div class="relative bg-gradient-to-b from-black/25 via-black/10 to-transparent pt-6 pb-96 pointer-events-none">
            <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8 pointer-events-auto">
                <!-- One row: title, one panel, button. The panel takes
                     every pixel between the other two rather than sitting at a
                     fixed width with empty space beside it. What the page is
                     and what to expect from it are the same message, so they
                     are one panel and not two. -->
                <div class="flex justify-between items-start flex-wrap gap-4">
                    <!-- items-start: the heading sits at the top of the row,
                         where it does on every other page. Centring it against
                         the panel, which is the tall thing in this row, floated
                         it down into the middle of the block and nowhere near
                         where a reader expects to find a page title. -->
                    <h1 class="text-2xl md:text-3xl font-black text-gray-300/90 flex-shrink-0">
                        {{ $t('Bugs & Wishlist') }}
                    </h1>

                    <div class="flex-1 min-w-[20rem] bg-black/40 backdrop-blur-sm rounded-xl border border-white/10 px-4 py-3 shadow-2xl">
                        <!-- Why the board exists, in the words of the person it
                             exists for. It used to say "ask for something and
                             vote on it", which is what any board does and says
                             nothing about this one. -->
                        <p class="text-sm leading-relaxed text-gray-300">
                            {{ $t('Bug reports and requests used to reach me as private messages on Discord and I often lost track of them. This is where they go now: report a bug or ask for something here, it stays written down, and everybody can see what has already been reported or asked for and vote on it.') }}
                        </p>
                        <!-- Said in the brightest text in the panel, because it
                             is the thing that sets the expectation. -->
                        <!-- Where the money goes is answered inside the
                             sentence that raises the question, rather than in a
                             paragraph of its own further down. Somebody told
                             that one person runs all of this wonders about it
                             right there.

                             Same heading as the donations page uses for the
                             same statement, so the two read as one voice. -->
                        <p class="text-sm font-bold text-white mt-3">
                            {{ $t('One person does all of this, for free') }}
                        </p>
                        <p class="text-sm leading-relaxed text-gray-100 font-semibold mt-1">
                            {{ $t('The site, the launcher, DemoMe and this board were built by me and by nobody else. So was DefragLive, apart from its early parts, which frog wrote. Nobody is paid and there is no team (donations pay the running costs only, and not a cent of them reaches my pocket).') }}
                        </p>
                        <p class="text-sm leading-relaxed text-gray-300 mt-1.5">
                            {{ $t('Why? Because I want defrag to get better, and easier to pick up for anyone new to it.') }}
                        </p>
                        <p class="text-sm leading-relaxed text-gray-400 mt-1.5">
                            {{ $t('It goes the other way as well: things like the DefragLive contest prizes come out of my own pocket.') }}
                        </p>
                        <p class="text-sm leading-relaxed text-gray-400 mt-1.5">
                            {{ $t('Votes decide the order, but everything on this list waits for the same pair of hands. So please be patient with anything you ask for.') }}
                        </p>
                        <p class="text-sm text-gray-500 italic mt-2">
                            {{ $t('Yours truly,') }} <Link href="/profile/8" class="not-italic font-semibold hover:text-gray-300 transition-colors">neyo</Link>
                        </p>
                    </div>

                    <button v-if="user" @click="showForm = !showForm"
                        class="px-5 py-2.5 rounded-lg bg-purple-500 hover:bg-purple-600 text-white font-bold transition-colors flex-shrink-0">
                        {{ showForm ? $t('Close') : $t('Add a bug or a wish') }}
                    </button>
                    <Link v-else href="/login"
                        class="px-5 py-2.5 rounded-lg bg-white/5 hover:bg-white/10 text-gray-200 font-bold transition-colors flex-shrink-0">
                        {{ $t('Log in to add or vote') }}
                    </Link>
                </div>
            </div>
        </div>

        <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8 pb-12" style="margin-top: -22rem;">

            <form v-if="showForm && user" @submit.prevent="submit"
                class="bg-black/40 backdrop-blur-sm border border-white/10 rounded-2xl p-5 mb-6 space-y-4">
                <div>
                    <label class="block text-sm text-gray-300 mb-2">{{ $t('Which project is it about?') }}</label>
                    <div class="flex flex-wrap gap-2">
                        <button v-for="(label, key) in projects" :key="key" type="button" @click="form.project = key"
                            class="px-3 py-1.5 rounded-lg text-sm font-semibold border transition-colors"
                            :class="form.project === key
                                ? 'bg-purple-500/20 border-purple-400/50 text-purple-200'
                                : 'bg-black/40 border-white/10 text-gray-400 hover:text-white hover:border-white/25'">
                            {{ label }}
                        </button>
                    </div>
                    <div v-if="form.errors.project" class="text-red-400 text-sm mt-1">{{ form.errors.project }}</div>
                </div>
                <EnglishOnlyNotice :compact="false" />

                <div>
                    <label class="block text-sm text-gray-300 mb-1">{{ $t('Title') }}</label>
                    <input v-model="form.title" type="text" maxlength="120"
                        class="w-full bg-black/50 border border-white/10 rounded-lg px-3 py-2 text-white placeholder-gray-500 focus:outline-none focus:border-purple-400/50"
                        :placeholder="$t('One line - what is broken, or what do you want?')" />
                    <div v-if="form.errors.title" class="text-red-400 text-sm mt-1">{{ form.errors.title }}</div>
                </div>
                <div>
                    <label class="block text-sm text-gray-300 mb-1">{{ $t('Description') }}</label>
                    <textarea v-model="form.body" rows="4" maxlength="2000"
                        class="w-full bg-black/50 border border-white/10 rounded-lg px-3 py-2 text-white placeholder-gray-500 focus:outline-none focus:border-purple-400/50"
                        :placeholder="$t('Briefly: what happens or what it should do, and why it matters.')"></textarea>
                    <div v-if="form.errors.body" class="text-red-400 text-sm mt-1">{{ form.errors.body }}</div>
                </div>

                <!-- Both optional. Half the wishes here are about something
                     you can see, and a screenshot says it faster than a
                     paragraph does. -->
                <div class="grid sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm text-gray-300 mb-1">
                            {{ $t('Screenshot') }} <span class="text-gray-600 font-normal">{{ $t('optional') }}</span>
                        </label>

                        <div v-if="imagePreview" class="flex items-start gap-3">
                            <img :src="imagePreview" alt=""
                                class="w-24 h-16 object-cover rounded-lg border border-white/10" />
                            <button type="button" @click="clearImage"
                                class="text-sm text-gray-400 hover:text-red-300 transition-colors">
                                {{ $t('Remove') }}
                            </button>
                        </div>

                        <input v-else ref="imageInput" type="file" accept="image/jpeg,image/png,image/webp,image/gif"
                            @change="pickImage"
                            class="w-full text-sm text-gray-400 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:bg-white/10 file:text-gray-200 file:font-semibold hover:file:bg-white/20 file:cursor-pointer" />

                        <div v-if="form.errors.image" class="text-red-400 text-sm mt-1">{{ form.errors.image }}</div>
                        <p v-else class="text-xs text-gray-600 mt-1">{{ $t('jpg, png, webp or gif, up to 4 MB.') }}</p>
                    </div>

                    <div>
                        <label class="block text-sm text-gray-300 mb-1">
                            {{ $t('YouTube link') }} <span class="text-gray-600 font-normal">{{ $t('optional') }}</span>
                        </label>
                        <input v-model="form.youtube_url" type="text" maxlength="255"
                            class="w-full bg-black/50 border border-white/10 rounded-lg px-3 py-2 text-white placeholder-gray-500 focus:outline-none focus:border-purple-400/50"
                            placeholder="https://youtube.com/watch?v=..." />
                        <div v-if="form.errors.youtube_url" class="text-red-400 text-sm mt-1">{{ form.errors.youtube_url }}</div>
                        <p v-else class="text-xs text-gray-600 mt-1">{{ $t('Any YouTube link works - watch, youtu.be or shorts.') }}</p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <button type="submit" :disabled="form.processing"
                        class="px-5 py-2.5 rounded-lg bg-purple-500 hover:bg-purple-600 text-white font-bold transition-colors disabled:opacity-50">
                        {{ $t('Post it') }}
                    </button>
                    <span class="text-sm text-gray-500">
                        {{ $t('An admin reads it before it goes on the list. You will see it here with a "waiting" mark until then.') }}
                    </span>
                </div>
            </form>

            <!-- Two questions, two shapes. The projects are a standing menu
                 down the side; the status is a switch over the list. As two
                 rows of pills they were one control wearing two hats. -->
            <div class="lg:grid lg:grid-cols-[15rem_minmax(0,1fr)] lg:gap-5">

                <aside class="mb-4 lg:mb-0">
                    <div class="bg-black/40 backdrop-blur-sm border border-white/10 rounded-2xl p-2 lg:sticky lg:top-4">
                        <div class="px-2 py-1.5 text-[11px] font-bold text-gray-500 uppercase tracking-wider">{{ $t('Projects') }}</div>

                        <!-- Horizontal scroll on small screens, a list on wide
                             ones: the same buttons either way. -->
                        <div class="flex lg:block gap-1 overflow-x-auto lg:overflow-visible pb-1 lg:pb-0">
                            <button @click="setProject(null)"
                                class="shrink-0 lg:w-full flex items-center justify-between gap-3 px-3 py-2 rounded-lg text-sm font-semibold transition-colors whitespace-nowrap"
                                :class="!projectFilter ? 'bg-blue-500/20 text-blue-100' : 'text-gray-400 hover:text-white hover:bg-white/10'">
                                <span>{{ $t('All projects') }}</span>
                                <span class="text-xs opacity-50">{{ totalWishes }}</span>
                            </button>
                            <button v-for="(label, key) in projects" :key="key" @click="setProject(key)"
                                class="shrink-0 lg:w-full flex items-center justify-between gap-3 px-3 py-2 rounded-lg text-sm font-semibold transition-colors whitespace-nowrap"
                                :class="projectFilter === key ? 'bg-blue-500/20 text-blue-100' : 'text-gray-400 hover:text-white hover:bg-white/10'">
                                <span>{{ label }}</span>
                                <span class="text-xs opacity-50">{{ projectCounts[key] || 0 }}</span>
                            </button>
                        </div>
                    </div>
                </aside>

                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-3 mb-4">
                        <div class="flex items-center gap-1 p-1 rounded-xl bg-black/40 border border-white/10">
                            <button @click="setFilter('open')"
                                class="px-3 py-1.5 rounded-lg text-sm font-semibold transition-colors"
                                :class="filter === 'open' ? 'bg-purple-500/25 text-purple-100' : 'text-gray-400 hover:text-white hover:bg-white/10'">
                                {{ $t('Open') }}
                            </button>
                            <button v-for="[key, label] in openStatuses" :key="key" @click="setFilter(key)"
                                class="px-3 py-1.5 rounded-lg text-sm font-semibold transition-colors"
                                :class="filter === key ? 'bg-purple-500/25 text-purple-100' : 'text-gray-400 hover:text-white hover:bg-white/10'">
                                {{ label }}
                            </button>

                            <span class="w-px self-stretch my-1 bg-white/10"></span>

                            <button v-for="[key, label] in closedStatuses" :key="key" @click="setFilter(key)"
                                class="px-3 py-1.5 rounded-lg text-sm font-semibold transition-colors"
                                :class="filter === key ? 'bg-purple-500/25 text-purple-100' : 'text-gray-400 hover:text-white hover:bg-white/10'">
                                {{ label }}
                            </button>
                        </div>

                        <!-- Your votes. Sits next to the list rather than in a
                             help page, because the number changes as you use
                             it and that is the whole point of showing it. -->
                        <div v-if="budget && budget.total"
                            class="flex items-center gap-2 px-3 py-1.5 rounded-xl bg-black/40 border"
                            :class="votesLeft ? 'border-white/10' : 'border-amber-400/40'">
                            <span class="text-sm font-bold" :class="votesLeft ? 'text-green-300' : 'text-amber-300'">
                                {{ votesLeft }}
                            </span>
                            <span class="text-xs text-gray-500">
                                {{ $tc('of :count vote left|of :count votes left', budget.total) }}
                            </span>
                        </div>

                        <div class="ml-auto text-xs text-gray-500">
                            {{ $tc(':count request|:count requests', wishes.length) }}
                        </div>
                    </div>

                    <div v-if="budget && budget.total && !votesLeft" class="mb-4 text-xs text-gray-500">
                        {{ $t('You have spent all your votes. Take one back from a wish you care less about to free it up - downvotes and your own wishes never cost you anything.') }}
                    </div>

                    <div v-if="voteError"
                        class="mb-4 px-4 py-3 rounded-xl border border-amber-400/40 bg-amber-500/10 text-sm text-amber-200">
                        {{ voteError }}
                    </div>

            <div v-if="!wishes.length" class="bg-black/40 border border-white/10 rounded-2xl p-10 text-center text-gray-500">
                {{ $t('Nothing here yet. Be the first.') }}
            </div>

            <div v-else class="space-y-3">
                <div v-for="wish in wishes" :key="wish.id" :id="'wish-' + wish.id"
                    class="bg-black/40 backdrop-blur-sm border rounded-2xl p-4 md:p-5 flex gap-4 transition-colors duration-500"
                    :class="highlightId === wish.id ? 'border-fuchsia-400/60 ring-2 ring-fuchsia-500/40' : 'border-white/10'">

                    <!-- Score column. The net number is the big one because it
                         is what the list is sorted by; the split sits under it
                         so a contested wish does not read like an ignored one. -->
                    <div class="flex flex-col items-center gap-1 w-16 shrink-0" :class="wish.pending ? 'opacity-40' : ''">
                        <button @click="vote(wish, 1)" :disabled="!canUpvote(wish)"
                            :title="user && !canUpvote(wish) ? $t('No votes left - take one back from another wish') : ''"
                            class="w-9 h-9 rounded-lg flex items-center justify-center transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                            :class="wish.my_vote === 1 ? 'bg-green-500/25 text-green-300' : 'bg-white/5 text-gray-400 hover:bg-white/10'">
                            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor"><path d="M12 4l8 10h-6v6h-4v-6H4z"/></svg>
                        </button>
                        <div class="text-xl font-black" :class="wish.score > 0 ? 'text-green-300' : wish.score < 0 ? 'text-red-300' : 'text-gray-400'">
                            {{ wish.score > 0 ? '+' : '' }}{{ wish.score }}
                        </div>
                        <div class="text-[10px] text-gray-600 whitespace-nowrap">+{{ wish.upvotes }} / -{{ wish.downvotes }}</div>
                        <button @click="vote(wish, -1)" :disabled="!user || wish.pending"
                            class="w-9 h-9 rounded-lg flex items-center justify-center transition-colors disabled:opacity-40"
                            :class="wish.my_vote === -1 ? 'bg-red-500/25 text-red-300' : 'bg-white/5 text-gray-400 hover:bg-white/10'">
                            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor"><path d="M12 20L4 10h6V4h4v6h6z"/></svg>
                        </button>
                    </div>

                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2 mb-1">
                            <span v-if="wish.pending"
                                class="text-[11px] px-2 py-0.5 rounded border border-amber-400/40 bg-amber-500/15 text-amber-200 font-bold">
                                {{ $t('Waiting for approval') }}
                            </span>
                            <button type="button" @click="setProject(wish.project)"
                                class="text-[11px] px-2 py-0.5 rounded border border-blue-400/30 bg-blue-500/15 text-blue-200 font-bold hover:bg-blue-500/25 transition-colors">
                                {{ wish.project_label }}
                            </button>
                            <h2 class="text-lg font-bold text-white">{{ wish.title }}</h2>
                            <span class="text-[11px] px-2 py-0.5 rounded border font-bold" :class="statusClass(wish.status)">
                                {{ wish.status_label }}
                            </span>
                        </div>
                        <p class="text-gray-300 whitespace-pre-line leading-relaxed">{{ wish.body }}</p>

                        <!-- Deliberately small. A wish is read in a list of
                             wishes, so the picture is a hint that there is one
                             rather than the thing taking up the row. -->
                        <div v-if="wish.image_url || wish.youtube_id" class="mt-3 flex items-center gap-2">
                            <button v-if="wish.image_url" type="button" @click="openLightbox(wish)"
                                class="group relative rounded-lg overflow-hidden border border-white/10 hover:border-purple-400/50 transition-colors"
                                :title="$t('Click to enlarge')">
                                <img :src="wish.image_url" alt="" loading="lazy"
                                    class="w-20 h-14 object-cover group-hover:opacity-80 transition-opacity" />
                            </button>

                            <button v-if="wish.youtube_id" type="button" @click="openLightbox(wish)"
                                class="group relative w-20 h-14 rounded-lg overflow-hidden border border-white/10 hover:border-purple-400/50 transition-colors"
                                :title="$t('Click to play')">
                                <img :src="`https://i.ytimg.com/vi/${wish.youtube_id}/mqdefault.jpg`" alt="" loading="lazy"
                                    class="w-full h-full object-cover group-hover:opacity-80 transition-opacity" />
                                <span class="absolute inset-0 flex items-center justify-center">
                                    <svg class="w-6 h-6 text-white drop-shadow" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M8 5v14l11-7z" />
                                    </svg>
                                </span>
                            </button>
                        </div>

                        <!-- The answer, and the conversation under it. Only
                             the person who asked and an admin can write here:
                             anybody else who wants the same thing files their
                             own wish, which keeps a vote cast on one request
                             rather than on a discussion that moved on. -->
                        <div v-if="wish.status_note" class="mt-3 border-l-2 border-purple-400/40 pl-3">
                            <div class="text-[10px] font-black uppercase tracking-wider text-purple-400/70 mb-1">
                                {{ $t('Answer from neyo') }}
                            </div>
                            <div class="text-sm text-purple-200 whitespace-pre-line">{{ wish.status_note }}</div>
                        </div>

                        <div v-if="wish.replies?.length" class="mt-3 space-y-2">
                            <div v-for="r in wish.replies" :key="r.id"
                                 class="rounded-lg border-l-2 px-3 py-2"
                                 :class="r.by_admin
                                     ? 'border-purple-400/40 bg-purple-500/[0.06]'
                                     : 'border-white/15 bg-white/[0.03]'">
                                <div class="flex items-center gap-2 mb-1 text-[11px]">
                                    <Link v-if="r.author" :href="`/profile/${r.author.id}`"
                                          class="font-bold hover:underline"
                                          :class="r.by_admin ? 'text-purple-300' : 'text-gray-300'">
                                        {{ r.author.name }}
                                    </Link>
                                    <span v-else class="text-gray-500">{{ $t('deleted account') }}</span>
                                    <span v-if="r.by_admin"
                                          class="rounded bg-purple-500/20 px-1.5 py-0.5 text-[9px] font-black uppercase tracking-wider text-purple-300">
                                        {{ $t('Admin') }}
                                    </span>
                                    <span class="ml-auto text-gray-600">{{ timeAgo(r.created_at) }}</span>
                                </div>
                                <div class="text-sm whitespace-pre-line"
                                     :class="r.by_admin ? 'text-purple-200' : 'text-gray-300'">{{ r.body }}</div>
                            </div>
                        </div>

                        <!-- Append only: there is no edit and no delete, so a
                             wish people have voted on cannot quietly become a
                             different one. -->
                        <div v-if="wish.can_reply && (wish.status_note || wish.replies?.length)" class="mt-3">
                            <button
                                v-if="replyOpen !== wish.id"
                                type="button"
                                @click="openReply(wish.id)"
                                class="text-xs font-bold text-purple-300 hover:text-purple-200 transition-colors"
                            >
                                {{ $t('Reply') }}
                            </button>

                            <form v-else @submit.prevent="submitReply(wish.id)">
                                <textarea
                                    v-model="replyBody"
                                    rows="3"
                                    :maxlength="2000"
                                    :placeholder="$t('Add to your wish. You cannot change what you already wrote.')"
                                    class="w-full bg-black/40 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-gray-500 focus:border-purple-500/50 focus:ring-1 focus:ring-purple-500/50 resize-none"
                                ></textarea>
                                <div class="mt-2 flex items-center gap-2">
                                    <button
                                        type="submit"
                                        :disabled="replySending || !replyBody.trim()"
                                        class="px-3 py-1.5 rounded-lg bg-purple-600 hover:bg-purple-700 disabled:bg-gray-700 disabled:text-gray-500 text-white text-xs font-bold transition-colors"
                                    >
                                        {{ replySending ? $t('Submitting...') : $t('Reply') }}
                                    </button>
                                    <button
                                        type="button"
                                        @click="replyOpen = null"
                                        class="px-3 py-1.5 text-xs font-bold text-gray-400 hover:text-white transition-colors"
                                    >
                                        {{ $t('Cancel') }}
                                    </button>
                                    <span class="ml-auto text-[11px] tabular-nums text-gray-600">{{ replyBody.length }} / 2000</span>
                                </div>
                            </form>
                        </div>

                        <div class="mt-3 flex flex-wrap items-center gap-3 text-xs text-gray-500">
                            <Link v-if="wish.author" :href="`/profile/${wish.author.id}`" class="hover:underline">
                                {{ wish.author.name }}
                            </Link>
                            <span v-else>{{ $t('deleted account') }}</span>
                            <span>{{ fmtDate(wish.created_at) }}</span>
                            <span v-if="wish.mine && wish.removal_requested" class="text-amber-300">
                                {{ $t('Removal requested - waiting for an admin') }}
                            </span>
                            <button v-else-if="wish.mine" @click="askRemoval(wish)" class="text-red-400 hover:underline">
                                {{ $t('Ask an admin to remove it') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

                </div>
            </div>

        </div>

        <!-- The bigger look. Backdrop click and Escape both close it; the
             panel itself swallows the click so hitting play does not. -->
        <Teleport to="body">
            <div v-if="lightbox" class="fixed inset-0 z-[100] bg-black/85 backdrop-blur-sm flex items-center justify-center p-4"
                @click="closeLightbox">
                <div class="max-w-4xl w-full" @click.stop>
                    <div class="flex items-start justify-between gap-4 mb-2">
                        <h3 class="text-white font-bold">{{ lightbox.title }}</h3>
                        <button type="button" @click="closeLightbox"
                            class="shrink-0 text-gray-400 hover:text-white transition-colors" :aria-label="$t('Close')">
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <img v-if="lightbox.image_url" :src="lightbox.image_url" alt=""
                        class="w-full max-h-[70vh] object-contain rounded-xl border border-white/10 bg-black" />

                    <div v-if="lightbox.youtube_id" class="mt-3 aspect-video">
                        <iframe class="w-full h-full rounded-xl border border-white/10"
                            :src="`https://www.youtube.com/embed/${lightbox.youtube_id}`"
                            title="YouTube" frameborder="0" allowfullscreen
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"></iframe>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>
