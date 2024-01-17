<script setup>
    import MainLayout from '@/Layouts/MainLayout.vue';
    import ServerCard from '@/Components/ServerCard.vue';

    import { router } from '@inertiajs/vue3';
    import { onMounted, ref } from 'vue';

    const props = defineProps({
        servers: Array,
    });

    const players = ref(0);

    const countPlayers = () => {
        players.value = 0
        props.servers.forEach(element => {
            players.value += element.online_players.length
        });
    }

    onMounted(() => {
        countPlayers()
    })
</script>

<template>
    <MainLayout title="Servers">
        <template #header>
            <div class="flex justify-between">
                <h2 class="font-semibold text-3xl text-gray-800 dark:text-gray-200 leading-tight">
                    Servers
                </h2>

                
            </div>

            <div class="text-sm text-blue-400 flex items-center mt-3">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                </svg>

                <div class="ml-2 mr-5">{{ players }} Online Players</div>

                <div class="flex items-center text-gray-400 ml-5">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 14.25h13.5m-13.5 0a3 3 0 0 1-3-3m3 3a3 3 0 1 0 0 6h13.5a3 3 0 1 0 0-6m-16.5-3a3 3 0 0 1 3-3h13.5a3 3 0 0 1 3 3m-19.5 0a4.5 4.5 0 0 1 .9-2.7L5.737 5.1a3.375 3.375 0 0 1 2.7-1.35h7.126c1.062 0 2.062.5 2.7 1.35l2.587 3.45a4.5 4.5 0 0 1 .9 2.7m0 0a3 3 0 0 1-3 3m0 3h.008v.008h-.008v-.008Zm0-6h.008v.008h-.008v-.008Zm-3 6h.008v.008h-.008v-.008Zm0-6h.008v.008h-.008v-.008Z" />
                    </svg>
    
                    <div class="ml-2">{{ servers.length }} Online Servers</div>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-8xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg p-4 flex justify-center flex-wrap">
                    <ServerCard v-for="server in servers" :server="server" />
                </div>
            </div>
        </div>
    </MainLayout>
</template>
