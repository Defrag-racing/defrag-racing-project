<script setup>
    import { Head } from '@inertiajs/vue3';
    import Pagination from '@/Components/Basic/Pagination.vue';
    import { Link } from '@inertiajs/vue3';

    const props = defineProps({
        notifications: Object,
    });

    const timeDiff = (time, bettertime) => {
        return Math.abs(bettertime - time)
    }
</script>

<template>
    <div>
        <Head title="Notifications" />

        <div class="max-w-8xl mx-auto pt-6 px-4 md:px-6 lg:px-8">
            <div class="flex justify-between flex-wrap">
                <h2 class="font-semibold text-3xl text-gray-800 dark:text-gray-200 leading-tight">
                    Notifications
                </h2>
            </div>

            <div class="text-sm text-blue-400 flex items-center mt-3">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
                </svg>
                <div class="ml-2 mr-5">{{ notifications.total }} Notifications</div>
            </div>
        </div>

        <div class="py-12">
            <div class="max-w-8xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-grayop-800 overflow-hidden shadow-xl sm:rounded-lg p-4">
                    <div v-for="notification in notifications.data">
                        <div class="mb-1 flex items-center text-gray-400 p-2 hover:bg-blackop-30 rounded-md">
                            <div class="mr-5 text-orange-500">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-7 h-7">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 12c0-1.232-.046-2.453-.138-3.662a4.006 4.006 0 0 0-3.7-3.7 48.678 48.678 0 0 0-7.324 0 4.006 4.006 0 0 0-3.7 3.7c-.017.22-.032.441-.046.662M19.5 12l3-3m-3 3-3-3m-12 3c0 1.232.046 2.453.138 3.662a4.006 4.006 0 0 0 3.7 3.7 48.656 48.656 0 0 0 7.324 0 4.006 4.006 0 0 0 3.7-3.7c.017-.22.032-.441.046-.662M4.5 12l3 3m-3-3-3 3" />
                                </svg>
                            </div>
                            <div>
                                <img onerror="this.src='/images/flags/_404.png'" :src="`/images/flags/${notification.country}.png`" class="w-5 inline mr-2 mb-0.5">

                                <span class="font-bold" v-html="q3tohtml(notification.name)"></span>

                                <span> broke your </span>

                                <div class="inline text-white rounded-full text-xs px-2 py-0.5 uppercase ml-0.5 font-bold" :class="{'bg-green-700': notification.physics.includes('cpm'), 'bg-blue-600': !notification.physics.includes('cpm')}">
                                    <span>{{ notification.physics }}</span>
                                </div>

                                <span> time on </span>

                                <Link class="inline text-blue-400 hover:text-blue-300 font-bold" :href="route('maps.map', notification.mapname)"> {{ notification.mapname }} </Link>

                                <span> with time <span class="font-bold">{{ formatTime(notification.time) }}</span> </span>

                                <span>
                                    (<span class="text-green-500">+{{ formatTime(timeDiff(notification.my_time, notification.time)) }}</span>)
                                </span>

                                <span class="text-sm text-gray-500"> {{ timeSince(notification.date_set) }} ago </span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex justify-center" v-if="notifications.length > 0">
                        <Pagination :current_page="notifications.current_page" :last_page="notifications.last_page" :link="notifications.first_page_url" />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
