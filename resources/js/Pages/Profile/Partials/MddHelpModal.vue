<script setup>
    import DialogModal from '@/Components/Laravel/DialogModal.vue';
    import SecondaryButton from '@/Components/Laravel/SecondaryButton.vue';

    defineProps({
        closeModal: Function,
        show: Boolean
    });
</script>

<template>
    <DialogModal :show="show" @close="closeModal">
        <template #title>
            How to get profile link ?
        </template>

        <template #content>
            <div class="mt-4">
                <div>
                    <ol class="list-decimal mt-3 ml-2">
                        <li class="ml-4">Navigate to <a href="https://q3df.org/records" target="_blank" class="link-shadow text-blue-400">q3df.org/records</a></li>
                        <li class="ml-4">Input your user name in the search box on the right and press [ENTER].</li>
                        <li class="ml-4">Find your user name on the list and click it.</li>
                        <li class="ml-4">Copy the URL, which will look something like this: <span class="text-orange-600">https://q3df.org/profil?id=12705</span></li>
                    </ol>
                </div>
            </div>
        </template>

        <template #footer>
            <SecondaryButton @click="closeModal">
                Close
            </SecondaryButton>
        </template>
    </DialogModal>
</template>