<script setup>
    import { Link } from '@inertiajs/vue3';
    import { computed } from 'vue';

    const props = defineProps({
        record: Object,
        cpmrecord: Object,
        vq3record: Object
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
        <div class="flex items-center justify-between">
            <div class="font-bold mr-5 text-white text-lg">#{{ record.rank }}</div>
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

        <div class="flex justify-between items-center mt-1">
            <div>
                <div class="text-white rounded-full text-xs px-2 py-0.5 uppercase font-bold" :class="{'bg-green-700': record.physics.includes('cpm'), 'bg-blue-600': !record.physics.includes('cpm')}">
                    <div>{{ record.physics }}</div>
                </div>
            </div>

            <div class="text-gray-400 text-xs mt-2"> {{ timeSince(record.date_set) }} ago</div>

            <div class="flex justify-between items-center text-right mt-3">
                <div class="text-xs text-red-500" v-if="timeDiff !== null">- {{  formatTime(timeDiff) }}</div>
            </div>
        </div>
    
        <hr class="my-2 text-gray-700 border-gray-700 bg-gray-700">
    </div>
</template>