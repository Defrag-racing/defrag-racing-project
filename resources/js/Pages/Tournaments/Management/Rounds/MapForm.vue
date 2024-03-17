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
        tournament: Object,
        round: Object
    });

    const form = useForm({
        _method: 'POST',
        name: '',
        pk3: '',
        crc: '',
    });

    const pk3Input = ref(null);

    const finishBasicInformation = () => {
        let submitUrl = route('tournaments.rounds.maps.store', {
            tournament: props.tournament.id,
            round: props.round.id,
        });

        form.post(submitUrl, {
            errorBag: 'finishBasicInformation',
            preserveScroll: true
        });
    };

    const selectPk3File = () => {
        pk3Input.value.click();
    };

    const updateFile = () => {
        const pk3 = pk3Input.value.files[0];

        if (! pk3) {
            form.errors.pk3 = 'The pk3 field is required.';
            return;
        };

        form.errors.pk3 = '';

        form.pk3 = pk3;
    };
</script>

<template>
    <div class="bg-blackop-30 p-3 rounded-md my-5">
        <div class="text-2xl text-center text-gray-300 mb-3">Add Map</div>
        <form enctype="multipart/form-data" @submit.prevent="finishBasicInformation" class="flex justify-center">
            <div class="w-80">
                <div class="mb-3">
                    <InputLabel for="name" value="Download File Name" />
                    <TextInput
                        id="name"
                        v-model="form.name"
                        placeholder="mapname.pk3"
                        type="text"
                        class="mt-1 block w-full"
                        :class="{ 'border-red-500': form.errors.name }"
                    />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="mb-3">
                    <InputLabel for="crc" value="BSP CRC" />
                    <TextInput
                        id="crc"
                        v-model="form.crc"
                        type="text"
                        placeholder="Leave empty if unknown."
                        class="mt-1 block w-full"
                        :class="{ 'border-red-500': form.errors.crc }"
                    />
                    <InputError :message="form.errors.crc" />
                </div>
    
                <div class="mb-3">
                    <input
                        id="pk3"
                        ref="pk3Input"
                        type="file"
                        class="hidden"
                        accept=".pk3"
                        @change="updateFile"
                    >
    
                    <InputLabel for="pk3" value="PK3 File" />
    
                    <SecondaryButton class="mt-2 me-2" type="button" @click.prevent="selectPk3File">
                        Select PK3 File
                    </SecondaryButton>
                    <InputError :message="form.errors.pk3" class="mt-2" />
                </div>
    
                <div class="flex justify-between w-full">
                    <PrimaryButton>Submit</PrimaryButton>
                </div>
            </div>
        </form>
    </div>
</template>
