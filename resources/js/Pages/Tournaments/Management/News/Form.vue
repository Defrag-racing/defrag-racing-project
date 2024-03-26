<script setup>
    import { ref } from 'vue';
    import { useForm } from '@inertiajs/vue3';
    import FormSection from '@/Components/Laravel/FormSection.vue';
    import InputError from '@/Components/Laravel/InputError.vue';
    import InputLabel from '@/Components/Laravel/InputLabel.vue';
    import PrimaryButton from '@/Components/Laravel/PrimaryButton.vue';
    import TextInput from '@/Components/Laravel/TextInput.vue';
    import SecondaryButton from '@/Components/Laravel/SecondaryButton.vue';

    const props = defineProps({
        item: Object,
        tournament: Object,
        url: String
    });

    const photoPreview = ref(null);
    const photoInput = ref(null);

    const form = useForm({
        _method: 'POST',
        title: props.item?.title || '',
        content: props.item?.content || '',
        image: null,
    });

    const finishBasicInformation = () => {
        if (form.title.length < 1) {
            form.errors.title = 'Title is required.';
            return;
        }

        form.errors.title = null;

        if (form.content.length < 1) {
            form.errors.content = 'Content is required.';
            return;
        }

        form.errors.content = null;

        const photo = photoInput?.value?.files[0];

        if (photo && ! photo.type.startsWith('image/')) {
            form.errors.image = 'The file must be an Image.';
            return;
        }

        if (photo) {
            form.image = photo;
        }

        let submitUrl = null;

        if (props.item?.id) {
            submitUrl = route(props.url, {
                tournament: props.tournament.id,
                news: props.item.id,
            });
        } else {
            submitUrl = route(props.url, {
                tournament: props.tournament.id,
            });
        }

        form.post(submitUrl, {
            errorBag: 'finishBasicInformation',
            preserveScroll: true
        });
    };

    const selectNewPhoto = () => {
        photoInput.value.click();
    };

    const updatePhotoPreview = () => {
        const photo = photoInput.value.files[0];

        if (! photo) {
            form.errors.image = 'The image field is required.';
            return;
        };

        if (! photo.type.startsWith('image/')) {
            form.errors.image = 'The file must be an Image.';
            photoInput.value = '';
            return;
        }

        const reader = new FileReader();
        form.errors.image = '';

        reader.onload = (e) => {
            photoPreview.value = e.target.result;
        };

        reader.readAsDataURL(photo);

        form.image = photo;
    };
</script>

<template>
    <div>
        <FormSection @submitted="finishBasicInformation">
            <template #title>
                <div>Add news</div>
            </template>
    
            <template #description>
                <div>Add tournament news that people will be notified by.</div>
            </template>
    
            <template #form>
                <div class="col-span-6">
                    <div class="mb-3">
                        <InputLabel for="title" value="Title" />
                        <TextInput
                            id="title"
                            v-model="form.title"
                            type="text"
                            class="mt-1 block w-full"
                            :class="{ 'border-red-500': form.errors.title }"
                        />
                        <InputError :message="form.errors.title" />
                    </div>
    
                    <div class="mb-3">
                        <InputLabel for="user_id" value="Content" />
                        <textarea
                            id="content"
                            v-model="form.content"
                            type="text"
                            class="mt-1 block w-full border-2 border-grayop-700 bg-grayop-900 text-gray-300 focus:border-blue-600 focus:ring-blue-600 rounded-md shadow-sm"
                        />
                        <InputError :message="form.errors.content" />
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
        
                        <InputLabel for="photo" value="News Image (optional)" />

                        <div v-if="photoPreview" class="mt-2">
                            <span
                                class="block rounded-lg bg-cover bg-no-repeat bg-center"
                                :style="'background-image: url(\'' + photoPreview + '\'); width: 100%; height: 200px;'"
                            />
                        </div>

                        <div v-if="!photoPreview && item?.image" class="mt-2">
                            <span
                                class="block rounded-lg bg-cover bg-no-repeat bg-center"
                                :style="'background-image: url(\'/storage/' + item.image + '\'); width: 100%; height: 200px;'"
                            />
                        </div>
        
                        <SecondaryButton class="mt-2 me-2" type="button" @click.prevent="selectNewPhoto">
                            Select A New Photo
                        </SecondaryButton>
                        <InputError :message="form.errors.image" class="mt-2" />
                    </div>
                </div>
            </template>
    
            <template #actions>
                <div class="flex justify-between w-full">
                    <PrimaryButton>Submit</PrimaryButton>
                </div>
            </template>
        </FormSection>
    </div>
</template>
