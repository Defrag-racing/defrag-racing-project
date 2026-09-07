<script setup>
import { ref, computed, getCurrentInstance } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { t } from '@/utils/i18n';
import InputLabel from '@/Components/Laravel/InputLabel.vue';
import InputError from '@/Components/Laravel/InputError.vue';
import TextInput from '@/Components/Laravel/TextInput.vue';
import PrimaryButton from '@/Components/Laravel/PrimaryButton.vue';
import SecondaryButton from '@/Components/Laravel/SecondaryButton.vue';

const { proxy } = getCurrentInstance();
const q3tohtml = proxy.q3tohtml;
const page = usePage();
const aliases = computed(() => page.props.aliases || []);
const mddAliases = computed(() => aliases.value.filter(a => a.source === 'mdd_import'));
const manualAliases = computed(() => aliases.value.filter(a => a.source !== 'mdd_import'));
const showAddForm = ref(false);
const newAlias = ref('');
const processing = ref(false);
const error = ref('');
const success = ref('');

const submitAlias = () => {
    if (!newAlias.value.trim()) return;

    processing.value = true;
    error.value = '';
    success.value = '';

    router.post(route('aliases.store'), {
        alias: newAlias.value
    }, {
        preserveScroll: true,
        onSuccess: () => {
            newAlias.value = '';
            showAddForm.value = false;
            success.value = t('Alias added successfully!');
            setTimeout(() => success.value = '', 3000);
        },
        onError: (errors) => {
            error.value = errors.alias || t('Failed to add alias');
        },
        onFinish: () => {
            processing.value = false;
        }
    });
};

const deleteAlias = (aliasId) => {
    if (!confirm(t('Are you sure you want to delete this alias?'))) return;

    processing.value = true;
    error.value = '';

    router.delete(route('aliases.destroy', aliasId), {
        preserveScroll: true,
        onSuccess: () => {
            success.value = t('Alias deleted successfully!');
            setTimeout(() => success.value = '', 3000);
            // Reload to get updated aliases
            router.reload({ only: ['user'] });
        },
        onError: () => {
            error.value = t('Failed to delete alias');
        },
        onFinish: () => {
            processing.value = false;
        }
    });
};

const cancelAddAlias = () => {
    showAddForm.value = false;
    newAlias.value = '';
    error.value = '';
};
</script>

<template>
    <div class="p-4">
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-indigo-500/20 to-indigo-600/20 border border-indigo-500/30 flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 text-indigo-400">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                    </svg>
                </div>
                <h2 class="text-sm font-bold text-white">{{ $t('Player Aliases') }}</h2>
            </div>
            <div v-if="success" class="flex items-center gap-1.5 px-2 py-1 rounded bg-green-500/10 border border-green-500/20">
                <svg class="w-3 h-3 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                <span class="text-xs font-medium text-green-400">{{ success }}</span>
            </div>
        </div>

        <div class="text-xs text-gray-400 mb-4">
            {{ $t('Add alternative nicknames you\'ve used in Defrag. Aliases help match your demos automatically and make you searchable by any of your past nicknames.') }}
        </div>

        <!-- MDD Imported Aliases (read-only) -->
        <div v-if="mddAliases.length > 0" class="mb-4">
            <div class="text-[10px] text-blue-400 uppercase tracking-wider font-semibold mb-2">{{ $t('Imported from mDd') }} ({{ mddAliases.length }})</div>
            <div class="flex flex-wrap gap-1.5">
                <div
                    v-for="alias in mddAliases"
                    :key="alias.id"
                    class="px-2.5 py-1 rounded-lg bg-blue-500/5 border border-blue-500/15 text-sm text-gray-300 flex items-center gap-1.5"
                    :title="$tc(':count record with this name|:count records with this name', alias.usage_count)"
                >
                    <span v-html="q3tohtml(alias.alias_colored || alias.alias)"></span>
                    <span v-if="alias.usage_count" class="text-[10px] text-gray-600 tabular-nums">({{ alias.usage_count }})</span>
                </div>
            </div>
        </div>

        <!-- Manual Aliases (editable) -->
        <div v-if="manualAliases.length > 0" class="mb-4">
            <div class="text-[10px] text-indigo-400 uppercase tracking-wider font-semibold mb-2">{{ $t('Manual aliases') }} ({{ manualAliases.length }})</div>
            <div class="space-y-2">
                <div
                    v-for="alias in manualAliases"
                    :key="alias.id"
                    class="flex items-center justify-between p-3 rounded-lg bg-black/20 border border-white/5 hover:border-white/10 transition"
                >
                    <div class="flex items-center gap-3">
                        <span class="text-sm text-white">{{ alias.alias }}</span>
                        <span
                            v-if="!alias.is_approved"
                            class="text-xs bg-yellow-500/20 text-yellow-400 px-2 py-0.5 rounded"
                        >
                            {{ $t('Pending Approval') }}
                        </span>
                        <span
                            v-else
                            class="text-xs bg-green-500/20 text-green-400 px-2 py-0.5 rounded"
                        >
                            {{ $t('Approved') }}
                        </span>
                    </div>
                    <button
                        @click="deleteAlias(alias.id)"
                        :disabled="processing"
                        class="text-red-400 hover:text-red-300 transition disabled:opacity-50"
                        :title="$t('Delete alias')"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <div v-if="aliases.length === 0" class="text-neutral-500 text-sm mb-4 py-6 text-center border border-dashed border-white/10 rounded-lg bg-black/10">
            {{ $t('No aliases added yet') }}
        </div>

        <!-- Add Alias Form -->
        <div v-if="!showAddForm">
            <button
                @click="showAddForm = true"
                class="w-full bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg transition text-sm font-semibold"
            >
                {{ $t('+ Add New Alias') }}
            </button>
        </div>

        <form v-if="showAddForm" @submit.prevent="submitAlias" class="space-y-3">
            <div>
                <InputLabel for="new_alias" :value="$t('New Alias')" />
                <TextInput
                    id="new_alias"
                    v-model="newAlias"
                    type="text"
                    :placeholder="$t('Enter alias (e.g., [gt]neiT.)')"
                    class="mt-1 block w-full"
                    :disabled="processing"
                    maxlength="255"
                    required
                />
                <InputError :message="error" class="mt-2" />
            </div>
            <div class="flex gap-2">
                <PrimaryButton type="submit" :disabled="processing || !newAlias.trim()">
                    {{ processing ? $t('Adding...') : $t('Add Alias') }}
                </PrimaryButton>
                <SecondaryButton type="button" @click="cancelAddAlias" :disabled="processing">
                    {{ $t('Cancel') }}
                </SecondaryButton>
            </div>
        </form>

    </div>
</template>
