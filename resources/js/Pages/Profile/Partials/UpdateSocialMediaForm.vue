<script setup>
    import { useForm, router } from '@inertiajs/vue3';
    import ActionMessage from '@/Components/Laravel/ActionMessage.vue';
    import FormSection from '@/Components/Laravel/FormSection.vue';
    import InputError from '@/Components/Laravel/InputError.vue';
    import InputLabel from '@/Components/Laravel/InputLabel.vue';
    import PrimaryButton from '@/Components/Laravel/PrimaryButton.vue';
    import SecondaryButton from '@/Components/Laravel/SecondaryButton.vue';
    import TextInput from '@/Components/Laravel/TextInput.vue';
    import { t } from '@/utils/i18n';

    const props = defineProps({
        user: Object,
    });

    const form = useForm({
        _method: 'POST',
        twitter_name: props.user.twitter_name,
        twitch_name: props.user.twitch_name,
        discord_name: props.user.discord_name
    });

    const updateSocialMediaInformation = () => {
        form.post(route('settings.socialmedia'), {
            preserveScroll: true
        });
    };

    const disconnectDiscord = () => {
        if (confirm(t('Are you sure you want to disconnect your Discord account?'))) {
            router.post(route('oauth.discord.disconnect'), {}, {
                preserveScroll: true
            });
        }
    };

    const disconnectTwitch = () => {
        if (confirm(t('Are you sure you want to disconnect your Twitch account?'))) {
            router.post(route('oauth.twitch.disconnect'), {}, {
                preserveScroll: true
            });
        }
    };

    const disconnectSteam = () => {
        if (confirm(t('Are you sure you want to disconnect your Steam account?'))) {
            router.post(route('oauth.steam.disconnect'), {}, {
                preserveScroll: true
            });
        }
    };

    const disconnectTwitter = () => {
        if (confirm(t('Are you sure you want to disconnect your Twitter/X account?'))) {
            router.post(route('oauth.twitter.disconnect'), {}, {
                preserveScroll: true
            });
        }
    };
</script>

<template>
    <div class="p-4">
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-purple-500/20 to-purple-600/20 border border-purple-500/30 flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 text-purple-400">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M7.217 10.907a2.25 2.25 0 1 0 0 2.186m0-2.186c.18.324.283.696.283 1.093s-.103.77-.283 1.093m0-2.186 9.566-5.314m-9.566 7.5 9.566 5.314m0 0a2.25 2.25 0 1 0 3.935 2.186 2.25 2.25 0 0 0-3.935-2.186Zm0-12.814a2.25 2.25 0 1 0 3.933-2.185 2.25 2.25 0 0 0-3.933 2.185Z" />
                    </svg>
                </div>
                <h2 class="text-sm font-bold text-white">{{ $t('Connections') }}</h2>
            </div>
            <div v-if="form.recentlySuccessful" class="flex items-center gap-1.5 px-2 py-1 rounded bg-green-500/10 border border-green-500/20">
                <svg class="w-3 h-3 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                <span class="text-xs font-medium text-green-400">{{ $t('Saved') }}</span>
            </div>
        </div>

        <form @submit.prevent="updateSocialMediaInformation" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
            <div>
                <InputLabel for="twitch" value="Twitch" />
                <div v-if="user.twitch_id" class="mt-1 flex items-center gap-2">
                    <div class="flex-1 px-3 py-2 bg-green-500/10 border border-green-500/30 rounded-md text-green-400 text-sm">
                        ✓ {{ $t('Connected as :name', { name: user.twitch_name }) }}
                    </div>
                    <button type="button" @click="disconnectTwitch" class="px-3 py-2 bg-red-600 hover:bg-red-500 text-white text-sm font-semibold rounded-md transition">
                        {{ $t('Disconnect') }}
                    </button>
                </div>
                <div v-else class="mt-1">
                    <a :href="route('oauth.twitch')" class="inline-flex items-center gap-2 px-4 py-2 bg-purple-600 hover:bg-purple-500 text-white text-sm font-semibold rounded-md transition">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M11.571 4.714h1.715v5.143H11.57zm4.715 0H18v5.143h-1.714zM6 0L1.714 4.286v15.428h5.143V24l4.286-4.286h3.428L22.286 12V0zm14.571 11.143l-3.428 3.428h-3.429l-3 3v-3H6.857V1.714h13.714Z"/>
                        </svg>
                        {{ $t('Connect Twitch') }}
                    </a>
                </div>
                <InputError :message="form.errors.twitch_name" class="mt-1" />
            </div>

            <div>
                <InputLabel for="discord" value="Discord" />
                <div v-if="user.discord_id && user.discord_id.length > 0" class="mt-1 flex items-center gap-2">
                    <div class="flex-1 px-3 py-2 bg-green-500/10 border border-green-500/30 rounded-md text-green-400 text-sm">
                        ✓ {{ $t('Connected as :name', { name: user.discord_name }) }}
                    </div>
                    <button type="button" @click="disconnectDiscord" class="px-3 py-2 bg-red-600 hover:bg-red-500 text-white text-sm font-semibold rounded-md transition">
                        {{ $t('Disconnect') }}
                    </button>
                </div>
                <div v-else class="mt-1">
                    <a :href="route('oauth.discord')" class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-semibold rounded-md transition">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M20.317 4.37a19.791 19.791 0 0 0-4.885-1.515.074.074 0 0 0-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 0 0-5.487 0 12.64 12.64 0 0 0-.617-1.25.077.077 0 0 0-.079-.037A19.736 19.736 0 0 0 3.677 4.37a.07.07 0 0 0-.032.027C.533 9.046-.32 13.58.099 18.057a.082.082 0 0 0 .031.057 19.9 19.9 0 0 0 5.993 3.03.078.078 0 0 0 .084-.028c.462-.63.874-1.295 1.226-1.994a.076.076 0 0 0-.041-.106 13.107 13.107 0 0 1-1.872-.892.077.077 0 0 1-.008-.128 10.2 10.2 0 0 0 .372-.292.074.074 0 0 1 .077-.01c3.928 1.793 8.18 1.793 12.062 0a.074.074 0 0 1 .078.01c.12.098.246.198.373.292a.077.077 0 0 1-.006.127 12.299 12.299 0 0 1-1.873.892.077.077 0 0 0-.041.107c.36.698.772 1.362 1.225 1.993a.076.076 0 0 0 .084.028 19.839 19.839 0 0 0 6.002-3.03.077.077 0 0 0 .032-.054c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 0 0-.031-.03zM8.02 15.33c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.956-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.956 2.418-2.157 2.418zm7.975 0c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.955-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.946 2.418-2.157 2.418z"/>
                        </svg>
                        {{ $t('Connect Discord') }}
                    </a>
                </div>
                <InputError :message="form.errors.discord_name" class="mt-1" />
            </div>

            <div>
                <InputLabel for="steam" value="Steam" />
                <div v-if="user.steam_id" class="mt-1 flex items-center gap-2">
                    <div class="flex-1 px-3 py-2 bg-green-500/10 border border-green-500/30 rounded-md text-green-400 text-sm">
                        ✓ {{ $t('Connected as :name', { name: user.steam_name }) }}
                    </div>
                    <button type="button" @click="disconnectSteam" class="px-3 py-2 bg-red-600 hover:bg-red-500 text-white text-sm font-semibold rounded-md transition">
                        {{ $t('Disconnect') }}
                    </button>
                </div>
                <div v-else class="mt-1">
                    <a :href="route('oauth.steam')" class="inline-flex items-center gap-2 px-4 py-2 bg-gray-800 hover:bg-gray-700 text-white text-sm font-semibold rounded-md transition border border-gray-600">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M11.979 0C5.678 0 .511 4.86.022 11.037l6.432 2.658c.545-.371 1.203-.59 1.912-.59.063 0 .125.004.188.006l2.861-4.142V8.91c0-2.495 2.028-4.524 4.524-4.524 2.494 0 4.524 2.031 4.524 4.527s-2.03 4.525-4.524 4.525h-.105l-4.076 2.911c0 .052.004.105.004.159 0 1.875-1.515 3.396-3.39 3.396-1.635 0-3.016-1.173-3.331-2.727L.436 15.27C1.862 20.307 6.486 24 11.979 24c6.627 0 11.999-5.373 11.999-12S18.605 0 11.979 0zM7.54 18.21l-1.473-.61c.262.543.714.999 1.314 1.25 1.297.539 2.793-.076 3.332-1.375.263-.63.264-1.319.005-1.949s-.75-1.121-1.377-1.383c-.624-.26-1.29-.249-1.878-.03l1.523.63c.956.4 1.409 1.5 1.009 2.455-.397.957-1.497 1.41-2.454 1.012H7.54zm11.415-9.303c0-1.662-1.353-3.015-3.015-3.015-1.665 0-3.015 1.353-3.015 3.015 0 1.665 1.35 3.015 3.015 3.015 1.663 0 3.015-1.35 3.015-3.015zm-5.273-.005c0-1.252 1.013-2.266 2.265-2.266 1.249 0 2.266 1.014 2.266 2.266 0 1.251-1.017 2.265-2.266 2.265-1.253 0-2.265-1.014-2.265-2.265z"/>
                        </svg>
                        {{ $t('Connect Steam') }}
                    </a>
                </div>
            </div>

            <div>
                <InputLabel for="twitter" value="Twitter / X" />
                <div v-if="user.twitter_id" class="mt-1 flex items-center gap-2">
                    <div class="flex-1 px-3 py-2 bg-green-500/10 border border-green-500/30 rounded-md text-green-400 text-sm">
                        ✓ {{ $t('Connected as :name', { name: user.twitter_name }) }}
                    </div>
                    <button type="button" @click="disconnectTwitter" class="px-3 py-2 bg-red-600 hover:bg-red-500 text-white text-sm font-semibold rounded-md transition">
                        {{ $t('Disconnect') }}
                    </button>
                </div>
                <div v-else class="mt-1">
                    <a :href="route('oauth.twitter')" class="inline-flex items-center gap-2 px-4 py-2 bg-gray-900 hover:bg-gray-800 text-white text-sm font-semibold rounded-md transition border border-gray-600">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
                        </svg>
                        {{ $t('Connect X') }}
                    </a>
                </div>
            </div>

            <div>
                <InputLabel :value="$t('mDd Profile')" />
                <div v-if="user.mdd_id && !isNaN(parseInt(user.mdd_id))" class="mt-1 flex items-center gap-2">
                    <div class="flex-1 px-3 py-2 bg-green-500/10 border border-green-500/30 rounded-md text-green-400 text-sm [&_a]:text-blue-400 [&_a:hover]:text-blue-300"
                        v-html="'✓ ' + $t('Connected as <a href=:url target=_blank>User #:id</a>', { url: 'https://q3df.org/profil?id=' + parseInt(user.mdd_id), id: parseInt(user.mdd_id) })"></div>
                </div>
                <div v-else class="mt-1">
                    <a href="/link-account" class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white text-sm font-semibold rounded-md transition">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244" />
                        </svg>
                        {{ $t('Link mDd Account') }}
                    </a>
                </div>
            </div>

        </form>
    </div>
</template>
