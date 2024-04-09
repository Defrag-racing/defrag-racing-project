<script setup>
    import { useForm } from '@inertiajs/vue3';
    import { ref } from 'vue';
    import SecondaryButton from '@/Components/Laravel/SecondaryButton.vue';
    import FormSection from '@/Components/Laravel/FormSection.vue';
    import InputError from '@/Components/Laravel/InputError.vue';
    import InputLabel from '@/Components/Laravel/InputLabel.vue';
    import PrimaryButton from '@/Components/Laravel/PrimaryButton.vue';
    import TextInput from '@/Components/Laravel/TextInput.vue';
    import ClassicEditor from '@ckeditor/ckeditor5-build-classic';
    import rulesData from '@/rules';

    const props = defineProps({
        tournament: Object,
        url: String
    });

    const form = useForm({
        _method: 'POST',
        name: props.tournament.name || '',
        description: props.tournament.description || '',
        photo: null,
        trailer: props.tournament.trailer || '',
        rules: props.tournament.rules || rulesData,
        has_teams: (props.tournament.has_teams == 1) ? true : false,
        has_donations: (props.tournament.has_donations == 1) ? true : false,
        prize_pool: props.tournament.prize_pool?.toString() || '0',
        donation_link: props.tournament.donation_link || '',
    });

    const editor = ClassicEditor;
    const editorConfig = {
        heading: {
            options: [
                { model: 'paragraph', title: 'Paragraph', class: 'ck-heading_paragraph' },
                { model: 'heading1', view: 'h1', title: 'Heading 1', class: 'ck-heading_heading1' },
                { model: 'heading2', view: 'h2', title: 'Heading 2', class: 'ck-heading_heading2' },
                { model: 'heading3', view: 'h3', title: 'Heading 3', class: 'ck-heading_heading3' },
                { model: 'heading4', view: 'h4', title: 'Heading 4', class: 'ck-heading_heading4' },
                { model: 'heading5', view: 'h5', title: 'Heading 5', class: 'ck-heading_heading5' },
                { model: 'heading6', view: 'h6', title: 'Heading 6', class: 'ck-heading_heading6' }
            ]
        }
    };

    const photoPreview = ref(null);
    const photoInput = ref(null);

    const tabs = ['basic', 'details', 'options'];
    const currentTab = ref(0);

    const finishBasicInformation = () => {
        const tab = tabs[currentTab.value];

        if (tab === 'basic') {
            if ( basicTabFinished() ) currentTab.value++;

            return;
        }

        if (tab === 'details') {
            if ( detailsTabFinished() ) currentTab.value++;

            return;
        }

        let submitUrl = null;

        if (props.tournament) {
            submitUrl = route(props.url, props.tournament.id);
        } else {
            submitUrl = route(props.url);
        }

        form.post(submitUrl, {
            errorBag: 'finishBasicInformation',
            preserveScroll: true,
            onError: () => {
                currentTab.value = 0;
            }
        });
    };

    const detailsTabFinished = () => {
        if (form.rules.length === 0) {
            form.errors.rules = 'The rules field is required.';
            return false;
        }

        form.errors.rules = '';

        return true;
    };

    const basicTabFinished = () => {
        if (form.name.length === 0) {
            form.errors.name = 'The name field is required.';
            return false;
        }

        form.errors.name = '';

        if (form.description.length === 0) {
            form.errors.description = 'The description field is required.';
            return false;
        }
        
        form.errors.description = '';

        const photo = photoInput?.value?.files[0];

        if ( ! photo && ! props.tournament?.image) {
            form.errors.photo = 'The image field is required.';
            return false;
        }
        
        form.errors.photo = '';

        form.photo = photo;

        return true;
    };

    const selectNewPhoto = () => {
        photoInput.value.click();
    };

    const updatePhotoPreview = () => {
        const photo = photoInput.value.files[0];

        if (! photo) {
            form.errors.photo = 'The image field is required.';
            return;
        };

        if (! photo.type.startsWith('image/')) {
            form.errors.photo = 'The file must be an Image.';
            photoInput.value = '';
            return;
        }

        const reader = new FileReader();
        form.errors.photo = '';

        reader.onload = (e) => {
            photoPreview.value = e.target.result;
        };

        reader.readAsDataURL(photo);

        form.photo = photo;
    };

    const previousTab = () => {
        if (currentTab.value > 0) currentTab.value--;
    }
</script>

<template>
    <div>
        <FormSection @submitted="finishBasicInformation">
            <template #title>
                <div v-if="tabs[currentTab] == 'basic'">Basic Information</div>
                <div v-if="tabs[currentTab] == 'details'">Tournament details</div>
                <div v-if="tabs[currentTab] == 'options'">Tournament Options</div>
            </template>
    
            <template #description>
                <div v-if="tabs[currentTab] == 'basic'">Basic information about your tournament.</div>
                <div v-if="tabs[currentTab] == 'details'">Tournament in-depth details and dates.</div>
                <div v-if="tabs[currentTab] == 'options'">Edit various options for the tournament.</div>
            </template>
    
            <template #form>
                <div class="col-span-6" v-if="tabs[currentTab] == 'basic'">
                    <div class="mb-3">
                        <InputLabel for="name" value="Name" />
                        <TextInput
                            id="name"
                            v-model="form.name"
                            type="text"
                            class="mt-1 block w-full"
                        />
                        <InputError :message="form.errors.name" class="mt-2" />
                    </div>

                    <div class="mb-3">
                        <InputLabel for="description" value="Description" />
                        <textarea
                            id="description"
                            v-model="form.description"
                            type="text"
                            class="mt-1 block w-full border-2 border-grayop-700 bg-grayop-900 text-gray-300 focus:border-blue-600 focus:ring-blue-600 rounded-md shadow-sm"
                        />
                        <InputError :message="form.errors.description" class="mt-2" />
                    </div>

                    <div class="mb-3">
                        <input
                            id="photo"
                            ref="photoInput"
                            type="file"
                            class="hidden"
                            accept="image/*"
                            @change="updatePhotoPreview"
                        >
        
                        <InputLabel for="photo" value="Tournament Image" />

                        <div v-if="photoPreview" class="mt-2">
                            <span
                                class="block rounded-lg bg-cover bg-no-repeat bg-center"
                                :style="'background-image: url(\'' + photoPreview + '\'); width: 100%; height: 400px;'"
                            />
                        </div>

                        <div v-if="!photoPreview && tournament?.image" class="mt-2">
                            <span
                                class="block rounded-lg bg-cover bg-no-repeat bg-center"
                                :style="'background-image: url(\'/storage/' + tournament.image + '\'); width: 100%; height: 200px;'"
                            />
                        </div>
        
                        <SecondaryButton class="mt-2 me-2" type="button" @click.prevent="selectNewPhoto">
                            Select A New Photo
                        </SecondaryButton>
                        <InputError :message="form.errors.photo" class="mt-2" />
                    </div>

                    <div class="mb-3">
                        <InputLabel for="trailer" value="Trailer (optional)" />
                        <TextInput
                            id="trailer"
                            v-model="form.trailer"
                            type="text"
                            class="mt-1 block w-full"
                        />
                        <InputError :message="form.errors.trailer" class="mt-2" />
                    </div>
                </div>

                <div class="col-span-6" v-if="tabs[currentTab] == 'details'">
                    <div class="mb-3">
                        <InputLabel for="rules" value="Rules" />
                        <ckeditor
                            :editor="editor"
                            :config="editorConfig"
                            id="rules"
                            v-model="form.rules"
                            type="text"
                            class="mt-1 block w-full border-2 border-grayop-700 bg-grayop-900 text-gray-300 focus:border-blue-600 focus:ring-blue-600 rounded-md shadow-sm"
                        />
                        <InputError :message="form.errors.rules" class="mt-2" />
                    </div>
                </div>

                <div class="col-span-6" v-if="tabs[currentTab] == 'options'">
                    <div class="mb-3">
                        <div class="flex">
                            <input
                                id="has_teams"
                                v-model="form.has_teams"
                                type="checkbox"
                                class="mt-1 rounded-md mr-2"
                            />

                            <p class="text-white">Teams support</p>
                        </div>
                        <p class="text-sm text-gray-500">
                            Players can create a team of two players (1 vq3, 1 cpm) and the teams results are independent of normal tournament result. (Similar to DFWC teams).
                        </p>
                        <InputError :message="form.errors.has_teams" class="mt-2" />
                    </div>

                    <div class="mb-3">
                        <div class="flex">
                            <input
                                id="has_donations"
                                v-model="form.has_donations"
                                type="checkbox"
                                class="mt-1 rounded-md mr-2"
                            />

                            <p class="text-white">Donations support</p>
                        </div>
                        <p class="text-sm text-gray-500">
                            Does this tournament support player donation ?
                        </p>
                        <InputError :message="form.errors.has_donations" class="mt-2" />
                    </div>

                    <div class="mb-3" v-if="form.has_donations">
                        <InputLabel for="donation_link" value="Donation Link" />
                        <TextInput
                            id="donation_link"
                            v-model="form.donation_link"
                            type="text"
                            class="mt-1 block w-full"
                        />
                        <InputError :message="form.errors.donation_link" class="mt-2" />
                    </div>

                    <div class="mb-3">
                        <InputLabel for="prize_pool" value="Prize Pool" />
                        <TextInput
                            id="prize_pool"
                            v-model="form.prize_pool"
                            type="number"
                            class="mt-1 block w-full"
                        />
                        <p class="text-sm text-gray-500">
                            Leave at 0 if there is no prize for this tournament.
                        </p>
                        <InputError :message="form.errors.prize_pool" class="mt-2" />
                    </div>
                </div>
            </template>
    
            <template #actions>
                <div class="flex justify-between w-full">
                    <SecondaryButton v-if="currentTab > 0" @click="previousTab">
                        Back
                    </SecondaryButton>

                    <PrimaryButton>
                        <span v-if="tabs[currentTab] === 'options'">Submit</span>
                        <span v-else>Next</span>
                    </PrimaryButton>
                </div>
            </template>
        </FormSection>
    </div>
</template>
