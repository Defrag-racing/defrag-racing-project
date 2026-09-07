<script setup>
import { ref } from 'vue';
import { t } from '@/utils/i18n';
import FormSection from '@/Components/Laravel/FormSection.vue';
import InputError from '@/Components/Laravel/InputError.vue';
import InputLabel from '@/Components/Laravel/InputLabel.vue';
import PrimaryButton from '@/Components/Laravel/PrimaryButton.vue';
import SecondaryButton from '@/Components/Laravel/SecondaryButton.vue';
import TextInput from '@/Components/Laravel/TextInput.vue';
import MddHelpModal from '@/Pages/Profile/Partials/MddHelpModal.vue';

const props = defineProps({
    user: Object,
});

const profile_link = ref('');
const error = ref('');
const stage = ref(1);
const image = ref('');
const name = ref('');
const processing = ref(false);
const showHelp = ref(false);

const hasMdd = ref(! isNaN(parseInt(props.user.mdd_id)));
const mddId = ref(props.user.mdd_id);

const verifyMddProfile = () => {
    processing.value = true;

    axios.post(route('settings.mdd.generate'), {
        profile_link: profile_link.value
    }).then((response) => {
        if (response.data.success == false) {
            error.value = response.data.message
            return;
        }

        error.value = '';
        stage.value = 2;
        image.value = response.data?.image;
        name.value = response.data?.name;
    }).catch((e) => {
        error.value = e.response?.data?.errors?.profile_link[0]
    }).finally(() => {
        processing.value = false;
    });
};

const verifyImage = () => {
    processing.value = true;

    axios.post(route('settings.mdd.verify'), {
        profile_link: profile_link.value
    }).then((response) => {
        if (response.data.success == false) {
            error.value = t('Verification failed, are you sure you set your profile image to the provided image?');
            return;
        }

        error.value = '';

        hasMdd.value = true;
        mddId.value = response.data.mdd_id;
    }).catch((e) => {
        returnBack()
    }).finally(() => {
        processing.value = false;
    });
};

const returnBack = () => {
    error.value = ''
    stage.value = 1;
    image.value = '';
    name.value = '';
    profile_link.value = '';
}

const submitForm = () => {
    if (stage.value == 1) {
        verifyMddProfile();
    } else if (stage.value == 2) {
        verifyImage()
    }
}

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
}



</script>

<template>
    <div class="p-4">
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-blue-500/20 to-blue-600/20 border border-blue-500/30 flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 text-blue-400">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244" />
                    </svg>
                </div>
                <h2 class="text-sm font-bold text-white">{{ $t('mDd Profile') }}</h2>
            </div>
        </div>

        <form @submit.prevent="submitForm" class="space-y-3">
            <div v-if="! hasMdd">
                <div v-if="stage == 1">
                    <div class="text-sm font-medium text-white mb-2">{{ $t('Step 1 - Verify Profile') }}</div>
                    <InputLabel for="profile_link" :value="$t('mDd Profile Link')" />
                    <TextInput
                        id="profile_link"
                        v-model="profile_link"
                        type="text"
                        class="mt-1 block w-full"
                        placeholder="https://q3df.org/profil?id=12705"
                    />
                    <InputError :message="error" class="mt-1" />
                    <div @click="showHelp = true" class="text-gray-400 text-xs mt-1 flex items-center gap-1 cursor-pointer hover:text-gray-300">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 5.25h.008v.008H12v-.008Z" />
                        </svg>
                        <span>{{ $t('How to get the link?') }}</span>
                        <MddHelpModal :show="showHelp" :closeModal="() => showHelp = false" />
                    </div>
                </div>

                <div v-if="stage == 2">
                    <div class="text-sm font-medium text-white mb-2">{{ $t('Step 2 - Confirm Account') }}</div>

                    <div class="my-2 p-2 bg-grayop-700 rounded text-sm" v-html="q3tohtml(name)"></div>

                    <div class="text-xs text-gray-300 mb-2 [&_a]:text-blue-400 [&_a:hover]:text-blue-300"
                        v-html="$t('Update your <a href=https://q3df.org/profil/edit target=_blank>q3df.org</a> profile image:')"></div>

                    <div class="flex flex-col items-center justify-center my-2">
                        <img :src="image" class="max-w-[200px]" />
                        <div @click="downloadImage" class="mt-1 text-sm font-medium text-blue-400 hover:text-blue-300 cursor-pointer">{{ $t('Download') }}</div>
                    </div>

                    <InputError :message="error" class="my-1" />

                    <div class="text-gray-500 text-xs mt-1">
                        {{ $t('Don\'t modify the image before uploading') }}
                    </div>
                </div>
            </div>

            <div v-else>
                <div class="flex items-center justify-between p-3 rounded-lg bg-green-500/10 border border-green-500/20">
                    <div class="flex items-center gap-3">
                        <div class="rounded-full p-1.5 text-white bg-green-600">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
                            </svg>
                        </div>
                        <div>
                            <div class="text-sm font-medium text-white">{{ $t('mDd Connected') }}</div>
                            <a :href="'https://q3df.org/profil?id=' + mddId" target="_blank" class="text-xs text-blue-400 hover:text-blue-300">{{ $t('User #:id', { id: mddId }) }}</a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex justify-between items-center pt-2" v-if="! hasMdd">
                <SecondaryButton type="button" @click.prevent="returnBack" v-if="stage == 2">
                    {{ $t('Back') }}
                </SecondaryButton>
                <div v-else></div>

                <PrimaryButton :class="{ 'opacity-25': processing }" :disabled="processing">
                    <span class="mr-2" v-if="processing">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 animate-spin">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                        </svg>
                    </span>
                    {{ (stage === 1) ? $t('Start') : $t('Finalize') }}
                </PrimaryButton>
            </div>
        </form>
    </div>
</template>
