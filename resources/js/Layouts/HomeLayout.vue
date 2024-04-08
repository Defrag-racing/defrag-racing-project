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
    import SystemNotificationMenu from '@/Components/SystemNotificationMenu.vue';

    import AlertBanner from '@/Components/Basic/AlertBanner.vue';

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

        <div v-if="$page.props.danger">
            <AlertBanner styling="danger" :random="$page.props.dangerRandom">
                {{ $page.props.danger }}
            </AlertBanner>
        </div>

        <div v-if="$page.props.success">
            <AlertBanner styling="success" :random="$page.props.successRandom">
                {{ $page.props.success }}
            </AlertBanner>
        </div>

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

        <div class="min-h-screen bg-gray-900 bg-[url('/images/pattern.svg')]" style="z-index: 10;">
            
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
                                <div v-if="$page.props.auth.user && screenWidth <= 640" class="flex">
                                    <NotificationMenu :ping="false" />
                                    <SystemNotificationMenu :ping="false" />
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
                                <div v-if="$page.props.auth.user" class="flex">
                                    <NotificationMenu :ping="true" />
                                    <SystemNotificationMenu :ping="true" />
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

                        <NavLink :href="route('clans.index')" :active="route().current('clans.index')">
                            Clans
                        </NavLink>

                        <NavLink :href="route('tournaments.index')" :active="route().current('tournaments.index')">
                            Tournaments
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

                        <ResponsiveNavLink :href="route('clans.index')" :active="route().current('clans.index')">
                            Clans
                        </ResponsiveNavLink>

                        <ResponsiveNavLink :href="route('tournaments.index')" :active="route().current('tournaments.index')">
                            Tournaments
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

        <div class="group fixed bottom-0 right-0 p-2  flex items-end justify-end w-24 h-24">
            <!-- main -->
            <div class="text-white shadow-xl flex items-center justify-center p-3 rounded-full bg-gradient-to-r from-blue-700 to-blue-900 z-50 absolute cursor-pointer">
                <svg width="800px" height="800px" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 group-hover:scale-150 transition  transition-all duration-[0.3s]">
                    <path d="M19.4003 18C19.7837 17.2499 20 16.4002 20 15.5C20 12.4624 17.5376 10 14.5 10C11.4624 10 9 12.4624 9 15.5C9 18.5376 11.4624 21 14.5 21L21 21C21 21 20 20 19.4143 18.0292M18.85 12C18.9484 11.5153 19 11.0137 19 10.5C19 6.35786 15.6421 3 11.5 3C7.35786 3 4 6.35786 4 10.5C4 11.3766 4.15039 12.2181 4.42676 13C5.50098 16.0117 3 18 3 18H9.5" stroke="#ffffff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>

            <!-- sub bottom -->
            <a target="_blank" href="https://discord.defrag.racing" class="absolute rounded-full transition-all duration-[0.2s] ease-out scale-y-0 group-hover:scale-y-100 group-hover:-translate-x-16 flex p-2 hover:p-3 bg-indigo-600 scale-100 hover:bg-indigo-700 text-white cursor-pointer">
                <svg class="w-6 h-6" width="800px" height="800px" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M18.59 5.88997C17.36 5.31997 16.05 4.89997 14.67 4.65997C14.5 4.95997 14.3 5.36997 14.17 5.69997C12.71 5.47997 11.26 5.47997 9.83001 5.69997C9.69001 5.36997 9.49001 4.95997 9.32001 4.65997C7.94001 4.89997 6.63001 5.31997 5.40001 5.88997C2.92001 9.62997 2.25001 13.28 2.58001 16.87C4.23001 18.1 5.82001 18.84 7.39001 19.33C7.78001 18.8 8.12001 18.23 8.42001 17.64C7.85001 17.43 7.31001 17.16 6.80001 16.85C6.94001 16.75 7.07001 16.64 7.20001 16.54C10.33 18 13.72 18 16.81 16.54C16.94 16.65 17.07 16.75 17.21 16.85C16.7 17.16 16.15 17.42 15.59 17.64C15.89 18.23 16.23 18.8 16.62 19.33C18.19 18.84 19.79 18.1 21.43 16.87C21.82 12.7 20.76 9.08997 18.61 5.88997H18.59ZM8.84001 14.67C7.90001 14.67 7.13001 13.8 7.13001 12.73C7.13001 11.66 7.88001 10.79 8.84001 10.79C9.80001 10.79 10.56 11.66 10.55 12.73C10.55 13.79 9.80001 14.67 8.84001 14.67ZM15.15 14.67C14.21 14.67 13.44 13.8 13.44 12.73C13.44 11.66 14.19 10.79 15.15 10.79C16.11 10.79 16.87 11.66 16.86 12.73C16.86 13.79 16.11 14.67 15.15 14.67Z" fill="#ffffff"/>
                </svg>
            </a>

            <!-- sub top -->
            <a target="_blank" href="https://youtube.com/defraglegends" class="absolute rounded-full transition-all duration-[0.2s] ease-out scale-x-0 group-hover:scale-x-100 group-hover:-translate-y-16  flex  p-2 hover:p-3 bg-red-600 hover:bg-red-700 text-white">
                <svg class="w-6 h-6" width="800px" height="800px" viewBox="0 -3 20 20" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
                    <g id="Page-1" stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                        <g id="Dribbble-Light-Preview" transform="translate(-300.000000, -7442.000000)" fill="#ffffff">
                            <g id="icons" transform="translate(56.000000, 160.000000)">
                                <path d="M251.988432,7291.58588 L251.988432,7285.97425 C253.980638,7286.91168 255.523602,7287.8172 257.348463,7288.79353 C255.843351,7289.62824 253.980638,7290.56468 251.988432,7291.58588 M263.090998,7283.18289 C262.747343,7282.73013 262.161634,7282.37809 261.538073,7282.26141 C259.705243,7281.91336 248.270974,7281.91237 246.439141,7282.26141 C245.939097,7282.35515 245.493839,7282.58153 245.111335,7282.93357 C243.49964,7284.42947 244.004664,7292.45151 244.393145,7293.75096 C244.556505,7294.31342 244.767679,7294.71931 245.033639,7294.98558 C245.376298,7295.33761 245.845463,7295.57995 246.384355,7295.68865 C247.893451,7296.0008 255.668037,7296.17532 261.506198,7295.73552 C262.044094,7295.64178 262.520231,7295.39147 262.895762,7295.02447 C264.385932,7293.53455 264.28433,7285.06174 263.090998,7283.18289" />
                            </g>
                        </g>
                    </g>
                </svg>
            </a>
            <!-- sub middle -->
            <a target="_blank" href="https://twitch.tv/defraglive" class="absolute rounded-full transition-all duration-[0.2s] ease-out scale-x-0 group-hover:scale-x-100 group-hover:-translate-y-14 group-hover:-translate-x-14 flex p-2 hover:p-3 bg-purple-600 hover:bg-purple-700 text-white cursor-pointer">
                <svg class="w-6 h-6" width="800px" height="800px" viewBox="-0.5 0 20 20" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
                    <g id="Page-1" stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                        <g id="Dribbble-Light-Preview" transform="translate(-141.000000, -7399.000000)" fill="#ffffff">
                            <g id="icons" transform="translate(56.000000, 160.000000)">
                                <path d="M97,7249 L99,7249 L99,7244 L97,7244 L97,7249 Z M92,7249 L94,7249 L94,7244 L92,7244 L92,7249 Z M102,7250.307 L102,7241 L88,7241 L88,7253 L92,7253 L92,7255.953 L94.56,7253 L99.34,7253 L102,7250.307 Z M98.907,7256 L94.993,7256 L92.387,7259 L90,7259 L90,7256 L85,7256 L85,7242.48 L86.3,7239 L104,7239 L104,7251.173 L98.907,7256 Z" />
                            </g>
                        </g>
                    </g>
                </svg>
            </a>
        </div>
    </div>
</template>

<style>
    .main-background {
        background-color: #111827;
        background-repeat: no-repeat;
        background-attachment: fixed;
        background-position: center;
        background-size: cover;
        justify-content: center;
    }

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