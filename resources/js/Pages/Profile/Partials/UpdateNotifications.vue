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
            Notification Settings
        </template>

        <template #description>
            Control your notification settings
        </template>

        <template #form>
            
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
