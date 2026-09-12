<script setup>
    import { computed } from 'vue';
    // q3tohtml is a global property registered in app.js, like everywhere else.
    import { describeGametype, describePhysics, describeValidity } from '@/utils/demoDetails';
    import { t, currentLocale } from '@/utils/i18n';

    const props = defineProps({
        demo: {
            type: Object,
            required: true,
        },
    });

    const gametype = computed(() => describeGametype(props.demo.gametype));
    const physics = computed(() => describePhysics(props.demo.physics));
    const flags = computed(() => describeValidity(props.demo.validity));

    const fileSize = computed(() => {
        const bytes = props.demo.file_size;

        if (!bytes) {
            return null;
        }

        return bytes < 1024 * 1024
            ? `${(bytes / 1024).toFixed(0)} KB`
            : `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
    });

    const recordedOn = computed(() => {
        const date = props.demo.record_date || props.demo.created_at;

        return date ? new Date(date).toLocaleDateString(currentLocale()) : null;
    });

    /**
     * The run itself, in the order somebody deciding whether to download would
     * ask: what kind of run, in which physics, on what.
     */
    const summary = computed(() => {
        const rows = [];

        if (gametype.value) {
            rows.push({ label: t('Mode'), value: gametype.value.label });
        }

        if (physics.value) {
            rows.push({ label: t('Physics'), value: physics.value.base });

            if (physics.value.timereset) {
                rows.push({ label: t('Type'), value: t('Timereset - the timer was reset during the demo, the run itself is a normal run') });
            }

            if (physics.value.ctf !== null) {
                rows.push({ label: t('Capture mode'), value: `CTF ${physics.value.ctf}` });
            }
        }

        if (props.demo.country) {
            rows.push({ label: t('Country'), value: props.demo.country });
        }

        if (recordedOn.value) {
            rows.push({ label: props.demo.record_date ? t('Recorded') : t('Uploaded'), value: recordedOn.value });
        }

        return rows;
    });

    const file = computed(() => {
        const rows = [];

        if (fileSize.value) {
            rows.push({ label: t('Size'), value: fileSize.value });
        }

        if (props.demo.download_count) {
            rows.push({ label: t('Downloads'), value: String(props.demo.download_count) });
        }

        if (props.demo.source) {
            rows.push({ label: t('Source'), value: props.demo.source === 'demome' ? t('Demome archive') : t('Uploaded here') });
        }

        return rows;
    });
</script>

<template>
    <div class="rounded-lg border border-white/10 bg-white/[0.04] backdrop-blur-sm px-4 py-3">
        <div class="grid gap-4 md:grid-cols-3">
            <div>
                <h4 class="text-[11px] font-semibold uppercase tracking-wider text-gray-400 mb-2">{{ $t('The run') }}</h4>
                <dl class="space-y-1">
                    <div v-for="row in summary" :key="row.label" class="flex gap-2 text-xs">
                        <dt class="text-gray-500 w-20 flex-shrink-0">{{ row.label }}</dt>
                        <dd class="text-gray-200">{{ row.value }}</dd>
                    </div>
                    <div v-if="demo.player_name" class="flex gap-2 text-xs">
                        <dt class="text-gray-500 w-20 flex-shrink-0">{{ $t('Player') }}</dt>
                        <dd class="text-gray-200" v-html="q3tohtml(demo.q3df_login_name_colored || demo.player_name)"></dd>
                    </div>
                </dl>
            </div>

            <div>
                <h4 class="text-[11px] font-semibold uppercase tracking-wider text-gray-400 mb-2">{{ $t('The file') }}</h4>
                <dl class="space-y-1">
                    <div v-for="row in file" :key="row.label" class="flex gap-2 text-xs">
                        <dt class="text-gray-500 w-20 flex-shrink-0">{{ row.label }}</dt>
                        <dd class="text-gray-200">{{ row.value }}</dd>
                    </div>
                </dl>
                <p v-if="demo.original_filename" class="mt-2 text-[11px] text-gray-500 break-all font-mono">
                    {{ demo.original_filename }}
                </p>
            </div>

            <div>
                <h4 class="text-[11px] font-semibold uppercase tracking-wider text-gray-400 mb-2">{{ $t('Flagged settings') }}</h4>

                <ul v-if="flags.length" class="space-y-1">
                    <li v-for="flag in flags" :key="flag.key" class="text-xs">
                        <span class="font-mono text-orange-300">{{ flag.key }}</span>
                        <span class="text-gray-300"> = {{ flag.value }}</span>
                        <span v-if="flag.note" class="block text-[11px] text-gray-500">{{ flag.note }}</span>
                    </li>
                </ul>

                <p v-else class="text-xs text-gray-500">
                    {{ $t('Nothing flagged - the parser found no unusual server or client settings.') }}
                </p>
            </div>
        </div>

        <p class="mt-3 text-[11px] text-gray-500">
            {{ $t('Everything here comes from the demo itself. Packet jump is not among it - the parser does not read it yet.') }}
        </p>
    </div>
</template>
