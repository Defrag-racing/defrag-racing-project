<script setup>
    import { Link } from '@inertiajs/vue3';

    const props = defineProps({
        item: Object,
        physics: String,
        rank: Number
    });
</script>

<template>
    <div class="px-2 py-1 mx-auto border-b-1 border-grayop-700" :class="{'bg-cpm': physics === 'cpm', 'bg-vq3': physics === 'vq3'}">
        <div class="w-full px-5 py-2 rounded-md mb-1 flex items-center">
            <div class="w-10/12 inline-flex items-center truncate">
                <div>
                    <div class="w-full flex items-center flex-nowrap">
                        <div class="font-bold text-lg text-white mr-4">{{ rank }}</div>
                        <div class="mr-4 flex items-center">
                            <img class="h-10 w-10 rounded-full object-cover" :src="'/storage/' + item.clan.image">
                            
                            <div class="ml-4">
                                <Link class="flex rounded-md" :href="route('clans.show', item.clan.id)">
                                    <div class="flex justify-between items-center">
                                        <div>
                                            <div class="font-bold text-white" v-html="q3tohtml(item.clan.name)"></div>
                                        </div>
                                    </div>
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="w-2/12 inline-flex justify-end flex-wrap">
                <div class="flex flex-col items-center">
                    <div class="text-sm text-gray-200 text-center">
                        {{ item.points / item.number }} <span class="text-gray-500">Pts</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
    .bg-cpm {
        background-color: #00000030;
        background: linear-gradient(90deg, #09290d30, #00000030);
    }

    .bg-vq3 {
        background-color: #00000030;
        background: linear-gradient(90deg, #131a4630, #00000030);
    }
</style>