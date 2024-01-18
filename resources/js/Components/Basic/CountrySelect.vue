<template>
    <div class="relative" >
        <input @focus="isOpen = true" @blur="onBlur" class="w-full border-2 border-grayop-700 bg-grayop-900 text-gray-300 focus:border-blue-600 focus:ring-blue-600 rounded-md shadow-sm" v-model="country" @input="filterOptions" placeholder="Select Country" autocomplete="off" />
        
        <div class="options p-2 m-1 rounded-md" v-show="isOpen">
            <div class="p-2 cursor-pointer option rounded-md mb-2 text-white flex items-center" v-for="(name, code) in filteredOptions" :key="code" @click="selectOption(code)">
                <img :src="`/images/flags/${code}.png`" class="w-8 pt-1 mr-5">
                {{ name}}
            </div>
        </div>
    </div>
  </template>
  
<script setup>
    import { ref, watch } from 'vue';
    import countries from '@/Components/stubs/countries'

    const isOpen = ref(false);
    const filteredOptions = ref(countries);
    const country = ref("");
    const selectedOption = ref(null);
    let blurTimeout;

    const props = defineProps({
        setCountry: Function,
    });

    const filterOptions = () => {
        filteredOptions.value = {};

        Object.keys(countries).forEach((code) => {
            if (countries[code].toLowerCase().includes(country.value.toLowerCase())) {
                filteredOptions.value[code] = countries[code];
            }
        });
    };

    const onBlur = () => {
        blurTimeout = setTimeout(() => {
            isOpen.value = false;
        }, 100);
    }

    const selectOption = (option) => {
        props.setCountry(option)
        selectedOption.value = option;
        country.value = countries[option]
        isOpen.value = false;
    };

    watch(isOpen, (value) => {
        if (!value) {
            filterOptions()
        }
    });
</script>
  
<style scoped>
    .options {
        background-color: #272e3b;
        position: absolute;
        top: 100%;
        left: 0;
        width: 100%;
        max-height: 200px;
        overflow-y: auto;
    }

    .option {
        background-color: #2b323f;
    }

    .option:hover {
        background-color: #343b49;
    }
  </style>
  