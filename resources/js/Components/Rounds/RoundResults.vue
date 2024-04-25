<script setup>
    import DemoResultEntry from '@/Components/Rounds/DemoResultEntry.vue';
    import ResultTabs from '@/Components/Rounds/ResultTabs.vue';
    import { ref } from 'vue';

    const props = defineProps({
        round: Object,
        tournament: Object
    })

    const maxResults = ref(props.round.vq3_results?.length > props.round.cpm_results?.length ? props.round.vq3_results?.length : props.round.cpm_results?.length)
    const visibilityIndex = ref(maxResults.value)
    const revealed = ref(false)
    const revealOneByOne = ref(false)
    const speed = ref(2)

    const revealerWorking = ref(false)
    const revealer = ref(null);
    const slider = ref(0);

    const AttachSpoiler = () => {
        revealed.value = false;
        revealOneByOne.value = false;
        visibilityIndex.value = props.round.vq3_results?.length > props.round.cpm_results?.length ? props.round.vq3_results?.length : props.round.cpm_results?.length;
    
        revealerWorking.value = false;
        slider.value = 0;
        speed.value = 2;

        if (revealer.value) {
            clearInterval(revealer.value);
            revealer.value = null;
        }
    }

    const RevealAll = () => {
        visibilityIndex.value = -1;
        revealed.value = true;
    }

    const RevealOneByOne = () => {
        revealed.value = true;
        revealOneByOne.value = true;
    }

    const decreaseSpeed = () => {
        if (speed.value > 0) {
            speed.value -= 0.5;
        }
    }

    const increaseSpeed = () => {
        if (speed.value < 3) {
            speed.value += 0.5;
        }
    }

    const getStyle = () => {
        if (revealed.value === true) {
            return ''
        }

        return 'min-height: 300px; filter: blur(20px);'
    }

    const controlRevealer = () => {
        revealerWorking.value = !revealerWorking.value

        if (revealerWorking.value == true) {
            RevealerInterval();
            revealer.value = setInterval(RevealerInterval, (3.5 - speed.value) * 1000);
        } else {
            if (revealer.value) {
                clearInterval(revealer.value);
                revealer.value = null;
            }
        }
    }

    const RevealerInterval = () => {
        if (visibilityIndex.value > -1) {
            visibilityIndex.value--;
        } else {
            clearInterval(revealer.value);
            revealer.value = null;
            revealerWorking.value = false;
        }

        if (visibilityIndex.value <= -1) {
            slider.value = 500;
        } else if (visibilityIndex.value >= maxResults.value) {
            slider.value = 0;
        } else {
            slider.value = (500 * (maxResults.value - visibilityIndex.value - 1)) / maxResults.value;
        }
    }

    const SliderMouseEvent = (event) => {
        const sliderElement = event.currentTarget;
        const sliderWidth = sliderElement.clientWidth;
        const clickPosition = event.clientX - sliderElement.getBoundingClientRect().left;
        let percentage = (clickPosition / sliderWidth) * 100;
        
        percentage = percentage < 0 ? 0 : percentage;
        percentage = Math.ceil(percentage);

        if (percentage < 2) {
            percentage = 0;
        }
        
        if (percentage > 98) {
            percentage = 100;
        }

        visibilityIndex.value = maxResults.value - Math.floor((percentage * maxResults.value) / 100) - 1;

        slider.value = (percentage * 500) / 100;
    }
</script>

<template>
    <h1 class="font-black text-3xl text-white mb-3">Results</h1>

    <div class="tech-line-overview my-4"></div>

    <ResultTabs url="tournaments.rounds.index" :args="[tournament.id, round.id]" active="single" :tournament="tournament" />

    <div class="tech-line-overview my-4"></div>

    <div class="mb-5 pt-5 rounded-md flex justify-between px-3">
        <a :href="route('tournaments.results.anonymous.download', {tournament: tournament.id, round: round.id})" class="bg-white hover:bg-black hover:text-white text-black font-bold py-2 px-4 rounded-lg">Download Anonymous Results</a>
        <a :href="route('tournaments.results.download', {tournament: tournament.id, round: round.id})" class="bg-black hover:bg-white hover:text-black text-white font-bold py-2 px-4 rounded-lg">Download Results</a>
    </div>

    <div class="p-4 rounded-lg bg-blackop-30 my-5 flex flex-col justify-center items-center" v-if="revealOneByOne">
        <div class="flex justify-between mb-5">
            <button id="revealer-change-button" @click="controlRevealer" class="text-white bg-black hover:bg-gray-600 bg-white hover:bg-black hover:text-white text-black font-bold py-2 px-4 rounded-lg">
                {{ revealerWorking ? 'Stop' : 'Start' }}
            </button>
    
            <div class="rounded-lg px-2 py-1 flex items-center ml-5">
                <div class="cursor-pointer" @click="decreaseSpeed">
                    <svg fill="#ffffff" width="24px" height="24px" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" id="decrease-circle" class="icon glyph"><path d="M12,2A10,10,0,1,0,22,12,10,10,0,0,0,12,2Zm4.24,11H7.76a1,1,0,1,1,0-2h8.48a1,1,0,0,1,0,2Z"></path></svg>
                </div>
        
                <div class="mx-5 text-white">
                    Speed: {{ speed }}
                </div>
        
                <div class="cursor-pointer" @click="increaseSpeed">
                    <svg fill="#ffffff" width="24px" height="24px" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" id="increase-circle" class="icon glyph"><path d="M12,2A10,10,0,1,0,22,12,10,10,0,0,0,12,2Zm4.24,11H13v3.24a1,1,0,0,1-2,0V13H7.76a1,1,0,1,1,0-2H11V7.76a1,1,0,1,1,2,0V11h3.24a1,1,0,0,1,0,2Z"></path></svg>
                </div>
            </div>
        
            <div @click="AttachSpoiler" class="text-white rounded-lg px-2 py-1 flex items-center ml-5 hover:bg-grayop-700 cursor-pointer">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 24px; height: 24px;">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                </svg>
            </div>
        </div>
    
        <div class="ml-5 flex items-center">
            <div class="rounded-full bg-white relative px-2 inline w-2 h-4" :style="`left: ${slider}px`"></div>
            <div @click="SliderMouseEvent($event)" class="p-1 bg-grayop-700 rounded-lg hover:cursor-pointer hover:bg-grayop-500" style="width: 500px; max-width: 100%;"></div>
        </div>
    </div>
    
    <div class="w-full relative" style="top: 50px;" v-if="! revealed">
        <div class="absolute w-full text-white" style="z-index: 1000;">
            <div class="flex justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 100px; height: 100px;">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                </svg>
            </div>
    
            <h1 class="text-3xl text-center font-bold">Spoilers</h1>
    
            <div class="mt-10 text-center">
                <div class="mb-5">
                    <button @click="RevealOneByOne" class="bg-white hover:bg-black hover:text-white text-black font-bold py-2 px-4 rounded-lg">Reveal One By One</button>
                </div>

                <div>
                    <button @click="RevealAll" class="hover:text-gray-300 text-gray-400">Reveal Results Instantly</button>
                </div>
            </div>
        </div>
    </div>

    <div class="grid sm:grid-cols-2 gap-4 mt-10" :style="getStyle()">
        <div class="m-1 w-full p-2 pt-0 mr-6">
            <!-- Heading -->
            <div class="w-full flex items-center rounded-md bg-blue-900 bg-opacity-25 bg-opacity-15 p-2 shadow-md">
                <div class="uppercase font-black text-2xl text-blue-200 text-center w-full">vq3</div>
            </div>


            <DemoResultEntry v-for="(demo, index) in round.vq3_results" :demo="demo" :key="demo.id" v-show="index > visibilityIndex" />

            <div v-if="round.vq3_results?.length == 0">
                <div class="text-xl text-white mt-5 text-center">No Demos Submitted</div>
            </div>
        </div>

        <div class="m-1 w-full p-2 pt-0">
            <div class="w-full flex items-center rounded-md bg-green-900 bg-opacity-25 bg-opacity-15 p-2 shadow-md">
                <div class="uppercase font-black text-2xl text-green-200 text-center w-full">CPM</div>
            </div>

            <div v-for="(demo, index) in round.cpm_results" :key="demo.id">
                <DemoResultEntry :demo="demo" v-show="index > visibilityIndex" />
            </div>

            <div v-if="round.cpm_results?.length == 0">
                <div class="text-xl text-white mt-5 text-center">No Demos Submitted</div>
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