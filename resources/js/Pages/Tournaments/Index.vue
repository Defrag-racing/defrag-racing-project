<script setup>
    import { Head, Link } from '@inertiajs/vue3';

    const props = defineProps({
        tournaments: Object,
    });
</script>

<template>
    <div>
        <Head title="Tournaments" />

        <div class="py-12">
            <div class="max-w-8xl mx-auto sm:px-6 lg:px-8">
                <div class="flex justify-between items-center flex-wrap">
                    <h2 class="font-semibold text-3xl text-gray-200 leading-tight">
                        Tournaments
                    </h2>
    
                    <Link :href="route('tournaments.create')" class="flex items-center text-white bg-grayop-700 py-2 px-4 rounded-md font-bold cursor-pointer bg-grayop-700 hover:bg-gray-600">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 mr-2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
    
                        <div>Create Tournaments</div>
                    </Link>
                </div>

                
            </div>
        </div>
    </div>
</template>
