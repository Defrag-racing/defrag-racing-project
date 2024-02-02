<script setup>
    import { Head } from '@inertiajs/vue3';
    import ProfileRecord from '@/Components/ProfileRecord.vue';

    const props = defineProps({
        user: Object,
        records: Object,
    });
</script>

<template>
    <div>
        <Head title="Profile" />

        <div class="max-w-8xl mx-auto py-5 sm:px-6 lg:px-8">
            <div class="mt-10">
                <div class="flex justify-center">
                    <!-- <div class="mr-10">
                        <div class="text-sm w-16 h-16 flex flex-col items-center justify-center border-green-500 text-white rounded-full p-1 relative">
                            <span class="font-bold">19555</span>
                            <span>Records</span>
                            <div class="effect-cpm"></div>
                        </div>
                    </div> -->

                    <div>
                        <div class="flex flex-col items-center p-4 relative">
                            <div class="profile-effect"></div>
                            <img style="z-index: 2;" class="h-24 w-24 rounded-full border-4 border-gray-500 object-cover" :src="user.profile_photo_path ? '/storage/' + user.profile_photo_path : '/images/null.jpg'" :alt="user.name">

                            <div class="text-gray-500 absolute" style="width: 300px; top: 5%">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor"  viewBox="0 0 750 300">
                                    <path d="M349.81,251.94H246.47c-7.16-8-13.77-16.62-21.47-24.1C211,214.19,192.41,204.26,172.82,204c-8-.09-16.33,1.61-22.38,6.8s-9,14.51-5.1,21.48a20.6,20.6,0,0,0,6,6.38c4.32,3.19,9.57,5.48,14.93,5.16S177,240.15,178.55,235c-.86.34-11,4.14-17.1-.87a9.23,9.23,0,0,1-3.46-5.21,8.91,8.91,0,0,1,1.49-6.74c4.37-5.28,11.93-6.81,18.75-6.17,5.63.52,11.37,2.44,15.23,6.58s5.29,10.81,2.3,15.6a19.33,19.33,0,0,1,22.49-1.37c5.32,3.51,8.24,9.13,10.75,15.12H109.76A773.53,773.53,0,0,0,30.82,256l-13.3,1.37,13.3,1.37q35.13,3.61,70.47,4a24.06,24.06,0,0,0-13.53,6.78,41,41,0,0,1,21.7-.95c10.57,2.43,19.71,8.93,29.87,12.72,11,4.09,23.37,4.86,34.47,1.09A40.92,40.92,0,0,0,197,262.78h36.48a13.87,13.87,0,0,1-.77,8.71,14.67,14.67,0,0,1-8.16,7.38,10.24,10.24,0,0,1-6.31.19,6.83,6.83,0,0,1-4.45-4.29c-.89-2.94.88-6.29,3.63-7.66-4.51-.92-9,.55-11.3,3.81a10,10,0,0,0-1.71,6.54c.38,4.62,3.87,7.31,4.8,8a13.51,13.51,0,0,0,4.76,2.3c7.52,2.08,15.11-1.58,15.11-1.58a23.33,23.33,0,0,0,10.78-10.95c.28-.58.51-1.16.72-1.73a63,63,0,0,0,18.82,14.3c14.67,7.53,32.08,10.5,48,6,21.07-5.93,30.23-24.49,33-31.08h9.45a5.42,5.42,0,0,0,0-10.84ZM154.27,274.06c-7.78-.41-15.23-3.11-22.54-5.81-6.06-2.23-12.2-4.47-18.55-5.47h68.66C175.27,270.57,164.55,274.6,154.27,274.06Zm168.67,1c-7.41,4.07-16.14,5.21-24.58,4.7a68.51,68.51,0,0,1-35.52-12.6c-1.94-1.39-3.78-2.87-5.57-4.42H335A29.34,29.34,0,0,1,322.94,275.1Z" />
                                    <path d="M732.48,257.36,719.18,256a773.53,773.53,0,0,0-78.94-4.05H521c2.51-6,5.43-11.61,10.75-15.12a19.33,19.33,0,0,1,22.49,1.37c-3-4.79-1.56-11.47,2.3-15.6s9.6-6.06,15.23-6.58c6.82-.64,14.38.89,18.75,6.17a8.91,8.91,0,0,1,1.49,6.74,9.23,9.23,0,0,1-3.46,5.21c-6.1,5-16.24,1.21-17.1.87,1.55,5.15,6.92,8.54,12.29,8.86s10.61-2,14.93-5.16a20.6,20.6,0,0,0,6-6.38c3.86-7,.94-16.29-5.1-21.48S585.15,204,577.18,204c-19.59.22-38.13,10.15-52.18,23.8-7.7,7.48-14.31,16.1-21.47,24.1H400.19a5.42,5.42,0,0,0,0,10.84h9.45c2.79,6.59,11.95,25.15,33,31.08,15.88,4.47,33.29,1.5,48-6a63,63,0,0,0,18.82-14.3c.21.57.44,1.15.72,1.73a23.33,23.33,0,0,0,10.78,10.95s7.59,3.66,15.11,1.58a13.51,13.51,0,0,0,4.76-2.3c.93-.72,4.42-3.41,4.8-8a10,10,0,0,0-1.71-6.54c-2.34-3.26-6.79-4.73-11.3-3.81,2.75,1.37,4.52,4.72,3.63,7.66a6.83,6.83,0,0,1-4.45,4.29,10.24,10.24,0,0,1-6.31-.19,14.67,14.67,0,0,1-8.16-7.38,13.87,13.87,0,0,1-.77-8.71H553a40.92,40.92,0,0,0,23.18,19.59c11.1,3.77,23.48,3,34.47-1.09,10.16-3.79,19.3-10.29,29.87-12.72a41,41,0,0,1,21.7.95,24.06,24.06,0,0,0-13.53-6.78q35.31-.39,70.47-4ZM487.16,267.2a68.51,68.51,0,0,1-35.52,12.6c-8.44.51-17.17-.63-24.58-4.7A29.34,29.34,0,0,1,415,262.78h77.75C490.94,264.33,489.1,265.81,487.16,267.2Zm108.57,6.86c-10.28.54-21-3.49-27.57-11.28h68.66c-6.35,1-12.49,3.24-18.55,5.47C611,271,603.51,273.65,595.73,274.06Z" />
                                    <circle cx="167.94" cy="226.68" r="5.52" />
                                    <path d="M186,226a3.87,3.87,0,1,0-3.87,3.88A3.87,3.87,0,0,0,186,226Z" />
                                    <circle cx="582.06" cy="226.68" r="5.52" />
                                    <path d="M567.85,222.13a3.88,3.88,0,1,0,3.87,3.87A3.87,3.87,0,0,0,567.85,222.13Z" />
                                </svg>
                            </div>
                        </div>

                        <div class="flex items-center">
                            <div>
                                <img onerror="this.src='/images/flags/_404.png'" :src="`/images/flags/${user.country}.png`" :title="user.country" class="w-7 inline mr-2 mb-0.5">
                            </div>
                            <div class="text-2xl font-medium text-gray-900 dark:text-gray-100" v-html="q3tohtml(user.name)"></div>
                        </div>
                    </div>

                    <!-- <div class="ml-10">
                        <div class="text-sm w-16 h-16 flex flex-col items-center justify-center border-green-500 text-white rounded-full p-1 relative">
                            <span class="font-bold">19555</span>
                            <span>Records</span>
                            <div class="effect-vq3"></div>
                        </div>
                    </div> -->
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-5">
                <div class="col-span-1">
                    <div class="text-center text-xl font-medium text-gray-900 dark:text-gray-100">CPM</div>
                    <div class="tech-line-cpm mb-4"></div>

                    <div class="text-gray-400 text-sm"> 
                        <div class="flex justify-between">
                            <span class="mr-1">Records Num</span>
                            <span>22500</span>
                        </div>

                        <div class="hr-cpm w-full my-4"></div>

                        <div class="flex justify-between">
                            <span class="mr-1">Avg Rank</span>
                            <span>500</span>
                        </div>

                        <div class="hr-cpm w-full my-4"></div>
                        
                        <div class="flex justify-between">
                            <span class="mr-1">World Records</span>
                            <span>250</span>
                        </div>
                    </div>
                </div>

                <div class="col-span-1 md:col-span-3 mt-10">
                    <div class="text-center text-xl font-medium text-gray-900 dark:text-gray-100">Records</div>
                    <div class="tech-line-plain mx-10 my-4">
                        <ProfileRecord v-for="record in records.data" :key="record.id" :record="record" />
                    </div>
                </div>

                <div class="col-span-1">
                    <div class="text-center text-xl font-medium text-gray-900 dark:text-gray-100">VQ3</div>
                    <div class="tech-line-vq3 mb-4"></div>

                    <div class="text-gray-400 text-sm"> 
                        <div class="flex justify-between">
                            <span class="mr-1">Records Num</span>
                            <span>22500</span>
                        </div>

                        <div class="hr-vq3 w-full my-4"></div>

                        <div class="flex justify-between">
                            <span class="mr-1">Avg Rank</span>
                            <span>500</span>
                        </div>

                        <div class="hr-vq3 w-full my-4"></div>
                        
                        <div class="flex justify-between">
                            <span class="mr-1">World Records</span>
                            <span>250</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style>
    .profile-effect {
        transition: all .5s ease-in-out;
        box-shadow: 0 0 100px 40px #20f555b3;
        height: 1px;
        left: 50%;
        top: 50%;
        position: absolute;
        width: 1px;
        z-index: 1;
    }

    .tech-line-cpm {
        background: linear-gradient(90deg,#ffffff42,#7dff88,#ffffff42);
        box-shadow: 0 0 50px 4px #0aff336f;
        height: 1px;
        margin-top: 5px;
        max-width: 100%;
    }

    .tech-line-vq3 {
        background: linear-gradient(90deg,#ffffff42,#5171ff,#ffffff42);
        box-shadow: 0 0 50px 4px #0a0eff6f;
        height: 1px;
        margin-top: 5px;
        max-width: 100%;
    }

    .tech-line-plain {
        background: linear-gradient(90deg,#ffffff42,#ffffff,#ffffff42);
        box-shadow: 0 0 60px 4px #ffffff6f;
        height: 1px;
        margin-top: 5px;
        max-width: 100%;
    }

    .hr-cpm {
        height: 1px;
        background: linear-gradient(90deg,#374151,rgb(85, 134, 91),#374151);
    }

    .hr-vq3 {
        height: 1px;
        background: linear-gradient(90deg,#374151,rgb(61, 84, 161),#374151);
    }
</style>