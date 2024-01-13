<script setup>
import MainLayout from '@/Layouts/MainLayout.vue';
import Server from '@/Components/Server.vue';
</script>

<template>
    <MainLayout title="Servers">
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg p-4">
                    <Server />
                </div>
            </div>
        </div>
    </MainLayout>
</template>
