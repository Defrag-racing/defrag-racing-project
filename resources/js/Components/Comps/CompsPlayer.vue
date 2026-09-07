<script setup>
    import { Link } from '@inertiajs/vue3';

    // A player as comps shows them: flag, avatar, name, linking to the profile.
    // Small enough to be repeated in an entrant list, a standings row and a
    // winner badge without any of the three drifting from the others.
    defineProps({
        player: { type: Object, required: true },
        size: { type: String, default: 'md' },
        // A run an admin took out. Struck through and dimmed rather than
        // hidden, so the list still shows they turned up.
        struck: { type: Boolean, default: false },
    });
</script>

<template>
    <component
        :is="player.id ? Link : 'span'"
        :href="player.id ? route('profile.index', player.id) : undefined"
        class="inline-flex items-center gap-2 min-w-0 group"
        :class="struck ? 'opacity-50' : ''"
    >
        <img
            v-if="player.country"
            :src="`/images/flags/${player.country}.png`"
            :alt="player.country"
            class="flex-shrink-0 rounded-sm"
            :class="size === 'sm' ? 'w-4 h-3' : size === 'lg' ? 'w-6 h-[18px]' : 'w-5 h-4'"
        />
        <img
            v-if="player.photo"
            :src="`/storage/${player.photo}`"
            alt=""
            class="flex-shrink-0 rounded-full object-cover"
            :class="size === 'sm' ? 'w-5 h-5' : size === 'lg' ? 'w-8 h-8' : 'w-7 h-7'"
        />
        <!-- The name goes through q3tohtml, same as everywhere else: it
             arrives with Quake's ^-codes in it and is a string of literal
             carets until something turns them into spans. The name-effect
             class and --effect-color are the paid cosmetics; without them a
             player looks plain here and animated on every other page. -->
        <span
            class="truncate text-gray-200 group-hover:text-white transition-colors"
            :class="[
                size === 'sm' ? 'text-xs' : size === 'lg' ? 'text-lg font-bold' : 'text-sm',
                'name-effect-' + (player.name_effect || 'none'),
                struck ? 'line-through decoration-red-500/70' : '',
            ]"
            :style="`--effect-color: ${player.color || '#ffffff'}`"
            v-html="q3tohtml(player.name)"
        ></span>
    </component>
</template>
