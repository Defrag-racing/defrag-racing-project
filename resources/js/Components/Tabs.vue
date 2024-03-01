<script setup>
    import { Link } from '@inertiajs/vue3';

    const props = defineProps({
        tabs: Array,
        activeTab: String,
        onClick: Function,
        condition: {
            type: Boolean,
            default: true
        }
    });

    const toggleTab = (tab) => {
        props.onClick(tab.name);
    };
</script>

<template>
    <div class="mb-5 text-sm font-medium text-center" v-if="condition">
        <ul class="mr-5 w-60">
            <li v-for="tab in tabs">
                <Link :href="route(tab.route, tab.params)" v-if="tab.link">
                    <div :class="{ 'text-white bg-grayop-300': activeTab === tab.name, 'bg-grayop-500 hover:bg-grayop-400': activeTab !== tab.name}" class="cursor-pointer rounded-lg w-full py-3 text-gray-300 mb-4">
                        {{ tab.label }}
                    </div>
                </Link>

                <div v-else @click="toggleTab(tab)" :class="{ 'text-white bg-grayop-300': activeTab === tab.name, 'bg-grayop-500 hover:bg-grayop-400': activeTab !== tab.name}" class="cursor-pointer rounded-lg w-full py-3 text-gray-300 mb-4">
                    {{ tab.label }}
                </div>
            </li>
        </ul>
    </div>
</template>