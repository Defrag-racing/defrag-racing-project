<script setup>
    import { Head } from '@inertiajs/vue3';
    import moment from 'moment';

    const props = defineProps({
        announcements: Object,
    });

    const date = (created_at) => {
        return moment(created_at).fromNow();
    }
</script>

<template>
    <div>
        <Head title="Changelog" />

        <div class="py-12">
            <div class="max-w-8xl mx-auto sm:px-6 lg:px-8">
                <ol class="relative border-s border-gray-200 dark:border-gray-700">                  
                    <li class="mb-5 ms-4" v-for="(announcement, index) in announcements">
                        <div class="absolute w-3 h-3 bg-gray-200 rounded-full mt-1.5 -start-1.5 border border-white dark:border-gray-900 dark:bg-gray-700"></div>
                        <time class="mb-1 text-sm font-normal leading-none text-gray-400 dark:text-gray-500">
                            {{ date(announcement.created_at) }}
                        </time>
                        <span v-if="index === 0" class="bg-blue-100 text-blue-800 text-sm font-medium me-2 px-2.5 py-0.5 rounded dark:bg-blue-900 dark:text-blue-300 ms-3">Latest</span>

                        <p class="mb-4 text-base font-normal text-gray-500 dark:text-gray-400" v-html="announcement.text">
                        </p>
                    </li>
                </ol>
            </div>
        </div>
    </div>
</template>
