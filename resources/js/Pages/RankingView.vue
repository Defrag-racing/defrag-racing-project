<script setup>
    import { Head, router, Link, usePage } from '@inertiajs/vue3';
    import Rating from '@/Components/Rating.vue';
    import AmnestyBanner from '@/Components/AmnestyBanner.vue';
    import Pagination from '@/Components/Basic/Pagination.vue';
    import IconSelect from '@/Components/Basic/IconSelect.vue';
    import { watchEffect, ref, computed, onMounted, onUnmounted } from 'vue';
    import { t } from '@/utils/i18n';

    const props = defineProps({
        vq3Ratings: Object,
        cpmRatings: Object,
        myVq3Rating: Object,
        myCpmRating: Object,
        countries: {
            type: Array,
            default: () => [],
        },
        lastRecalculation: String,
    });

    const order = ref('ASC');
    const column = ref('time');
    const gametype = ref('run');
    const gametypeGroups = computed(() => [
        {
            label: 'Run',
            types: [{ value: 'run', label: 'Run' }]
        },
        {
            label: 'CTF',
            types: [
                { value: 'ctf1', label: 'CTF 1' },
                { value: 'ctf2', label: 'CTF 2' },
                { value: 'ctf3', label: 'CTF 3' },
                { value: 'ctf4', label: 'CTF 4' },
                { value: 'ctf5', label: 'CTF 5' },
                { value: 'ctf6', label: 'CTF 6' },
                { value: 'ctf7', label: 'CTF 7' },
            ]
        }
    ]);

    const rankingtype = ref('active_players');
    const rankingtypes = [
        'active_players',
        'all_players',
    ];

    const category = ref('overall');
    const categories = computed(() => [
        { value: 'overall', label: t('All'), icon: '🏆', image: null, color: 'orange' },
        { value: 'strafe', label: t('Strafe'), icon: '🏃', image: '/images/weapons/iconw_gauntlet.svg', color: 'sky' },
        { value: 'slick', label: t('Slick'), icon: '🧊', image: '/images/functions/slick.svg', color: 'yellow' },
        { value: 'tele', label: t('Tele'), icon: '🌀', image: '/images/functions/teleporter.svg', color: 'cyan' },
        { value: 'rocket', label: 'RL', icon: '🚀', image: '/images/weapons/iconw_rocket.svg', color: 'red' },
        { value: 'plasma', label: 'PG', icon: '⚡', image: '/images/weapons/iconw_plasma.svg', color: 'purple' },
        { value: 'grenade', label: 'GL', icon: '💣', image: '/images/weapons/iconw_grenade.svg', color: 'green' },
        { value: 'lg', label: 'LG', icon: '⚡', image: '/images/weapons/iconw_lightning.svg', color: 'white' },
        { value: 'bfg', label: 'BFG', icon: '💥', image: '/images/weapons/iconw_bfg.svg', color: 'blue' },
    ]);

    // One country at a time, or the whole board. Read from the address so a
    // filtered ranking can be linked to.
    const country = ref(new URLSearchParams(window.location.search).get('country')?.toUpperCase() || '');

    const countryName = (code) => {
        try {
            return new Intl.DisplayNames([document.documentElement.lang || 'en'], { type: 'region' }).of(code) || code;
        } catch (e) {
            return code;
        }
    };

    // By name in the reader's language, not by code: "Germany" and
    // "Allemagne" do not sort the same way, and the list is read, not typed.
    const sortedCountries = computed(() => [...props.countries]
        .map((c) => ({ ...c, label: countryName(c.code) }))
        .sort((a, b) => a.label.localeCompare(b.label, document.documentElement.lang || 'en')));

    // The three filters as option lists, so one component draws them all
    // and every choice can carry its picture.
    const modeOptions = computed(() => gametypeGroups.value.flatMap((g) => g.types.map((t2) => ({ value: t2.value, label: t2.label }))));

    const categoryOptions = computed(() => categories.value.map((c) => ({
        value: c.value,
        label: c.label,
        image: c.image,
        icon: c.image ? null : c.icon,
        disabled: !isLoggedIn.value && guestLockedCategories.includes(c.value),
    })));

    const countryOptions = computed(() => [
        { value: '', label: t('All countries') },
        ...sortedCountries.value.map((c) => ({ value: c.code, label: c.label, flag: c.code, count: c.players })),
    ]);

    // Back to the board as it opens: active players, run, all categories,
    // every country. Only offered once something is actually set.
    const filtersTouched = computed(() => rankingtype.value !== 'active_players'
        || gametype.value !== 'run'
        || category.value !== 'overall'
        || country.value !== '');

    const resetFilters = () => {
        rankingtype.value = 'active_players';
        gametype.value = 'run';
        category.value = 'overall';
        country.value = '';
        reloadRankings();
    };

    const selectCountry = (code) => {
        country.value = code || '';
        reloadRankings();
    };

    const reloadRankings = () => {
        ratingsLoaded.value = false;
        router.reload({
            only: ['vq3Ratings', 'cpmRatings', 'myVq3Rating', 'myCpmRating'],
            data: {
                gametype: gametype.value,
                rankingtype: rankingtype.value,
                category: category.value,
                country: country.value,
            },
            onFinish: () => {
                ratingsLoaded.value = true;
            }
        })
    }

    const sortByGametype = (gtValue) => {
        gametype.value = gtValue;
        reloadRankings();
    }

    const selectRankingType = (rt) => {
        rankingtype.value = rt;
        reloadRankings();
    }

    const isLoggedIn = computed(() => !!usePage().props.auth?.user);
    const showLoginPopup = ref(false);
    const guestLockedCategories = ['strafe', 'slick', 'rocket', 'plasma', 'grenade', 'lg', 'bfg', 'tele'];

    const selectCategory = (cat) => {
        if (!isLoggedIn.value && guestLockedCategories.includes(cat)) {
            showLoginPopup.value = true;
            return;
        }
        category.value = cat;
        reloadRankings();
    }

    watchEffect(() => {
        gametype.value = route().params['gametype'] ?? 'run';
        rankingtype.value = route().params['rankingtype'] ?? 'active_players';
        category.value = route().params['category'] ?? 'overall';
    });

    const screenWidth = ref(window.innerWidth);
    const isRotating = ref(false);
    const interval = ref(null);
    const page = usePage();
    const cpmFirst = computed(() => page.props.physicsOrder === 'cpm_first');

    const startInterval = () => {
        if (interval.value == null) {
            interval.value = setInterval(updatePage, 30000);
        }
    }

    const stopInterval = () => {
        clearInterval(interval.value);
        interval.value = null;
    }

    const updatePage = () => {
        if (isRotating.value) {
            return;
        }

        isRotating.value = true;

        router.reload({
            only: ['vq3Ratings', 'cpmRatings', 'myVq3Rating', 'myCpmRating'],
            data: {
                gametype: gametype.value,
                rankingtype: rankingtype.value,
                category: category.value,
                country: country.value,
            },
        })

        setTimeout(() => {
            isRotating.value = false;
        }, 1500);
    }

    const resizeScreen = () => {
        screenWidth.value = window.innerWidth
    }

    const ratingsLoaded = ref(false);

    onMounted(() => {
        window.addEventListener("resize", resizeScreen);
        startInterval();

        // Lazy-load rating data (page renders immediately with header/filters)
        if (!props.vq3Ratings && !props.cpmRatings) {
            const start = Date.now();
            router.reload({
                only: ['vq3Ratings', 'cpmRatings', 'myVq3Rating', 'myCpmRating'],
                onFinish: () => {
                    const remaining = 400 - (Date.now() - start);
                    if (remaining > 0) {
                        setTimeout(() => { ratingsLoaded.value = true; }, remaining);
                    } else {
                        ratingsLoaded.value = true;
                    }
                }
            });
        } else {
            ratingsLoaded.value = true;
        }
    });

    onUnmounted(() => {
        window.removeEventListener("resize", resizeScreen);
        stopInterval();
    });
</script>

<template>
    <div class="">
        <Head :title="$t('Ranking')" />

        <!-- WIP Banner -->
        <div class="relative z-20 bg-red-600 border-b-4 border-red-800 px-4 py-4 text-center">
            <div class="max-w-4xl mx-auto">
                <div class="flex items-center justify-center gap-3 mb-1">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-7 h-7 text-white"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" /></svg>
                    <span class="text-white font-black text-lg sm:text-xl uppercase tracking-wide">{{ $t('Work in Progress') }}</span>
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-7 h-7 text-white"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" /></svg>
                </div>
                <p class="text-white/90 text-sm sm:text-base font-semibold">{{ $t('The ranking system is still under active development. Parameters are being tuned and formulas may change. Please give me time until the system is finalized.') }}</p>
            </div>
        </div>

        <!-- Header Section -->
        <div class="relative z-10 bg-gradient-to-b from-black/25 via-black/10 to-transparent pt-6 pb-96 pointer-events-none">
            <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8 pointer-events-auto">
                <!-- One line for what the page is, one line for what it
                     shows. Every control the same height, every filter the
                     same shape, so the eye reads a bar and not five widgets. -->
                <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                    <h1 class="text-2xl md:text-3xl font-black text-gray-300/90">{{ $t('Player Rankings') }}</h1>

                    <div class="relative group">
                        <Link href="/ranking/how-it-works" class="flex items-center gap-1.5 text-gray-500 hover:text-gray-300 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3.5 h-3.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
                            </svg>
                            <span class="text-[11px] font-semibold underline decoration-dotted decoration-gray-500 underline-offset-2">{{ $t('How it works') }}</span>
                        </Link>
                        <div class="absolute left-0 top-full mt-2 w-80 bg-gray-900/95 border border-white/10 rounded-xl px-4 py-3 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-50 shadow-2xl">
                            <div class="text-xs text-gray-400 leading-relaxed space-y-1.5">
                                <p class="[&_span]:text-gray-300 [&_span]:font-semibold" v-html="$t('Each record is scored using a <span>logistic curve</span> based on how close the time is to the world record (reltime).')"></p>
                                <p class="[&_span]:text-gray-300 [&_span]:font-semibold" v-html="$t('Map scores are then multiplied by a <span>map multiplier</span> - maps with more active players count fully, maps with few players are penalized proportionally using a Hill function based on the category median.')"></p>
                                <p class="[&_span]:text-gray-300 [&_span]:font-semibold" v-html="$t('Your best maps are <span>weighted exponentially</span> - top scores count the most, weaker ones are diminished. The final rating is a weighted average.')"></p>
                                <p class="[&_span]:text-gray-300 [&_span]:font-semibold" v-html="$t('Players with fewer than <span>10 records</span> receive a proportional penalty. Maps with fewer than <span>5 players</span> or 4+ tied WR times are excluded.')"></p>
                                <p class="[&_span]:text-gray-300 [&_span]:font-semibold" v-html="$t('Rankings <span>update in real-time</span> with every new record, plus a full recalculation runs daily.')"></p>
                            </div>
                            <Link href="/ranking/how-it-works" class="block mt-2 pt-2 border-t border-white/10 text-blue-400 hover:text-blue-300 text-[11px] font-semibold">
                                {{ $t('Read full explanation with examples') }} &#x2192;
                            </Link>
                        </div>
                    </div>

                    <div v-if="lastRecalculation" class="relative group/recalc">
                        <div class="flex items-center gap-1.5 text-[11px] cursor-help">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3.5 h-3.5 text-green-500">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.992 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182" />
                            </svg>
                            <span class="text-green-400/80">{{ $t('Rankings update automatically with every new record') }}</span>
                        </div>
                        <div class="absolute top-full left-0 mt-1.5 w-72 p-2.5 bg-gray-900 border border-gray-700 rounded-lg text-[11px] text-gray-300 shadow-xl opacity-0 pointer-events-none group-hover/recalc:opacity-100 group-hover/recalc:pointer-events-auto transition-opacity z-50">
                            <p class="text-white font-medium mb-1">{{ $t('Always up-to-date') }}</p>
                            <p>{{ $t('Rankings are recalculated instantly every time a new record is submitted. A full recalculation across all maps also runs once daily.') }}</p>
                        </div>
                    </div>

                    <div class="ml-auto">
                        <AmnestyBanner />
                    </div>
                </div>

                <!-- Filters -->
                <div class="mt-3 bg-black/40 backdrop-blur-sm border border-white/10 rounded-xl p-2.5">
                    <div class="flex flex-wrap items-center gap-2">
                        <div class="flex gap-1.5">
                            <button
                                v-for="rt in rankingtypes"
                                :key="rt"
                                @click="selectRankingType(rt)"
                                :class="[
                                    rankingtype === rt ? 'bg-blue-500/30 border-blue-400/50 text-white' : 'bg-white/5 border-white/10 text-gray-400 hover:bg-white/10',
                                    rt === 'active_players' ? 'pr-2' : 'pr-3',
                                ]"
                                class="h-10 pl-3 rounded-lg border text-sm font-bold uppercase transition-all whitespace-nowrap flex items-center gap-2">
                                {{ rt === 'active_players' ? $t('Active players') : $t('All players') }}

                                <!-- The explanation rides on the choice it explains:
                                     hovering the i opens it, clicking it does not
                                     pick the button underneath. -->
                                <span v-if="rt === 'active_players'" class="relative group/who flex items-center" @click.stop @mousedown.stop>
                                    <svg class="w-4 h-4 text-gray-400 hover:text-white transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <span class="absolute top-full mt-1.5 left-1/2 -translate-x-1/2 hidden group-hover/who:block z-50 w-64 p-2.5 bg-gray-900 border border-white/20 rounded-lg shadow-2xl normal-case text-left whitespace-normal break-words">
                                        <span class="block text-[11px] font-bold text-white mb-1">{{ rt === 'active_players' ? $t('Recently active players only') : $t('All time rankings') }}</span>
                                        <span class="block text-[11px] text-gray-300 font-medium leading-relaxed">
                                            {{ rt === 'active_players' ? $t('Players who set a record in this physics within the last 3 calendar months') : $t('All players who have ever set a record in this physics') }}
                                        </span>
                                    </span>
                                </span>
                            </button>

                        </div>

                        <IconSelect
                            :model-value="gametype"
                            :options="modeOptions"
                            width-class="w-32"
                            @change="sortByGametype" />

                        <IconSelect
                            :model-value="category"
                            :options="categoryOptions"
                            width-class="w-36"
                            @change="selectCategory" />

                        <IconSelect
                            v-if="countries.length"
                            :model-value="country"
                            :options="countryOptions"
                            width-class="w-52"
                            @change="selectCountry" />

                        <button
                            v-if="filtersTouched"
                            type="button"
                            @click="resetFilters"
                            class="ml-auto h-10 px-3 flex items-center gap-1.5 rounded-lg border border-white/10 bg-white/5 text-sm font-bold text-gray-400 hover:text-white hover:bg-white/10 transition-colors">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992V4.356m0 4.992-3.181-3.183a8.25 8.25 0 0 0-13.803 3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7M2.985 19.644v-4.992m0 0h4.993m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7" />
                            </svg>
                            {{ $t('Reset') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Content Section -->
        <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8" style="margin-top: -22rem;">
            <!-- My Ratings Section -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
                <!-- My VQ3 Rating -->
                <div :style="{ order: cpmFirst ? 2 : 1 }" class="bg-black/40 backdrop-blur-sm rounded-xl overflow-hidden shadow-2xl border border-blue-500/20">
                    <div class="bg-gradient-to-r from-blue-600/20 to-blue-500/10 border-b border-blue-500/30 px-4 py-3">
                        <div class="flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-blue-400">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                            </svg>
                            <h2 class="text-sm font-bold text-blue-400">{{ $t('My VQ3 Rating') }}</h2>
                        </div>
                    </div>
                    <div class="px-2 py-1" style="min-height: 60px; display: flex; align-items: center;">
                        <div v-if="myVq3Rating" class="w-full">
                            <Rating :rating="myVq3Rating" :rank="rankingtype === 'active_players' ? myVq3Rating.active_players_rank : myVq3Rating.all_players_rank"/>
                        </div>
                        <div v-else class="flex items-center justify-center text-gray-400 text-sm w-full">
                            <div v-if="page.props?.auth?.user">{{ $t('You have no VQ3 rating in this category') }}</div>
                            <div v-else>{{ $t('You need to be logged in to see your rating') }}</div>
                        </div>
                    </div>
                </div>

                <!-- My CPM Rating -->
                <div :style="{ order: cpmFirst ? 1 : 2 }" class="bg-black/40 backdrop-blur-sm rounded-xl overflow-hidden shadow-2xl border border-purple-500/20">
                    <div class="bg-gradient-to-r from-purple-600/20 to-purple-500/10 border-b border-purple-500/30 px-4 py-3">
                        <div class="flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-purple-400">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                            </svg>
                            <h2 class="text-sm font-bold text-purple-400">{{ $t('My CPM Rating') }}</h2>
                        </div>
                    </div>
                    <div class="px-2 py-1" style="min-height: 60px; display: flex; align-items: center;">
                        <div v-if="myCpmRating" class="w-full">
                            <Rating :rating="myCpmRating" :rank="rankingtype === 'active_players' ? myCpmRating.active_players_rank : myCpmRating.all_players_rank"/>
                        </div>
                        <div v-else class="flex items-center justify-center text-gray-400 text-sm w-full">
                            <div v-if="page.props?.auth?.user">{{ $t('You have no CPM rating in this category') }}</div>
                            <div v-else>{{ $t('You need to be logged in to see your rating') }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Loading skeleton while ratings load -->
            <div v-if="!ratingsLoaded" class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <div v-for="i in 2" :key="i" class="bg-black/40 backdrop-blur-sm rounded-xl overflow-hidden shadow-2xl border border-white/10 animate-pulse">
                    <div class="bg-white/5 border-b border-white/10 px-4 py-3">
                        <div class="h-6 bg-white/10 rounded w-40"></div>
                    </div>
                    <div class="px-4 py-2 space-y-3">
                        <div v-for="j in 10" :key="j" class="h-10 bg-white/5 rounded"></div>
                    </div>
                </div>
            </div>

            <!-- All Rankings Section -->
            <div v-else class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <!-- VQ3 Rankings -->
                <div :style="{ order: cpmFirst ? 2 : 1 }" class="bg-black/40 backdrop-blur-sm rounded-xl overflow-hidden shadow-2xl border border-blue-500/20">
                    <div class="bg-gradient-to-r from-blue-600/20 to-blue-500/10 border-b border-blue-500/30 px-4 py-3">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <h2 class="text-lg font-bold text-blue-400">{{ $t('VQ3 Rankings') }} <span v-if="vq3Ratings" class="text-sm font-normal text-gray-400">({{ vq3Ratings.total }})</span></h2>
                            </div>
                            <Link v-if="page.props.auth?.user" href="/user/settings?tab=global-customize" class="text-xs text-gray-500 hover:text-blue-400 transition-colors underline decoration-dotted underline-offset-2">
                                {{ $t('Swap VQ3/CPM sides') }}
                            </Link>
                        </div>
                    </div>
                    <div class="px-2 py-1">
                        <div v-if="vq3Ratings && vq3Ratings.total > 0">
                            <Rating v-for="rating in vq3Ratings.data" :key="rating.id" :rating="rating" :rank="rating.rank"/>
                        </div>
                        <div v-else class="flex items-center justify-center text-gray-400" style="min-height: 490px;">
                            <div class="text-center">
                                <div class="flex items-center justify-center mb-4">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-12 h-12">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                    </svg>
                                </div>
                                <div class="text-lg">{{ rankingtype === 'active_players' ? $t('No active VQ3 players in the last 3 months') : $t('There are no VQ3 Ratings') }}</div>
                            </div>
                        </div>
                    </div>
                    <div v-if="vq3Ratings && vq3Ratings.total > vq3Ratings.per_page" class="border-t border-blue-500/20 p-4">
                        <Pagination pageName="vq3Page" :last_page="vq3Ratings.last_page" :current_page="vq3Ratings.current_page" :link="vq3Ratings.first_page_url" :only="['vq3Ratings', 'cpmRatings']" />
                    </div>
                </div>

                <!-- CPM Rankings -->
                <div :style="{ order: cpmFirst ? 1 : 2 }" class="bg-black/40 backdrop-blur-sm rounded-xl overflow-hidden shadow-2xl border border-purple-500/20">
                    <div class="bg-gradient-to-r from-purple-600/20 to-purple-500/10 border-b border-purple-500/30 px-4 py-3">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <h2 class="text-lg font-bold text-purple-400">{{ $t('CPM Rankings') }} <span v-if="cpmRatings" class="text-sm font-normal text-gray-400">({{ cpmRatings.total }})</span></h2>
                            </div>
                            <Link v-if="page.props.auth?.user" href="/user/settings?tab=global-customize" class="text-xs text-gray-500 hover:text-purple-400 transition-colors underline decoration-dotted underline-offset-2">
                                {{ $t('Swap VQ3/CPM sides') }}
                            </Link>
                        </div>
                    </div>
                    <div class="px-2 py-1">
                        <div v-if="cpmRatings && cpmRatings.total > 0">
                            <Rating v-for="rating in cpmRatings.data" :key="rating.id" :rating="rating" :rank="rating.rank"/>
                        </div>
                        <div v-else class="flex items-center justify-center text-gray-400" style="min-height: 490px;">
                            <div class="text-center">
                                <div class="flex items-center justify-center mb-4">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-12 h-12">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                    </svg>
                                </div>
                                <div class="text-lg">{{ rankingtype === 'active_players' ? $t('No active CPM players in the last 3 months') : $t('There are no CPM Ratings') }}</div>
                            </div>
                        </div>
                    </div>
                    <div v-if="cpmRatings && cpmRatings.total > cpmRatings.per_page" class="border-t border-purple-500/20 p-4">
                        <Pagination pageName="cpmPage" :last_page="cpmRatings.last_page" :current_page="cpmRatings.current_page" :link="cpmRatings.first_page_url" :only="['vq3Ratings', 'cpmRatings']" />
                    </div>
                </div>
            </div>
        </div>

        <div class="h-4"></div>
    </div>

    <!-- Login Required Popup -->
    <Teleport to="body">
        <div v-if="showLoginPopup" class="fixed inset-0 z-[9999] flex items-center justify-center" @click.self="showLoginPopup = false">
            <div class="fixed inset-0 bg-black/60 backdrop-blur-sm"></div>
            <div class="relative bg-gray-900 border border-white/10 rounded-2xl shadow-2xl max-w-md w-full mx-4 p-6">
                <button @click="showLoginPopup = false" class="absolute top-3 right-3 text-gray-500 hover:text-white transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 rounded-full bg-blue-500/20 flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
                        </svg>
                    </div>
                    <h3 class="text-lg font-bold text-white">{{ $t('Login Required') }}</h3>
                </div>
                <p class="text-gray-300 text-sm mb-5">{{ $t('Category filters are available only for logged-in users. Please login or register to access detailed rankings.') }}</p>
                <div class="flex gap-3">
                    <a href="/login" class="flex-1 text-center px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white font-semibold rounded-lg transition-colors">{{ $t('Login') }}</a>
                    <a href="/register" class="flex-1 text-center px-4 py-2 bg-green-600 hover:bg-green-500 text-white font-semibold rounded-lg transition-colors">{{ $t('Register') }}</a>
                </div>
            </div>
        </div>
    </Teleport>
</template>
