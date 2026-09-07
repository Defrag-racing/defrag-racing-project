<script setup>
    import { ref, computed, onMounted } from 'vue';
    import axios from 'axios';
    import { t, currentLocale } from '@/utils/i18n';

    // Mirrors LauncherTokensForm.vue. Same Sanctum personal access token
    // mechanism, but tokens are prefixed "api:" and carry the "api:read"
    // ability instead of "launcher:upload". Used to authenticate calls to
    // /api/records/search, /api/profile/*/extras, etc. from external
    // scripts or third-party tools - the browser frontend continues to
    // authenticate via session cookie automatically.

    const tokens = ref([]);
    const loading = ref(true);
    const error = ref(null);

    const freshToken = ref(null);
    const copied = ref(false);

    const label = ref('');
    const creating = ref(false);
    const showForm = ref(false);
    const createError = ref(null);

    const fetchTokens = async () => {
        loading.value = true;
        try {
            const { data } = await axios.get(route('api-tokens.index'));
            tokens.value = data.tokens ?? [];
        } catch (e) {
            error.value = t('Failed to load API tokens');
        } finally {
            loading.value = false;
        }
    };

    onMounted(fetchTokens);

    const createToken = async () => {
        if (! label.value.trim()) return;
        creating.value = true;
        createError.value = null;
        try {
            const { data } = await axios.post(route('api-tokens.store'), { label: label.value.trim() });
            freshToken.value = data;
            tokens.value.unshift({
                id: data.id,
                label: data.label,
                created_at: data.created_at,
                last_used_at: null,
            });
            label.value = '';
            showForm.value = false;
        } catch (e) {
            createError.value = e.response?.data?.message || t('Failed to create token');
        } finally {
            creating.value = false;
        }
    };

    // Styled confirm modal state - replaces native window.confirm().
    const confirmModal = ref({
        open: false,
        tokenId: null,
        label: '',
    });

    // API documentation modal
    const docsOpen = ref(false);

    // Endpoints described once and rendered with v-for so adding new
    // ones later means appending to this array. Examples truncated for
    // readability; real responses can be longer.
    const endpoints = computed(() => [
        {
            method: 'GET',
            path: '/api/records/search',
            description: t('Search records by map name prefix (or by player name as fallback when nothing matches). Returns up to 500 records.'),
            params: [
                { name: 'q',    required: true,  desc: t('Search term (min 2 chars). Tried first as map-name prefix, then as player-name substring.') },
                { name: 'time', required: false, desc: t('Filter to records with time ≤ this value (milliseconds).') },
            ],
            example: 'curl -H "Authorization: Bearer <token>" \\\n  "https://defrag.racing/api/records/search?q=bug-w9"',
            response: `[
  {
    "id": 12345,
    "user_id": 8,
    "mapname": "bug-w9",
    "time": 4523,
    "physics": "cpm",
    "mode": "run",
    "gametype": 0,
    "date_set": "2024-09-12 18:42:00",
    "user": { "id": 8, "name": "^4[^7gt^4]^7neiT^4." }
  }
]`,
        },
        {
            method: 'GET',
            path: '/api/search-players',
            description: t('Search mDd profiles by name / plain_name. Returns up to 10 matches.'),
            params: [
                { name: 'q', required: true, desc: t('Search term (min 2 chars). LIKE match on name + plain_name.') },
            ],
            example: 'curl -H "Authorization: Bearer <token>" \\\n  "https://defrag.racing/api/search-players?q=neyo"',
            response: `[
  {
    "id": 2640,
    "name": "^4[^7gt^4]^7neiT^4.",
    "plain_name": "[gt]neiT.",
    "user": { "id": 8, "name": "..." }
  }
]`,
        },
        {
            method: 'GET',
            path: '/api/profile/{mddId}/extras',
            description: t('Similar-skill competitors + head-to-head rivals for a player. Mirrors the data shown on profile pages.'),
            params: [
                { name: 'mddId', required: true, desc: t('Path parameter - the target player\'s mDd profile id.') },
            ],
            example: 'curl -H "Authorization: Bearer <token>" \\\n  "https://defrag.racing/api/profile/2640/extras"',
            response: `{
  "cpm_competitors": {
    "my_score": 4523,
    "better": [ { "id":...,  "name":"...", "score":..., "wrs":..., "total_records":... } ],
    "worse":  [ ... ]
  },
  "vq3_competitors": { ... },
  "cpm_rivals": {
    "beaten":    [ { "id":..., "name":"...", "maps_beaten":..., "times_beaten":... } ],
    "beaten_by": [ ... ]
  },
  "vq3_rivals": { ... }
}`,
        },
        {
            method: 'GET',
            path: '/api/profile/{userId}/compare/{rivalId}',
            description: t('Head-to-head map-by-map comparison between two users for one physics.'),
            params: [
                { name: 'userId',  required: true, desc: t('Path - defrag.racing user id of the "me" side.') },
                { name: 'rivalId', required: true, desc: t('Path - defrag.racing user id of the rival.') },
                { name: 'physics', required: false, desc: 'cpm | vq3 (default: cpm).' },
            ],
            example: 'curl -H "Authorization: Bearer <token>" \\\n  "https://defrag.racing/api/profile/8/compare/42?physics=vq3"',
            response: `[
  {
    "mapname": "bug-w9",
    "mode": "run",
    "physics": "vq3",
    "rival_time": 5012,
    "rival_rank": 3,
    "my_time": 4523,
    "my_rank": 1,
    "time_diff": 489,
    "status": "ahead"
  }
]`,
        },
    ]);

    const askRevoke = (token) => {
        confirmModal.value = {
            open: true,
            tokenId: token.id,
            label: token.label,
        };
    };

    const closeConfirm = () => {
        confirmModal.value = { open: false, tokenId: null, label: '' };
    };

    const revoke = async () => {
        const tokenId = confirmModal.value.tokenId;
        closeConfirm();
        if (! tokenId) return;
        try {
            await axios.delete(route('api-tokens.destroy', tokenId));
            tokens.value = tokens.value.filter(t => t.id !== tokenId);
            if (freshToken.value?.id === tokenId) freshToken.value = null;
        } catch (e) {
            alert(t('Failed to revoke token'));
        }
    };

    const copyToken = async () => {
        try {
            await navigator.clipboard.writeText(freshToken.value.plain_text_token);
            copied.value = true;
            setTimeout(() => (copied.value = false), 2000);
        } catch {}
    };

    const formatDate = (iso) => {
        if (! iso) return '-';
        return new Date(iso).toLocaleString(currentLocale());
    };
</script>

<template>
    <div class="p-6 space-y-5">
        <div class="flex items-start justify-between gap-4 flex-wrap">
            <div>
                <h3 class="text-lg font-semibold text-white">{{ $t('API tokens') }}</h3>
                <p class="mt-1 text-sm text-gray-400 max-w-2xl leading-relaxed [&_code]:text-xs [&_code]:bg-black/40 [&_code]:px-1 [&_code]:rounded"
                   v-html="$t('Personal access tokens for the defrag.racing public API. Send the token as <code>Authorization: Bearer \x26lt;token\x26gt;</code> on requests to <code>/api/profile/\x26lt;id\x26gt;/extras</code>, <code>/api/records/search</code>, etc. Rate-limited per token to 60 requests/minute. Revoke any time.')"></p>
                <button
                    type="button"
                    @click="docsOpen = true"
                    class="mt-2 inline-flex items-center gap-1.5 text-xs text-blue-400 hover:text-blue-300 underline decoration-dotted underline-offset-2"
                >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 5.25h.008v.008H12v-.008Z" />
                    </svg>
                    {{ $t('View API documentation') }}
                </button>
            </div>
            <button
                v-if="!showForm"
                type="button"
                @click="showForm = true"
                class="px-3 py-1.5 text-sm rounded bg-blue-500/20 hover:bg-blue-500/30 text-blue-200 font-semibold"
            >{{ $t('+ New token') }}</button>
        </div>

        <!-- Create form -->
        <form v-if="showForm" @submit.prevent="createToken" class="flex gap-2 items-start bg-black/30 border border-white/[0.06] rounded-lg p-3">
            <div class="flex-1">
                <input
                    v-model="label"
                    type="text"
                    :placeholder="$t('Token label (e.g. Stats bot, Discord webhook)')"
                    maxlength="50"
                    class="w-full bg-black/60 border border-white/10 rounded px-3 py-2 text-sm text-white placeholder:text-gray-500"
                />
                <p v-if="createError" class="mt-1 text-xs text-red-400">{{ createError }}</p>
                <p v-else class="mt-1 text-xs text-gray-500">{{ $t('A label so you remember which integration uses this token.') }}</p>
            </div>
            <button
                type="submit"
                :disabled="creating || !label.trim()"
                class="px-4 py-2 rounded bg-blue-500/30 hover:bg-blue-500/40 disabled:opacity-50 text-blue-100 text-sm font-semibold"
            >{{ creating ? $t('Generating…') : $t('Generate') }}</button>
            <button
                type="button"
                @click="showForm = false; label = ''; createError = null;"
                class="px-4 py-2 rounded bg-white/5 hover:bg-white/10 text-gray-300 text-sm"
            >{{ $t('Cancel') }}</button>
        </form>

        <!-- Fresh token (one-time display) -->
        <div v-if="freshToken" class="border border-emerald-500/40 bg-emerald-500/5 rounded-lg p-4 space-y-3">
            <div class="flex items-center gap-2 text-emerald-300 font-semibold text-sm">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
                {{ $t('Token ":label" created - copy it now', { label: freshToken.label }) }}
            </div>
            <p class="text-xs text-gray-400">
                {{ $t('This is the only time I will show the token in plaintext. Paste it into your script or tool now. If you lose it, revoke this one and generate a new one.') }}
            </p>
            <div class="flex gap-2">
                <input
                    type="text"
                    readonly
                    :value="freshToken.plain_text_token"
                    class="flex-1 bg-black/60 border border-white/10 rounded px-3 py-2 text-sm font-mono text-white select-all"
                    @focus="$event.target.select()"
                />
                <button
                    type="button"
                    @click="copyToken"
                    class="px-4 py-2 rounded bg-emerald-500/20 hover:bg-emerald-500/30 text-emerald-200 text-sm font-semibold whitespace-nowrap"
                >{{ copied ? $t('Copied!') : $t('Copy') }}</button>
            </div>
        </div>

        <!-- Existing tokens -->
        <div v-if="loading" class="text-sm text-gray-500 py-4">{{ $t('Loading tokens…') }}</div>
        <div v-else-if="error" class="text-sm text-red-400 py-4">{{ error }}</div>
        <div v-else-if="tokens.length" class="bg-black/30 border border-white/[0.06] rounded-lg divide-y divide-white/[0.04]">
            <div v-for="t in tokens" :key="t.id" class="px-4 py-3 flex items-center justify-between gap-4">
                <div class="min-w-0">
                    <div class="text-white font-medium truncate">{{ t.label }}</div>
                    <div class="text-xs text-gray-500 mt-0.5">
                        {{ $t('Created :created · Last used :used', { created: formatDate(t.created_at), used: formatDate(t.last_used_at) }) }}
                    </div>
                </div>
                <button
                    type="button"
                    @click="askRevoke(t)"
                    class="px-3 py-1.5 text-xs rounded bg-red-500/15 hover:bg-red-500/25 text-red-300 flex-shrink-0"
                >{{ $t('Revoke') }}</button>
            </div>
        </div>
        <div v-else class="text-sm text-gray-500 py-4 text-center border border-dashed border-white/[0.06] rounded-lg">
            {{ $t('No tokens yet. Generate one to call the defrag.racing API from your scripts.') }}
        </div>
    </div>

    <!-- Styled confirm modal - replaces native window.confirm() -->
    <Teleport to="body">
        <transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-100 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0">
            <div v-if="confirmModal.open"
                 class="fixed inset-0 z-[200] flex items-center justify-center px-4"
                 @click.self="closeConfirm">
                <div class="absolute inset-0 bg-black/70 backdrop-blur-sm"></div>

                <div class="relative w-full max-w-md rounded-2xl border border-white/10 bg-black/70 backdrop-blur-md shadow-2xl">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-white mb-2">{{ $t('Revoke this token?') }}</h3>
                        <p class="text-sm text-gray-400">
                            {{ $t('Token') }} <span class="font-mono text-gray-200">"{{ confirmModal.label }}"</span> <span class="[&_span]:font-mono" v-html="$t('will stop working immediately. Any script or tool using it will start getting <span>401 Unauthorized</span> until you generate a new one.')"></span>
                        </p>
                    </div>
                    <div class="px-6 py-4 border-t border-white/5 flex justify-end gap-2">
                        <button type="button"
                                @click="closeConfirm"
                                class="px-4 py-2 rounded-lg bg-white/5 hover:bg-white/10 border border-white/10 text-sm text-gray-300 transition">
                            {{ $t('Cancel') }}
                        </button>
                        <button type="button"
                                @click="revoke"
                                class="px-4 py-2 rounded-lg bg-red-500/20 hover:bg-red-500/30 border border-red-500/40 text-sm text-red-200 font-medium transition">
                            {{ $t('Revoke token') }}
                        </button>
                    </div>
                </div>
            </div>
        </transition>
    </Teleport>

    <!-- API documentation modal -->
    <Teleport to="body">
        <transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-100 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0">
            <div v-if="docsOpen"
                 class="fixed inset-0 z-[200] flex items-center justify-center px-4 py-8"
                 @click.self="docsOpen = false">
                <div class="absolute inset-0 bg-black/70 backdrop-blur-sm"></div>

                <div class="relative w-full max-w-3xl max-h-full flex flex-col rounded-2xl border border-white/10 bg-black/70 backdrop-blur-md shadow-2xl">
                    <!-- Header -->
                    <div class="px-6 py-4 border-b border-white/10 flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-white">defrag.racing API</h3>
                            <p class="text-xs text-gray-500 mt-0.5"
                               v-html="$t('Authenticated endpoints - all require an API token in <code>Authorization: Bearer …</code> header.')"></p>
                        </div>
                        <button type="button" @click="docsOpen = false"
                                class="p-1.5 rounded-lg text-gray-400 hover:text-white hover:bg-white/10 transition">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                        </button>
                    </div>

                    <!-- Body -->
                    <div class="flex-1 overflow-y-auto px-6 py-5 space-y-6">
                        <!-- Authentication note -->
                        <div class="rounded-lg border border-blue-500/30 bg-blue-500/5 p-4">
                            <div class="text-sm font-semibold text-blue-300 mb-1">{{ $t('Authentication') }}</div>
                            <p class="text-xs text-gray-400 mb-2">
                                {{ $t('Generate a token above ("+ New token"), then include it in every request:') }}
                            </p>
                            <pre class="text-xs bg-black/60 border border-white/10 rounded-lg p-3 overflow-x-auto text-gray-300 font-mono">Authorization: Bearer 5|aB1cD2eF3gH4iJ5kL6mN7oP8…</pre>
                            <p class="text-xs text-gray-500 mt-2 [&_strong]:text-gray-400"
                               v-html="$t('<strong>Rate limit:</strong> 60 requests per minute per user (shared across all your tokens). Hitting the limit returns <code>429 Too Many Requests</code>. All authenticated calls are logged - admins can see per-user / per-endpoint stats.')"></p>
                        </div>

                        <!-- Endpoint cards -->
                        <div v-for="(e, i) in endpoints" :key="i" class="rounded-lg border border-white/10 bg-white/5">
                            <div class="px-4 py-3 border-b border-white/10 flex items-center gap-3">
                                <span class="inline-block px-2 py-0.5 rounded bg-emerald-500/20 text-emerald-300 text-xs font-bold font-mono">{{ e.method }}</span>
                                <code class="text-sm text-gray-200 font-mono break-all">{{ e.path }}</code>
                            </div>
                            <div class="px-4 py-3 space-y-3">
                                <p class="text-sm text-gray-300">{{ e.description }}</p>

                                <div v-if="e.params.length">
                                    <div class="text-xs uppercase tracking-wide text-gray-500 mb-1">{{ $t('Parameters') }}</div>
                                    <table class="w-full text-xs">
                                        <tbody class="divide-y divide-white/5">
                                            <tr v-for="p in e.params" :key="p.name">
                                                <td class="py-1.5 pr-3 align-top w-32">
                                                    <code class="text-gray-200">{{ p.name }}</code>
                                                    <span v-if="p.required" class="text-red-400 ml-1">*</span>
                                                </td>
                                                <td class="py-1.5 text-gray-400">{{ p.desc }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <div>
                                    <div class="text-xs uppercase tracking-wide text-gray-500 mb-1">{{ $t('Example') }}</div>
                                    <pre class="text-xs bg-black/60 border border-white/10 rounded-lg p-3 overflow-x-auto text-gray-300 font-mono whitespace-pre">{{ e.example }}</pre>
                                </div>

                                <div>
                                    <div class="text-xs uppercase tracking-wide text-gray-500 mb-1">{{ $t('Response (truncated)') }}</div>
                                    <pre class="text-xs bg-black/60 border border-white/10 rounded-lg p-3 overflow-x-auto text-gray-300 font-mono whitespace-pre">{{ e.response }}</pre>
                                </div>
                            </div>
                        </div>

                        <p class="text-xs text-gray-500 text-center pt-2 [&_a]:text-blue-400 [&_a:hover]:underline"
                           v-html="$t('Need a new endpoint? Open an issue on the <a href=https://github.com/Defrag-racing/defrag-racing-project target=_blank>GitHub repo</a>.')"></p>
                    </div>

                    <!-- Footer -->
                    <div class="px-6 py-3 border-t border-white/10 flex justify-end">
                        <button type="button" @click="docsOpen = false"
                                class="px-4 py-2 rounded-lg bg-white/5 hover:bg-white/10 border border-white/10 text-sm text-gray-300 transition">
                            {{ $t('Close') }}
                        </button>
                    </div>
                </div>
            </div>
        </transition>
    </Teleport>
</template>
