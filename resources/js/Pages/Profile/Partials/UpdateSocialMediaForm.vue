<script setup>
    import { useForm } from '@inertiajs/vue3';
    import ActionMessage from '@/Components/Laravel/ActionMessage.vue';
    import FormSection from '@/Components/Laravel/FormSection.vue';
    import InputError from '@/Components/Laravel/InputError.vue';
    import InputLabel from '@/Components/Laravel/InputLabel.vue';
    import PrimaryButton from '@/Components/Laravel/PrimaryButton.vue';
    import TextInput from '@/Components/Laravel/TextInput.vue';

    const props = defineProps({
        user: Object,
    });

    const form = useForm({
        _method: 'POST',
        twitter_name: props.user.twitter_name,
        twitch_name: props.user.twitch_name,
        discord_name: props.user.discord_name
    });

    const updateSocialMediaInformation = () => {
        form.post(route('settings.socialmedia'), {
            preserveScroll: true
        });
    };
</script>

<template>
    <FormSection @submitted="updateSocialMediaInformation">
        <template #title>
            Social Media
        </template>

        <template #description>
            Update your social media information.
        </template>

        <template #form>
            <!-- Twitter -->
            <div class="col-span-6 sm:col-span-4">
                <InputLabel for="twitter" value="Twitter" />
                <TextInput
                    id="twitter"
                    v-model="form.twitter_name"
                    type="text"
                    class="mt-1 block w-full"
                />
                <InputError :message="form.errors.twitter_name" class="mt-2" />
            </div>

            <!-- Twitch -->
            <div class="col-span-6 sm:col-span-4">
                <InputLabel for="twitch" value="Twitch" />
                <TextInput
                    id="twitch"
                    v-model="form.twitch_name"
                    type="text"
                    class="mt-1 block w-full"
                />
                <InputError :message="form.errors.twitch_name" class="mt-2" />
            </div>

            <!-- Discord -->
            <div class="col-span-6 sm:col-span-4">
                <InputLabel for="discord" value="Discord" />
                <TextInput
                    id="discord"
                    v-model="form.discord_name"
                    type="text"
                    class="mt-1 block w-full"
                />
                <InputError :message="form.errors.discord_name" class="mt-2" />
            </div>
        </template>

        <template #actions>
            <ActionMessage :on="form.recentlySuccessful" class="me-3">
                Saved.
            </ActionMessage>

            <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                Save
            </PrimaryButton>
        </template>
    </FormSection>
</template>
