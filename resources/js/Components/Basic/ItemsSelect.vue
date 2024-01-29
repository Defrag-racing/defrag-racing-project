<script setup>
    import { onMounted, ref, watch } from 'vue';

    const emit = defineEmits(['update:modelValue'])

    const props = defineProps({
        multi: {
            type: Boolean,
            default: false
        },
        options: Array,
        values: Object,
        placeholder: String
    });

    const isOpen = ref(false);
    const filteredOptions = ref(props.options);

    const search = ref("");

    const includeOptions = ref(props.values?.include ?? []);
    const excludeOptions = ref(props.values?.exclude ?? []);
    const recentlySelected = ref(false);

    const searchInput = ref(null);

    let blurTimeout;

    const filterOptions = () => {
        filteredOptions.value = props.options.filter((item) => {
            return item.name.toLowerCase().includes(search.value.toLowerCase()) || item.code.toLowerCase().includes(search.value.toLowerCase())
        })
    };

    const onBlur = () => {
        blurTimeout = setTimeout(() => {
            if (recentlySelected.value) {
                recentlySelected.value = false;
                searchInput.value.focus();
            } else {
                isOpen.value = false;
            }
        }, 200);
    }

    const includeOption = (code) => {
        recentlySelected.value = true;

        if (excludeOptions.value.includes(code)) {
            clearItem(code, 'exclude')
        }

        if (includeOptions.value.includes(code)) {
            clearItem(code, 'include')
        } else {
            includeOptions.value.push(code)
        }

        emit('update:modelValue', {
            include: includeOptions.value,
            exclude: excludeOptions.value
        });
    };

    const excludeOption = (code) => {
        recentlySelected.value = true;

        if (includeOptions.value.includes(code)) {
            clearItem(code, 'include')
        }

        if (excludeOptions.value.includes(code)) {
            clearItem(code, 'exclude')
        } else {
            excludeOptions.value.push(code)
        }

        emit('update:modelValue', {
            include: includeOptions.value,
            exclude: excludeOptions.value
        });
    };

    const clearItem = (code, type='all') => {
        if (type === 'include') {
            let index = includeOptions.value.indexOf(code)
            if (index !== -1) {
                includeOptions.value.splice(index, 1)
            }
        }

        if (type === 'exclude') {
            let index = excludeOptions.value.indexOf(code)
            if (index !== -1) {
                excludeOptions.value.splice(index, 1)
            }
        }


        if (type === 'all') {
            clearItem(code, 'include')
            clearItem(code, 'exclude')
        }
    }

    const clear = (code) => {
        recentlySelected.value = true;
        clearItem(code, 'all')
    }

    const includeAll = () => {
        excludeOptions.value = []
        includeOptions.value = props.options.map(item => item.code)

        emit('update:modelValue', {
            include: includeOptions.value,
            exclude: excludeOptions.value
        });
    }

    const excludeAll = () => {
        includeOptions.value = []
        excludeOptions.value = props.options.map(item => item.code)

        emit('update:modelValue', {
            include: includeOptions.value,
            exclude: excludeOptions.value
        });
    }

    const clearAll = () => {
        includeOptions.value = []
        excludeOptions.value = []

        emit('update:modelValue', {
            include: includeOptions.value,
            exclude: excludeOptions.value
        });
    }


    watch(isOpen, (value) => {
        if (!value) {
            filterOptions()
        }
    });
</script>

<template>
    <div class="relative">
        <input ref="searchInput" @focus="isOpen = true" @blur="onBlur" class="w-full border-2 border-grayop-700 bg-grayop-900 text-gray-300 focus:border-blue-600 focus:ring-blue-600 rounded-md shadow-sm" v-model="search" @input="filterOptions" :placeholder="placeholder" autocomplete="off" />

        <div class="options p-2 m-1 rounded-md" v-show="isOpen">
            <div class="flex justify-between w-full text-white mb-2">
                <div class="p-2 flex flex-grow items-center rounded-md cursor-pointer" :class="{'bg-green-500': includeOptions.length == options.length, 'bg-red-600': excludeOptions.length == options.length}" @click="clearAll()">
                    <span class="ml-2"> All </span>
                </div>

                <div class="flex items-center">
                    <div>
                        <div title="Must Include" @click="includeAll()" class="p-2 cursor-pointer rounded-md bg-green-600 hover:bg-green-500 ml-1">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                            </svg>
                        </div>
                    </div>

                    <div>
                        <div title="Must Exclude" @click="excludeAll()" class="p-2 cursor-pointer rounded-md bg-red-600 hover:bg-red-500 ml-1">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                            </svg>
                        </div>
                    </div>
                </div>
            </div>

            <div class=" cursor-pointer rounded-md mb-2 text-white flex items-center" v-for="(item) in filteredOptions" :key="item.code">
                <div class="flex justify-between w-full">
                    <div class="p-2 flex flex-grow items-center rounded-md" :class="{'bg-green-500': includeOptions.includes(item.code), 'bg-red-600': excludeOptions.includes(item.code)}" @click="clear(item.code)">
                        <div :class="`sprite-items sprite-${item.code} w-4 h-4 flex-shrink-0 mx-1`"></div>
                        <span class="ml-2"> {{ item.name }}</span>
                    </div>

                    <div class="flex items-center">
                        <div>
                            <div title="Must Include" @click="includeOption(item.code)" class="p-2 cursor-pointer rounded-md bg-green-600 hover:bg-green-500 ml-1">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                </svg>
                            </div>
                        </div>

                        <div>
                            <div title="Must Exclude" @click="excludeOption(item.code)" class="p-2 cursor-pointer rounded-md bg-red-600 hover:bg-red-500 ml-1">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
  
<style scoped>
    .options {
        background-color: #272e3b;
        position: absolute;
        top: 100%;
        left: 0;
        width: 100%;
        height: 200px;
        overflow-y: auto;
        z-index: 100;
    }

    .option {
        background-color: #2b323f;
    }
</style>
  