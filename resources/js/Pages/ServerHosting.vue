<script>
import MainLayout from '@/Layouts/MainLayout.vue';

export default {
    layout: MainLayout,
};
</script>

<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { t } from '@/utils/i18n';

const props = defineProps({
    application: Object,
    credentials: { type: Array, default: () => [] },
    countries: { type: Object, default: () => ({}) },
});

// Backend ships countries as { "CZ": "CZ - Czechia", ... } already sorted.
// Convert to an array of {code,label} for v-for stability.
const countryOptions = computed(() =>
    Object.entries(props.countries).map(([code, label]) => ({ code, label }))
);

const page = usePage();
const successMsg = computed(() => page.props.success);
const dangerMsg = computed(() => page.props.danger);

// Per-credential UI state, keyed by credential id - a user can hold
// several credentials (one per VPS), each with its own pending password.
const passwordRevealed = ref({});
const passwordCopied = ref({});

const ackForm = useForm({ credential_id: null });
const resetForm = useForm({ credential_id: null });

// Single styled confirm modal, driven by state. Replaces native
// browser confirm() - same blocking semantics via the .onConfirm cb.
const modal = ref({
    open: false,
    title: '',
    body: '',
    confirmLabel: '',
    confirmTone: 'emerald', // tailwind color stub: emerald | amber | red | blue
    onConfirm: () => {},
});

const openConfirm = (opts) => {
    modal.value = {
        open: true,
        title: opts.title ?? t('Are you sure?'),
        body: opts.body ?? '',
        confirmLabel: opts.confirmLabel ?? t('Confirm'),
        confirmTone: opts.confirmTone ?? 'emerald',
        onConfirm: opts.onConfirm ?? (() => {}),
    };
};

const closeModal = () => { modal.value.open = false; };

const confirmModal = () => {
    const cb = modal.value.onConfirm;
    closeModal();
    cb();
};

// Color presets so we don't ship dynamic Tailwind classes (purged at build).
const toneClasses = {
    emerald: 'bg-emerald-500/20 hover:bg-emerald-500/30 border-emerald-500/40 text-emerald-200',
    amber:   'bg-amber-500/20  hover:bg-amber-500/30  border-amber-500/40  text-amber-200',
    red:     'bg-red-500/20    hover:bg-red-500/30    border-red-500/40    text-red-200',
    blue:    'bg-blue-500/20   hover:bg-blue-500/30   border-blue-500/40   text-blue-200',
};

const credentialTitle = (cred) => cred.label || cred.sftp_username;

const requestReset = (cred) => {
    openConfirm({
        title: t('Generate a new password for ":name"?', { name: credentialTitle(cred) }),
        body: t('The current one will stop working immediately. The new password is shown only once on this page - make sure you can save it. Your other credentials are not affected.'),
        confirmLabel: t('Generate new password'),
        confirmTone: 'amber',
        onConfirm: () => {
            resetForm.credential_id = cred.id;
            resetForm.post(route('server-hosting.reset-password'), {
                preserveScroll: true,
            });
        },
    });
};

const copyPassword = async (cred) => {
    if (!cred.pending_password) return;
    try {
        await navigator.clipboard.writeText(cred.pending_password);
        passwordCopied.value[cred.id] = true;
        setTimeout(() => { passwordCopied.value[cred.id] = false; }, 2500);
    } catch (e) {
        // Fallback for non-https contexts: just leave the value visible
        // so the user can select+copy manually.
        passwordRevealed.value[cred.id] = true;
    }
};

const acknowledgePassword = (cred) => {
    openConfirm({
        title: t('Wipe the password from this page?'),
        body: t("Make sure you've saved it somewhere safe - after confirming, it cannot be shown again. You'd need to generate a new one (or ask an admin to rotate)."),
        confirmLabel: t("Yes, I've saved it"),
        confirmTone: 'emerald',
        onConfirm: () => {
            ackForm.credential_id = cred.id;
            ackForm.post(route('server-hosting.acknowledge-password'), {
                preserveScroll: true,
            });
        },
    });
};

const GAMETYPES = computed(() => [
    { value: 'mixed',     label: t('Mixed') },
    { value: 'cpm',       label: 'CPM' },
    { value: 'vq3',       label: 'VQ3' },
    { value: 'teamruns',  label: 'Teamruns' },
    { value: 'fastcaps',  label: 'Fastcaps' },
    { value: 'freestyle', label: 'Freestyle' },
]);

const blankServer = () => ({
    gametype: 'mixed',
    ip: '',
    port: 27960,
    rcon: '',
    location: '',
});

const form = useForm({
    message: '',
    servers: [blankServer()],
    rules_accepted: false,
});

const addServer = () => form.servers.push(blankServer());
const removeServer = (i) => {
    if (form.servers.length > 1) form.servers.splice(i, 1);
};

const submit = () => {
    form.post(route('server-hosting.apply'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
};

const state = computed(() => {
    if (props.credentials.length) return 'active';
    if (!props.application) return 'none';
    return props.application.status;
});

// server_info on the API side is cast to array - render defensively in
// case an older record still has a plain string in there.
const submittedServers = computed(() => {
    const raw = props.application?.server_info;
    if (Array.isArray(raw)) return raw;
    return [];
});

const submittedServerInfoString = computed(() => {
    const raw = props.application?.server_info;
    return typeof raw === 'string' ? raw : '';
});

const gametypeLabel = (value) => GAMETYPES.value.find(g => g.value === value)?.label ?? value;

// Add-server form (one shared form; addServerOpenId tracks which
// credential's card has it open). Pre-fills IP, rcon and country from
// that credential's most-recent declared server so the common "another
// port on the same box" case is one click - they only tweak port + gametype.
const addServerOpenId = ref(null);

const existingIpsFor = (cred) =>
    [...new Set((cred.servers || []).map(s => s.ip).filter(Boolean))];

const addServerForm = useForm({
    credential_id: null,
    gametype: 'mixed',
    ip: '',
    port: 27960,
    rcon: '',
    location: '',
});

const openAddServer = (cred) => {
    addServerForm.reset();
    addServerForm.credential_id = cred.id;
    const last = (cred.servers || []).filter(s => s && s.ip).at(-1) ?? {};
    addServerForm.ip = last.ip ?? existingIpsFor(cred)[0] ?? '';
    addServerForm.rcon = last.rcon ?? '';
    addServerForm.location = last.location ?? '';
    addServerOpenId.value = cred.id;
};

const submitAddServer = () => {
    addServerForm.post(route('server-hosting.add-server'), {
        preserveScroll: true,
        onSuccess: () => {
            addServerOpenId.value = null;
            addServerForm.reset();
        },
    });
};

// Additional-credential form - approved owners provision one SFTP account
// per VPS so every box gets its own password.
const newCredOpen = ref(false);

const newCredForm = useForm({
    label: '',
});

const submitNewCred = () => {
    newCredForm.post(route('server-hosting.request-credential'), {
        preserveScroll: true,
        onSuccess: () => {
            newCredOpen.value = false;
            newCredForm.reset();
        },
    });
};
</script>

<template>
    <Head :title="$t('Server hosting access')" />

    <div class="container mx-auto max-w-3xl px-4 py-10">
        <h1 class="text-2xl md:text-3xl font-bold text-white mb-2">{{ $t('Server hosting access') }}</h1>
        <p class="text-sm text-gray-400 mb-3"
           v-html="$t('If you host (or plan to host) a public defrag server, apply here for an SFTP account on the storage VPS. Approved server owners get a chroot-isolated drop-box where the server bundle can ship <code>.dm_68</code> demos for indexing on defrag.racing.')"></p>
        <p class="text-sm text-gray-400 mb-3">
            {{ $t('Bundles:') }}
            <a href="https://github.com/Defrag-racing/defrag-server-bundle"
               target="_blank" rel="noopener"
               class="text-emerald-300 hover:text-emerald-200 underline decoration-dotted">
                defrag-server-bundle
            </a>
            {{ $t('for Linux,') }}
            <a href="https://github.com/Defrag-racing/defrag-server-bundle-windows"
               target="_blank" rel="noopener"
               class="text-emerald-300 hover:text-emerald-200 underline decoration-dotted">
                defrag-server-bundle-windows
            </a>
            {{ $t('for Windows.') }}
        </p>
        <p class="text-xs text-gray-500 mb-8"
           v-html="$t('Install the bundle on your defrag host first, then plug your SFTP credentials into <code>sv.conf</code> - I\'ll give them to you after approval. Running servers on several machines? Add one credential per VPS so each box has its own login.')"></p>

        <!-- SERVER HOSTING RULES - open for applicants, collapsed for active owners -->
        <details :open="state !== 'active'"
                 class="mb-8 rounded-xl border border-blue-500/30 bg-black/40 backdrop-blur-sm shadow-2xl group">
            <summary class="cursor-pointer select-none px-6 py-4 text-base font-semibold text-blue-300 hover:text-blue-200 transition">
                {{ $t('Server hosting rules') }}
                <span class="text-xs font-normal text-gray-500 ml-2">{{ $t('- required reading for every server admin') }}</span>
            </summary>
            <div class="px-6 pb-6 space-y-4 text-sm text-gray-300 [&_strong]:text-gray-200">
                <ol class="space-y-4 list-none">
                    <li>
                        <div class="font-semibold text-gray-100 mb-1">{{ $t('1. Registration and activation') }}</div>
                        <p class="text-gray-400">{{ $t('Every server must be registered through defrag.racing server hosting before going public. Servers are activated only after they meet all the requirements below. Unregistered or non-compliant servers will not be listed on defrag.racing.') }}</p>
                    </li>
                    <li>
                        <div class="font-semibold text-gray-100 mb-1">{{ $t('2. Server demos are mandatory') }}</div>
                        <p class="text-gray-400"
                           v-html="$t('All servers must run with server-side demo recording enabled (serverdemos). Demos are <strong>not public and never will be</strong> - they are stored privately on defrag.racing infrastructure and are only reviewed by staff if a player report is filed and its validity is confirmed. They exist purely as an integrity/review mechanism.')"></p>
                        <!-- The rule above says the SITE keeps them private. It
                             never said the admin must not pass on the copies
                             sitting on his own box, which is the half that was
                             actually easy to break. -->
                        <p class="text-gray-400 mt-2"
                           v-html="$t('<strong>The demos your server records are not yours to hand out.</strong> Do not share, publish, sell or forward them to anybody - not to another admin, not to a player asking for their own run, not into a Discord, not anywhere. A serverdemo is recorded without the player choosing to record it, and it holds their whole run: their route, their movement, their mistakes. Passing that around is giving away something nobody offered.')"></p>
                        <p class="text-gray-400 mt-2">{{ $t('This one has no grey area and no warning. Sharing a serverdemo deactivates your server.') }}</p>
                    </li>
                    <li>
                        <div class="font-semibold text-gray-100 mb-1">{{ $t('3. SFTP demo upload connection is mandatory') }}</div>
                        <p class="text-gray-400"
                           v-html="$t('Every server must be connected to the defrag.racing storage via the provided SFTP account (automatic demo upload in the server bundle: <code>DEMO_SFTP_ENABLED=1</code> + the credentials you receive from neyo during registration). Servers that are not connected, or whose upload stops working for an extended period, will not be activated - and already active servers will be deactivated until the connection is restored.')"></p>
                    </li>
                    <li>
                        <div class="font-semibold text-gray-100 mb-1">{{ $t('4. Keep your server up to date') }}</div>
                        <p class="text-gray-400">{{ $t('Run the current defrag.racing server bundle (or keep your engine/mod updated to the latest supported release). Outdated servers may misbehave in the server browser and record system and may be delisted until updated.') }}</p>
                    </li>
                    <li>
                        <div class="font-semibold text-gray-100 mb-1">{{ $t('5. Your engine must answer with the whole player list, and say when cheats are on') }}</div>
                        <p class="text-gray-400"
                           v-html="$t('A <code>getdfstatus</code> reply is one 1400 byte packet. The server info takes 550-700 of it and each player line another 57-74, so the reply fills up at around ten players and everyone after that is <strong>missing from it with nothing to say they exist</strong> - on servers configured for 32 slots. The site would list your server with a third of its players and match records against a scoreboard that is not the real one.')"></p>
                        <p class="text-gray-400 mt-2">{{ $t("A bigger packet is not the answer - past the network's MTU it gets split in transit, and losing one piece loses the whole reply instead of just the end of a list. Neither is a shorter line. As it stands a line cannot go below 25 bytes with every text field empty (nine spaces, a newline, five pairs of quotes, and five numbers of at least one digit), and 32 of those on top of a 700 byte server info is already over the limit before the first character of the first nickname.") }}</p>
                        <p class="text-gray-400 mt-2"
                           v-html="$t('Even throwing the format away entirely does not get there. With <strong>nothing but a slot and a name</strong> - no ping, no score, no country, no mDd id, no model - 32 players leave about 15 bytes each for the name on a typical server. Measured against the nicknames online right now, a quarter of them are already too long for that, and colour codes count (<code>^8</code> is two bytes). So the reply gets asked for in parts instead.')"></p>
                        <p class="text-gray-400 mt-2">{{ $t('So the reply has to be askable in parts. Run a current') }}
                        <a href="https://github.com/Defrag-racing/oDFe" target="_blank" rel="noopener"
                           class="text-emerald-300 hover:text-emerald-200 underline decoration-dotted">oDFe</a>
                        <span v-html="$t('or the defrag.racing server bundle and you already have it. On any other engine it is these few lines in <code>SVC_Status_Defrag</code>:')"></span></p>
                        <pre class="mt-2 p-3 rounded-lg bg-black/50 border border-white/10 text-xs text-gray-300 overflow-x-auto"><code>// Read both arguments FIRST, before anything else in this handler runs.
// Cbuf_ExecuteText( EXEC_NOW, "score" ) goes through Cmd_ExecuteString,
// which tokenizes "score" over this packet's own arguments, and every
// Cmd_Argv after that line comes back empty. Put these two below the
// score call and the paging argument is always 0 - and the challenge
// echo has been silently broken by exactly this for years.
Q_strncpyz( challenge, Cmd_Argv( 1 ), sizeof( challenge ) );
firstClient = atoi( Cmd_Argv( 2 ) );        // "getdfstatus &lt;challenge&gt; &lt;first&gt;"
if ( firstClient &lt; 0 ) {
    firstClient = 0;
}

// ...then, where the infostring is built:
Info_SetValueForKey( infostring, "challenge", challenge );

// Cheats are systeminfo, so they are not in the serverinfo string above
// and no server browser can see them. Only sent when they are on - the
// key costs twelve bytes and nearly every server would be paying it to
// say "no". Send it and clients/clientsFrom together, never one alone.
if ( Cvar_VariableIntegerValue( "sv_cheats" ) ) {
    Info_SetValueForKey( infostring, "sv_cheats", "1" );
}

for ( i = 0; i &lt; sv.maxclients; i++ ) {
    if ( svs.clients[i].state &gt;= CS_CONNECTED ) {
        totalClients++;
    }
}

Info_SetValueForKey( infostring, "clients", va( "%i", totalClients ) );
Info_SetValueForKey( infostring, "clientsFrom", va( "%i", firstClient ) );

// ...and start the loop that writes the player lines at that slot
// instead of at zero:
for ( i = firstClient; i &lt; sv.maxclients; i++ ) {</code></pre>
                        <p class="text-gray-400 mt-2">{{ $t('Page by the client slot, not by how many lines you have already sent. If somebody disconnects between two requests everyone behind them moves down a place, and a count would step straight over whoever landed on the boundary. The asker continues from one past the last slot it received, which is the same answer whatever happened in between.') }}</p>
                        <p class="text-gray-400 mt-2"
                           v-html="$t('<strong>Both parts or neither.</strong> A server that sends <code>clientsFrom</code> is telling the site its engine is new enough to report cheats, so a missing <code>sv_cheats</code> is read as cheats being off. Take the paging and leave the cheat line out and your server will be listed as clean whether it is or not.')"></p>
                        <p class="text-gray-400 mt-2"
                           v-html="$t('It breaks nothing. An engine without any of it ignores the extra argument and replies as it always did, and one request is still one reply, so the existing rate limit bounds it exactly as before. I can see which servers have it - the reply either carries <code>clients</code> or it does not - and a server that never sends it will be delisted once its player list starts getting cut off.')"></p>
                    </li>
                    <li>
                        <div class="font-semibold text-gray-100 mb-1">{{ $t('6. Stability and fair play') }}</div>
                        <p class="text-gray-400">{{ $t('Keep your server reasonably stable and reachable. Do not tamper with recorded demos, timers, or record submission in any way. Any manipulation leads to immediate deactivation.') }}</p>
                    </li>
                    <!-- Rule 6 named three things - demos, timers, record
                         submission - and somebody who breaks spectating so
                         that watching a player disconnects them has touched
                         none of them.

                         Written broadly on purpose. A list of banned changes
                         is a list of the ones somebody thought of, and the
                         first person who wants around it simply picks one that
                         is not on it. The only precise part is which file is
                         the owner's, because that they do need to know. -->
                    <li>
                        <div class="font-semibold text-gray-100 mb-1">{{ $t('7. Run the server as it is shipped') }}</div>
                        <p class="text-gray-400"
                           v-html="$t('Do not modify source code that changes how the game behaves, in the engine or in the mod. That is the whole rule and it is meant to be read broadly: <strong>if your server does not behave like Defrag, it is covered</strong>, whether or not anybody had thought of that particular change when this was written.')"></p>
                        <p class="text-gray-400 mt-2"
                           v-html="$t('The configs the bundle ships are part of that. <code>global.cfg</code> and the mode configs - <code>mixed</code>, <code>cpm</code>, <code>vq3</code>, <code>teamruns</code>, <code>fastcaps</code>, <code>freestyle</code> - are what those modes mean. A cpm server that is not running the shipped cpm config is not a cpm server, and the times it produces cannot be compared with anybody else\'s. Leave them as they are.')"></p>
                        <p class="text-gray-400 mt-2"
                           v-html="$t('<strong><code>sv.conf</code> is yours.</strong> Hostname, rcon, admin and contact details, location, private server and its password, how many servers of each type and what they are called, ports, the map mode, your mDd ids and your SFTP credentials. Everything in that file is there for you to set, and none of it changes how the game plays.')"></p>
                        <p class="text-gray-400 mt-2">{{ $t('Breaking this means immediate deactivation, and every record set on the server while it was broken gets reviewed.') }}</p>
                    </li>
                    <li>
                        <div class="font-semibold text-gray-100 mb-1">{{ $t('8. Naming and conduct') }}</div>
                        <p class="text-gray-400">{{ $t('Server names must not impersonate other servers/communities and must not contain offensive content. Admins are expected to act respectfully towards players.') }}</p>
                    </li>
                    <li>
                        <div class="font-semibold text-gray-100 mb-1">{{ $t('9. Support') }}</div>
                        <p class="text-gray-400">{{ $t('If you have trouble setting up the bundle or the SFTP connection, contact') }} <a href="/profile/8"
                                   class="text-emerald-300 hover:text-emerald-200 underline decoration-dotted">neyo</a>
                        {{ $t('- via his defrag.racing profile or on Discord in the server-hosting section. I would rather help you get compliant than delist you.') }}</p>
                    </li>
                </ol>
                <p class="text-xs text-gray-500 pt-2 border-t border-white/5">
                    {{ $t('These rules exist to keep the record system verifiable. Demos stay private and untouched unless a confirmed report requires a review. Thanks for hosting!') }}
                </p>
                <p class="text-xs text-gray-500">
                    {{ $t('Players on your server are bound by the') }}
                    <Link :href="route('rules')" class="text-emerald-300 hover:text-emerald-200 underline decoration-dotted">{{ $t('player rules') }}</Link>{{ $t('. Worth reading: most of what an admin gets asked about is in there.') }}
                </p>
            </div>
        </details>

        <div v-if="successMsg"
             class="mb-6 rounded-xl border border-green-500/30 bg-black/40 backdrop-blur-sm px-4 py-3 text-sm text-green-300 shadow-2xl">
            {{ successMsg }}
        </div>
        <div v-if="dangerMsg"
             class="mb-6 rounded-xl border border-red-500/30 bg-black/40 backdrop-blur-sm px-4 py-3 text-sm text-red-300 shadow-2xl">
            {{ dangerMsg }}
        </div>

        <!-- STATE: ACTIVE CREDENTIALS (one card per VPS) --------------------- -->
        <template v-if="state === 'active'">
            <section v-for="cred in credentials" :key="cred.id"
                     class="rounded-xl border border-emerald-500/30 bg-black/40 backdrop-blur-sm p-6 shadow-2xl mb-6">
                <div class="flex items-center justify-between gap-2 mb-4">
                    <div class="flex items-center gap-2 min-w-0">
                        <span class="inline-block h-2 w-2 rounded-full bg-emerald-400 shrink-0"></span>
                        <h2 class="text-lg font-semibold text-emerald-300 truncate">{{ credentialTitle(cred) }}</h2>
                    </div>
                    <span v-if="cred.label" class="text-xs text-gray-500 font-mono shrink-0">{{ cred.sftp_username }}</span>
                </div>

                <p class="text-sm text-gray-400 mb-4 [&_strong]:text-emerald-300"
                   v-html="$t('Use these in the <code>sv.conf</code> on this VPS. The password is shown <strong>only once</strong> - copy it now, or generate a new one later if you lose it.')"></p>

                <!-- One-time password reveal box (only when pending) -->
                <div v-if="cred.pending_password"
                     class="mb-6 rounded-lg border border-yellow-400/40 bg-yellow-500/5 p-4">
                    <div class="flex items-start gap-3">
                        <span class="text-yellow-300 text-xl leading-none">⚠</span>
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-semibold text-yellow-200 mb-1">{{ $t('Save your password now') }}</div>
                            <p class="text-xs text-gray-400 mb-3">
                                {{ $t("This is the only time you'll see it. After clicking \"I've saved it\", I wipe it from the database - it lives only on the storage VPS in hashed form. If you lose it, generate a new one below.") }}
                            </p>

                            <div class="flex items-center gap-2 mb-3">
                                <code class="flex-1 px-3 py-2 bg-black/60 border border-white/10 rounded-lg text-sm font-mono text-emerald-200 select-all break-all">
                                    <span v-if="passwordRevealed[cred.id]">{{ cred.pending_password }}</span>
                                    <span v-else class="text-gray-500">●●●●●●●●●●●●●●●●●●●●●●●●</span>
                                </code>
                                <button type="button"
                                        @click="passwordRevealed[cred.id] = !passwordRevealed[cred.id]"
                                        class="px-3 py-2 rounded-lg bg-white/5 hover:bg-white/10 border border-white/10 text-xs text-gray-300 transition whitespace-nowrap">
                                    {{ passwordRevealed[cred.id] ? $t('Hide') : $t('Reveal') }}
                                </button>
                                <button type="button"
                                        @click="copyPassword(cred)"
                                        class="px-3 py-2 rounded-lg bg-blue-500/20 hover:bg-blue-500/30 border border-blue-500/30 text-xs text-blue-200 transition whitespace-nowrap">
                                    {{ passwordCopied[cred.id] ? $t('Copied ✓') : $t('Copy') }}
                                </button>
                            </div>

                            <button type="button"
                                    @click="acknowledgePassword(cred)"
                                    :disabled="ackForm.processing"
                                    class="px-3 py-2 rounded-lg bg-emerald-500/20 hover:bg-emerald-500/30 border border-emerald-500/30 text-xs text-emerald-200 transition disabled:opacity-50">
                                {{ $t("✓ I've saved it - wipe from this page") }}
                            </button>
                        </div>
                    </div>
                </div>

                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3 text-sm">
                    <div>
                        <dt class="text-gray-500">{{ $t('Host') }}</dt>
                        <dd class="text-gray-200 font-mono">{{ cred.host }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">{{ $t('Port') }}</dt>
                        <dd class="text-gray-200 font-mono">{{ cred.port }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">{{ $t('Username') }}</dt>
                        <dd class="text-gray-200 font-mono">{{ cred.sftp_username }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">{{ $t('Remote path') }}</dt>
                        <dd class="text-gray-200 font-mono">{{ cred.remote_path }}</dd>
                    </div>
                    <div v-if="!cred.pending_password" class="sm:col-span-2">
                        <dt class="text-gray-500">{{ $t('Password') }}</dt>
                        <dd class="flex items-center gap-3 flex-wrap">
                            <span class="text-gray-400 italic">{{ $t('no longer shown') }}</span>
                            <button type="button"
                                    @click="requestReset(cred)"
                                    :disabled="resetForm.processing"
                                    class="px-3 py-1 rounded-lg bg-amber-500/15 hover:bg-amber-500/25 border border-amber-500/30 text-xs text-amber-200 transition disabled:opacity-50">
                                {{ resetForm.processing && resetForm.credential_id === cred.id ? $t('Resetting…') : $t('Generate new password') }}
                            </button>
                            <span class="text-xs text-gray-500">{{ $t('(rate-limited - max 6/hour)') }}</span>
                        </dd>
                    </div>
                </dl>

                <!-- Per-server RS codes (filled in by admin after approval) -->
                <div class="mt-6">
                    <div v-if="cred.servers && cred.servers.length">
                        <div class="text-xs uppercase tracking-wide text-gray-500 mb-2">{{ $t('Servers on this credential') }}</div>
                        <div class="rounded-lg border border-white/10 overflow-hidden">
                            <table class="w-full text-sm">
                                <thead class="bg-white/5 text-xs uppercase tracking-wide text-gray-500">
                                    <tr>
                                        <th class="px-3 py-2 text-left">{{ $t('Gametype') }}</th>
                                        <th class="px-3 py-2 text-left">{{ $t('IP : Port') }}</th>
                                        <th class="px-3 py-2 text-left">{{ $t('Location') }}</th>
                                        <th class="px-3 py-2 text-left">{{ $t('RS code') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-white/5">
                                    <tr v-for="(s, i) in cred.servers" :key="i">
                                        <td class="px-3 py-2 font-medium text-emerald-200/80">{{ (s.gametype || '').toUpperCase() }}</td>
                                        <td class="px-3 py-2 font-mono text-gray-300">{{ s.ip }}:{{ s.port }}</td>
                                        <td class="px-3 py-2 font-mono text-gray-300">
                                            <span v-if="s.location" class="inline-flex items-center gap-1.5">
                                                <img :src="`/images/flags/${s.location}.png`" class="w-4 h-3 rounded shadow-sm" :alt="s.location" onerror="this.style.display='none'">
                                                {{ s.location }}
                                            </span>
                                            <span v-else class="text-gray-500 italic">{{ $t('not set') }}</span>
                                        </td>
                                        <td class="px-3 py-2 font-mono">
                                            <span v-if="s.rs_code" class="text-emerald-200">{{ s.rs_code }}</span>
                                            <span v-else class="text-gray-500 italic">{{ $t('awaiting admin') }}</span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <p class="text-xs text-gray-500 mt-2"
                           v-html="$t('Put each RS code into <code>sv.conf</code> as <code>rs\x26lt;PORT\x26gt;=\x26lt;rs_code\x26gt;</code> (set <code>MDD_ENABLED=1</code> too).')"></p>
                    </div>
                    <p v-else class="text-xs text-gray-500">
                        {{ $t('No servers declared on this credential yet - add the ones running on this VPS below.') }}
                    </p>

                    <!-- Add another server (reuses this credential) -->
                    <div class="mt-4">
                        <button v-if="addServerOpenId !== cred.id"
                                type="button"
                                @click="openAddServer(cred)"
                                class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-emerald-500/10 hover:bg-emerald-500/20 border border-emerald-500/30 text-emerald-300 text-sm transition">
                            <span class="text-base leading-none">+</span> {{ $t('Add another server') }}
                        </button>

                        <form v-else
                              @submit.prevent="submitAddServer"
                              class="rounded-lg border border-emerald-500/20 bg-black/30 p-4 space-y-3">
                            <div class="flex items-center justify-between">
                                <h4 class="text-sm font-semibold text-emerald-200">{{ $t('Register another server') }}</h4>
                                <p class="text-xs text-gray-500">{{ $t('Reuses this credential - no new password.') }}</p>
                            </div>

                            <div class="grid grid-cols-12 gap-2 items-start">
                                <div class="col-span-2">
                                    <label class="block text-xs text-gray-500 mb-1">{{ $t('Gametype') }}</label>
                                    <select v-model="addServerForm.gametype"
                                            class="w-full bg-black/50 border border-white/10 rounded-lg px-2 py-2 text-sm text-gray-200 focus:border-blue-500 focus:ring-0 transition appearance-none cursor-pointer
                                                   bg-[url('data:image/svg+xml;utf8,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 20 20%22 fill=%22%23a1a1aa%22><path d=%22M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z%22/></svg>')]
                                                   bg-no-repeat bg-[right_0.5rem_center] bg-[length:1rem] pr-8">
                                        <option v-for="g in GAMETYPES" :key="g.value" :value="g.value"
                                                class="bg-slate-900 text-gray-200">{{ g.label }}</option>
                                    </select>
                                </div>
                                <div class="col-span-3">
                                    <label class="block text-xs text-gray-500 mb-1">{{ $t('IP') }}</label>
                                    <input v-model="addServerForm.ip"
                                           :list="existingIpsFor(cred).length ? `existing-ips-${cred.id}` : null"
                                           type="text" placeholder="123.45.67.89"
                                           class="w-full bg-black/50 border border-white/10 rounded-lg px-2 py-2 text-sm text-gray-200 font-mono focus:border-blue-500 focus:ring-0 transition" />
                                    <datalist v-if="existingIpsFor(cred).length" :id="`existing-ips-${cred.id}`">
                                        <option v-for="ip in existingIpsFor(cred)" :key="ip" :value="ip" />
                                    </datalist>
                                    <p v-if="addServerForm.errors.ip" class="text-xs text-red-400 mt-1">{{ addServerForm.errors.ip }}</p>
                                </div>
                                <div class="col-span-2">
                                    <label class="block text-xs text-gray-500 mb-1">{{ $t('Port') }}</label>
                                    <input v-model.number="addServerForm.port"
                                           type="number" min="1" max="65535" placeholder="27960"
                                           class="w-full bg-black/50 border border-white/10 rounded-lg px-2 py-2 text-sm text-gray-200 font-mono focus:border-blue-500 focus:ring-0 transition" />
                                    <p v-if="addServerForm.errors.port" class="text-xs text-red-400 mt-1">{{ addServerForm.errors.port }}</p>
                                </div>
                                <div class="col-span-2">
                                    <label class="block text-xs text-gray-500 mb-1">{{ $t('Rcon') }}</label>
                                    <input v-model="addServerForm.rcon"
                                           type="text" placeholder="rcon"
                                           class="w-full bg-black/50 border border-white/10 rounded-lg px-2 py-2 text-sm text-gray-200 font-mono focus:border-blue-500 focus:ring-0 transition" />
                                    <p v-if="addServerForm.errors.rcon" class="text-xs text-red-400 mt-1">{{ addServerForm.errors.rcon }}</p>
                                </div>
                                <div class="col-span-3">
                                    <label class="block text-xs text-gray-500 mb-1">{{ $t('Country') }}</label>
                                    <select v-model="addServerForm.location"
                                            class="w-full bg-black/50 border border-white/10 rounded-lg px-2 py-2 text-sm text-gray-200 focus:border-blue-500 focus:ring-0 transition appearance-none cursor-pointer
                                                   bg-[url('data:image/svg+xml;utf8,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 20 20%22 fill=%22%23a1a1aa%22><path d=%22M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z%22/></svg>')]
                                                   bg-no-repeat bg-[right_0.5rem_center] bg-[length:1rem] pr-8">
                                        <option value="" class="bg-slate-900 text-gray-400">{{ $t('- flag -') }}</option>
                                        <option v-for="c in countryOptions" :key="c.code" :value="c.code"
                                                class="bg-slate-900 text-gray-200">{{ c.label }}</option>
                                    </select>
                                    <p v-if="addServerForm.errors.location" class="text-xs text-red-400 mt-1">{{ addServerForm.errors.location }}</p>
                                </div>
                            </div>

                            <div class="flex justify-end gap-2 pt-1">
                                <button type="button"
                                        @click="addServerOpenId = null"
                                        class="px-3 py-1.5 rounded-lg bg-white/5 hover:bg-white/10 border border-white/10 text-xs text-gray-300 transition">
                                    {{ $t('Cancel') }}
                                </button>
                                <button type="submit"
                                        :disabled="addServerForm.processing"
                                        class="px-3 py-1.5 rounded-lg bg-emerald-500/20 hover:bg-emerald-500/30 border border-emerald-500/30 text-xs text-emerald-200 transition disabled:opacity-50">
                                    {{ addServerForm.processing ? $t('Adding…') : $t('Add server') }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <details class="mt-6 group">
                    <summary class="cursor-pointer text-sm text-emerald-300 hover:text-emerald-200 select-none"
                             v-html="$t('Example <code>sv.conf</code> snippet')"></summary>
                    <pre class="mt-3 text-xs bg-black/60 border border-white/10 rounded-lg p-4 overflow-x-auto text-gray-300"
><code>MDD_ENABLED=1
<template v-for="s in (cred.servers || [])" :key="s.port">rs{{ s.port }}={{ s.rs_code || '0' }}
</template>
DEMO_SFTP_ENABLED=1
DEMO_SFTP_HOST={{ cred.host }}
DEMO_SFTP_PORT={{ cred.port }}
DEMO_SFTP_USER={{ cred.sftp_username }}
DEMO_SFTP_PASS={{ cred.pending_password || '<your password>' }}
DEMO_SFTP_REMOTEDIR={{ cred.remote_path }}</code></pre>
                </details>
            </section>

            <!-- Add another VPS credential (separate SFTP account) -->
            <section class="rounded-xl border border-white/10 bg-black/40 backdrop-blur-sm p-6 shadow-2xl">
                <button v-if="!newCredOpen"
                        type="button"
                        @click="newCredOpen = true"
                        class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-blue-500/10 hover:bg-blue-500/20 border border-blue-500/30 text-blue-300 text-sm transition">
                    <span class="text-base leading-none">+</span> {{ $t('Add another VPS credential') }}
                </button>
                <p v-if="!newCredOpen" class="text-xs text-gray-500 mt-2">
                    {{ $t('Hosting on more than one machine? Provision a separate SFTP account per VPS - each gets its own username and password, so rotating or revoking one never breaks the others.') }}
                </p>

                <form v-if="newCredOpen"
                      @submit.prevent="submitNewCred"
                      class="space-y-3">
                    <div>
                        <h4 class="text-sm font-semibold text-blue-200 mb-1">{{ $t('New SFTP account') }}</h4>
                        <p class="text-xs text-gray-500">
                            {{ $t("Creates a fresh account on the storage VPS with its own password (shown once). Name it after the machine it's for.") }}
                        </p>
                    </div>
                    <div class="flex items-start gap-2">
                        <div class="flex-1">
                            <input v-model="newCredForm.label"
                                   type="text" maxlength="40" :placeholder='$t("e.g. \"USA VPS\" or \"Canada box\"")'
                                   class="w-full bg-black/50 border border-white/10 rounded-lg px-3 py-2 text-sm text-gray-200 focus:border-blue-500 focus:ring-0 transition" />
                            <p v-if="newCredForm.errors.label" class="text-xs text-red-400 mt-1">{{ newCredForm.errors.label }}</p>
                        </div>
                        <button type="button"
                                @click="newCredOpen = false"
                                class="px-3 py-2 rounded-lg bg-white/5 hover:bg-white/10 border border-white/10 text-xs text-gray-300 transition">
                            {{ $t('Cancel') }}
                        </button>
                        <button type="submit"
                                :disabled="newCredForm.processing || newCredForm.label.trim().length < 2"
                                class="px-3 py-2 rounded-lg bg-blue-500/20 hover:bg-blue-500/30 border border-blue-500/30 text-xs text-blue-200 transition disabled:opacity-50">
                            {{ newCredForm.processing ? $t('Provisioning…') : $t('Create account') }}
                        </button>
                    </div>
                </form>
            </section>
        </template>

        <!-- STATE: PENDING APPLICATION -------------------------------------- -->
        <section v-else-if="state === 'pending'"
                 class="rounded-xl border border-yellow-500/30 bg-black/40 backdrop-blur-sm p-6 shadow-2xl">
            <div class="flex items-center gap-2 mb-2">
                <span class="inline-block h-2 w-2 rounded-full bg-yellow-400 animate-pulse"></span>
                <h2 class="text-lg font-semibold text-yellow-300">{{ $t('Application pending review') }}</h2>
            </div>
            <p class="text-sm text-gray-400 mb-4">
                {{ $t("Submitted :date. An admin will be in touch - you don't need to resubmit.", { date: new Date(application.created_at).toLocaleString($page.props.locale) }) }}
            </p>
            <blockquote class="text-sm text-gray-300 border-l-2 border-yellow-500/30 pl-3 whitespace-pre-line">{{ application.message }}</blockquote>

            <div v-if="submittedServers.length" class="mt-4">
                <div class="text-xs text-gray-500 mb-2">{{ $t('Declared servers') }}</div>
                <ul class="space-y-1 text-sm text-gray-300 font-mono">
                    <li v-for="(s, i) in submittedServers" :key="i" class="flex flex-wrap gap-x-3 gap-y-1">
                        <span class="inline-block min-w-16 text-yellow-300/80">{{ gametypeLabel(s.gametype) }}</span>
                        <span>{{ s.ip }}:{{ s.port }}</span>
                        <span class="text-gray-500">rcon: <span class="text-gray-400">●●●●●●</span></span>
                    </li>
                </ul>
            </div>
            <div v-else-if="submittedServerInfoString" class="mt-3 text-xs text-gray-500 whitespace-pre-line">
                {{ $t('Server info:') }} {{ submittedServerInfoString }}
            </div>
        </section>

        <!-- STATE: REJECTED - banner only; form is re-rendered below ------ -->
        <section v-if="state === 'rejected'"
                 class="rounded-xl border border-red-500/30 bg-black/40 backdrop-blur-sm p-6 mb-6 shadow-2xl">
            <div class="flex items-center gap-2 mb-2">
                <span class="inline-block h-2 w-2 rounded-full bg-red-400"></span>
                <h2 class="text-lg font-semibold text-red-300">{{ $t('Previous application rejected') }}</h2>
            </div>
            <p v-if="application.review_note" class="text-sm text-gray-300 whitespace-pre-line">
                <span class="text-gray-500">{{ $t('Note from reviewer:') }}</span> {{ application.review_note }}
            </p>
        </section>

        <!-- STATE: NO APPLICATION or REJECTED (show form) ------------------ -->
        <section v-if="state === 'none' || state === 'rejected'"
                 class="rounded-xl border border-white/10 bg-black/40 backdrop-blur-sm p-6 shadow-2xl">
            <h2 class="text-lg font-semibold text-white mb-4">
                {{ state === 'rejected' ? $t('Submit a new application') : $t('Apply for SFTP access') }}
            </h2>

            <form @submit.prevent="submit" class="space-y-6">
                <!-- Message -->
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-1">
                        {{ $t('Why do you want server hosting access?') }}
                        <span class="text-red-400">*</span>
                    </label>
                    <textarea v-model="form.message"
                              rows="4"
                              maxlength="2000"
                              :placeholder="$t('Briefly describe your defrag.racing history, why you want to host, anything I should know.')"
                              class="w-full bg-black/50 border border-white/10 rounded-lg px-3 py-2 text-sm text-gray-200 focus:border-blue-500 focus:ring-0 transition"></textarea>
                    <div class="text-xs text-gray-500 mt-1 flex justify-between">
                        <span :class="form.errors.message ? 'text-red-400' : ''">{{ form.errors.message || $t('Minimum 20 characters.') }}</span>
                        <span>{{ form.message.length }}/2000</span>
                    </div>
                </div>

                <!-- Servers (gametype + IP + port + rcon, repeatable) -->
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label class="block text-sm font-medium text-gray-300">
                            {{ $t('Your defrag servers') }} <span class="text-red-400">*</span>
                        </label>
                        <span class="text-xs text-gray-500">{{ $t('add one row per running server') }}</span>
                    </div>

                    <div class="space-y-2">
                        <div v-for="(s, i) in form.servers" :key="i"
                             class="grid grid-cols-12 gap-2 items-start">
                            <div class="col-span-2">
                                <select v-model="s.gametype"
                                        class="w-full bg-black/50 border border-white/10 rounded-lg px-2 py-2 text-sm text-gray-200 focus:border-blue-500 focus:ring-0 transition appearance-none cursor-pointer
                                               bg-[url('data:image/svg+xml;utf8,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 20 20%22 fill=%22%23a1a1aa%22><path d=%22M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z%22/></svg>')]
                                               bg-no-repeat bg-[right_0.5rem_center] bg-[length:1rem] pr-8">
                                    <option v-for="g in GAMETYPES" :key="g.value" :value="g.value"
                                            class="bg-slate-900 text-gray-200">{{ g.label }}</option>
                                </select>
                                <p v-if="form.errors[`servers.${i}.gametype`]" class="text-xs text-red-400 mt-1">{{ form.errors[`servers.${i}.gametype`] }}</p>
                            </div>
                            <div class="col-span-3">
                                <input v-model="s.ip"
                                       type="text"
                                       placeholder="123.45.67.89"
                                       class="w-full bg-black/50 border border-white/10 rounded-lg px-2 py-2 text-sm text-gray-200 font-mono focus:border-blue-500 focus:ring-0 transition" />
                                <p v-if="form.errors[`servers.${i}.ip`]" class="text-xs text-red-400 mt-1">{{ form.errors[`servers.${i}.ip`] }}</p>
                            </div>
                            <div class="col-span-2">
                                <input v-model.number="s.port"
                                       type="number"
                                       min="1" max="65535"
                                       placeholder="27960"
                                       class="w-full bg-black/50 border border-white/10 rounded-lg px-2 py-2 text-sm text-gray-200 font-mono focus:border-blue-500 focus:ring-0 transition" />
                                <p v-if="form.errors[`servers.${i}.port`]" class="text-xs text-red-400 mt-1">{{ form.errors[`servers.${i}.port`] }}</p>
                            </div>
                            <div class="col-span-2">
                                <input v-model="s.rcon"
                                       type="text"
                                       placeholder="rcon"
                                       class="w-full bg-black/50 border border-white/10 rounded-lg px-2 py-2 text-sm text-gray-200 font-mono focus:border-blue-500 focus:ring-0 transition" />
                                <p v-if="form.errors[`servers.${i}.rcon`]" class="text-xs text-red-400 mt-1">{{ form.errors[`servers.${i}.rcon`] }}</p>
                            </div>
                            <div class="col-span-2">
                                <select v-model="s.location"
                                        class="w-full bg-black/50 border border-white/10 rounded-lg px-2 py-2 text-sm text-gray-200 focus:border-blue-500 focus:ring-0 transition appearance-none cursor-pointer
                                               bg-[url('data:image/svg+xml;utf8,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 20 20%22 fill=%22%23a1a1aa%22><path d=%22M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z%22/></svg>')]
                                               bg-no-repeat bg-[right_0.5rem_center] bg-[length:1rem] pr-8">
                                    <option value="" class="bg-slate-900 text-gray-400">{{ $t('- flag -') }}</option>
                                    <option v-for="c in countryOptions" :key="c.code" :value="c.code"
                                            class="bg-slate-900 text-gray-200">{{ c.label }}</option>
                                </select>
                                <p v-if="form.errors[`servers.${i}.location`]" class="text-xs text-red-400 mt-1">{{ form.errors[`servers.${i}.location`] }}</p>
                            </div>
                            <div class="col-span-1 flex justify-end">
                                <button type="button"
                                        @click="removeServer(i)"
                                        :disabled="form.servers.length <= 1"
                                        class="h-9 w-9 inline-flex items-center justify-center rounded-lg bg-red-500/10 hover:bg-red-500/20 disabled:opacity-30 disabled:cursor-not-allowed border border-red-500/30 text-red-300 text-lg leading-none transition"
                                        :title="$t('Remove this row')">
                                    −
                                </button>
                            </div>
                        </div>
                    </div>

                    <button type="button"
                            @click="addServer"
                            class="mt-3 inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-emerald-500/10 hover:bg-emerald-500/20 border border-emerald-500/30 text-emerald-300 text-sm transition">
                        <span class="text-base leading-none">+</span> {{ $t('Add another server') }}
                    </button>

                    <p v-if="form.errors.servers" class="text-xs text-red-400 mt-2">{{ form.errors.servers }}</p>
                </div>

                <!-- Rules consent + submit -->
                <div class="pt-2 border-t border-white/5 space-y-3">
                    <label class="flex items-start gap-3 cursor-pointer select-none">
                        <input v-model="form.rules_accepted"
                               type="checkbox"
                               class="mt-0.5 h-4 w-4 rounded border-white/20 bg-black/50 text-blue-500 focus:ring-blue-500/50 focus:ring-offset-0 cursor-pointer" />
                        <span class="text-sm text-gray-300">
                            {{ $t('I have read and agree to the') }}
                            <span class="text-blue-300 font-medium">{{ $t('server hosting rules') }}</span>
                            {{ $t('above - including mandatory serverdemos recording and the SFTP demo upload connection.') }}
                            <span class="text-red-400">*</span>
                        </span>
                    </label>
                    <p v-if="form.errors.rules_accepted" class="text-xs text-red-400">{{ form.errors.rules_accepted }}</p>

                    <div class="flex justify-end">
                        <button type="submit"
                                :disabled="form.processing || form.message.length < 20 || !form.rules_accepted"
                                class="px-4 py-2 rounded-lg bg-blue-500 hover:bg-blue-400 disabled:bg-gray-700 disabled:cursor-not-allowed text-white text-sm font-medium transition"
                                :title="!form.rules_accepted ? $t('You must agree to the server hosting rules first') : null">
                            {{ form.processing ? $t('Submitting…') : $t('Submit application') }}
                        </button>
                    </div>
                </div>
            </form>
        </section>

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
            <div v-if="modal.open"
                 class="fixed inset-0 z-[200] flex items-center justify-center px-4"
                 @click.self="closeModal">
                <div class="absolute inset-0 bg-black/70 backdrop-blur-sm"></div>

                <div class="relative w-full max-w-md rounded-2xl border border-white/10 bg-black/70 backdrop-blur-md shadow-2xl"
                     @keydown.esc.stop="closeModal">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-white mb-2">{{ modal.title }}</h3>
                        <p class="text-sm text-gray-400 whitespace-pre-line">{{ modal.body }}</p>
                    </div>
                    <div class="px-6 py-4 border-t border-white/5 flex justify-end gap-2">
                        <button type="button"
                                @click="closeModal"
                                class="px-4 py-2 rounded-lg bg-white/5 hover:bg-white/10 border border-white/10 text-sm text-gray-300 transition">
                            {{ $t('Cancel') }}
                        </button>
                        <button type="button"
                                @click="confirmModal"
                                :class="[toneClasses[modal.confirmTone] || toneClasses.emerald,
                                         'px-4 py-2 rounded-lg border text-sm font-medium transition']">
                            {{ modal.confirmLabel }}
                        </button>
                    </div>
                </div>
            </div>
        </transition>
    </Teleport>
</template>
