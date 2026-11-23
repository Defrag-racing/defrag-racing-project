<script setup>
/**
 * A select that can carry a picture: a flag, a weapon icon, an emoji.
 * The native one cannot, and a row of chips per choice eats a screen,
 * so filters that have both a name and an image use this instead.
 * Opens on click, filters by typing once the list is long, and closes
 * on Escape or on a click outside.
 */
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';
import { t } from '@/utils/i18n';

const props = defineProps({
    modelValue: { type: [String, Number], default: '' },
    // { value, label, image?, flag?, icon?, count?, disabled? }
    options: { type: Array, required: true },
    placeholder: { type: String, default: '' },
    // Shown above the button, the way every filter here is labelled.
    label: { type: String, default: '' },
    searchableFrom: { type: Number, default: 12 },
    widthClass: { type: String, default: 'w-48' },
});

const emit = defineEmits(['update:modelValue', 'change']);

const open = ref(false);
const search = ref('');
const rootRef = ref(null);
const searchRef = ref(null);

const selected = computed(() => props.options.find((o) => String(o.value) === String(props.modelValue)) || null);
const searchable = computed(() => props.options.length >= props.searchableFrom);

const shown = computed(() => {
    const needle = search.value.trim().toLowerCase();
    if (!needle) return props.options;
    return props.options.filter((o) => o.label.toLowerCase().includes(needle));
});

const pick = (option) => {
    if (option.disabled) return;
    open.value = false;
    search.value = '';
    emit('update:modelValue', option.value);
    emit('change', option.value);
};

const toggle = async () => {
    open.value = !open.value;
    if (open.value && searchable.value) {
        await nextTick();
        searchRef.value?.focus();
    }
};

const onDocumentClick = (event) => {
    if (rootRef.value && !rootRef.value.contains(event.target)) open.value = false;
};

const onKey = (event) => {
    if (event.key === 'Escape') open.value = false;
};

watch(() => props.modelValue, () => { open.value = false; });

onMounted(() => {
    document.addEventListener('click', onDocumentClick);
    document.addEventListener('keydown', onKey);
});

onUnmounted(() => {
    document.removeEventListener('click', onDocumentClick);
    document.removeEventListener('keydown', onKey);
});
</script>

<template>
    <div ref="rootRef" class="relative">
        <div v-if="label" class="text-[10px] font-bold uppercase tracking-wider text-gray-500 mb-1.5">{{ label }}</div>

        <button
            type="button"
            @click="toggle"
            :class="[widthClass, open ? 'border-white/30' : 'border-white/10']"
            class="h-10 flex items-center gap-2 pl-3 pr-2.5 rounded-lg border bg-white/5 hover:bg-white/10 text-sm font-bold text-white transition-colors">
            <img v-if="selected?.flag" :src="`/images/flags/${selected.flag}.png`" class="w-6 h-4 rounded shadow-md flex-shrink-0" :alt="selected.label" onerror="this.style.display='none'">
            <img v-else-if="selected?.image" :src="selected.image" class="w-5 h-5 flex-shrink-0" :alt="selected.label">
            <span v-else-if="selected?.icon" class="text-base leading-none flex-shrink-0">{{ selected.icon }}</span>
            <span class="truncate flex-1 text-left">{{ selected ? selected.label : (placeholder || t('Select')) }}</span>
            <svg class="w-3.5 h-3.5 text-gray-400 flex-shrink-0" :class="open ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
            </svg>
        </button>

        <div v-if="open" class="absolute z-50 mt-1 min-w-full w-max max-w-[20rem] bg-gray-900 border border-white/15 rounded-lg shadow-2xl overflow-hidden">
            <div v-if="searchable" class="p-1.5 border-b border-white/10">
                <input
                    ref="searchRef"
                    v-model="search"
                    type="text"
                    :placeholder="t('Search')"
                    class="w-full h-9 px-2.5 rounded-md bg-white/5 border border-white/10 text-sm text-white placeholder-gray-500 focus:outline-none focus:border-white/30">
            </div>

            <div class="max-h-72 overflow-y-auto py-1">
                <button
                    v-for="option in shown"
                    :key="option.value"
                    type="button"
                    @click="pick(option)"
                    :disabled="option.disabled"
                    :class="[
                        String(option.value) === String(modelValue) ? 'bg-white/10 text-white' : 'text-gray-300 hover:bg-white/5',
                        option.disabled ? 'opacity-40 cursor-not-allowed' : '',
                    ]"
                    class="w-full flex items-center gap-2 px-3 py-2 text-sm font-semibold text-left transition-colors">
                    <img v-if="option.flag" :src="`/images/flags/${option.flag}.png`" class="w-6 h-4 rounded shadow-md flex-shrink-0" :alt="option.label" onerror="this.style.display='none'">
                    <img v-else-if="option.image" :src="option.image" class="w-5 h-5 flex-shrink-0" :alt="option.label">
                    <span v-else-if="option.icon" class="text-base leading-none flex-shrink-0 w-6 text-center">{{ option.icon }}</span>
                    <span v-else class="w-6 flex-shrink-0"></span>
                    <span class="truncate flex-1">{{ option.label }}</span>
                    <span v-if="option.count !== undefined" class="text-[10px] text-gray-500 tabular-nums flex-shrink-0">{{ option.count }}</span>
                </button>

                <div v-if="!shown.length" class="px-2.5 py-2 text-xs text-gray-500">{{ t('No results') }}</div>
            </div>
        </div>
    </div>
</template>
