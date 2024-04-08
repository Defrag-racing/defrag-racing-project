<script setup>
    import { useForm } from '@inertiajs/vue3';
    import FormSection from '@/Components/Laravel/FormSection.vue';
    import InputError from '@/Components/Laravel/InputError.vue';
    import InputLabel from '@/Components/Laravel/InputLabel.vue';
    import PrimaryButton from '@/Components/Laravel/PrimaryButton.vue';
    import TextInput from '@/Components/Laravel/TextInput.vue';

    const props = defineProps({
        item: Object,
        tournament: Object,
        url: String
    });

    const form = useForm({
        _method: 'POST',
        name: props.item?.name || '',
        type: 'cpm'
    });

    const finishBasicInformation = () => {
        let submitUrl = null;

        if (props.item?.id) {
            submitUrl = route(props.url, {
                tournament: props.tournament.id,
                team: props.item.id,
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
</script>

<template>
    <div>
        <FormSection @submitted="finishBasicInformation">
            <template #title>
                <div>Create new Team</div>
            </template>
    
            <template #description>
                <div>A Team consists of two players, a cpm player and a vq3 player. The teams score is calculating by adding the time of both players together.</div>
            </template>
    
            <template #form>
                <div class="col-span-6">
                    <div class="mb-4">
                        <InputLabel for="name" v-html="'Team Name: ' + q3tohtml(form.name)" />
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

                    <div class="mb-4">
                        <InputLabel for="type" value="Assign yourself as" />
                        <select id="type" v-model="form.type" class="border-2 border-grayop-700 bg-grayop-900 text-gray-300 focus:border-blue-600 focus:ring-blue-600 rounded-md shadow-sm w-full">
                            <option value="cpm">CPM Player</option>
                            <option value="vq3">VQ3 Player</option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.type" />
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
