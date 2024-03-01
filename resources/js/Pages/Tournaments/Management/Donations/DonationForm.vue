<script setup>
    import { useForm } from '@inertiajs/vue3';
    import FormSection from '@/Components/Laravel/FormSection.vue';
    import InputError from '@/Components/Laravel/InputError.vue';
    import InputLabel from '@/Components/Laravel/InputLabel.vue';
    import PrimaryButton from '@/Components/Laravel/PrimaryButton.vue';
    import TextInput from '@/Components/Laravel/TextInput.vue';

    const props = defineProps({
        donation: Object,
        tournament: Object,
        url: String
    });

    const form = useForm({
        _method: 'POST',
        name: props.donation?.name || '',
        amount: props.donation?.amount.toString() || '',
        currency: props.donation?.currency || '',
        message: props.donation?.message || '',
        tournament_id: props.donation?.tournament_id || '',
    });

    const finishBasicInformation = () => {
        let submitUrl = null;

        if (props.donation?.id) {
            submitUrl = route(props.url, {
                tournament: props.tournament.id,
                donation: props.donation.id,
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
                <div>Donation Details</div>
            </template>
    
            <template #description>
                <div>Add the details of the new donation</div>
            </template>
    
            <template #form>
                <div class="col-span-6">
                    <div class="mb-3">
                        <InputLabel for="name" value="Name" />
                        <TextInput
                            id="name"
                            v-model="form.name"
                            type="text"
                            class="mt-1 block w-full"
                        />
                        <InputError :message="form.errors.name" class="mt-2" />
                    </div>

                    <div class="mb-3">
                        <InputLabel for="amount" value="Amount" />
                        <TextInput
                            id="amount"
                            v-model="form.amount"
                            type="number"
                            class="mt-1 block w-full"
                        />
                        <InputError :message="form.errors.amount" class="mt-2" />
                    </div>

                    <div class="mb-3">
                        <InputLabel for="currency" value="Currency" />
                        <TextInput
                            id="currency"
                            v-model="form.currency"
                            type="text"
                            class="mt-1 block w-full"
                        />
                        <InputError :message="form.errors.currency" class="mt-2" />
                    </div>

                    <div class="mb-3">
                        <InputLabel for="message" value="Message" />
                        <TextInput
                            id="message"
                            v-model="form.message"
                            type="text"
                            class="mt-1 block w-full"
                        />
                        <InputError :message="form.errors.message" class="mt-2" />
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
