<script setup>
    const props = defineProps({
        demo: Object
    });
</script>

<template>
    <div class="px-2 py-1 mx-auto bg-blackop-30">
        <div class="w-full px-5 py-2 rounded-md mb-1 flex items-center">
            <div class="w-10/12 inline-flex items-center truncate">
                <div>
                    <div class="w-full flex items-center flex-nowrap">
                        <div class="text-xl text-white">{{ formatTime(demo.time) }}</div>
                    </div>
                    <div class="text-sm text-gray-500">Uploaded <b>{{ timeSince(demo.created_at) }} ago</b></div>
                </div>
            </div>

            <div class="w-2/12 inline-flex justify-end flex-wrap">
                <a href="#" class="font-black text-lg leading-none mb-1 text-indigo-900 dark:text-indigo-100 hover:underline cursor-pointer">
                    Download
                </a>
            </div>
        </div>
    </div>
</template>