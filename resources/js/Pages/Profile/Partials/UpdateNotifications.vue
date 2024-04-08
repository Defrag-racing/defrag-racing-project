<script setup>
    import { useForm } from '@inertiajs/vue3';
    import ActionMessage from '@/Components/Laravel/ActionMessage.vue';
    import FormSection from '@/Components/Laravel/FormSection.vue';
    import InputError from '@/Components/Laravel/InputError.vue';
    import InputLabel from '@/Components/Laravel/InputLabel.vue';
    import PrimaryButton from '@/Components/Laravel/PrimaryButton.vue';
    import Checkbox from '@/Components/Laravel/Checkbox.vue';

    const props = defineProps({
        user: Object,
    });

    const form = useForm({
        _method: 'POST',
        defrag_news: props.user.defrag_news ? true : false,
        tournament_news: props.user.tournament_news ? true : false,
        invitations: true,
        records_vq3: props.user.records_vq3,
        records_cpm: props.user.records_cpm
    });

    const updateSocialMediaInformation = () => {
        form.post(route('settings.notifications'), {
            preserveScroll: true
        });
    };
</script>

<template>
    <FormSection @submitted="updateSocialMediaInformation">
        <template #title>
            <div id="notifications-settings">
                Notification Settings
            </div>
        </template>

        <template #description>
            Control your notification settings
        </template>

        <template #form>
            <div class="col-span-6">
                <div class="text-lg text-white font-bold">System Notifications</div>
                <div class="text-sm text-gray-400">When do you want to recieve system notifications</div>

                <div class="mt-4">
                    <label class="flex items-center cursor-pointer mb-3">
                        <Checkbox v-model:checked="form.defrag_news" name="defrag_news" />
                        <span class="ms-2 text-sm text-gray-400">
                            Defrag News (Announcements, Changelog, game updates, etc...)
                        </span>
                    </label>

                    <label class="flex items-center cursor-pointer mb-3">
                        <Checkbox v-model:checked="form.tournament_news" name="tournament_news" />
                        <span class="ms-2 text-sm text-gray-400">
                            Tournament News (Round starts, Results, updates, etc...)
                        </span>
                    </label>

                    <label class="flex items-center cursor-pointer mb-3">
                        <Checkbox :checked="true" name="invitations" disabled class="text-gray-500" />
                        <span class="ms-2 text-sm text-gray-400">
                            Invitations (Clan invites, Team invites, etc...) [Mandatory]
                        </span>
                    </label>
                </div>
            </div>

            <div class="col-span-6 mt-5">
                <div class="text-lg text-white font-bold">Records Notifications</div>
                <div class="text-sm text-gray-400">When do you want to recieve records notifications</div>

                <div class="mt-4 w-full flex justify-between">
                    <div class="flex-1">
                        <div class="text-blue-400 font-bold text-lg mb-3">VQ3</div>

                        <div class="flex items-center mb-4">
                            <input id="recordsall_vq3" type="radio" v-model="form.records_vq3" value="all" name="recordsall_vq3" class="w-4 h-4 text-blue-600 focus:ring-blue-600 ring-offset-gray-800 focus:ring-2 bg-gray-700 border-gray-600">
                            <label for="recordsall_vq3" class="ms-2 text-sm font-medium text-gray-300">All</label>
                        </div>
    
                        <div class="flex items-center mb-4">
                            <input id="recordswr_vq3" type="radio" v-model="form.records_vq3" value="wr" name="recordswr_vq3" class="w-4 h-4 text-blue-600 focus:ring-blue-600 ring-offset-gray-800 focus:ring-2 bg-gray-700 border-gray-600">
                            <label for="recordswr_vq3" class="ms-2 text-sm font-medium text-gray-300">World Records Only</label>
                        </div>
                    </div>

                    <div class="flex-1">
                        <div class="text-green-400 font-bold text-lg mb-3">CPM</div>
    
                        <div class="flex items-center mb-4">
                            <input id="recordsall_cpm" type="radio" v-model="form.records_cpm" value="all" name="recordsall_cpm" class="w-4 h-4 text-blue-600 focus:ring-blue-600 ring-offset-gray-800 focus:ring-2 bg-gray-700 border-gray-600">
                            <label for="recordsall_cpm" class="ms-2 text-sm font-medium text-gray-300">All</label>
                        </div>
    
                        <div class="flex items-center mb-4">
                            <input id="recordswr_cpm" type="radio" v-model="form.records_cpm" value="wr" name="recordswr_cpm" class="w-4 h-4 text-blue-600 focus:ring-blue-600 ring-offset-gray-800 focus:ring-2 bg-gray-700 border-gray-600">
                            <label for="recordswr_cpm" class="ms-2 text-sm font-medium text-gray-300">World Records Only</label>
                        </div>
                    </div>
                </div>
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
