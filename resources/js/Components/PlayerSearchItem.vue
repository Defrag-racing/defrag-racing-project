<script setup>
    import { Link } from '@inertiajs/vue3';

    const props = defineProps({
        player: Object
    });
</script>

<template>
    <div>
        <Link class="flex rounded-md hover:bg-grayop-800 p-2" href="#">
            <div class="mr-4 flex items-center">
                <img class="h-10 w-10 rounded-full object-cover" :src="player.profile_photo_path ? '/storage/' + player.profile_photo_path : '/images/null.jpg'" :alt="player.name">
                
                <div class="ml-4">
                    <div class="flex rounded-md">
                        <div class="flex justify-between items-center">
                            <div>
                                <img :src="`/images/flags/${player.country}.png`" class="w-5 inline mr-2" onerror="this.src='/images/flags/_404.png'">
                                <a class="font-bold text-white" href="#" v-html="q3tohtml(player.name)"></a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </Link>
    
        <hr class="my-1 text-gray-800 border-gray-800 bg-gray-800">
    </div>
</template>