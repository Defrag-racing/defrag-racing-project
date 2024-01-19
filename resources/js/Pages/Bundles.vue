<script setup>
    import { Head } from '@inertiajs/vue3';
    import Bundle from '@/Components/Bundle.vue';
    import { onMounted, ref } from 'vue';

    const props = defineProps({
        categories: Array
    });

    const currentCategory = ref(null);

    const selectCategory = (category) => {
        currentCategory.value = category
    }

    onMounted(() => {
        if (props.categories.length > 0) {
            currentCategory.value = props.categories[0]
        }
    })

</script>

<template>
    <div>
        <Head title="Bundles" />

        <div class="max-w-8xl mx-auto pt-6 px-4 md:px-6 lg:px-8">
            <h2 class="font-semibold text-3xl text-gray-800 dark:text-gray-200 leading-tight">
                Bundles
            </h2>
        </div>

        <div class="max-w-8xl mx-auto py-10 sm:px-6 lg:px-8">
            <div class="md:flex justify-center">
                <div class="md:mr-5 flex-2">
                    <div v-for="category in categories" :key="category.id">
                        <div @click="selectCategory(category)" class="cursor-pointer rounded-md py-2 px-5 w-400 text-gray-400 mb-3 flex justify-between" :class="{'bg-grayop-800 hover:bg-grayop-700': currentCategory != category, 'bg-grayop-600 font-bold': currentCategory == category}">
                            <div class="mr-5">
                                {{ category.name }}
                            </div>

                            <div class="ml-5">
                                {{ category.bundles.length }}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="md:ml-5 rounded-md bg-grayop-800 p-3 w-full flex-1" v-if="currentCategory && currentCategory.bundles.length > 0">
                    <div v-for="bundle in currentCategory?.bundles" :key="bundle.id">
                        <Bundle :bundle="bundle" />
                    </div>
                </div>

                <div class="md:ml-5 rounded-md bg-grayop-800 p-3 text-white text-gray-300 text-center text-lg w-full h-20 flex items-center justify-center flex-1" v-else>
                    No items in this category.
                </div>
            </div>
        </div>
    </div>
</template>
