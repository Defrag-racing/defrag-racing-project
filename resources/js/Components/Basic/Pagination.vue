
<script setup>
    import { ref } from 'vue';

    const props = defineProps({
        last_page: Number,
        current_page: Number
    });

    const pages = ref([]);

    const getLabel = () => {
        let before = []
        let after = []

        for(let i = 1; i <= 3; i++) {
            if (props.current_page + i <= props.last_page) {
                after.push(props.current_page + i)
            }
        }

        for(let i = 3; i >= 1; i--) {
            if (props.current_page - i > 0) {
                before.push(props.current_page - i)
            }
        }

        pages.value.push(...before)
        pages.value.push(props.current_page)
        pages.value.push(...after)
    }

    getLabel()

    console.log(pages.value)

</script>


<template>
    <div class="flex">
        <div v-for="page in pages" class="ml-2 font-bold">
            <div :class="'py-2 px-4 rounded-md cursor-pointer ' + ((page == current_page) ? 'bg-blackop-20 text-blue-400' : 'bg-blackop-30 hover:bg-blackop-20 text-gray-400')">
                {{ page }}
            </div>
        </div>
    </div>
</template>