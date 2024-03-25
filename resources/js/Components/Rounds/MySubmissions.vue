<script setup>
    defineProps({
        round: Object,
        tournament: Object
    })
</script>

<template>
    <h1 class="font-black text-3xl dark:text-white mb-3">My Times</h1>

    <div class="tech-line-overview my-4"></div>

    <div class="grid sm:grid-cols-2 gap-4 mt-10">
        <div class="m-1 w-full p-2 pt-0 mr-6">
            <!-- Heading -->
            <div class="w-full flex items-center rounded-md bg-blue-900 dark:bg-opacity-25 bg-opacity-15 p-2">
                <div class="uppercase font-black text-2xl dark:text-blue-200 text-center w-full">vq3</div>
            </div>

            <div v-for="submission in round.submissions" class="w-full px-5 py-2 rounded-md bg-white bg-opacity-20 dark:bg-opacity-5 mb-1 flex items-center">
                <div class="w-10/12 inline-flex items-center truncate">
                    <div>
                        <div class="w-full flex items-center flex-nowrap">
                            <div class="text-xl q3shadow">{{ submission.time }}</div>
                        </div>
                        <div class="text-sm opacity-60 whitespace-nowrap">Uploaded <b>{{ submission.created }} ago</b></div>
                    </div>
                </div>

                <div class="w-2/12 inline-flex justify-end flex-wrap">
                    <a href="#" class="font-black text-lg leading-none mb-1 text-indigo-900 dark:text-indigo-100 hover:underline cursor-pointer">
                        Download
                    </a>
                </div>
            </div>

            <div v-if="round.submissions?.length == 0" class="w-full px-5 py-4 rounded-md text-center mb-1 flex items-center justify-center">
                <div class="inline-flex items-center truncate">
                    <div>
                        <div class="w-full flex items-center justify-center">
                            <div class="text-xl q3shadow text-center">No Demos Submitted</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="m-1 w-full p-2 pt-0">
            <div class="w-full flex items-center rounded-md bg-green-900 dark:bg-opacity-25 bg-opacity-15 p-2">
                <div class="uppercase font-black text-2xl dark:text-green-200 text-center w-full">CPM</div>
            </div>

            <div v-for="submission in round.submissions" class="w-full px-5 py-2 rounded-md bg-white bg-opacity-20 dark:bg-opacity-5 mb-1 flex items-center">
                <div class="w-10/12 inline-flex items-center truncate">
                    <div>
                        <div class="w-full flex items-center flex-nowrap">
                            <div class="text-xl q3shadow">{{ submission.time }}</div>
                        </div>
                        <div class="text-sm opacity-60 whitespace-nowrap">Uploaded <b>{{ submission.created }} ago</b></div>
                    </div>
                </div>

                <div class="w-2/12 inline-flex justify-end flex-wrap">
                    <a href="#" class="font-black text-lg leading-none mb-1 text-indigo-900 dark:text-indigo-100 hover:underline cursor-pointer">
                        Download
                    </a>
                </div>
            </div>

            <div v-if="round.submissions?.length == 0" class="w-full px-5 py-4 rounded-md text-center mb-1 flex items-center justify-center">
                <div class="inline-flex items-center truncate">
                    <div>
                        <div class="w-full flex items-center justify-center">
                            <div class="text-xl q3shadow text-center">No Demos Submitted</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</template>

<style scoped>
    .tech-line-overview {
        background: linear-gradient(90deg,#ffffff42,#2f90ff,#ffffff42);
        box-shadow: 0 0 50px 4px #0a7cffb2;
        height: 1px;
        max-width: 100%;
    }
</style>