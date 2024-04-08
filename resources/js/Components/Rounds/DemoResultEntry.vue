<script setup>
    import { Link } from '@inertiajs/vue3';

    const props = defineProps({
        demo: Object
    });
</script>

<template>
    <div class="px-2 py-1 mx-auto border-b-1 border-grayop-700" :class="{'bg-cpm': demo.physics === 'cpm', 'bg-vq3': demo.physics === 'vq3'}">
        <div class="w-full px-5 py-2 rounded-md mb-1 flex items-center">
            <div class="text-xl text-white font-bold mr-4">
                {{ demo.rank }}
            </div>

            <div class="w-10/12 inline-flex items-center truncate">
                <div>
                    <div class="w-full flex items-center flex-nowrap">
                        <div class="mr-4 flex items-center">
                            <img class="h-10 w-10 rounded-full object-cover" :src="demo.user.profile_photo_path ? '/storage/' + demo.user?.profile_photo_path : '/images/null.jpg'" :alt="demo.user?.name ?? record.name">
                            
                            <div class="ml-4">
                                <Link class="flex rounded-md" :href="route(demo.user ? 'profile.index' : 'profile.mdd', demo.user ? demo.user.id : record.mdd_id)">
                                    <div class="flex justify-between items-center">
                                        <div>
                                            <img :src="`/images/flags/${demo.user.country}.png`" class="w-5 inline mr-2" onerror="this.src='/images/flags/_404.png'" :title="demo.user.country">
                                            <Link class="font-bold text-white" :href="route(demo.user ? 'profile.index' : 'profile.mdd', demo.user ? demo.user.id : record.mdd_id)" v-html="q3tohtml(demo.user?.name ?? record.name)"></Link>
                                        </div>
                                    </div>
                                </Link>
                
                                <div class="text-gray-400 text-xs mt-2" :title="demo.created_at"> {{ timeSince(demo.created_at) }} ago</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="w-2/12 inline-flex justify-end flex-wrap">
                <div class="flex flex-col items-center">
                    <a :href="route('tournaments.demos.download', demo.id)" download class="font-black text-lg leading-none mb-1 text-indigo-900 dark:text-indigo-100 hover:underline cursor-pointer">
                        {{ formatTime(demo.time) }}
                    </a>

                    <div class="text-sm text-gray-200">
                        {{ demo.points }} <span class="text-gray-500">Pts</span>
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