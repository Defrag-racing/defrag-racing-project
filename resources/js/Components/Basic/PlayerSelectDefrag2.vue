<script setup>
    import { onMounted, ref, watch, computed } from 'vue';

    const emit = defineEmits(['update:modelValue'])

    const props = defineProps({
        multi: {
            type: Boolean,
            default: false
        },
        options: Array,
        values: Array
    });

    const isOpen = ref(false);
    const filteredOptions = ref(props.options);

    const search = ref("");

    const selectedOptions = ref(props.values ?? []);
    const recentlySelected = ref(false);

    const searchInput = ref(null);

    let blurTimeout;

    const filterOptions = () => {
        filteredOptions.value = props.options.filter((item) => {
            return getPlainName(item).toLowerCase().includes(search.value.toLowerCase())
        }).sort((a, b) => {
            if (selectedOptions.value.includes(a.id)) {
                return -1
            } else if (selectedOptions.value.includes(b.id)) {
                return 1
            } else {
                return 0
            }
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

    const selectOption = (id) => {
        recentlySelected.value = true;
        if (props.multi) {
            selectMultipleOptions(id)
        } else {
            selectSingleOption(id)
        }

        emit('update:modelValue', [...selectedOptions.value]);
    };

    const selectMultipleOptions = (id) => {
        let index = selectedOptions.value.indexOf(id)
        if (index !== -1) {
            selectedOptions.value.splice(index, 1)
        } else {
            selectedOptions.value.push(id)
        }
    }

    const selectSingleOption = (id) => {
        selectedOptions.value = [id]
    }

    const clearOptions = () => {
        selectedOptions.value = []

        emit('update:modelValue', [...selectedOptions.value]);
    }

    watch(isOpen, (value) => {
        if (!value) {
            filterOptions()
        }
    });

    onMounted(() => {
        selectedOptions.value = props.values
    })

    const getCountry = (user) => {
        return user.country
    }

    const getName = (user) => {
        return user.name
    }

    const getPlainName = (user) => {
        return user.plain_name
    }
</script>

<template>
    <div class="relative">
        <input ref="searchInput" @focus="isOpen = true" @blur="onBlur" class="w-full border-2 border-grayop-700 bg-grayop-900 text-gray-300 focus:border-blue-600 focus:ring-blue-600 rounded-md shadow-sm" v-model="search" @input="filterOptions" placeholder="Select Player" autocomplete="off" />

        <div class="options defrag-scrollbar p-2 m-1 rounded-md" v-show="isOpen">
            <div class="flex justify-between items-center text-sm text-blue-400 mb-2">
                <div>{{ selectedOptions.length }} Selected</div>
                <div class="hover:text-blue-300 cursor-pointer" @click="clearOptions">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                </div>
            </div>
            <div :class="{'option': !selectedOptions.includes(user.id), 'bg-blue-600 hover:bg-blue-700': selectedOptions.includes(user.id)}" class="p-2 cursor-pointer rounded-md mb-2 text-white flex items-center" v-for="(user, index) in filteredOptions" :key="index" @click="selectOption(user.id)">
                <img :src="`/images/flags/${getCountry(user)}.png`" onerror="this.src='/images/flags/_404.png'" class="w-8 pt-1 mr-5">
                <span v-html="q3tohtml(getName(user))"></span>
            </div>
        </div>
    </div>
</template>
  
<style scoped>
    .options {
        background-color: #272e3b;
        position: relative;
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

    .option:hover {
        background-color: #343b49;
    }
</style>
  