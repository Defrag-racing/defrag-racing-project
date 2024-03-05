<script setup>
    import { Head } from '@inertiajs/vue3';
    import { Link } from '@inertiajs/vue3';
    import { useForm } from '@inertiajs/vue3';
    import { ref } from 'vue';
    import FormSection from '@/Components/Laravel/FormSection.vue';
    import InputError from '@/Components/Laravel/InputError.vue';
    import InputLabel from '@/Components/Laravel/InputLabel.vue';
    import PrimaryButton from '@/Components/Laravel/PrimaryButton.vue';
    import TextInput from '@/Components/Laravel/TextInput.vue';
    import SecondaryButton from '@/Components/Laravel/SecondaryButton.vue';

    const form = useForm({
        _method: 'POST',
        name: '',
        image: null
    });

    const imagePreview = ref(null);
    const imageInput = ref(null);

    const submitForm = () => {
        form.post(route('clans.store'), {
            errorBag: 'submitForm',
            preserveScroll: true
        });
    };

    const selectNewImage = () => {
        imageInput.value.click();
    };

    const updateimagePreview = () => {
        const image = imageInput.value.files[0];

        if (! image) {
            form.errors.image = 'The image field is required.';
            return;
        };

        if (! image.type.startsWith('image/')) {
            form.errors.image = 'The file must be an Image.';
            imageInput.value = '';
            return;
        }

        const reader = new FileReader();
        form.errors.image = '';

        reader.onload = (e) => {
            imagePreview.value = e.target.result;
        };

        reader.readAsDataURL(image);

        form.image = image;
    };
</script>

<template>
    <Head title="Create Clan" />

    <div class="py-12">
        <div class="max-w-8xl mx-auto sm:px-6 lg:px-8">
            <FormSection @submitted="submitForm">
                <template #title>
                    <div>Create Clan</div>
                </template>
        
                <template #description>
                    <div>Create a new Clan and manage it as admin.</div>
                </template>
        
                <template #form>
                    <div class="col-span-6">
                        <div class="mb-4">
                            <InputLabel for="name" v-html="'Clan Name: ' + q3tohtml(form.name)" />
                            <TextInput
                                id="name"
                                v-model="form.name"
                                type="text"
                                class="mt-1 block w-full"
                                required
                                autofocus
                                autocomplete="name"
                            />
                            <div class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                You can use Quake3 color codes, such as: ^1Red^2Green.
                            </div>
                            <InputError class="mt-2" :message="form.errors.name" />
                        </div>
    
                        <div class="mb-3">
                            <input
                                id="image"
                                ref="imageInput"
                                type="file"
                                class="hidden"
                                accept="image/*"
                                @change="updateimagePreview"
                            >
            
                            <InputLabel for="image" value="Clan Avatar" />
            
                            <SecondaryButton class="mt-2 me-2" type="button" @click.prevent="selectNewImage">
                                Select an Image
                            </SecondaryButton>
    
                            <div v-if="imagePreview" class="mt-2">
                                <img :src="imagePreview" class="mt-2 rounded-full h-16 w-16">
                            </div>

                            <InputError :message="form.errors.image" class="mt-2" />
                        </div>
                    </div>
                </template>
        
                <template #actions>
                    <div class="flex justify-end w-full">
                        <PrimaryButton>Submit</PrimaryButton>
                    </div>
                </template>
            </FormSection>
        </div>
    </div>
</template>
