<script setup>
    import { Link, useForm } from '@inertiajs/vue3';
    import PrimaryButton from '@/Components/Laravel/PrimaryButton.vue';
    import TextInput from '@/Components/Laravel/TextInput.vue';
    import moment from 'moment';
    import { ref } from 'vue';

    const date = (created_at) => {
        return moment(created_at).fromNow();
    }

    const showSection = ref(false);

    const props = defineProps({
        comments: Array,
        url: String
    });

    const form = useForm({
        comment: ''
    });

    const onComment = () => {
        if (form.comment.length === 0) {
            return;
        }

        form.post(props.url, {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
            }
        });
    }
</script>

<template>
    <div>
        <div @click="showSection = !showSection" class="flex justify-center items-center cursor-pointer text-center text-xl font-medium text-gray-300 bg-blackop-30 p-2 rounded-md hover:text-white">
            <div class="mr-2">
                Comments
            </div>

            <svg v-if="!showSection" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
            </svg>

            <svg v-else xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 15.75 7.5-7.5 7.5 7.5" />
            </svg>
        </div>

        <div class="bg-blackop-30 p-4 rounded-md" v-show="showSection">
            <div class="flex justify-between items-center">
                <TextInput
                    v-model="form.comment"
                    name="comment"
                    type="text"
                    placeholder="Type your comment..."
                    class="w-full"
                    v-on:keyup.enter="onComment()"
                />
    
                <PrimaryButton @click="onComment(form)" class="ml-2">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5" />
                    </svg>
                </PrimaryButton>
            </div>
    
            <div class="mt-5">
                <div v-for="comment in comments" :key="comment.id">
                    <div class="flex w-full">
                        <div class="flex-shrink-0">
                            <img
                                :src="comment.user.profile_photo_path ? '/storage/' + comment.user.profile_photo_path : '/images/null.jpg'"
                                class="w-10 h-10 rounded-full"
                            />
                        </div>
        
                        <div class="ml-3 w-full">
                            <div class="flex w-full justify-between items-center">
                                <Link
                                    class="text-sm font-medium text-white"
                                    v-html="q3tohtml(comment.user?.name)"
                                    :href="route('profile.index', comment.user.id)"
                                />
                                <div class="text-sm text-gray-400">Posted {{ date(comment.created_at) }}</div>
                            </div>
        
                            <div class="text-sm text-gray-400 mt-1">
                                {{ comment.comment }}
                            </div>
                        </div>
                    </div>
    
                    <div class="ruler my-5"></div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
    .ruler {
        height: 1px;
        width: 100%;
        background-color:rgba(255, 255, 255, 0.137);
    }
</style>