<script setup>
    defineProps({
        server: Object,
    });

    const copyServer = () => {
        const textarea = document.createElement('textarea');
        textarea.value = 'SERVER_IP';
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
    };

    const copyMap = () => {
        const textarea = document.createElement('textarea');
        textarea.value = 'MAPNAME';
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
    };
</script>

<template>
    <div class="mb-5 mx-2 group bg-gray-700 text-gray-300 rounded-md shadow-sm p-3" style="width: 420px; max-width: 100%;">
        <!-- Top Bar -->
        <div class="flex justify-between">
            <div class="flex">
                <div class="flex items-center">
                    <img :src="`/images/flags/${server.country}.png`" class="w-8 pt-1" title="CZ">
                    <div class="text-lg font-bold ml-4" v-html="q3tohtml(server.name)"></div>
                </div>
            </div>

            <div class="flex items-center">
                <a :href="`defrag://${server.ip}:${server.port}`">
                    <div class="rounded-md bg-blackop-30 hover:bg-blackop-20 p-2 cursor-pointer">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 0 1 0 1.972l-11.54 6.347a1.125 1.125 0 0 1-1.667-.986V5.653Z" />
                        </svg>                      
                    </div>
                </a>

                <div class="text-green-500 p-2">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.288 15.038a5.25 5.25 0 0 1 7.424 0M5.106 11.856c3.807-3.808 9.98-3.808 13.788 0M1.924 8.674c5.565-5.565 14.587-5.565 20.152 0M12.53 18.22l-.53.53-.53-.53a.75.75 0 0 1 1.06 0Z" />
                    </svg>                      
                </div>
            </div>
        </div>

        <!-- Server Ip -->
        <div>
            <div class="flex items-center">
                <div class="w-8 h-1"></div>
    
                <div class="text-gray-400 ml-4">
                    {{  server.ip }}:{{ server.port }}
                </div>
                
                <div class="transition-opacity duration-300 ease-in-out opacity-0 group-hover:opacity-100 cursor-pointer text-gray-400 hover:text-green-500 ml-3" @click="copyServer">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 8.25V6a2.25 2.25 0 0 0-2.25-2.25H6A2.25 2.25 0 0 0 3.75 6v8.25A2.25 2.25 0 0 0 6 16.5h2.25m8.25-8.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-7.5A2.25 2.25 0 0 1 8.25 18v-1.5m8.25-8.25h-6a2.25 2.25 0 0 0-2.25 2.25v6" />
                    </svg>     
                </div>
            </div>
        </div>

        <!-- Map Details -->
        <div class="flex mt-5">
            <div class="rounded-md flex items-end border-b-4 border-red-500" style="background-image: url('https://defrag.racing/uploads/thumbs/3806.jpg'); width: 200px; height: 175px; background-size: cover;">
                <div class="absolute bg-black w-full opacity-60 h-8 rounded-md m-2" style="width: 180px;"></div>
                <div class="m-3 z-10 font-bold text-center text-red-500" style="width: 180px;">
                    CPM . RUN
                </div>
            </div>

            <div class="ml-4">
                <!-- Mapname -->
                <div class="flex items-center">
                    <a class="text-lg text-blue-400 hover:text-blue-300 font-bold" href="#">egyptsm1</a>
                
                    <div class="transition-opacity duration-300 ease-in-out opacity-0 group-hover:opacity-100 cursor-pointer text-gray-400 hover:text-green-500 ml-2" @click="copyMap">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 8.25V6a2.25 2.25 0 0 0-2.25-2.25H6A2.25 2.25 0 0 0 3.75 6v8.25A2.25 2.25 0 0 0 6 16.5h2.25m8.25-8.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-7.5A2.25 2.25 0 0 1 8.25 18v-1.5m8.25-8.25h-6a2.25 2.25 0 0 0-2.25 2.25v6" />
                        </svg>                      
                    </div>
                </div>

                <!-- Author -->
                <div class="flex items-center text-gray-400 mb-3">
                    By  <a class="ml-2 hover:text-gray-300" href="#">gooby</a>
                </div>

                <!-- Weapons -->
                <div class="mt-2 flex flex-wrap">
                    <div class="sprite-items sprite-jumppad w-4 h-4 mr-1 flex-shrink-0 mb-1"></div>
                    <div class="sprite-items sprite-rl w-4 h-4 mr-1 flex-shrink-0 mb-1"></div>
                </div>

                <!-- Items -->
                <div class="mt-2 flex flex-wrap">
                    <div class="sprite-items sprite-jumppad w-4 h-4 mr-1 flex-shrink-0 mb-1"></div>
                    <div class="sprite-items sprite-jumppad w-4 h-4 mr-1 flex-shrink-0 mb-1"></div>
                    <div class="sprite-items sprite-jumppad w-4 h-4 mr-1 flex-shrink-0 mb-1"></div>
                    <div class="sprite-items sprite-jumppad w-4 h-4 mr-1 flex-shrink-0 mb-1"></div>
                </div>

                <!-- Functions -->
                <div class="mt-2 flex flex-wrap">
                    <div class="sprite-items sprite-jumppad w-4 h-4 mr-1 flex-shrink-0 mb-1"></div>
                    <div class="sprite-items sprite-sg w-4 h-4 mr-1 flex-shrink-0 mb-1"></div>
                    <div class="sprite-items sprite-gl w-4 h-4 mr-1 flex-shrink-0 mb-1"></div>
                    <div class="sprite-items sprite-rl w-4 h-4 mr-1 flex-shrink-0 mb-1"></div>
                </div>
            </div>
        </div>

        <!-- Best Record -->
        <div class="text-lg p-2 bg-blackop-30 rounded-md mt-5">
            <div class="text-xs capitalize text-gray-400 font-bold">BEST TIME </div>

            <div class="flex justify-between">
                <a class="hover:underline text-gray-500" href="#" v-html="q3tohtml('^9Jan^8Hejazi')"></a>
                <div class="font-bold">
                    {{  formatTime('117032') }}
                </div>
            </div>
        </div>

        <hr class="mt-4 text-gray-600 border-gray-600 bg-gray-600">
    </div>
</template>
