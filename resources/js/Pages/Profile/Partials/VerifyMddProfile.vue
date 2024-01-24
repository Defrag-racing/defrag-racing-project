<script setup>
import { ref } from 'vue';
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
            error.value = 'Verification failed, are you sure you set your profile image to the provided image ?';
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
    <FormSection @submitted="submitForm">
        <template #title>
            Mdd Profile
        </template>

        <template #description>
            Connect your MDD Profile to defrag racing.
        </template>

        <template #form>
            <div class="col-span-6 sm:col-span-4" v-if="! hasMdd">
                <div v-if="stage == 1">
                    <div class="text-xl font-bold text-white mb-4">Step 1 - Verifying the profile</div>
                    <InputLabel for="profile_link" value="To verify your MDD profile, please provide the full link to your MDD profile" />
                    <TextInput
                        id="profile_link"
                        v-model="profile_link"
                        type="text"
                        class="my-3 block w-full"
                        placeholder="Example: https://q3df.org/profil?id=12705"
                    />
                    <InputError :message="error" class="mt-2" />
                    <div @click="showHelp = true" class="text-gray-500 text-sm mt-1 flex cursor-pointer hover:text-gray-400">
                        <span>
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 5.25h.008v.008H12v-.008Z" />
                            </svg>
                        </span>
                        <span class="ml-1">How to get the link ?</span>
                        <MddHelpModal :show="showHelp" :closeModal="() => showHelp = false" />
                    </div>
                </div>
    
                <div v-if="stage == 2">
                    <div class="text-xl font-bold text-white mb-4">Step 2 - Confirming it's your account</div>
                    
                    <div class="my-4 p-3 bg-grayop-700 rounded-md" v-html="q3tohtml(name)"></div>
    
                    <div class="text-gray-300">
                        Please update your <a target="_blank" href="https://q3df.org/profil/edit" class="text-blue-400 hover:text-blue-300">q3df.org</a> profile image to the following image. Then click Finalize
                    </div>
                    
                    <div class="flex flex-col items-center justify-center mt-4 mb-4">
                        <img :src="image" />
                        <div @click="downloadImage" class="mt-1 text-lg font-bold text-blue-400 hover:text-blue-300 cursor-pointer">Download</div>
                    </div>
    
                    <InputError :message="error" class="my-2" />
    
                    <div class="text-gray-500 text-sm mt-1">
                        Please do not modify the image before updating your q3df profile.
                    </div>
                </div>
            </div>

            <div class="col-span-6" v-else>
                <div class="text-2xl font-bold text-blue-400">MDD (q3df.org)</div>
                <div class="flex items-center justify-between">
                    <div class="text-md text-gray-300">
                        <span>Your account is successfully connected with </span>
                        <a :href="'https://q3df.org/profil?id=' + mddId" target="_blank" class="font-bold text-blue-400 hover:text-blue-300 cursor-pointer">MDD User #{{mddId}}</a>
                    </div>

                    <div>
                        <div class="rounded-full p-2 text-white bg-green-700">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-8 h-8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
                            </svg>
                        </div>
                    </div>

                </div>
            </div>
        </template>

        <template #actions>
            <div class="flex justify-between items-center w-full" v-if="! hasMdd">
                <SecondaryButton class="mt-2 me-2" type="button" @click.prevent="returnBack" v-if="stage == 2">
                    Back
                </SecondaryButton>
                <div></div>
    
                <PrimaryButton :class="{ 'opacity-25': processing }" :disabled="processing">
                    <span class="mr-3" v-if="processing">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 animate-spin">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                        </svg>
                    </span>
                    {{ (stage === 1) ? 'Start The Process' : 'Finalize' }}
                </PrimaryButton>
            </div>
        </template>
    </FormSection>
</template>
