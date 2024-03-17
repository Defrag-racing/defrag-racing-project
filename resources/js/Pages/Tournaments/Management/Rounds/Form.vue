<script setup>
    import { ref } from 'vue';
    import { useForm } from '@inertiajs/vue3';
    import FormSection from '@/Components/Laravel/FormSection.vue';
    import InputError from '@/Components/Laravel/InputError.vue';
    import InputLabel from '@/Components/Laravel/InputLabel.vue';
    import PrimaryButton from '@/Components/Laravel/PrimaryButton.vue';
    import TextInput from '@/Components/Laravel/TextInput.vue';
    import SecondaryButton from '@/Components/Laravel/SecondaryButton.vue';
    import ItemsChoices from '@/Components/Basic/ItemsChoices.vue';


    const weapons = [
        {
            'code': 'gauntlet',
            'name': 'Gauntlet'
        },
        {
            'code': 'mg',
            'name': 'Machine Gun'
        },{
            'code': 'sg',
            'name': 'Shot Gun'
        },{
            'code': 'gl',
            'name': 'Grenade Launcher'
        },{
            'code': 'rl',
            'name': 'Rocket Launcher'
        },{
            'code': 'lg',
            'name': 'Lightening Gun'
        },{
            'code': 'rg',
            'name': 'Rail Gun'
        },{
            'code': 'pg',
            'name': 'Plasma Gun'
        },{
            'code': 'bfg',
            'name': 'BFG'
        },{
            'code': 'hook',
            'name': 'Grappling Hook'
        },{
            'code': 'cg',
            'name': 'Chain Gun'
        },{
            'code': 'ng',
            'name': 'Nail Gun'
        },{
            'code': 'pml',
            'name': 'Proximity Mine Launcher'
        },
    ];

    const functions = [
        {
            'code': 'door',
            'name': 'Door'
        },
        {
            'code': 'button',
            'name': 'Button'
        },{
            'code': 'tele',
            'name': 'Teleporter'
        },{
            'code': 'jumppad',
            'name': 'Jump Pad'
        },{
            'code': 'moving',
            'name': 'Moving Object'
        },{
            'code': 'slick',
            'name': 'Slick'
        },{
            'code': 'water',
            'name': 'Water'
        },{
            'code': 'fog',
            'name': 'Fog'
        },{
            'code': 'slime',
            'name': 'Slime'
        },{
            'code': 'lava',
            'name': 'Lava'
        },{
            'code': 'break',
            'name': 'Breakable'
        },{
            'code': 'sound',
            'name': 'Sound'
        },{
            'code': 'timer',
            'name': 'Timer'
        },
    ];

    const items = [
        {
            'code': 'ra',
            'name': 'Red Armor'
        },
        {
            'code': 'ya',
            'name': 'Yellow Armor'
        },{
            'code': 'enviro',
            'name': 'Battle Suit'
        },{
            'code': 'flight',
            'name': 'Flight'
        },{
            'code': 'haste',
            'name': 'Haste'
        },{
            'code': 'health',
            'name': 'Health'
        },{
            'code': 'mega',
            'name': 'Mega Health'
        },{
            'code': 'smallhealth',
            'name': 'Small Health'
        },{
            'code': 'bighealth',
            'name': 'Large Health'
        },{
            'code': 'invis',
            'name': 'Invisibility'
        },{
            'code': 'quad',
            'name': 'Quad Damage'
        },{
            'code': 'regen',
            'name': 'Regeneration'
        },{
            'code': 'medkit',
            'name': 'Medi Kit / Double Jump'
        },{
            'code': 'ptele',
            'name': 'Personal Teleporter'
        }
    ];

    const props = defineProps({
        item: Object,
        tournament: Object,
        url: String
    });

    const form = useForm({
        _method: 'POST',
        name: props.item?.name || '',
        image: props.item?.image || '',
        start_date: props.item?.start_date || '',
        end_date: props.item?.end_date || '',
        author: props.item?.author || '',
        weapons: props.item?.weapons || {},
        items: props.item?.items || {},
        functions: props.item?.functions || {},
    });

    const imagePreview = ref(null);
    const imageInput = ref(null);

    const finishBasicInformation = () => {
        let submitUrl = null;

        if (props.item?.id) {
            submitUrl = route(props.url, {
                tournament: props.tournament.id,
                round: props.item.id,
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

    const selectNewImage = () => {
        imageInput.value.click();
    };

    const updateImagePreview = () => {
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
    <div>
        <FormSection @submitted="finishBasicInformation">
            <template #title>
                <div>Round Details</div>
            </template>
    
            <template #description>
                <div>Add the details of the new round</div>
            </template>
    
            <template #form>
                <div class="col-span-6">
                    <div class="mb-3">
                        <InputLabel for="name" value="Round Name" />
                        <TextInput
                            id="name"
                            v-model="form.name"
                            type="text"
                            class="mt-1 block w-full"
                            :class="{ 'border-red-500': form.errors.name }"
                        />
                        <InputError :message="form.errors.name" />
                    </div>
                    
                    <div class="mb-3">
                        <InputLabel for="author" value="Map Author" />
                        <TextInput
                            id="author"
                            v-model="form.author"
                            type="text"
                            class="mt-1 block w-full"
                            :class="{ 'border-red-500': form.errors.author }"
                        />
                        <InputError :message="form.errors.author" />
                    </div>

                    <div class="mb-3">
                        <input
                            id="image"
                            ref="imageInput"
                            type="file"
                            class="hidden"
                            accept="image/*"
                            @change="updateImagePreview"
                        >
        
                        <InputLabel for="image" value="Tournament Image" />

                        <div v-if="imagePreview" class="mt-2">
                            <span
                                class="block rounded-lg bg-cover bg-no-repeat bg-center"
                                :style="'background-image: url(\'' + imagePreview + '\'); width: 100%; height: 200px;'"
                            />
                        </div>

                        <div v-if="!imagePreview && item?.image" class="mt-2">
                            <span
                                class="block rounded-lg bg-cover bg-no-repeat bg-center"
                                :style="'background-image: url(\'/storage/' + item.image + '\'); width: 100%; height: 200px;'"
                            />
                        </div>
        
                        <SecondaryButton class="mt-2 me-2" type="button" @click.prevent="selectNewImage">
                            Select A New Image
                        </SecondaryButton>
                        <InputError :message="form.errors.image" class="mt-2" />
                    </div>

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
                        <InputLabel value="Weapons" />
                        <ItemsChoices
                            :options="weapons"
                            :multi="false"
                            v-model="form.weapons"
                            :values="form.weapons"
                            placeholder="Select Weapons"
                        />
                        <InputError :message="form.errors.weapons" class="mt-2" />
                    </div>

                    <div class="mb-3">
                        <InputLabel value="Items" />
                        <ItemsChoices
                            :options="items"
                            :multi="false"
                            v-model="form.items"
                            :values="form.items"
                            placeholder="Select Items"
                        />
                        <InputError :message="form.errors.items" class="mt-2" />
                    </div>

                    <div class="mb-3">
                        <InputLabel value="Functions" />
                        <ItemsChoices
                            :options="functions"
                            :multi="false"
                            v-model="form.functions"
                            :values="form.functions"
                            placeholder="Select Functions"
                        />
                        <InputError :message="form.errors.functions" class="mt-2" />
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
