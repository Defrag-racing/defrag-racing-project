<script setup>
    import { ref, onMounted, onUnmounted } from 'vue';
    import { Head, Link, router } from '@inertiajs/vue3';
    import ApplicationMark from '@/Components/Laravel/ApplicationMark.vue';
    import Banner from '@/Components/Laravel/Banner.vue';
    import Dropdown from '@/Components/Laravel/Dropdown.vue';
    import DropdownLink from '@/Components/Laravel/DropdownLink.vue';
    import NavLink from '@/Components/Laravel/NavLink.vue';
    import ResponsiveNavLink from '@/Components/Laravel/ResponsiveNavLink.vue';
    import TextInput from '@/Components/Laravel/TextInput.vue';
    import SecondaryButton from '@/Components/Laravel/SecondaryButton.vue';
    import PrimaryButton from '@/Components/Laravel/PrimaryButton.vue';

    import MapSearchItem from '@/Components/MapSearchItem.vue';
    import PlayerSearchItem from '@/Components/PlayerSearchItem.vue';
    import NotificationMenu from '@/Components/NotificationMenu.vue';
import { watchEffect } from 'vue';

    defineProps({
        title: String,
    });

    const showingNavigationDropdown = ref(false);

    const search = ref('');

    const maps = ref([]);
    const players = ref([]);

    const showResultsSection = ref(false);

    const resultsSection = ref(null);
    const searchSection = ref(null);

    const searchCategory = ref('all');

    const screenWidth = ref(window.innerWidth);

    let timer;

    const debounce = () => {
        let searchingString = search.value;

        timer = setTimeout(() => {
            if (searchingString == search.value) {
                axios.post(route('search'), {
                    search: search.value
                }).then(response => {
                    maps.value = response.data?.maps
                    players.value = response.data?.players
                });
            }
        }, 250);
    }

    const logout = () => {
        router.post(route('logout'));
    };

    const onSearchFocus = () => {
        showResultsSection.value = true;
    }

    const onSearchBlur = (event) => {
        if (! showResultsSection.value) {
            return;
        }
        
        if (! searchSection.value.contains(event.target)) {
            showResultsSection.value = false;
        }

        if (! searchSection.value.contains(event.target)) {
            showResultsSection.value = false;
        }

    }

    const performSearch = () => {
        if (search.value.length == 0) {
            maps.value = [];
            players.value = [];
            return;
        }

        debounce()
    }

    const resizeScreen = () => {
        screenWidth.value = window.innerWidth
    }


    onMounted(() => {
        window.addEventListener("resize", resizeScreen);
    });

    onUnmounted(() => {
        window.removeEventListener("resize", resizeScreen);
    });
</script>

<template>
    <div @click="onSearchBlur">
        <Head :title="title">
            <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
            <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
            <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
            <link rel="manifest" href="/site.webmanifest">

            <!-- Google tag (gtag.js) -->
            <component :is="'script'" async src="https://www.googletagmanager.com/gtag/js?id=G-FMC55XYK1K">
                
            </component>
            <component :is="'script'">
                window.dataLayer = window.dataLayer || [];
                function gtag(){dataLayer.push(arguments);}
                gtag('js', new Date());

                gtag('config', 'G-FMC55XYK1K');
            </component>
        </Head>

        <Banner :show="true" styling="success" handle="dfracing2024-announcement">
            <span>
                <span class="font-bold">Anouncement:</span> 2024 new beginnings ... 
                <span class="font-bold italic hover:underline">
                    <Link :href="route('home')">
                        Read More
                    </Link>
                </span>
            </span>
        </Banner>

        <div class="min-h-screen bg-gray-900 main-background">
            <nav class="border-b border-grayop-700">
                <!-- Top Bar -->
                <div class="max-w-8xl mx-auto px-4 md:px-6 lg:px-8">
                    <div class="flex justify-between h-16 items-center">
                        <div class="flex flex-grow sm:flex-grow-0">
                            <!-- Logo -->
                            <div class="shrink-0 flex items-center mt-2 sm:mt-0">
                                <Link :href="route('home')">
                                    <ApplicationMark class="block h-9 w-auto" />
                                </Link>
                            </div>

                            <div class="flex flex-grow items-center justify-end">
                                <div v-if="$page.props.auth.user && screenWidth <= 640">
                                    <NotificationMenu :ping="false" />
                                </div>
                            </div>
                        </div>

                        <div ref="searchSection">
                            <TextInput
                                v-model="search"
                                @focus="onSearchFocus"
                                type="text"
                                class="mt-1 h-10 hidden sm:block md:w-80 lg:w-100"
                                placeholder="Search..."
                                @input="performSearch"
                            />

                            <div v-if="showResultsSection" class="defrag-scrollbar search-results hidden p-3 sm:block md:w-80 lg:w-100 mt-1 absolute bg-gray-900 border-2 border-grayop-700 rounded-md text-gray-500" style="z-index: 2000;">
                                <div v-if="search.length == 0">
                                    Type a search query...
                                </div>

                                <div ref="resultsSection" v-else>
                                    <div class="flex justify-between items-center mb-3">
                                        <div @click="searchCategory = 'all'" class="flex-1 text-center cursor-pointer px-4 py-1 rounded-full text-sm mx-0.5" :class="{'bg-gray-600 text-white': searchCategory == 'all', 'bg-gray-800 hover:bg-gray-800 text-gray-400 hover:text-gray-200': searchCategory != 'all'}">All</div>
                                        <div @click="searchCategory = 'maps'" class="flex-1 text-center cursor-pointer px-4 py-1 rounded-full text-sm mx-0.5" :class="{'bg-gray-600 text-white': searchCategory == 'maps', 'bg-gray-800 hover:bg-gray-800 text-gray-400 hover:text-gray-200': searchCategory != 'maps'}">Maps</div>
                                        <div @click="searchCategory = 'players'" class="flex-1 text-center cursor-pointer px-4 py-1 rounded-full text-sm mx-0.5" :class="{'bg-gray-600 text-white': searchCategory == 'players', 'bg-gray-800 hover:bg-gray-800 text-gray-400 hover:text-gray-200': searchCategory != 'players'}">Players</div>
                                    </div>

                                    <div v-if="maps.data?.length > 0 && (searchCategory == 'all' || searchCategory == 'maps')">
                                        <div class="font-bold text-gray-400 capitalized text-sm mb-1">
                                            Maps
                                        </div>
                                        <MapSearchItem v-for="map in maps.data" :map="map" :key="map.id" />
                                    </div>

                                    <div v-if="players?.length > 0  && (searchCategory == 'all' || searchCategory == 'players')">
                                        <div class="font-bold text-gray-400 capitalized text-sm mb-1">
                                            Players
                                        </div>
                                        <PlayerSearchItem v-for="player in players" :player="player" :key="player.id" />
                                    </div>

                                    <div v-if="players?.length == 0 && maps.data?.length == 0">
                                        There are no results !
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center md:ms-6 justify-end">
                            <div class="flex justify-end flex-grow" v-if="screenWidth > 640">
                                <div v-if="$page.props.auth.user">
                                    <NotificationMenu :ping="true" />
                                </div>
                            </div>


                            <!-- Settings Dropdown -->
                            <div v-if="$page.props.auth.user"  class="hidden md:block ms-3">
                                <Dropdown align="right" width="48">
                                    <template #trigger>
                                        <button class="pr-10 pl-1 py-1 flex items-center text-sm rounded-full focus:bg-gray-50 dark:focus:bg-grayop-700 active:bg-gray-50 dark:active:bg-grayop-700 transition ease-in-out duration-150">
                                            <img class="h-8 w-8 rounded-full object-cover mr-3" :src="$page.props.auth.user.profile_photo_path ? '/storage/' + $page.props.auth.user.profile_photo_path : '/images/null.jpg'" :alt="$page.props.auth.user.name">

                                            <div class="mr-3" v-html="q3tohtml($page.props.auth.user.name)"></div>

                                            <div class="text-gray-400">
                                                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                                                </svg>
                                            </div>
                                        </button>
                                    </template>

                                    <template #content>
                                        <!-- Account Management -->
                                        <div class="block px-4 py-2 text-xs text-gray-400">
                                            Manage Account
                                        </div>

                                        <DropdownLink :href="route('profile.index', $page.props.auth.user.id)">
                                            My Profile
                                        </DropdownLink>

                                        <DropdownLink :href="route('profile.show')">
                                            Settings
                                        </DropdownLink>

                                        <div class="border-t border-gray-200 dark:border-gray-600" />

                                        <!-- Authentication -->
                                        <form @submit.prevent="logout">
                                            <DropdownLink as="button">
                                                Log Out
                                            </DropdownLink>
                                        </form>
                                    </template>
                                </Dropdown>
                            </div>

                            <!-- Auth Buttons -->
                            <div v-else v-if="screenWidth > 390">
                                <div class="flex">
                                    <Link :href="route('login')">
                                        <PrimaryButton type="button" class="py-1">
                                            Login
                                        </PrimaryButton>
                                    </Link>

                                    <Link :href="route('register')" class="ml-3">
                                        <SecondaryButton type="button" class="py-1">
                                            Register
                                        </SecondaryButton>
                                    </Link>
                                </div>
                            </div>

                            <div class="md:hidden">
                                <button class="inline-flex items-center justify-center p-2 rounded-md text-gray-500 hover:text-gray-400 hover:bg-grayop-900 focus:outline-none focus:bg-grayop-900 focus:text-gray-400 transition duration-150 ease-in-out" @click="showingNavigationDropdown = ! showingNavigationDropdown">
                                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                                        <path
                                            :class="{'hidden': showingNavigationDropdown, 'inline-flex': ! showingNavigationDropdown }"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M4 6h16M4 12h16M4 18h16"
                                        />
                                        <path
                                            :class="{'hidden': ! showingNavigationDropdown, 'inline-flex': showingNavigationDropdown }"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            stroke-width="2"
                                            d="M6 18L18 6M6 6l12 12"
                                        />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Primary Navigation Menu-->
                <div class="max-w-8xl mx-auto mt-2">
                    <!-- Navigation Links -->
                    <div class="hidden space-x-8 md:-my-px md:ms-6 md:flex">
                        <NavLink :href="route('home')" :active="route().current('home')">
                            Home
                        </NavLink>

                        <NavLink :href="route('servers')" :active="route().current('servers')">
                            Servers
                        </NavLink>

                        <NavLink :href="route('maps')" :active="route().current('maps')">
                            Maps
                        </NavLink>

                        <!-- <NavLink :href="route('ranking')" :active="route().current('ranking')">
                            Ranking
                        </NavLink> -->

                        <NavLink :href="route('records')" :active="route().current('records')">
                            Records
                        </NavLink>

                        <NavLink :href="route('bundles')" :active="route().current('bundles')">
                            Bundles
                        </NavLink>
                    </div>
                </div>

                <!-- Responsive Navigation Menu -->
                <div :class="{'block': showingNavigationDropdown, 'hidden': ! showingNavigationDropdown}" class="md:hidden">
                    <div class="pt-2 pb-3 space-y-1">
                        <ResponsiveNavLink :href="route('login')" :active="route().current('login')">
                            Login
                        </ResponsiveNavLink>

                        <ResponsiveNavLink :href="route('register')" :active="route().current('register')">
                            Register
                        </ResponsiveNavLink>

                        <ResponsiveNavLink :href="route('home')" :active="route().current('home')">
                            Home
                        </ResponsiveNavLink>

                        <ResponsiveNavLink :href="route('servers')" :active="route().current('servers')">
                            Servers
                        </ResponsiveNavLink>

                        <ResponsiveNavLink :href="route('maps')" :active="route().current('maps')">
                            Maps
                        </ResponsiveNavLink>

                        <!-- <ResponsiveNavLink :href="route('ranking')" :active="route().current('ranking')">
                            Ranking
                        </ResponsiveNavLink> -->

                        <ResponsiveNavLink :href="route('records')" :active="route().current('records')">
                            Records
                        </ResponsiveNavLink>

                        <ResponsiveNavLink :href="route('bundles')" :active="route().current('bundles')">
                            Bundles
                        </ResponsiveNavLink>
                    </div>

                    <!-- Responsive Settings Options -->
                    <div v-if="$page.props.auth.user" class="pt-4 pb-1 border-t border-gray-200 dark:border-gray-600">
                        <div class="flex items-center px-4">
                            <div v-if="$page.props.jetstream.managesProfilePhotos" class="shrink-0 me-3">
                                <img class="h-10 w-10 rounded-full object-cover" :src="$page.props.auth.user.profile_photo_path ? '/storage/' + $page.props.auth.user.profile_photo_path : '/images/null.jpg'" :alt="$page.props.auth.user.name">
                            </div>

                            <div>
                                <div class="font-medium text-base text-gray-800 dark:text-gray-200" v-html="q3tohtml($page.props.auth.user.name)"></div>
                                <div class="font-medium text-sm text-gray-500">
                                    @ {{ $page.props.auth.user.username }}
                                </div>
                            </div>
                        </div>

                        <div class="mt-3 space-y-1">
                            <ResponsiveNavLink :href="route('profile.show')" :active="route().current('profile.show')">
                                Profile
                            </ResponsiveNavLink>

                            <!-- Authentication -->
                            <form method="POST" @submit.prevent="logout">
                                <ResponsiveNavLink as="button">
                                    Log Out
                                </ResponsiveNavLink>
                            </form>
                        </div>
                    </div>
                </div>
            </nav>

            <div class="max-w-8xl mx-auto pt-2 px-4 md:px-6 lg:px-8" v-if="screenWidth <= 640">
                <div ref="searchSection">
                    <TextInput
                        v-model="search"
                        @focus="onSearchFocus"
                        type="text"
                        class="h-10 block sm:hidden w-full"
                        placeholder="Search..."
                        @input="performSearch"
                    />

                    <div v-if="showResultsSection" class="defrag-scrollbar search-results block p-3 sm:hidden mt-1 absolute z-10 bg-gray-900 border-2 border-grayop-700 rounded-md text-gray-500" style="left: 10px; right: 10px;">
                        <div v-if="search.length == 0">
                            Type a search query...
                        </div>

                        <div ref="resultsSection" v-else>
                            <div class="flex justify-between items-center mb-3">
                                <div @click="searchCategory = 'all'" class="flex-1 text-center cursor-pointer px-4 py-0.5 rounded-full text-sm mx-0.5" :class="{'bg-gray-600 text-white': searchCategory == 'all', 'bg-gray-800 hover:bg-gray-800 text-gray-400 hover:text-gray-200': searchCategory != 'all'}">All</div>
                                <div @click="searchCategory = 'maps'" class="flex-1 text-center cursor-pointer px-4 py-0.5 rounded-full text-sm mx-0.5" :class="{'bg-gray-600 text-white': searchCategory == 'maps', 'bg-gray-800 hover:bg-gray-800 text-gray-400 hover:text-gray-200': searchCategory != 'maps'}">Maps</div>
                                <div @click="searchCategory = 'players'" class="flex-1 text-center cursor-pointer px-4 py-0.5 rounded-full text-sm mx-0.5" :class="{'bg-gray-600 text-white': searchCategory == 'players', 'bg-gray-800 hover:bg-gray-800 text-gray-400 hover:text-gray-200': searchCategory != 'players'}">Players</div>
                            </div>

                            <div v-if="maps.data?.length > 0 && (searchCategory == 'all' || searchCategory == 'maps')">
                                <div class="font-bold text-gray-400 capitalized text-sm mb-1">
                                    Maps
                                </div>
                                <MapSearchItem v-for="map in maps.data" :map="map" :key="map.id" />
                            </div>

                            <div v-if="players?.length > 0 && (searchCategory == 'all' || searchCategory == 'players')">
                                <div class="font-bold text-gray-400 capitalized text-sm mb-1">
                                    Players
                                </div>
                                <PlayerSearchItem v-for="player in players" :player="player" :key="player.id" />
                            </div>

                            <div v-if="players?.length == 0 && maps.data?.length == 0">
                                There are no results !
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Page Heading -->
            <header v-if="$slots.header" class="shadow">
                <div class="max-w-8xl mx-auto pt-6 px-4 md:px-6 lg:px-8">
                    <slot name="header" />
                </div>
            </header>

            <!-- Page Content -->
            <main>
                <slot />
            </main>
        </div>
    </div>
</template>

<style>
    .popper {
        padding: 0 !important;
    }

    .spinner_V8m1 {
        transform-origin: center;
        animation: spinner_zKoa 2s linear infinite;
    }

    .spinner_V8m1 circle {
        stroke-linecap: round;
        animation: spinner_YpZS 1.5s ease-in-out infinite;
    }

    @keyframes spinner_zKoa {
        100% {
            transform: rotate(360deg);
        }
    }

    @keyframes spinner_YpZS {
        0% {
            stroke-dasharray: 0 150;
            stroke-dashoffset: 0;
        }
        47.5% {
            stroke-dasharray: 42 150;
            stroke-dashoffset: -16;
        }
        95%,
        100% {
            stroke-dasharray: 42 150;
            stroke-dashoffset: -59;
        }
    }
</style>