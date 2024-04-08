<script setup>
    import PrimaryButton from '@/Components/Laravel/PrimaryButton.vue';
    import ConfirmationModal from '@/Components/Laravel/ConfirmationModal.vue';

    const props = defineProps({
        close: Function,
        show: Boolean,
        confirm: Function,
        clan: Object,
        myClan: Object
    });

    const submitForm = () => {
        props.confirm();
    };
</script>

<template>
    <div>
        <ConfirmationModal :show="show" maxWidth="xl" :closeable="true" @close="close">
            <template #title>
                <div class="flex justify-between items-center">
                    <div>Leave clan and join the new clan ?</div>

                    <div class="text-gray-200 cursor-pointer rounded-full hover:bg-grayop-700 p-1" @click="close">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </div>
                </div>
            </template>

            <template #content>
                Are you sure you want to leave clan [<span class="text-gray-200" v-html="q3tohtml(myClan.name)"></span>] and join clan [<span class="text-gray-200" v-html="q3tohtml(clan.name)"></span>] ? To join again you will need to be invited by the clan's admin !
            </template>
                
            <template #footer>
                <div class="flex w-full justify-between">
                    <PrimaryButton @click="submitForm" class="bg-red-600 hover:bg-red-500">
                        Leave Clan and Join New Clan
                    </PrimaryButton>
    
                    <PrimaryButton @click="props.close" class="bg-gray-600 hover:bg-gray-500">
                        Cancel
                    </PrimaryButton>
                </div>
            </template>
        </ConfirmationModal>
    </div>
</template>