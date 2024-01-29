<script setup>
    import { Link } from '@inertiajs/vue3';
    import { computed } from 'vue';
    import MapCard from './MapCard.vue';

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
        <div class="rounded-md p-1">
            <div class="flex text-gray-400 items-center">
                <span>#</span>
                <div class="ml-2 font-bold mr-2 text-white">{{ record.rank }}</div>
                <Link :class="{'text-xs': record.mapname.length > 16}" class="ml-4 text-blue-400 hover:text-blue-300 font-bold" :href="route('maps.map', record.mapname)"> {{ record.mapname }} </Link>
            </div>

            <div class="flex justify-between items-center mt-2">
                <Link class="flex rounded-md" href="#">
                    <div class="flex justify-between items-center">
                        <div>
                            <img :src="`/images/flags/${bestrecordCountry}.png`" class="w-5 inline mr-2" onerror="this.src='/images/flags/_404.png'" :title="bestrecordCountry">
                            <a class="font-bold text-white" href="#" v-html="q3tohtml(record.user?.name ?? record.name)"></a>
                        </div>
                    </div>
                </Link>
                <div class="text-lg font-bold text-gray-300">{{  formatTime(record.time) }}</div>
            </div>
            

            <div class="flex items-center justify-between mt-2">
                <div class="text-white rounded-full text-xs px-2 py-0.5 uppercase font-bold" :class="{'bg-green-700': record.physics.includes('cpm'), 'bg-blue-600': !record.physics.includes('cpm')}">
                    <div>{{ record.physics }}</div>
                </div>

                <div class="text-gray-400 text-xs text-center" :title="record.date_set"> {{ timeSince(record.date_set) }} ago</div>

                <div class="rounded-full text-xs px-2 py-0.5 uppercase font-bold bg-gray-300 text-black">
                    <div>{{ record.mode }}</div>
                </div>
            </div>
        </div>
    
        <hr class="my-2 text-gray-700 border-gray-700 bg-gray-700">
    </div>
</template>