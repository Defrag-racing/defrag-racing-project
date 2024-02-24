<script setup>
    import { Head } from '@inertiajs/vue3';
    import { useForm } from '@inertiajs/vue3';
    import { ref } from 'vue';
    import SecondaryButton from '@/Components/Laravel/SecondaryButton.vue';
    import FormSection from '@/Components/Laravel/FormSection.vue';
    import InputError from '@/Components/Laravel/InputError.vue';
    import InputLabel from '@/Components/Laravel/InputLabel.vue';
    import PrimaryButton from '@/Components/Laravel/PrimaryButton.vue';
    import TextInput from '@/Components/Laravel/TextInput.vue';

    const form = useForm({
        _method: 'POST',
        name: '',
        description: '',
        photo: null,
        trailer: '',
        start_date: '',
        end_date: '',
    });

    const photoPreview = ref(null);
    const photoInput = ref(null);

    const tabs = ['basic', 'details'];
    const currentTab = ref(0);

    const finishBasicInformation = () => {
        const tab = tabs[currentTab.value];

        if (tab === 'basic') {
            if ( basicTabFinished() ) currentTab.value++;

            return;
        }
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

        const photo = photoInput.value.files[0];

        if ( ! photo ) {
            form.errors.photo = 'The image field is required.';
            return false;
        }
        
        form.errors.photo = '';

        form.photo = photo;

        return true;
    }

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
    };
</script>

<template>
    <div>
        <Head title="Create Tournament" />

        <div class="max-w-8xl mx-auto pt-6 px-4 md:px-6 lg:px-8">
            <h2 class="font-semibold text-3xl text-gray-200 leading-tight">
                Create Tournament
            </h2>
        </div>

        <div>
            <div class="max-w-8xl mx-auto py-10 sm:px-6 lg:px-8">
                <FormSection @submitted="finishBasicInformation">
                    <template #title>
                        <div v-if="tabs[currentTab] == 'basic'">Basic Information</div>
                        <div v-if="tabs[currentTab] == 'details'">Tournament details</div>
                    </template>
            
                    <template #description>
                        <div v-if="tabs[currentTab] == 'basic'">Basic information about your tournament.</div>
                        <div v-if="tabs[currentTab] == 'details'">Tournament in-depth details and dates.</div>
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
    
                                <div v-show="photoPreview" class="mt-2">
                                    <span
                                        class="block rounded-lg bg-cover bg-no-repeat bg-center"
                                        :style="'background-image: url(\'' + photoPreview + '\'); width: 100%; height: 200px;'"
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
                                <InputLabel for="start_date" value="Start Date" />
                                <TextInput
                                    id="start_date"
                                    v-model="form.start_date"
                                    type="datetime-local"
                                    class="mt-1 block w-full"
                                />
                                <InputError :message="form.errors.start_date" class="mt-2" />
                            </div>
    
                            <div class="mb-3">
                                <InputLabel for="end_date" value="End Date" />
                                <input
                                    id="end_date"
                                    v-model="form.end_date"
                                    type="datetime-local"
                                    class="mt-1 block w-full border-2 border-grayop-700 bg-grayop-900 text-gray-300 focus:border-blue-600 focus:ring-blue-600 rounded-md shadow-sm"
                                />
                                <InputError :message="form.errors.end_date" class="mt-2" />
                            </div>

                            <div class="mb-3">
                                <InputLabel for="rules" value="Rules" />
                                <textarea
                                    id="rules"
                                    v-model="form.rules"
                                    type="text"
                                    class="mt-1 block w-full border-2 border-grayop-700 bg-grayop-900 text-gray-300 focus:border-blue-600 focus:ring-blue-600 rounded-md shadow-sm"
                                />
                                <InputError :message="form.errors.rules" class="mt-2" />
                            </div>
                        </div>
                    </template>
            
                    <template #actions>
                        <PrimaryButton>
                            Next
                        </PrimaryButton>
                    </template>
                </FormSection>
            </div>
        </div>
    </div>
</template>
