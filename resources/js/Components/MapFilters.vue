<script setup>
    import { Link, useForm } from '@inertiajs/vue3';
    import TextInput from '@/Components/Laravel/TextInput.vue';
    import SpecialRadio from '@/Components/Basic/SpecialRadio.vue';
    import PlayerSelect from '@/Components/Basic/PlayerSelect.vue';
    import ItemsSelect from '@/Components/Basic/ItemsSelect.vue';

    const props = defineProps({
        show: Boolean,
        queries: Object,
        profiles: Array
    });

    const types = {
        'run': {
            value: 'Run',
            color: 'bg-blue-600'
        },
        'team': {
            value: 'Teamrun',
            color: 'bg-blue-600'
        },
        'freestyle': {
            value: 'Freestyle',
            color: 'bg-blue-600'
        },
        'fastcaps': {
            value: 'Fastcaps',
            color: 'bg-blue-600'
        },
    }

    const physics = {
        'vq3': {
            value: 'VQ3',
            color: 'bg-blue-600'
        },
        'cpm': {
            value: 'CPM',
            color: 'bg-green-600'
        }
    }

    const weapons = [
        {
            'code': 'gauntlet',
            'name': 'Gauntlet'
        },
        {
            'code': 'mg',
            'name': 'Machine Gun'
        },{
            'code': 'sg',
            'name': 'Shot Gun'
        },{
            'code': 'gl',
            'name': 'Grenade Launcher'
        },{
            'code': 'rl',
            'name': 'Rocket Launcher'
        },{
            'code': 'lg',
            'name': 'Lightening Gun'
        },{
            'code': 'rg',
            'name': 'Rail Gun'
        },{
            'code': 'pg',
            'name': 'Plasma Gun'
        },{
            'code': 'bfg',
            'name': 'BFG'
        },{
            'code': 'hook',
            'name': 'Grappling Hook'
        },{
            'code': 'cg',
            'name': 'Chain Gun'
        },{
            'code': 'ng',
            'name': 'Nail Gun'
        },{
            'code': 'pml',
            'name': 'Proximity Mine Launcher'
        },
    ];

    const functions = [
        {
            'code': 'door',
            'name': 'Door'
        },
        {
            'code': 'button',
            'name': 'Button'
        },{
            'code': 'tele',
            'name': 'Teleporter'
        },{
            'code': 'jumppad',
            'name': 'Jump Pad'
        },{
            'code': 'moving',
            'name': 'Moving Object'
        },{
            'code': 'slick',
            'name': 'Slick'
        },{
            'code': 'water',
            'name': 'Water'
        },{
            'code': 'fog',
            'name': 'Fog'
        },{
            'code': 'slime',
            'name': 'Slime'
        },{
            'code': 'lava',
            'name': 'Lava'
        },{
            'code': 'break',
            'name': 'Breakable'
        },{
            'code': 'sound',
            'name': 'Sound'
        },{
            'code': 'timer',
            'name': 'Timer'
        },
    ];

    const items = [
        {
            'code': 'ra',
            'name': 'Red Armor'
        },
        {
            'code': 'ya',
            'name': 'Yellow Armor'
        },{
            'code': 'enviro',
            'name': 'Battle Suit'
        },{
            'code': 'flight',
            'name': 'Flight'
        },{
            'code': 'haste',
            'name': 'Haste'
        },{
            'code': 'health',
            'name': 'Health'
        },{
            'code': 'mega',
            'name': 'Mega Health'
        },{
            'code': 'smallhealth',
            'name': 'Small Health'
        },{
            'code': 'bighealth',
            'name': 'Large Health'
        },{
            'code': 'invis',
            'name': 'Invisibility'
        },{
            'code': 'quad',
            'name': 'Quad Damage'
        },{
            'code': 'regen',
            'name': 'Regeneration'
        },{
            'code': 'medkit',
            'name': 'Medi Kit / Double Jump'
        },{
            'code': 'ptele',
            'name': 'Personal Teleporter'
        }
    ];

    const form = useForm({
        search: props.queries?.search ?? '',
        author: props.queries?.author ?? '',
        gametype: props.queries?.gametype ?? [],
        physics: props.queries?.physics ?? [],
        has_records: props.queries?.has_records ?? [],
        have_no_records: props.queries?.have_no_records ?? [],
        world_record: props.queries?.world_record ?? [],
        items: props.queries?.items ?? {},
        weapons: props.queries?.weapons ?? {},
        functions: props.queries?.functions ?? {},
        records_count: props.queries?.records_count ?? [0, 1000],
        average_length: props.queries?.average_length ?? [0, 1000],
    })
    
    const onFilterSubmit = () => {
        form.get(route('maps.filters'))
    }

    const RemoveElement = (key, inc, index) => {
        form[key][inc].splice(index, 1);
    }

</script>

<template>
    <div v-if="show" class="bg-grayop-800 overflow-hidden shadow-xl sm:rounded-lg p-4 mb-10 filter-container">
        <div class="flex justify-between items-center mb-4">
            <div class="text-white font-bold text-lg">
                Filters
            </div>
            <Link class="flex items-center text-blue-500 hover:text-blue-400 cursor-pointer" :href="route('maps')">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 mr-2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>
                Clear Filters
            </Link>
        </div>

        <div class="sm:flex mb-4">
            <div class="sm:pr-2 sm:w-1/2 mb-2 sm:mb-0">
                <div class="text-sm text-gray-400">Search Keywords</div>
                <TextInput
                    type="text"
                    v-model="form.search"
                    class="mt-1 block w-full"
                    v-on:keyup.enter="onFilterSubmit"
                />
            </div>

            <div class="sm:pr-2 sm:w-1/2 mb-2 sm:mb-0">
                <div class="text-sm text-gray-400">Author</div>
                <TextInput
                    type="text"
                    v-model="form.author"
                    v-on:keyup.enter="onFilterSubmit"
                    class="mt-1 block w-full"
                />
            </div>
        </div>

        <div class="sm:flex mb-4">
            <div class="sm:pr-2 sm:w-1/2 mb-2 sm:mb-0">
                <div class="flex justify-between">
                    <div class="text-sm text-gray-400">Player has record</div>
                    <div class="text-sm text-blue-400">{{ form.has_records.length }} Selected</div>
                </div>

                <div>
                    <PlayerSelect
                        :options="profiles"
                        :multi="true"
                        v-model="form.has_records"
                        :values="form.has_records"
                    />
                </div>
            </div>

            <div class="sm:pr-2 sm:w-1/2 mb-2 sm:mb-0">
                <div class="flex justify-between">
                    <div class="text-sm text-gray-400">Player Doesn't have record</div>
                    <div class="text-sm text-blue-400">{{ form.have_no_records.length }} Selected</div>
                </div>

                <div>
                    <PlayerSelect
                    :options="profiles"
                    :multi="true"
                    v-model="form.have_no_records"
                    :values="form.have_no_records"
                />
                </div>
            </div>
        </div>

        <div class="sm:flex mb-4">
            <div class="sm:pr-2 sm:w-1/2 mb-2 sm:mb-0">
                <div class="flex justify-between">
                    <div class="text-sm text-gray-400">Player has World Record</div>
                    <div class="text-sm text-blue-400">{{ form.world_record.length }} Selected</div>
                </div>

                <div>
                    <PlayerSelect
                        :options="profiles"
                        :multi="false"
                        v-model="form.world_record"
                        :values="form.world_record"
                    />
                </div>
            </div>

            <div class="sm:pr-2 sm:w-1/2 mb-2 sm:mb-0">
                <div class="flex justify-between">
                    <div class="text-sm text-gray-400">Weapons</div>
                </div>
                <div class="flex flex-wrap items-center text-gray-300 text-sm mb-2" v-if="form.weapons?.include?.length > 0 || form.weapons?.exclude?.length > 0">
                    <span v-for="(element, index) in form.weapons?.include" class="relative group w-7 h-7 flex justify-center items-center rounded-full cursor-pointer" style="background-color: rgba(50, 255, 36, 0.20);">
                        <div :class="`sprite-items sprite-${element} w-4 h-4 flex-shrink-0 mx-1`"></div>
                        <span @click="RemoveElement('weapons', 'include', index)" class="hidden group-hover:block absolute top-50% right-50% bg-black flex text-center opacity-90 text-white rounded-full p-0.5 w-5 h-5 flex items-center justify-center text-xs">
                            X
                        </span>
                    </span>

                    <span v-for="(element, index) in form.weapons?.exclude" class="relative group w-7 h-7 flex justify-center items-center rounded-full cursor-pointer" style="background-color: rgba(255, 50, 36, 0.20);">
                        <div :class="`sprite-items sprite-${element} w-4 h-4 flex-shrink-0 mx-1`"></div>
                        <span @click="RemoveElement('weapons', 'exclude', index)" class="hidden group-hover:block absolute top-50% right-50% bg-black flex text-center opacity-90 text-white rounded-full p-0.5 w-5 h-5 flex items-center justify-center text-xs">
                            X
                        </span>
                    </span>
                </div>

                <div>
                    <ItemsSelect
                        :options="weapons"
                        :multi="false"
                        v-model="form.weapons"
                        :values="form.weapons"
                        placeholder="Select Weapons"
                    />
                </div>
            </div>
        </div>

        <div class="sm:flex mb-4">
            <div class="sm:pr-2 sm:w-1/2 mb-2 sm:mb-0">
                <div class="flex justify-between">
                    <div class="text-sm text-gray-400">Functions</div>
                </div>

                <div class="flex flex-wrap items-center text-gray-300 text-sm mb-2" v-if="form.functions?.include?.length > 0 || form.functions?.exclude?.length > 0">
                    <span v-for="(element, index) in form.functions?.include" class="relative group w-7 h-7 flex justify-center items-center rounded-full cursor-pointer" style="background-color: rgba(50, 255, 36, 0.20);">
                        <div :class="`sprite-items sprite-${element} w-4 h-4 flex-shrink-0 mx-1`"></div>
                        <span @click="RemoveElement('functions', 'include', index)" class="hidden group-hover:block absolute top-50% right-50% bg-black flex text-center opacity-90 text-white rounded-full p-0.5 w-5 h-5 flex items-center justify-center text-xs">
                            X
                        </span>
                    </span>

                    <span v-for="(element, index) in form.functions?.exclude" class="relative group w-7 h-7 flex justify-center items-center rounded-full cursor-pointer" style="background-color: rgba(255, 50, 36, 0.20);">
                        <div :class="`sprite-items sprite-${element} w-4 h-4 flex-shrink-0 mx-1`"></div>
                        <span @click="RemoveElement('functions', 'exclude', index)" class="hidden group-hover:block absolute top-50% right-50% bg-black flex text-center opacity-90 text-white rounded-full p-0.5 w-5 h-5 flex items-center justify-center text-xs">
                            X
                        </span>
                    </span>
                </div>

                <div>
                    <ItemsSelect
                        :options="functions"
                        :multi="false"
                        v-model="form.functions"
                        :values="form.functions"
                        placeholder="Select Functions"
                    />
                </div>
            </div>

            <div class="sm:pr-2 sm:w-1/2 mb-2 sm:mb-0">
                <div class="flex justify-between">
                    <div class="text-sm text-gray-400">Items</div>
                </div>

                <div class="flex flex-wrap items-center text-gray-300 text-sm mb-2" v-if="form.items?.include?.length > 0 || form.items?.exclude?.length > 0">
                    <span v-for="(element, index) in form.items?.include" class="relative group w-7 h-7 flex justify-center items-center rounded-full cursor-pointer" style="background-color: rgba(50, 255, 36, 0.20);">
                        <div :class="`sprite-items sprite-${element} w-4 h-4 flex-shrink-0 mx-1`"></div>
                        <span @click="RemoveElement('items', 'include', index)" class="hidden group-hover:block absolute top-50% right-50% bg-black flex text-center opacity-90 text-white rounded-full p-0.5 w-5 h-5 flex items-center justify-center text-xs">
                            X
                        </span>
                    </span>

                    <span v-for="(element, index) in form.items?.exclude" class="relative group w-7 h-7 flex justify-center items-center rounded-full cursor-pointer" style="background-color: rgba(255, 50, 36, 0.20);">
                        <div :class="`sprite-items sprite-${element} w-4 h-4 flex-shrink-0 mx-1`"></div>
                        <span @click="RemoveElement('items', 'exclude', index)" class="hidden group-hover:block absolute top-50% right-50% bg-black flex text-center opacity-90 text-white rounded-full p-0.5 w-5 h-5 flex items-center justify-center text-xs">
                            X
                        </span>
                    </span>
                </div>

                <div>
                    <ItemsSelect
                        :options="items"
                        :multi="false"
                        v-model="form.items"
                        :values="form.items"
                        placeholder="Select Items"
                    />
                </div>
            </div>
        </div>

        <div class="sm:flex mb-4">
            <div class="sm:pr-2 sm:w-1/2 mb-2 sm:mb-0">
                <div class="text-sm text-gray-400">Gametype</div>
                <SpecialRadio
                    :options="types"
                    v-model="form.gametype"
                    :multi="true"
                    :values="form.gametype"
                />
            </div>

            <div class="sm:pr-2 sm:w-1/2 mb-2 sm:mb-0">
                <div class="text-sm text-gray-400">Physics</div>
                <SpecialRadio
                    :options="physics"
                    v-model="form.physics"
                    :values="form.physics"
                    :multi="true"
                />
            </div>
        </div>

        <div class="sm:flex mb-4">
            <div class="sm:pr-2 sm:w-1/2 mb-2 sm:mb-0">
                <div class="text-sm text-gray-400">Number of records</div>
                <div class="flex">
                    <div class="w-8 mt-1 text-gray-400">
                        {{ form.records_count[0] }}
                    </div>
                    <v-range-slider step="1" v-model="form.records_count" color="#007bff" :max="1000" :min="0" />
                    <div class="ml-4 w-8 mt-1 text-gray-400">
                        <img src="/images/infinity.svg" class="w-5 h-5 text-gray-400" alt="infinity" v-if="form.records_count[1] == 1000" />
                        <div v-else> {{ form.records_count[1] }}</div>
                    </div>
                </div>
            </div>

            <div class="sm:pr-2 sm:w-1/2 mb-2 sm:mb-0">
                <div class="text-sm text-gray-400">Average Length (seconds)</div>
                <div class="flex">
                    <div class="w-8 mt-1 text-gray-400">
                        {{ form.average_length[0] }}
                    </div>
                    <v-range-slider step="1" v-model="form.average_length" color="#007bff" :max="1000" :min="0" />
                    <div class="ml-4 w-8 mt-1 text-gray-400">
                        <img src="/images/infinity.svg" class="w-5 h-5 text-gray-400" alt="infinity" v-if="form.average_length[1] == 1000" />
                        <div v-else> {{ form.average_length[1] }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex justify-center">
            <button :disabled="form.processing" style="width: 300px; max-width: 100%" @click="onFilterSubmit" class="flex justify-center items-center text-center cursor-pointer px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-sm text-white uppercase hover:bg-blue-500 active:bg-blue-700 focus:outline-none transition ease-in-out duration-300">
                <span class="mr-3" v-if="form.processing">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 animate-spin">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                    </svg>
                </span>
                Apply
            </button>
        </div>
    </div>
</template>