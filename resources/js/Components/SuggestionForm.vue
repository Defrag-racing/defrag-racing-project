<script setup>
    import { Link, useForm } from '@inertiajs/vue3';
    import PrimaryButton from '@/Components/Laravel/PrimaryButton.vue';
    import TextInput from '@/Components/Laravel/TextInput.vue';
    import InputError from '@/Components/Laravel/InputError.vue';
    import InputLabel from '@/Components/Laravel/InputLabel.vue';

    const props = defineProps({
        closeForm: Function,
        tournament: Object
    });

    const form = useForm({
        _method: 'POST',
        title: '',
        message: '',
    });

    const submitSuggestionForm = () => {
        form.post(route('tournaments.suggestions.store', { tournament: props.tournament.id }), {
            errorBag: 'submitSuggestionForm',
            preserveScroll: true,
            onSuccess: () => {
                props.closeForm()
            }
        });
    }
</script>

<template>
    <div class="fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center z-50 w-screen h-screen" style="top: 0px; left: 0px;">
        <div class="bg-gray-800 text-white p-8 rounded-md shadow-lg text-left" style="width: 400px; max-width: 90vw;">
            <div class="flex justify-between">
                <h2 class="text-xl font-semibold mb-4">Suggest a change</h2>
                <div @click="closeForm" class="cursor-pointer">
                    <svg class="w-8 h-8" width="800px" height="800px" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M8.00386 9.41816C7.61333 9.02763 7.61334 8.39447 8.00386 8.00395C8.39438 7.61342 9.02755 7.61342 9.41807 8.00395L12.0057 10.5916L14.5907 8.00657C14.9813 7.61605 15.6144 7.61605 16.0049 8.00657C16.3955 8.3971 16.3955 9.03026 16.0049 9.42079L13.4199 12.0058L16.0039 14.5897C16.3944 14.9803 16.3944 15.6134 16.0039 16.0039C15.6133 16.3945 14.9802 16.3945 14.5896 16.0039L12.0057 13.42L9.42097 16.0048C9.03045 16.3953 8.39728 16.3953 8.00676 16.0048C7.61624 15.6142 7.61624 14.9811 8.00676 14.5905L10.5915 12.0058L8.00386 9.41816Z" fill="#aaaaaa"/>
                        <path fill-rule="evenodd" clip-rule="evenodd" d="M23 12C23 18.0751 18.0751 23 12 23C5.92487 23 1 18.0751 1 12C1 5.92487 5.92487 1 12 1C18.0751 1 23 5.92487 23 12ZM3.00683 12C3.00683 16.9668 7.03321 20.9932 12 20.9932C16.9668 20.9932 20.9932 16.9668 20.9932 12C20.9932 7.03321 16.9668 3.00683 12 3.00683C7.03321 3.00683 3.00683 7.03321 3.00683 12Z" fill="#aaaaaa"/>
                    </svg>
                </div>
            </div>
            <div class="w-1/2 h-1 mb-5" style="background-color: #4d78bf"></div>

            <div class="">
                <p class="text-sm mb-4">Suggest changes to the tournament.</p>
            </div>

            <div class="mt-2">
                <form method="POST" enctype="multipart/form-data" @submit.prevent="submitSuggestionForm">
                    <div class="">
                        <div class="">
                            <div>
                                <InputLabel for="title" value="Title" />
                                <TextInput
                                    id="title"
                                    v-model="form.title"
                                    type="text"
                                    class="mt-1 block w-full"
                                    required
                                />
                                <InputError :message="form.errors.title" class="mt-2" />
                            </div>
                        </div>
    
                        <div class="my-5">
                            <div>
                                <InputLabel for="message" value="Message" />
                                <textarea required maxlength="500" v-model="form.message" type="text" name="message" class="border-2 border-grayop-700 bg-grayop-900 text-gray-300 focus:border-blue-600 focus:ring-blue-600 rounded-md shadow-sm w-full"></textarea>
                                <InputError :message="form.errors.message" class="mt-2" />
                            </div>
                        </div>
    
                        <div class="flex items-start mt-5 justify-center text-center">
                            <PrimaryButton>Submit Suggestion</PrimaryButton>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>