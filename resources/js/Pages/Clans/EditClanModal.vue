<script setup>
    import { useForm } from '@inertiajs/vue3';
    import InputError from '@/Components/Laravel/InputError.vue';
    import PrimaryButton from '@/Components/Laravel/PrimaryButton.vue';
    import InputLabel from '@/Components/Laravel/InputLabel.vue';
    import TextInput from '@/Components/Laravel/TextInput.vue';
    import Modal from '@/Components/Laravel/Modal.vue';
    import SecondaryButton from '@/Components/Laravel/SecondaryButton.vue';
    import { ref } from 'vue';

    const props = defineProps({
        clan: Object,
        close: Function,
        show: Boolean
    });


    const imagePreview = ref(null);
    const imageInput = ref(null);

    const form = useForm({
        _method: 'POST',
        name: props.clan?.name || '',
        image: null
    });

    const submitForm = () => {
        form.post(route('clans.manage.update', props.clan.id), {
            errorBag: 'submitForm',
            preserveScroll: true,
            onSuccess: () => {
                props.close();
            }
        });
    };


    const selectNewImage = () => {
        imageInput.value.click();
    };

    const updateimagePreview = () => {
        const image = imageInput.value.files[0];

        if (! image) {
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
    <div>
        <Modal :show="show" maxWidth="xl" :closeable="false">
            <div class="p-5">
                <div class="flex justify-between items-center">
                    <h2 class="font-semibold text-xl text-gray-200 leading-tight">
                        Invite Player
                    </h2>

                    <div class="text-gray-200 cursor-pointer rounded-full hover:bg-grayop-700 p-1" @click="close">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </div>
                </div>
                <div class="w-full bg-gray-700 my-2" style="height: 1px;"></div>

                <form @submit.prevent="submitForm">
                    <div class="my-3">
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

                        <div v-else class="mt-2">
                            <img :src="'/storage/' + clan.image" class="mt-2 rounded-full h-16 w-16">
                        </div>

                        <InputError :message="form.errors.image" class="mt-2" />
                    </div>

                    <div class="flex justify-center">
                        <PrimaryButton type="submit" class="w-32 justify-center">
                            Edit
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>
    </div>
</template>