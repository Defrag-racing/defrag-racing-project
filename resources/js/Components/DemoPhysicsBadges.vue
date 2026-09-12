<script setup>
    import { computed } from 'vue';
    import { describePhysics } from '@/utils/demoDetails';

    const props = defineProps({
        physics: {
            type: String,
            default: null,
        },
    });

    const parsed = computed(() => describePhysics(props.physics));
</script>

<template>
    <div v-if="parsed" class="flex flex-wrap items-center gap-1">
        <!-- Colouring on the base, not on the whole string - CPM.TR and VQ3.2
             used to fall through to the CPM colour whatever they were. -->
        <span
            class="inline-flex items-center px-1 py-0.5 rounded text-[10px] font-medium"
            :class="parsed.base === 'VQ3' ? 'bg-blue-900/50 text-blue-200' : 'bg-purple-900/50 text-purple-200'"
        >
            {{ parsed.base }}
        </span>

        <span
            v-if="parsed.timereset"
            class="inline-flex items-center px-1 py-0.5 rounded text-[10px] font-medium bg-amber-900/50 text-amber-200"
            :title="$t('Timereset - the timer was reset during the demo, the run itself is a normal run')"
        >
            TR
        </span>

        <span
            v-if="parsed.ctf !== null"
            class="inline-flex items-center px-1 py-0.5 rounded text-[10px] font-medium bg-teal-900/50 text-teal-200"
            :title="`Capture mode ${parsed.ctf}`"
        >
            CTF {{ parsed.ctf }}
        </span>
    </div>

    <span v-else class="text-gray-500">-</span>
</template>
