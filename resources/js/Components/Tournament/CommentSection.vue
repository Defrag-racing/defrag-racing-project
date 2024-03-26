<script setup>
    import { Link, useForm } from '@inertiajs/vue3';
    import PrimaryButton from '@/Components/Laravel/PrimaryButton.vue';
    import moment from 'moment';
    import { ref } from 'vue';
    import CommentPart from '@/Components/Tournament/CommentPart.vue';

    const date = (created_at) => {
        return moment(created_at).fromNow();
    }

    const showSection = ref(false);

    const props = defineProps({
        comments: Array,
        url: String,
        tournament: Object
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

    const autoExpand = (event) => {
        const textarea = event.target;
        textarea.style.height = 'auto';
        textarea.style.height = textarea.scrollHeight + 'px';
    }
</script>

<template>
    <div>
        <div @click="showSection = !showSection" class="flex justify-center items-center cursor-pointer text-center text-xl font-medium text-gray-300 bg-blackop-30 p-2 rounded-md hover:text-white">
            <div>
                {{ showSection ? 'Hide Comments' : 'Show Comments' }}
            </div>
        </div>

        <div class="bg-blackop-30 p-4 rounded-md mt-5" v-show="showSection">
            <div class="flex justify-between items-center">
                <textarea
                    v-model="form.comment"
                    name="comment"
                    type="text"
                    placeholder="Type your comment..."
                    rows="1"
                    class="textarea mt-1 block w-full border-2 border-grayop-700 bg-grayop-900 text-gray-300 focus:border-blue-600 focus:ring-blue-600 rounded-md shadow-sm"
                    v-on:keyup.enter="onComment()"
                    @input="autoExpand($event)"
                ></textarea>

                <PrimaryButton @click="onComment(form)" class="ml-2">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5" />
                    </svg>
                </PrimaryButton>
            </div>
    
            <div class="mt-5">
                <div v-for="comment in comments" :key="comment.id">
                    <CommentPart :tournament="tournament" :comment="comment" />
                </div>

                <div class="text-center text-gray-400" v-if="comments.length === 0">
                    There are no comments yet.
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

    .textarea {
        width: 100%;
        resize: none;
        overflow-y: hidden;
        padding: 10px;
        box-sizing: border-box;
    }
</style>