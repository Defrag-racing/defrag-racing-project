<script setup>
    import { useForm } from '@inertiajs/vue3';
    import FormSection from '@/Components/Laravel/FormSection.vue';
    import InputError from '@/Components/Laravel/InputError.vue';
    import InputLabel from '@/Components/Laravel/InputLabel.vue';
    import PrimaryButton from '@/Components/Laravel/PrimaryButton.vue';
    import TextInput from '@/Components/Laravel/TextInput.vue';

    const props = defineProps({
        faq: Object,
        tournament: Object,
        url: String
    });

    const form = useForm({
        _method: 'POST',
        question: props.faq?.question || '',
        answer: props.faq?.answer || ''
    });

    const finishBasicInformation = () => {
        let submitUrl = null;

        if (props.faq?.id) {
            submitUrl = route(props.url, {
                tournament: props.tournament.id,
                faq: props.faq.id,
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
                <div>FAQ Details</div>
            </template>
    
            <template #description>
                <div>Add the details of the new faq</div>
            </template>
    
            <template #form>
                <div class="col-span-6">
                    <div class="mb-3">
                        <InputLabel for="question" value="Question" />
                        <TextInput
                            id="question"
                            v-model="form.question"
                            type="text"
                            class="mt-1 block w-full"
                            :class="{ 'border-red-500': form.errors.question }"
                        />
                        <InputError :message="form.errors.question" />
                    </div>
    
                    <div class="mb-3">
                        <InputLabel for="answer" value="Answer" />
                        <TextInput
                            id="answer"
                            v-model="form.answer"
                            type="text"
                            class="mt-1 block w-full"
                            :class="{ 'border-red-500': form.errors.answer }"
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
