<script setup>
    import { ref } from 'vue';
    import { useForm } from '@inertiajs/vue3';
    import InputError from '@/Components/Laravel/InputError.vue';
    import InputLabel from '@/Components/Laravel/InputLabel.vue';
    import PrimaryButton from '@/Components/Laravel/PrimaryButton.vue';
    import TextInput from '@/Components/Laravel/TextInput.vue';
    import { onMounted, onUnmounted } from 'vue';
    import MapSearchItem from '@/Components/MapSearchItem.vue';

    const props = defineProps({
        tournament: Object,
        round: Object
    });

    const form = useForm({
        _method: 'POST',
        mapname: ''
    });


    const search = ref('');

    const maps = ref([]);

    const showResultsSection = ref(false);

    const resultsSection = ref(null);
    const searchSection = ref(null);

    const searchCategory = ref('all');

    const screenWidth = ref(window.innerWidth);

    let timer;

    const debounce = () => {
        let searchingString = search.value;

        timer = setTimeout(() => {
            if (searchingString == search.value) {
                axios.post(route('search'), {
                    search: search.value
                }).then(response => {
                    maps.value = response.data?.maps
                });
            }
        }, 250);
    }

    const finishBasicInformation = () => {
        let submitUrl = route('tournaments.rounds.maps.existing.store', {
            tournament: props.tournament.id,
            round: props.round.id,
        });

        form.post(submitUrl, {
            errorBag: 'finishBasicInformation',
            preserveScroll: true
        });
    };

    const onSearchFocus = () => {
        showResultsSection.value = true;
    }

    const onSearchBlur = (event) => {
        if (! showResultsSection.value) {
            return;
        }
        
        if (! searchSection.value.contains(event.target)) {
            showResultsSection.value = false;
        }

        if (! searchSection.value.contains(event.target)) {
            showResultsSection.value = false;
        }

    }

    const performSearch = () => {
        if (search.value.length == 0) {
            maps.value = [];
            return;
        }

        debounce()
    }

    const selectMap = (mapname) => {
        form.mapname = mapname;
        showResultsSection.value = false;
    }

    const resizeScreen = () => {
        screenWidth.value = window.innerWidth
    }


    onMounted(() => {
        window.addEventListener("resize", resizeScreen);
        window.addEventListener("click", onSearchBlur);
    });

    onUnmounted(() => {
        window.removeEventListener("resize", resizeScreen);
        window.removeEventListener("click", onSearchBlur);
    });
</script>

<template>
    <div>
        <div class="text-2xl text-center text-gray-300 mb-3">Add Existing Map</div>
        <form enctype="multipart/form-data" @submit.prevent="finishBasicInformation" class="flex justify-center">
            <div class="w-80 flex flex-col justify-between">
                <div class="mb-3">
                    <InputLabel for="name" value="Search Map Name" />

                    <div ref="searchSection">
                        <TextInput
                            v-model="search"
                            @focus="onSearchFocus"
                            type="text"
                            class="mt-1 h-10 hidden sm:block w-full"
                            placeholder="Search..."
                            @input="performSearch"
                        />

                        <div v-if="showResultsSection" class="defrag-scrollbar search-results hidden p-3 sm:block w-80 mt-1 absolute bg-gray-900 border-2 border-grayop-700 rounded-md text-gray-500" style="z-index: 2000;">
                            <div v-if="search.length == 0">
                                Type a search query...
                            </div>

                            <div ref="resultsSection" v-else>
                                <div v-if="maps.data?.length > 0 && (searchCategory == 'all' || searchCategory == 'maps')">
                                    <div class="font-bold text-gray-400 capitalized text-sm mb-1">
                                        Maps
                                    </div>
                                    <MapSearchItem v-for="map in maps.data" :map="map" :key="map.id" :isRoute="false" :click="selectMap" />
                                </div>

                                <div v-if="maps.data?.length == 0">
                                    There are no results !
                                </div>
                            </div>
                        </div>
                    </div>
                    <p class="text-gray-500 text-sm mt-1" v-if="form.mapname">
                        Selected Map:
                        <span class="text-gray-300">{{ form.mapname }}</span>
                    </p>
                    <InputError :message="form.errors.name" />
                </div>
    
                <div class="flex justify-between w-full">
                    <PrimaryButton>Submit</PrimaryButton>
                </div>
            </div>
        </form>
    </div>
</template>
