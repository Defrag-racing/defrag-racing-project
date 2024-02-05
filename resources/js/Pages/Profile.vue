<script setup>
    import { ref, watch } from 'vue';
    import { Head, router } from '@inertiajs/vue3';
    import ProfileRecord from '@/Components/ProfileRecord.vue';
    import Pagination from '@/Components/Basic/Pagination.vue';

    const props = defineProps({
        user: Object,
        records: Object,
        type: String,
        profile: Object,
        cpm_world_records: {
            type: Number,
            default: 0
        },
        vq3_world_records: {
            type: Number,
            default: 0
        },
        hasProfile: Boolean
    });

    const options = ref({
        'latest': {
            label: 'Latest Records',
            icon: 'backintime',
            color: 'text-gray-400'
        },
        'recentlybeaten': {
            label: 'Recently Beaten',
            icon: 'retweet',
            color: 'text-gray-400'
        },
        'tiedranks': {
            label: 'Tied Ranks',
            icon: 'circle-equal',
            color: 'text-gray-400'
        },
        'activityhistory': {
            label: 'Activity History',
            icon: 'linegraph',
            color: 'text-gray-400'
        },
        'bestranks': {
            label: 'Best Ranks',
            icon: 'trophy',
            color: 'text-green-400'
        },
        'besttimes': {
            label: 'Best Times',
            icon: 'stopwatch',
            color: 'text-green-400'
        },
        'worstranks': {
            label: 'Worest Ranks',
            icon: 'trash',
            color: 'text-red-400'
        },
        'worsttimes': {
            label: 'Worst Times',
            icon: 'hourglass',
            color: 'text-red-400'
        },
    });

    const stats = [
        {
            label: 'Total Records',
            value: 'total_records',
            icon: 'infinity',
            type: 'svg'
        },
        {
            label: 'Fastcaps Records',
            value: 'ctf_records',
            icon: 'flag',
            type: 'svg'
        },
        {
            label: 'World Records',
            value: 'world_records',
            icon: 'trophy',
            type: 'svg',
            wr: true
        },
        {
            label: 'Strafe Records',
            value: 'strafe_records',
            icon: 'strafe',
            type: 'imgsvg'
        },
        {
            label: 'Rocket Records',
            value: 'rocket_records',
            icon: 'rl',
            type: 'item'
        },
        {
            label: 'Grenade Records',
            value: 'grenade_records',
            icon: 'gl',
            type: 'item'
        },
        {
            label: 'Plasma Records',
            value: 'plasma_records',
            icon: 'pg',
            type: 'item'
        },
        {
            label: 'BFG Records',
            value: 'bfg_records',
            icon: 'bfg',
            type: 'item'
        },
    ];

    const selectedOption = ref(props.type || 'latest');

    const selectOption = (option) => {
        router.reload({ data: { type: option, page: 1 } })
    }

    watch(() => props.type, (newVal) => {
        if (options.value[newVal]) {
            selectedOption.value = newVal;
        } else {
            selectedOption.value = 'latest';
        }
    });

</script>

<template>
    <div>
        <Head title="Profile" />

        <div class="max-w-8xl mx-auto py-5 sm:px-6 lg:px-8">
            <div class="mt-10">
                <div class="flex justify-center">
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

                        <div class="flex items-center justify-center">
                            <div>
                                <img onerror="this.src='/images/flags/_404.png'" :src="`/images/flags/${user.country}.png`" :title="user.country" class="w-7 inline mr-2 mb-0.5">
                            </div>
                            <div class="text-2xl font-medium text-gray-900 dark:text-gray-100" v-html="q3tohtml(user.name)"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-5 mx-5 lg:mx-0">
                <div class="col-span-2 lg:col-span-1 mt-10 lg:mt-0 order-0 lg:order-0">
                    <div class="text-center text-xl font-medium text-gray-900 dark:text-gray-100">CPM</div>
                    <div class="tech-line-cpm mb-4"></div>

                    <div class="text-gray-400 text-sm"> 
                        <div v-for="stat in stats">
                            <div class="flex justify-between">
                                <div class="flex">
                                    <svg v-if="stat.type == 'svg'" class="fill-current w-5 h-5 mr-2" viewBox="0 0 20 20"><use :xlink:href="`/images/svg/icons.svg#icon-` + stat.icon"></use></svg>
                                    <img v-if="stat.type == 'imgsvg'" class="fill-current w-5 mr-2" src="/images/svg/strafe.svg">
                                    <div v-if="stat.type == 'item'" :class="`sprite-items sprite-${stat.icon} w-4 h-4 mr-3`"></div>
                                    <span class="mr-1"> {{ stat.label }} </span>
                                </div>
                                <span v-if="!stat.wr">{{ ( profile?.hasOwnProperty('cpm_' + stat.value) ) ? profile['cpm_' + stat.value] : 0 }}</span>
                                <span v-else>{{ cpm_world_records }}</span>
                            </div>
    
                            <div class="hr-cpm w-full my-4"></div>
                        </div>
                    </div>
                </div>

                <div class="col-span-2 lg:col-span-3 mt-10 mx-0 lg:mx-10 order-2 lg:order-1">
                    <div class="grid grid-cols-4 gap-1 mb-10">
                        <div v-for="(data, option) in options" :key="option">
                            <button @click="selectOption(option)" v-if="selectedOption !== option" :class="`cursor-pointer group w-full z-10 relative overflow-hidden rounded-md bg-gray-700 bg-opacity-30 hover:bg-opacity-50 p-5 ` + data.color">
                                <div class="flex items-center transition-transform group-hover:scale-110">
                                    <svg class="fill-current w-6 h-6" viewBox="0 0 20 20"><use :xlink:href="`/images/svg/icons.svg#icon-` + data.icon"></use></svg>
                                    <div class="font-semibold w-full text-center">{{ data.label }} </div>
                                </div>

                                <div class="absolute -left-1 -top-1 w-24 h-24 z-0 opacity-15 transition-transform group-hover:scale-150">
                                    <svg class="text-black fill-current w-full h-full" viewBox="0 0 20 20"><use :xlink:href="`/images/svg/icons.svg#icon-` + data.icon"></use></svg>
                                </div>
                            </button>

                            <button v-else :class="`cursor-pointer group w-full z-10 relative rounded-md bg-gray-400 p-5  text-gray-900`">
                                <div class="flex items-center transition-transform group-hover:scale-110">
                                    <svg class="fill-current w-6 h-6" viewBox="0 0 20 20"><use :xlink:href="`/images/svg/icons.svg#icon-` + data.icon"></use></svg>
                                    <div class="font-semibold w-full text-center">{{ data.label }} </div>
                                </div>

                                <div class="absolute -left-1 -top-1 w-24 h-24 z-0 opacity-15">
                                    <svg class="text-black fill-current w-full h-full" viewBox="0 0 20 20"><use :xlink:href="`/images/svg/icons.svg#icon-` + data.icon"></use></svg>
                                </div>
                            </button>
                        </div>
                    </div>

                    <div class="tech-line-plain my-4"></div>


                    <div v-if="hasProfile && records.total > 0">
                        <div>
                            <ProfileRecord v-for="record in records.data" :key="record.id" :record="record" />
                        </div>
    
                        <div class="mt-5 flex justify-center">
                            <Pagination :last_page="records.last_page" :current_page="records.current_page" :link="records.first_page_url" />
                        </div>
                    </div>

                    <div v-else>
                        <div class="text-center text-gray-400">
                            This player has no records !
                        </div>
                    </div>
                </div>

                <div class="col-span-2 lg:col-span-1 mt-10 lg:mt-0 order-1 lg:order-2">
                    <div class="text-center text-xl font-medium text-gray-900 dark:text-gray-100">VQ3</div>
                    <div class="tech-line-vq3 mb-4"></div>

                    <div class="text-gray-400 text-sm"> 
                        <div v-for="stat in stats">
                            <div class="flex justify-between">
                                <div class="flex">
                                    <svg v-if="stat.type == 'svg'" class="fill-current w-5 h-5 mr-2" viewBox="0 0 20 20"><use :xlink:href="`/images/svg/icons.svg#icon-` + stat.icon"></use></svg>
                                    <img v-if="stat.type == 'imgsvg'" class="fill-current w-5 mr-2" src="/images/svg/strafe.svg">
                                    <div v-if="stat.type == 'item'" :class="`sprite-items sprite-${stat.icon} w-4 h-4 mr-3`"></div>
                                    <span class="mr-1"> {{ stat.label }} </span>
                                </div>
                                <span v-if="!stat.wr">{{ profile ? profile['vq3_' + stat.value] : 0 }}</span>
                                <span v-else>{{ vq3_world_records }}</span>
                            </div>
    
                            <div class="hr-vq3 w-full my-4"></div>
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

    .selected-button-effect {
        transition: all .5s ease-in-out;
        box-shadow: 0 0 70px 30px #ffffffb3;
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