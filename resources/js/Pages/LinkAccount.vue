<script>
    import MainLayout from '@/Layouts/MainLayout.vue'

    export default {
        layout: MainLayout,
    }
</script>

<script setup>
    import { Head, Link, usePage } from '@inertiajs/vue3';
    import { ref } from 'vue';
    import { t } from '@/utils/i18n';

    const props = defineProps({
        user: Object,
    });

    const page = usePage();

    const profile_link = ref('');
    const error = ref('');
    const stage = ref(1);
    const image = ref('');
    const name = ref('');
    const processing = ref(false);
    const showHelp = ref(false);
    const linked = ref(false);
    const mddId = ref(null);

    const generate = () => {
        processing.value = true;
        error.value = '';

        axios.post(route('settings.mdd.generate'), {
            profile_link: profile_link.value
        }).then((response) => {
            if (response.data.success == false) {
                error.value = response.data.message;
                return;
            }
            stage.value = 2;
            image.value = response.data?.image;
            name.value = response.data?.name;
        }).catch((e) => {
            error.value = e.response?.data?.errors?.profile_link?.[0] || t('Something went wrong');
        }).finally(() => {
            processing.value = false;
        });
    };

    const verify = () => {
        processing.value = true;
        error.value = '';

        axios.post(route('settings.mdd.verify'), {
            profile_link: profile_link.value
        }).then((response) => {
            if (response.data.success == false) {
                error.value = t('Verification failed. Make sure you set your profile image to the provided image.');
                return;
            }
            linked.value = true;
            mddId.value = response.data.mdd_id;
        }).catch(() => {
            goBack();
        }).finally(() => {
            processing.value = false;
        });
    };

    const goBack = () => {
        error.value = '';
        stage.value = 1;
        image.value = '';
        name.value = '';
        profile_link.value = '';
    };

    const submit = () => {
        if (stage.value === 1) generate();
        else if (stage.value === 2) verify();
    };

    const downloadImage = () => {
        const byteCharacters = atob(image.value.split(',')[1]);
        const byteNumbers = new Array(byteCharacters.length);
        for (let i = 0; i < byteCharacters.length; i++) {
            byteNumbers[i] = byteCharacters.charCodeAt(i);
        }
        const byteArray = new Uint8Array(byteNumbers);
        const blob = new Blob([byteArray], { type: 'image/png' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = 'profile.png';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    };
</script>

<template>
    <div class="">
        <Head :title="$t('Link Your Account')" />

        <div class="relative bg-gradient-to-b from-black/25 via-black/10 to-transparent pt-6 pb-12">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="text-center mb-8">
                <h1 class="text-2xl md:text-3xl font-black text-white mb-3">{{ $t('Link Your Q3DF Profile') }}</h1>
                <p class="text-lg text-gray-400 max-w-2xl mx-auto">
                    {{ $t('defrag.racing is built on top of the mDd/Q3DF database. Link your profile to unlock the full experience.') }}
                </p>
            </div>

            <!-- Two Column Layout: Wizard left, Benefits right -->
            <div class="grid grid-cols-1 lg:grid-cols-5 gap-6 mb-6">
                <!-- Wizard (left) -->
                <div class="lg:col-span-2">
                    <div class="bg-black/40 rounded-2xl border border-white/10 shadow-2xl overflow-hidden sticky top-24">
                        <div class="bg-gradient-to-r from-blue-600/20 to-purple-600/20 border-b border-white/10 px-6 py-4">
                            <h2 class="text-lg font-bold text-white">{{ $t('Connect Your mDd Profile') }}</h2>
                            <p class="text-sm text-gray-400 mt-1">{{ $t('Verify ownership by updating your Q3DF profile image.') }}</p>
                        </div>

                        <!-- Success State -->
                        <div v-if="linked" class="p-6">
                            <div class="text-center">
                                <div class="w-16 h-16 rounded-full bg-green-600 flex items-center justify-center mx-auto mb-4">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor" class="w-8 h-8 text-white">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                    </svg>
                                </div>
                                <h3 class="text-xl font-bold text-green-400 mb-2">{{ $t('Profile Linked!') }}</h3>
                                <p class="text-gray-400 text-sm mb-4">{{ $t('Your account is now connected to mDd user #:id.', { id: mddId }) }}</p>
                                <Link :href="route('profile.index', { userId: user.id })" class="inline-block px-6 py-2 bg-blue-600 hover:bg-blue-500 text-white font-bold rounded-lg transition-colors">
                                    {{ $t('Go to Profile') }}
                                </Link>
                            </div>
                        </div>

                        <!-- Step 1: Enter Profile Link -->
                        <form v-else @submit.prevent="submit" class="p-6 space-y-4">
                            <div v-if="stage === 1">
                                <label class="block text-sm font-medium text-white mb-2">{{ $t('mDd Profile Link') }}</label>
                                <input
                                    v-model="profile_link"
                                    type="text"
                                    class="w-full px-3 py-2 bg-black/40 border border-white/20 rounded-lg text-white text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none transition-colors"
                                    placeholder="https://q3df.org/profil?id=2640"
                                />
                                <p v-if="error" class="text-red-400 text-xs mt-1">{{ error }}</p>

                                <!-- Inline Help -->
                                <div class="mt-3 p-3 bg-blue-500/10 border border-blue-500/20 rounded-lg text-sm text-gray-300 space-y-1.5">
                                    <p class="font-medium text-gray-200">{{ $t('How to find your profile link:') }}</p>
                                    <p class="[&_a]:text-blue-400 [&_a:hover]:text-blue-300"
                                       v-html="$t('1. Open <a href=https://q3df.org/profil target=_blank>q3df.org/profil</a> (you must be logged in)')"></p>
                                    <p class="[&_span]:text-white [&_span]:font-semibold"
                                       v-html="$t('2. Click <span>Show all</span> below your avatar')"></p>
                                    <p>{{ $t('3. Copy the URL from your browser') }}</p>
                                    <p class="text-orange-400 pl-3">{{ $t('e.g. https://q3df.org/profil?id=2640') }}</p>
                                </div>
                            </div>

                            <!-- Step 2: Verify with Image -->
                            <div v-if="stage === 2" class="space-y-4">
                                <div class="p-2 bg-white/5 rounded-lg text-sm" v-html="q3tohtml(name)"></div>

                                <!-- Step A: Download -->
                                <div class="flex items-start gap-3 p-3 bg-yellow-500/10 border border-yellow-500/20 rounded-lg">
                                    <div class="w-7 h-7 rounded-full bg-yellow-500 text-black font-black text-sm flex items-center justify-center shrink-0">1</div>
                                    <div class="flex-1">
                                        <p class="text-sm font-bold text-white mb-2">{{ $t('Download this image') }}</p>
                                        <div class="flex flex-col items-center">
                                            <img :src="image" class="max-w-[150px] rounded-lg border border-white/10" />
                                            <button type="button" @click="downloadImage" class="mt-2 px-4 py-2 bg-yellow-500 hover:bg-yellow-400 text-black text-sm font-bold rounded-lg transition-colors">
                                                {{ $t('Download Image') }}
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Step B: Upload to Q3DF -->
                                <div class="flex items-start gap-3 p-3 bg-blue-500/10 border border-blue-500/20 rounded-lg">
                                    <div class="w-7 h-7 rounded-full bg-blue-500 text-white font-black text-sm flex items-center justify-center shrink-0">2</div>
                                    <div class="flex-1">
                                        <p class="text-sm font-bold text-white mb-1">{{ $t('Upload it as your Q3DF profile image') }}</p>
                                        <p class="text-xs text-gray-400 mb-2">{{ $t('Don\'t modify the image. Upload it exactly as downloaded.') }}</p>
                                        <a href="https://q3df.org/profil/edit" target="_blank" class="inline-flex items-center gap-1 px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white text-sm font-bold rounded-lg transition-colors">
                                            {{ $t('Open Q3DF Profile Editor') }}
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                                            </svg>
                                        </a>
                                    </div>
                                </div>

                                <!-- Step C: Come back and finalize -->
                                <div class="flex items-start gap-3 p-3 bg-green-500/10 border border-green-500/20 rounded-lg">
                                    <div class="w-7 h-7 rounded-full bg-green-500 text-white font-black text-sm flex items-center justify-center shrink-0">3</div>
                                    <div class="flex-1">
                                        <p class="text-sm font-bold text-white">{{ $t('Come back here and click Finalize') }}</p>
                                        <p class="text-xs text-gray-400">{{ $t('I\'ll verify the image matches and link your account.') }}</p>
                                    </div>
                                </div>

                                <p v-if="error" class="text-red-400 text-sm font-medium">{{ error }}</p>
                            </div>

                            <!-- Buttons -->
                            <div class="flex justify-between items-center pt-2">
                                <button v-if="stage === 2" type="button" @click="goBack" class="px-4 py-2 text-sm font-medium text-gray-400 hover:text-white border border-white/20 rounded-lg hover:border-white/40 transition-colors">
                                    {{ $t('Back') }}
                                </button>
                                <div v-else></div>

                                <button type="submit" :disabled="processing" :class="['px-5 py-2 text-sm font-bold rounded-lg transition-colors', processing ? 'bg-blue-600/50 text-white/50 cursor-not-allowed' : 'bg-blue-600 hover:bg-blue-500 text-white']">
                                    <span v-if="processing" class="flex items-center gap-2">
                                        <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                                        </svg>
                                        {{ $t('Processing...') }}
                                    </span>
                                    <span v-else>{{ stage === 1 ? $t('Start') : $t('Finalize') }}</span>
                                </button>
                            </div>
                        </form>

                        <div class="px-6 py-4 border-t border-white/5 text-center">
                            <p class="text-sm text-gray-400 [&_a]:text-blue-400 [&_a:hover]:text-blue-300 [&_a]:font-medium"
                               v-html="$t('Having trouble? <a href=https://discord.defrag.racing target=_blank>Contact me on Discord</a> and I\'ll help you out.')"></p>
                        </div>
                    </div>
                </div>

                <!-- Benefits (right) -->
                <div class="lg:col-span-3 grid grid-cols-1 sm:grid-cols-2 gap-3 content-start">
                    <div class="bg-black/40 rounded-xl p-4 border border-white/10 flex items-start gap-3">
                        <div class="p-2 bg-blue-500/20 rounded-lg shrink-0">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5 text-blue-400">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 18.75h-9m9 0a3 3 0 0 1 3 3h-15a3 3 0 0 1 3-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.007 0H9.497m5.007 0a7.454 7.454 0 0 1-.982-3.172M9.497 14.25a7.454 7.454 0 0 0 .981-3.172M5.25 4.236c-.982.143-1.954.317-2.916.52A6.003 6.003 0 0 0 7.73 9.728M5.25 4.236V4.5c0 2.108.966 3.99 2.48 5.228M5.25 4.236V2.721C7.456 2.41 9.71 2.25 12 2.25c2.291 0 4.545.16 6.75.47v1.516M18.75 4.236c.982.143 1.954.317 2.916.52A6.003 6.003 0 0 1 16.27 9.728M18.75 4.236V4.5c0 2.108-.966 3.99-2.48 5.228m4.322-6.997C18.776 2.41 16.522 2.25 14.231 2.25" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-white font-bold text-sm mb-0.5">{{ $t('Records & Rankings') }}</h3>
                            <p class="text-gray-400 text-xs">{{ $t('Personal records, world records, and global rankings.') }}</p>
                        </div>
                    </div>

                    <div class="bg-black/40 rounded-xl p-4 border border-white/10 flex items-start gap-3">
                        <div class="p-2 bg-green-500/20 rounded-lg shrink-0">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5 text-green-400">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-white font-bold text-sm mb-0.5">{{ $t('Detailed Statistics') }}</h3>
                            <p class="text-gray-400 text-xs">{{ $t('Dominance scores, rivals, competitors, and progress.') }}</p>
                        </div>
                    </div>

                    <div class="bg-black/40 rounded-xl p-4 border border-white/10 flex items-start gap-3">
                        <div class="p-2 bg-purple-500/20 rounded-lg shrink-0">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5 text-purple-400">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m15.75 10.5 4.72-4.72a.75.75 0 0 1 1.28.53v11.38a.75.75 0 0 1-1.28.53l-4.72-4.72M4.5 18.75h9a2.25 2.25 0 0 0 2.25-2.25v-9a2.25 2.25 0 0 0-2.25-2.25h-9A2.25 2.25 0 0 0 2.25 7.5v9a2.25 2.25 0 0 0 2.25 2.25Z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-white font-bold text-sm mb-0.5">{{ $t('Demo Matching') }}</h3>
                            <p class="text-gray-400 text-xs">{{ $t('Demos automatically matched to your records.') }}</p>
                        </div>
                    </div>

                    <div class="bg-black/40 rounded-xl p-4 border border-white/10 flex items-start gap-3">
                        <div class="p-2 bg-orange-500/20 rounded-lg shrink-0">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5 text-orange-400">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-white font-bold text-sm mb-0.5">{{ $t('Record Notifications') }}</h3>
                            <p class="text-gray-400 text-xs">{{ $t('Get notified when someone beats your records.') }}</p>
                        </div>
                    </div>

                    <div class="bg-black/40 rounded-xl p-4 border border-white/10 flex items-start gap-3">
                        <div class="p-2 bg-red-500/20 rounded-lg shrink-0">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5 text-red-400">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.53 16.122a3 3 0 0 0-5.78 1.128 2.25 2.25 0 0 1-2.4 2.245 4.5 4.5 0 0 0 8.4-2.245c0-.399-.078-.78-.22-1.128Zm0 0a15.998 15.998 0 0 0 3.388-1.62m-5.043-.025a15.994 15.994 0 0 1 1.622-3.395m3.42 3.42a15.995 15.995 0 0 0 4.764-4.648l3.876-5.814a1.151 1.151 0 0 0-1.597-1.597L14.146 6.32a15.996 15.996 0 0 0-4.649 4.763m3.42 3.42a6.776 6.776 0 0 0-3.42-3.42" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-white font-bold text-sm mb-0.5">{{ $t('Profile Customization') }}</h3>
                            <p class="text-gray-400 text-xs">{{ $t('Colored nickname, custom background, and effects.') }}</p>
                        </div>
                    </div>

                    <div class="bg-black/40 rounded-xl p-4 border border-white/10 flex items-start gap-3">
                        <div class="p-2 bg-cyan-500/20 rounded-lg shrink-0">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5 text-cyan-400">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0ZM3.75 12h.007v.008H3.75V12Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm-.375 5.25h.007v.008H3.75v-.008Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-white font-bold text-sm mb-0.5">{{ $t('Maplists & Tracking') }}</h3>
                            <p class="text-gray-400 text-xs">{{ $t('Personal maplists and unplayed maps tracking.') }}</p>
                        </div>
                    </div>

                    <div class="bg-black/40 rounded-xl p-4 border border-white/10 flex items-start gap-3">
                        <div class="p-2 bg-yellow-500/20 rounded-lg shrink-0">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5 text-yellow-400">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-white font-bold text-sm mb-0.5">{{ $t('Demo Uploads') }}</h3>
                            <p class="text-gray-400 text-xs">{{ $t('Share your runs and attach demos to records.') }}</p>
                        </div>
                    </div>

                    <div class="bg-black/40 rounded-xl p-4 border border-white/10 flex items-start gap-3">
                        <div class="p-2 bg-teal-500/20 rounded-lg shrink-0">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5 text-teal-400">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3Z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6Z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-white font-bold text-sm mb-0.5">{{ $t('Map Tagging') }}</h3>
                            <p class="text-gray-400 text-xs">{{ $t('Tag maps with categories and help organize the database.') }}</p>
                        </div>
                    </div>

                    <div class="bg-black/40 rounded-xl p-4 border border-white/10 flex items-start gap-3 sm:col-span-2">
                        <div class="p-2 bg-pink-500/20 rounded-lg shrink-0">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5 text-pink-400">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.362 5.214A8.252 8.252 0 0 1 12 21 8.25 8.25 0 0 1 6.038 7.047 8.287 8.287 0 0 0 9 9.601a8.983 8.983 0 0 1 3.361-6.867 8.21 8.21 0 0 0 3 2.48Z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 18a3.75 3.75 0 0 0 .495-7.468 5.99 5.99 0 0 0-1.925 3.547 5.975 5.975 0 0 1-2.133-1.001A3.75 3.75 0 0 0 12 18Z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-white font-bold text-sm mb-0.5">{{ $t('Tournaments') }}</h3>
                            <p class="text-gray-400 text-xs">{{ $t('Compete in community tournaments and special leaderboards.') }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Skip -->
            <div class="text-center mt-4">
                <Link href="/" class="text-sm text-gray-500 hover:text-gray-400 transition-colors">
                    {{ $t('Skip for now') }}
                </Link>
            </div>
        </div>
        </div>
    </div>
</template>
