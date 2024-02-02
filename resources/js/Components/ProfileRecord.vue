<script setup>
    import { Link } from '@inertiajs/vue3';
    import { computed } from 'vue';
    import MapCard from './MapCard.vue';

    const props = defineProps({
        record: Object
    });

    const timeDiff =  computed(() => {
        if (! props.record.besttime === -1) {
            return null;
        }

        return Math.abs(props.record.besttime - props.record.time)
    });
</script>

<template>
    <div>
        <div class="flex justify-between rounded-md p-2 items-center">
            <div class="mr-4 flex items-center">
                <div class="font-bold text-white text-lg w-11">{{ record.rank }}</div>

                <div style="width: 170px;">
                    <Popper placement="left" arrow hover :disabled="record.map == null" style="z-index: 100;">
                        <div class="flex items-center">
                            <div class="text-gray-400 mr-2">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                                </svg>
                            </div>
                            <Link class="flex-grow font-bold text-blue-400 hover:underline hover:text-blue-300" :href="route('maps.map', record.mapname)">
                                <span>{{  record.mapname }}</span>
                            </Link>
                        </div>
                
                
                        <template #content>
                            <div class="p-1 pt-3">
                                <MapCard :transparent="false" :map="record.map" v-if="record.map !== null" />
                            </div>
                        </template>
                    </Popper>
                </div>
            </div>

            <div class="ml-4">
                <div class="text-gray-400 text-xs" :title="record.date_set"> {{ timeSince(record.date_set) }} ago</div>
            </div>

            <div class="flex items-center">
                <div class="text-right mr-5">
                    
                </div>

                <div class="text-right ml-5">
                    <div class="text-lg font-bold text-gray-300 text-right" style="width: 100px;">{{  formatTime(record.time) }}</div>
                    <div class="text-xs text-red-500" v-if="timeDiff">- {{  formatTime(timeDiff) }}</div>
                </div>

                <div class="ml-5">
                    <div class="text-white rounded-full text-xs px-2 py-0.5 uppercase font-bold" :class="{'bg-green-700': record.physics.includes('cpm'), 'bg-blue-600': !record.physics.includes('cpm')}">
                        <div>{{ record.physics }}</div>
                    </div>
                    <div class="rounded-full text-xs px-2 py-0.5 uppercase font-bold bg-gray-300 text-black mt-1">
                        <div>{{ record.mode }}</div>
                    </div>
                </div>
            </div>
        </div>
    
        <hr class="my-2 text-gray-700 border-gray-700 bg-gray-700">
    </div>
</template>