<script setup>
    import { ref } from 'vue';
    import { useForm } from '@inertiajs/vue3';
    import FormSection from '@/Components/Laravel/FormSection.vue';
    import InputError from '@/Components/Laravel/InputError.vue';
    import InputLabel from '@/Components/Laravel/InputLabel.vue';
    import PrimaryButton from '@/Components/Laravel/PrimaryButton.vue';
    import TextInput from '@/Components/Laravel/TextInput.vue';
    import PlayerSelectDefrag from '@/Components/Basic/PlayerSelectDefrag.vue';

    const props = defineProps({
        item: Object,
        tournament: Object,
        url: String,
        users: Array
    });

    const user_id = ref([]);

    const form = useForm({
        _method: 'POST',
        role: props.item?.role || '',
        user_id: props.item?.user_id || ''
    });

    const finishBasicInformation = () => {
        if (user_id.value.length > 0) {
            form.user_id = user_id.value[0];
        }

        if (form.role.length === 0) {
            form.errors.role = 'Role is required';
            return;
        }

        form.errors.role = null;

        let submitUrl = null;

        if (props.item?.id) {
            submitUrl = route(props.url, {
                tournament: props.tournament.id,
                organizer: props.item.id,
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

    const roles = [
        {
            value: 'organizer',
            label: 'Organizer'
        },
        {
            value: 'mapper',
            label: 'Mapper'
        },
        {
            value: 'tester',
            label: 'Map Tester'
        },
        {
            value: 'validator',
            label: 'Demo Validator'
        }
    ];
</script>

<template>
    <div>
        <FormSection @submitted="finishBasicInformation">
            <template #title>
                <div>Organizer Details</div>
            </template>
    
            <template #description>
                <div>Add the details of the new Organizer</div>
            </template>
    
            <template #form>
                <div class="col-span-6">
                    <div class="mb-3">
                        <InputLabel for="role" value="Role" />

                        <select required v-model="form.role" class="w-full border-2 border-grayop-700 bg-grayop-900 text-gray-300 focus:border-blue-600 focus:ring-blue-600 rounded-md shadow-sm">
                            <option v-for="role in roles" :value="role.value">{{ role.label }}</option>
                        </select>

                        <InputError :message="form.errors.role" />
                    </div>

                    <div class="mb-3">
                        <InputLabel for="user_id" value="Defrag User" />
                        <PlayerSelectDefrag
                            id="user_id"
                            v-model="user_id"
                            :values="form.user_id ? [form.user_id] : []"
                            :options="users"
                            :class="{ 'border-red-500': form.errors.user_id }"
                        />
                        <InputError :message="form.errors.answer" />
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
