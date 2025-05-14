<script setup>
    import { router } from '@inertiajs/vue3';
    import { useClipboard } from '@/Composables/useClipboard';
    
    const { copy } = useClipboard();

    const props = defineProps({
        map: Object,
        isRoute: {
            type: Boolean,
            default: true
        },
        click: Function
    });

    let weaponsList = props.map?.weapons?.split(',') ?? [];

    if (weaponsList.length > 0) {
        if (weaponsList[0].length == 0) {
            weaponsList.splice(0, 1);
        }
    }

    let itemsList = props.map?.items?.split(',') ?? [];

    if (itemsList.length > 0) {
        if (itemsList[0].length == 0) {
            itemsList.splice(0, 1);
        }
    }

    let functionsList = props.map?.functions?.split(',') ?? [];

    if (functionsList.length > 0) {
        if (functionsList[0].length == 0) {
            functionsList.splice(0, 1);
        }
    }

    const onClick = () => {
        if (props.isRoute) {
            router.get(route('maps.map', (props.map.name)))
            return
        }

        props.click(props.map.name)
    }
</script>

<template>
    <div>
        <div class="flex cursor-pointer rounded-md hover:bg-grayop-800 p-2" @click="onClick">
            <div class="p-0.5 rounded-md">
                <img onerror="this.src='/images/unknown.jpg'" :src="`/storage/${map?.thumbnail}`" class="rounded-md" style="width: 65px; height: 50px;">
            </div>
    
            <div class="ml-4 flex justify-between w-full">
                <div>
                    <!-- Mapname -->
                    <div class="flex items-center">
                        <div class="text-md text-blue-400 font-bold"> {{ map.name }} </div>
                    
                        <div class="transition-all duration-500 ease-in-out opacity-0 group-hover:opacity-100 cursor-pointer text-gray-400 hover:text-green-500 ml-2" @click="copy(map?.name ?? mapname)">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 8.25V6a2.25 2.25 0 0 0-2.25-2.25H6A2.25 2.25 0 0 0 3.75 6v8.25A2.25 2.25 0 0 0 6 16.5h2.25m8.25-8.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-7.5A2.25 2.25 0 0 1 8.25 18v-1.5m8.25-8.25h-6a2.25 2.25 0 0 0-2.25 2.25v6" />
                            </svg>                      
                        </div>
                    </div>
    
                    <!-- Author -->
                    <div class="flex items-center text-gray-400 mb-3 text-xs">
                        By <div class="ml-0.5 overflow-hidden truncate" style="max-width: 120px;" :title="map?.author">{{ map?.author ?? 'Unknown' }}</div>
                    </div>
                </div>
    
                <div>
                    <!-- Weapons -->
                    <div class="flex flex-wrap" v-if="weaponsList.length > 0">
                        <div v-for="weapon in weaponsList" :title="weapon" :class="`sprite-items sprite-${weapon} w-4 h-4 mr-1 flex-shrink-0 mb-1`"></div>
                    </div>
    
                    <!-- Items -->
                    <div class="mt-1 flex flex-wrap" v-if="itemsList.length > 0">
                        <div v-for="item in itemsList" :title="item" :class="`sprite-items sprite-${item} w-4 h-4 mr-1 flex-shrink-0 mb-1`"></div>
                    </div>
    
                    <!-- Functions -->
                    <div class="mt-1 flex flex-wrap" v-if="functionsList.length > 0">
                        <div v-for="func in functionsList" :title="func" :class="`sprite-items sprite-${func} w-4 h-4 mr-1 flex-shrink-0 mb-1`"></div>
                    </div>
                </div>
            </div>
        </div>
    
        <hr class="my-1 text-gray-800 border-gray-800 bg-gray-800">
    </div>
</template>