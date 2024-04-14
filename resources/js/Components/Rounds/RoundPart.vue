<script setup>
    import { ref, reactive, onMounted } from 'vue';
    import { Link } from '@inertiajs/vue3';
    import moment from 'moment';

    const props = defineProps({
        round: Object,
        title: String
    });

    let seconds = ref(0);

    const showCountdown = ref(false);

    const countdownData = reactive({
        days: 0,
        hours: 0,
        minutes: 0,
        seconds: 0
    })

    const countdownTimer = ref(null);

    const initCountdown = () => {
        if (props.round.active) {
            seconds.value = props.round.seconds_till_finish ?? 0;
            countdown();
            showCountdown.value = true;

            setInterval(() => {
                seconds.value--;
                countdown();
            }, 1000);
        }
    }

    onMounted(() => {
        initCountdown();
    });

    const displayTime = ref('UTC')

    const getDate = (date) => {
        moment.tz.setDefault("UTC");
        const inputDate = moment(date, 'YYYY-MM-DD HH:mm:ss');

        const localDate = inputDate.clone().local();

        return displayTime.value === 'Local' ? localDate.format("YYYY-MM-DD HH:mm") : inputDate.format("YYYY-MM-DD HH:mm");
    }

    function countdown() {
        let secs = seconds.value;

        if (seconds.value < 0) {
            countdownData.days = 0;
            countdownData.hours = 0;
            countdownData.minutes = 0;
            countdownData.seconds = 0;
            return;
        }

        const oneMinute = 60;
        const oneHour = oneMinute * 60;
        const oneDay = oneHour * 24;

        const days = Math.floor(secs / oneDay);
        secs %= oneDay;

        const hours = Math.floor(secs / oneHour);
        secs %= oneHour;

        const minutes = Math.floor(secs / oneMinute);
        secs %= oneMinute;

        countdownData.days = days;
        countdownData.hours = hours;
        countdownData.minutes = minutes;
        countdownData.seconds = secs;
    }

    const changeDisplayTime = () => {
        displayTime.value = displayTime.value === 'Local' ? 'UTC' : 'Local';
    }
</script>

<template>
    <div class="mb-5 p-2 rounded-lg mx-auto">
        <div class="flex justify-between">
            <div class="font-black text-3xl text-white">{{ round.name }}</div>
        </div>
    
        <div class="tech-line-overview mt-4 mb-8"></div>
    
        <div class="md:flex justify-between">
            <div class="w-1/2 mx-auto">
                <a class="block p-2 text-center" href="#">
                    <img :src="`/storage/${round.image}`" class="rounded-md border-2 border-gray-700">
                </a>
                <div class="p-2 text-gray-500">
                    <div class="text-center font-bold text-sm"> {{ round.mapname }} </div>
                    <div class="text-center text-sm mt-1"> By 
                        <Link :href="route('maps.filters', { author: round.author })" class="hover:link-shadow text-gray-400 hover:text-gray-300" v-html="q3tohtml(round.author)"></Link>
                    </div>
                </div>
            </div>
    
            <div class="p-2 rounded-lg flex-grow ml-3">
                <div class="w-full text-left text-sm rounded-lg">
                    <div class="flex flex-col items-center" v-if="showCountdown">
                        <div class="font-semibold text-lg text-white uppercase tracking-widest mb-2">FINISHES AT</div>
                        <div class="font-semibold text-lg text-white">
                            <div class="grid grid-flow-col gap-5 text-center auto-cols-max">
                                <div class="flex flex-col">
                                    <span class="countdown font-mono text-5xl">
                                        {{ countdownData.days }}
                                    </span>
                                    <span>DAYS</span>
                                </div>
                            
                                <div class="flex flex-col">
                                    <span class="countdown font-mono text-5xl">
                                        {{ countdownData.hours }}
                                    </span>
                                    <span>HOURS</span>
                                </div> 
                                <div class="flex flex-col">
                                    <span class="countdown font-mono text-5xl">
                                        {{ countdownData.minutes }}
                                    </span>
                                    <span>MIN</span>
                                </div> 
                                <div class="flex flex-col">
                                    <span class="countdown font-mono text-5xl">
                                        {{ countdownData.seconds }}
                                    </span>
                                    <span>SEC</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="my-1.5" style="width: 100%; height: 1px; background-color: #72727244"></div>

                    <div class="flex">
                        <div scope="row" class="text-lg text-gray-400" style="min-width: 150px;">
                            Start
                        </div>
                        <div class="text-lg text-white flex-grow flex items-center">
                            <span class="mr-4">{{ getDate(round.start_date) }}</span>
                            <div class="h-4 w-4 rounded-full bg-blue-600"></div>
                            <span @click="changeDisplayTime" class="py-1 px-2 rounded-lg text-gray-500 hover:text-white hover:cursor-pointer">
                                {{ displayTime }}
                            </span>
                        </div>
                    </div>
    
                    <div class="my-1.5" style="width: 100%; height: 1px; background-color: #72727244"></div>
    
                    <div class="flex">
                        <div scope="row" class="text-lg text-gray-400" style="min-width: 150px;">
                            Finish
                        </div>
                        <div class="text-lg text-white flex-grow flex items-center">
                            <span class="mr-4">{{ getDate(round.end_date) }}</span>
                            <div class="h-4 w-4 rounded-full bg-blue-600"></div>
                            <span @click="changeDisplayTime" class="py-1 px-2 rounded-lg text-gray-500 hover:text-white hover:cursor-pointer">
                                {{ displayTime }}
                            </span>
                        </div>
                    </div>
    
                    <div class="my-1.5" style="width: 100%; height: 1px; background-color: #72727244"></div>

                    <div class="flex">
                        <div scope="row" class="text-lg text-gray-400" style="min-width: 150px;">
                            Type
                        </div>
                        <div class="text-lg text-white flex-grow">
                            {{ round.category }} Map
                        </div>
                    </div>
    
                    <div class="my-1.5" style="width: 100%; height: 1px; background-color: #72727244"></div>

                    <div class="flex">
                        <div scope="row" class="text-lg text-gray-400" style="min-width: 150px;">
                            Download
                        </div>
                        <div class="text-lg text-white flex-grow">
                            <div v-for="pk3 in round.maps">
                                <a v-if="pk3.external" :download="pk3.download_name" :href="'https://dl.defrag.racing/downloads/maps/' + pk3.pk3.split('/').pop()" class="text-indigo-400 hover:underline mr-3">
                                    {{ pk3.name }}
                                </a>
                                <a v-else target="_blank" :href="route('tournaments.maps.download', pk3.id)" class="text-indigo-400 hover:underline mr-3">
                                    {{ pk3.name }}
                                </a>
                            </div>
                        </div>
                    </div>
    
                    <div class="my-1.5" style="width: 100%; height: 1px; background-color: #72727244"></div>
    
                    <div class="flex">
                        <div scope="row" class="text-lg text-gray-400" style="min-width: 150px;">
                            Details
                        </div>
                        <div class="text-lg text-white flex-grow">
                            <div class="flex flex-wrap justify-start">
                                <div v-if="round.weapons.length > 0" class="flex flex-wrap items-center mr-4">
                                    <div v-for="item in round.weapons.split(',')" :class="`sprite-items sprite-${item} w-5 h-5 mr-1 mb-1`" :title="item"></div>
                                </div>

                                <div v-if="round.items.length > 0" class="flex flex-wrap items-center mr-4">
                                    <div v-for="item in round.items.split(',')" :class="`sprite-items sprite-${item} w-5 h-5 mr-1 mb-1`" :title="item"></div>
                                </div>

                                <div v-if="round.functions.length > 0" class="flex flex-wrap items-center mr-4">
                                    <div v-for="item in round.functions.split(',')" :class="`sprite-items sprite-${item} w-5 h-5 mr-1 mb-1`" :title="item"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="my-1.5" style="width: 100%; height: 1px; background-color: #72727244"></div>

                    <slot name="additional" />
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