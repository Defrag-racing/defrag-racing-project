<script setup>
    import { Link } from '@inertiajs/vue3';
    import { computed } from 'vue';
    import MapCard from './MapCard.vue';
    import { useClipboard } from '@/Composables/useClipboard';

    const { copy, copyState } = useClipboard();

    const props = defineProps({
        record: Object
    });


    const bestrecordCountry = computed(() => {
        let country = props.record.user?.country ?? props.record.country;

        return (country == 'XX') ? '_404' : country;
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
        <div class="flex justify-between rounded-md p-2 items-center group">
            <div class="mr-4 flex items-center">
                <div class="font-bold text-white text-lg w-11">{{ record.rank }}</div>
                <img class="h-10 w-10 rounded-full object-cover" :src="record.user?.profile_photo_path ? '/storage/' + record.user?.profile_photo_path : '/images/null.jpg'" :alt="record.user?.name ?? record.name">
                
                <div class="ml-4">
                    <Link class="flex rounded-md" :href="route(record.user ? 'profile.index' : 'profile.mdd', record.user ? record.user.id : record.mdd_id)">
                        <div class="flex justify-between items-center">
                            <div>
                                <img :src="`/images/flags/${bestrecordCountry}.png`" class="w-5 inline mr-2" onerror="this.src='/images/flags/_404.png'" :title="bestrecordCountry">
                                <Link class="font-bold text-white" :href="route(record.user ? 'profile.index' : 'profile.mdd', record.user ? record.user.id : record.mdd_id)" v-html="q3tohtml(record.user?.name ?? record.name)"></Link>
                            </div>
                        </div>
                    </Link>
    
                    <div class="text-gray-400 text-xs mt-2" :title="record.date_set"> {{ timeSince(record.date_set) }} ago</div>
                </div>
            </div>

            <div class="flex items-center">
                <div class="text-right mr-5">
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
                                <Popper :closeDelay="300" hover style="z-index: 3;" >
                                    <div class="transition-all duration-500 ease-in-out opacity-0 group-hover:opacity-100 cursor-pointer text-gray-400 hover:text-green-500 ml-2" @click="copy(record.mapname)">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 8.25V6a2.25 2.25 0 0 0-2.25-2.25H6A2.25 2.25 0 0 0 3.75 6v8.25A2.25 2.25 0 0 0 6 16.5h2.25m8.25-8.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-7.5A2.25 2.25 0 0 1 8.25 18v-1.5m8.25-8.25h-6a2.25 2.25 0 0 0-2.25 2.25v6" />
                                        </svg>                      
                                    </div>
                            
                            
                                    <template #content>
                                        <div class="copy-text px-4 py-2 rounded-md bg-blackop-80">
                                            <div class="text-gray-400">
                                                <span v-if="!copyState">Copy</span>
                                                <span v-else class="text-green-400">Copied!</span>
                                            </div>
                                        </div>
                                    </template>
                                </Popper>
                            </div>
                    
                    
                            <template #content>
                                <div class="p-1 pt-3">
                                    <MapCard :transparent="false" :map="record.map" v-if="record.map !== null" />
                                </div>
                            </template>
                        </Popper>
                    </div>
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

<style scoped>
.copy-text{
    font-size: 14px;
    font-weight: 400;
    z-index: 999;
}
</style> 