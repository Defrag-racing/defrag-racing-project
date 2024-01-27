<script setup>
    const props = defineProps({
        bundle: Object
    });
</script>

<template>
    <div class="rounded-md bg-blackop-30 px-5 py-3 mx-2 my-2">
        <div>
            <h2 class="font-semibold text-xl text-gray-200 leading-tight mb-2">
                {{ bundle.name }}
            </h2>

            <div class="text-gray-500">{{ bundle.description }}</div>
        </div>

        <div class="mt-4 flex justify-end">
            <a :href="'/storage/' + bundle.url" class="text-gray-400 bg-blackop-50 hover:bg-blackop-80 py-2 px-3 rounded-md cursor-pointer flex" title="Download">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 3.75H6.912a2.25 2.25 0 0 0-2.15 1.588L2.35 13.177a2.25 2.25 0 0 0-.1.661V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18v-4.162c0-.224-.034-.447-.1-.661L19.24 5.338a2.25 2.25 0 0 0-2.15-1.588H15M2.25 13.5h3.86a2.25 2.25 0 0 1 2.012 1.244l.256.512a2.25 2.25 0 0 0 2.013 1.244h3.218a2.25 2.25 0 0 0 2.013-1.244l.256-.512a2.25 2.25 0 0 1 2.013-1.244h3.859M12 3v8.25m0 0-3-3m3 3 3-3" />
                </svg>

                <div class="ml-2">Download</div>
            </a>
        </div>
    </div>
</template>