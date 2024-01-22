
<script setup>
    import { ref, watchEffect } from 'vue';
    import { router } from '@inertiajs/vue3';
    import SmallButton from '@/Components/Basic/SmallButton.vue';

    const props = defineProps({
        last_page: Number,
        current_page: Number,
        link: String
    });

    const newPage = ref(props.current_page);

    const before = ref([]);
    const after = ref([]);

    const getLabel = () => {
        after.value = []
        before.value = []

        let surroundingPages = 2

        for(let i = 1; i <= surroundingPages; i++) {
            if (props.current_page + i <= props.last_page) {
                after.value.push({
                    label: (props.current_page + i).toString(),
                    url: getUrl(props.current_page + i)
                })
            }
        }

        for(let i = surroundingPages; i >= 1; i--) {
            if (props.current_page - i > 0) {
                before.value.push({
                    label: (props.current_page - i).toString(),
                    url: getUrl(props.current_page - i)
                })
            }
        }
    }

    const changePage = () => {
        router.visit(getUrl(newPage.value))
    }
    
    const getUrl = (page) => {
        let result = props.link.replace('?page=1', '?page=' + page).replace('&page=1', '&page=' + page)

        return result
    }

    watchEffect(() => {
        getLabel()
    })

</script>


<template>
    <div class="flex">
        <SmallButton :url="getUrl(1)" v-if="current_page != 1">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                <path stroke-linecap="round" stroke-linejoin="round" d="m18.75 4.5-7.5 7.5 7.5 7.5m-6-15L5.25 12l7.5 7.5" />
            </svg>  
        </SmallButton>


        <SmallButton :url="getUrl(current_page - 1)" v-if="current_page != 1">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
            </svg>  
        </SmallButton>

        <SmallButton v-for="page in before" :url="page.url" :label="page.label" :key="page.label" />

        <div class="ml-2 font-bold">
            <input class="py-2 px-4 border-0 border-grayop-700 bg-blackop-50 text-gray-300 focus:border-grayop-700 focus:ring-transparent rounded-md shadow-sm w-20 text-center" v-model="newPage" @change="changePage" />
        </div>

        <SmallButton v-for="page in after" :url="page.url" :label="page.label" :key="page.label" />

        <SmallButton :url="getUrl(current_page + 1)" v-if="current_page < last_page">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
            </svg>
        </SmallButton>

        <SmallButton :url="getUrl(last_page)" v-if="current_page < last_page">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                <path stroke-linecap="round" stroke-linejoin="round" d="m5.25 4.5 7.5 7.5-7.5 7.5m6-15 7.5 7.5-7.5 7.5" />
            </svg>
        </SmallButton>
    </div>
</template>