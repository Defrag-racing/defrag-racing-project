<script setup>
    import { ref } from 'vue';
    import { useForm } from '@inertiajs/vue3';
    import FormSection from '@/Components/Laravel/FormSection.vue';
    import InputError from '@/Components/Laravel/InputError.vue';
    import InputLabel from '@/Components/Laravel/InputLabel.vue';
    import PrimaryButton from '@/Components/Laravel/PrimaryButton.vue';

    const props = defineProps({
        item: Object,
        tournament: Object,
        url: String,
        tournaments: Array
    });

    const form = useForm({
        _method: 'POST',
        related_tournament_id: props.item?.related_tournament_id || '',
    });

    const finishBasicInformation = () => {
        let submitUrl = null;

        if (props.item?.id) {
            submitUrl = route(props.url, {
                tournament: props.tournament.id,
                relatedTournament: props.item.id,
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
                <div>Related Tournament Details</div>
            </template>
    
            <template #description>
                <div>Add the details of the new related tournament</div>
            </template>
    
            <template #form>
                <div class="col-span-6">
                    <div class="mb-3">
                        <InputLabel for="related_tournament_id" value="Related Tournament" />
                        <select v-model="form.related_tournament_id" id="related_tournament_id" name="related_tournament_id" class="mt-2 border-2 border-grayop-700 bg-grayop-900 text-gray-300 focus:border-blue-600 focus:ring-blue-600 rounded-md shadow-sm w-full">
                            <option value="">Select a tournament</option>
                            <option v-for="pTournament in tournaments" :value="pTournament.id" :key="pTournament.id">
                                {{ pTournament.name }}
                            </option>
                        </select>
                        <InputError :message="form.errors.related_tournament_id" />
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
