<script setup>
    import { Link, useForm } from '@inertiajs/vue3';
    import PrimaryButton from '@/Components/Laravel/PrimaryButton.vue';
    import moment from 'moment';
    import { ref } from 'vue';

    const date = (created_at) => {
        return moment(created_at).fromNow();
    }

    const showReplies = ref(false);
    const showReplyForm = ref(false);

    const props = defineProps({
        comment: Object,
        subcomment: {
            type: Boolean,
            default: false
        },
        tournament: Object
    });

    const form = useForm({
        comment: ''
    });

    const onComment = () => {
        if (form.comment.length === 0) {
            return;
        }

        form.post(route('tournaments.comments.reply', {
            tournament: props.tournament.id,
            comment: props.comment.id
        }), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                showReplyForm.value = false;
                showReplies.value = true;
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
        <div class="flex w-full">
            <div style="width: 70px;" v-show="subcomment"></div>
            <div class="flex-shrink-0">
                <img
                    :src="comment.user.profile_photo_path ? '/storage/' + comment.user.profile_photo_path : '/images/null.jpg'"
                    :class="subcomment ? 'w-6 h-6 rounded-full' : 'w-10 h-10 rounded-full'"
                />
            </div>

            <div class="ml-3 w-full">
                <div class="flex w-full gap-2 items-center">
                    <Link
                        class="text-sm font-medium text-white"
                        v-html="q3tohtml(comment.user?.name)"
                        :href="route('profile.index', comment.user.id)"
                    />
                    <div class="text-xs text-gray-400">{{ date(comment.created_at) }}</div>
                </div>

                <div class="text-sm text-gray-400 mt-1">
                    {{ comment.comment }}
                </div>
            </div>
        </div>

        <div class="flex mb-2 items-center mt-1 ml-14">
            <div v-if="comment.comments.length > 0 && !subcomment" @click="showReplies = !showReplies" class="cursor-pointer hover:text-blue-400 text-sm text-blue-500 rounded-full py-1 flex items-center">
                <svg v-if="!showReplies" class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
                <svg v-else class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m18 15-6-6-6 6"/></svg>
                <span>Replies ({{ comment.comments.length }})</span>
            </div>
            
            <div v-if="!subcomment" @click="showReplyForm = !showReplyForm" class="cursor-pointer ml-3 text-gray-300 hover:text-white text-sm font-bold">
                Reply
            </div>
        </div>

        <div class="flex justify-between items-center mb-5" v-if="showReplyForm">
            <div style="width: 70px;" class="h-1"></div>
            <textarea
                v-model="form.comment"
                name="comment"
                type="text"
                placeholder="Type your reply..."
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

        <div class="w-full" v-show="showReplies">

            <div v-for="comment in comment.comments" :key="comment.id">
                <CommentPart :comment="comment" subcomment />
            </div>
        </div>

        <div class="h-1 my-2"></div>
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