<script setup>
    import { Link } from '@inertiajs/vue3';
    import NavLink from '@/Components/Laravel/NavLink.vue';

    const props = defineProps({
        tabs: Array,
        activeTab: String,
        onClick: Function
    });

    const toggleTab = (tab) => {
        props.onClick(tab.name);
    };
</script>

<template>
    <div class="mb-5 text-sm font-medium text-center">
        <ul class="mr-5 w-full flex flex-wrap justify-center">
            <li v-for="tab in tabs">
                <NavLink class="mr-5 mb-3" v-if="tab.link && tab.condition" :href="route(tab.route, tab.params)" :active="activeTab == tab.name">
                    {{ tab.label }}
                </NavLink>
            </li>
        </ul>
    </div>
</template>