<script setup>
    import { ref, defineEmits } from 'vue';

    const emit = defineEmits(['update:modelValue'])

    const props = defineProps({
        options: Object,
        multi: {
            type: Boolean,
            default: false
        },
        values: Array
    });

    const values = ref(props.values);

    const onClick = (key) => {
        if (props.multi) {
            onMultiClick(key)
        } else {
            onSingleClick(key)
        }

        let result = values.value.map(item => item)

        emit('update:modelValue', result);
    }

    const onMultiClick = (key) => {
        if (values.value.includes(key)) {
            let index = values.value.indexOf(key);

            if (index !== -1) {
                values.value.splice(index, 1);
            } 
        } else {
            values.value.push(key)
        }
    }

    const onSingleClick = (key) => {
        values.value = [key]
    }

    const getClasses = (key, index) => {
        let classes = {
            'rounded-l-md': (index == 0),
            'rounded-r-md border-r-2': (index == Object.keys(props.options).length - 1),
            'bg-grayop-900 hover:bg-grayop-800': !values.value.includes(key)
        }

        let color = props.options[key]['color'] ?? 'bg-blue-600'

        classes[color] = values.value.includes(key)

        return classes
    }
</script>

<template>
    <div class="sm:flex">
        <div v-for="(option, key, index) in options" class="flex-grow text-center">
            <div
                class="cursor-pointer border-l-2 px-5 py-2 border-y-2 border-grayop-700 text-gray-300 hover:text-gray-100 shadow-sm"
                :class="getClasses(key, index)"
                @click="onClick(key)"
                :key="key"
            >
                {{ option.value }}
            </div>
        </div>
    </div>
</template>