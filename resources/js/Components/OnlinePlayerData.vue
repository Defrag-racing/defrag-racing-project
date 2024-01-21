<script setup>
    import Popper from "vue3-popper";

    const props = defineProps({
        player: Object,
        spectator: Boolean
    });
</script>

<template>
    <div>
        <Popper arrow hover :disabled="player.profile == null" style="z-index: 100;">
            <div>
                <img :class="{'opacity-70 group-hover:opacity-90': spectator}" onerror="this.src='/images/flags/_404.png'" :src="`/images/flags/${player.country}.png`" class="w-5 inline mr-2 mb-0.5">
    
                <div :class="{'opacity-70 group-hover:opacity-90': spectator}" class="font-bold inline" v-html="q3tohtml(player.name)"></div>
    
                <svg v-if="player.profile" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="mb-0.5 ml-1 text-green-500 w-4 h-4 inline">
                    <path fill-rule="evenodd" d="M12 1.5a5.25 5.25 0 0 0-5.25 5.25v3a3 3 0 0 0-3 3v6.75a3 3 0 0 0 3 3h10.5a3 3 0 0 0 3-3v-6.75a3 3 0 0 0-3-3v-3c0-2.9-2.35-5.25-5.25-5.25Zm3.75 8.25v-3a3.75 3.75 0 1 0-7.5 0v3h7.5Z" clip-rule="evenodd" />
                </svg>
            </div>
    
    
            <template #content>
                <div class="flex items-center" v-if="player.profile">
                    <div class="mr-4">
                        <img class="h-10 w-10 rounded-full object-cover" :src="player.profile.profile_photo_path ? '/storage/' + player.profile.profile_photo_path : '/images/null.jpg'" :alt="player.profile.name">
                    </div>
    
                    <div>
                        <div class="text-gray-500">
                            Logged in as
                        </div>
    
                        <img onerror="this.src='/images/flags/_404.png'" :src="`/images/flags/${player.profile.country}.png`" class="w-6 inline mr-2 mb-0.5">
                        <div class="inline text-lg" v-html="q3tohtml(player.profile.name)"></div>
                    </div>
                </div>
            </template>
        </Popper>
    </div>
</template>

<style>
    :root {
        --popper-theme-background-color: #272e3bf8;
        --popper-theme-background-color-hover: #272e3bee;
        --popper-theme-text-color: #ffffff;
        --popper-theme-border-width: 0px;
        --popper-theme-border-style: solid;
        --popper-theme-border-radius: 6px;
        --popper-theme-padding: 10px 100px 10px 10px;
        --popper-theme-box-shadow: 0 6px 30px -6px rgba(0, 0, 0, 0.25);
    }
</style>
