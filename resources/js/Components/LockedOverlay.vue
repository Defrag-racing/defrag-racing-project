<script setup>
    // The "verified accounts only" veil over a profile block. The parent is
    // `relative`; what sits under this is a stand-in, the real data never
    // reached the browser (see ProfileController::profileLocked).
    import { computed } from 'vue';
    import { Link, usePage } from '@inertiajs/vue3';

    const page = usePage();
    const guest = computed(() => !page.props.auth?.user);
</script>

<template>
    <div class="absolute inset-0 z-10 rounded-xl backdrop-blur-[10px] bg-black/30 flex items-center justify-center p-4">
        <div class="bg-[#0a0e19]/95 border border-white/10 rounded-lg px-5 py-3 text-center max-w-xs">
            <div class="text-sm font-bold text-white">{{ $t('Verified accounts only') }}</div>
            <div class="text-xs text-gray-400 mt-0.5">
                <a v-if="guest" href="/login" class="text-blue-400 font-bold hover:text-blue-300">{{ $t('Log in and verify to see this') }}</a>
                <Link v-else href="/email/verify" class="text-red-400 font-bold hover:text-red-300">{{ $t('Verify your email to see this') }}</Link>
            </div>
        </div>
    </div>
</template>
