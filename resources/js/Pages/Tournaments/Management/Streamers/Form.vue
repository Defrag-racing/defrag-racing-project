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
        twitch_username: props.item?.twitch_username || '',
        user_id: props.item?.user_id || ''
    });

    const finishBasicInformation = () => {
        if (user_id.value.length > 0) {
            form.user_id = user_id.value[0];
        }

        let submitUrl = null;

        if (props.item?.id) {
            submitUrl = route(props.url, {
                tournament: props.tournament.id,
                streamer: props.item.id,
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
                <div>Streamer Details</div>
            </template>
    
            <template #description>
                <div>Add the details of the new streamer</div>
            </template>
    
            <template #form>
                <div class="col-span-6">
                    <div class="mb-3">
                        <InputLabel for="twitch_username" value="Twitch Username" />
                        <TextInput
                            id="twitch_username"
                            v-model="form.twitch_username"
                            type="text"
                            class="mt-1 block w-full"
                            :class="{ 'border-red-500': form.errors.twitch_username }"
                        />
                        <InputError :message="form.errors.twitch_username" />
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
