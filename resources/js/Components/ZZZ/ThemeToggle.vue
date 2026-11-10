<script setup lang="ts">
import { useTheme } from '@/composables/useTheme';

withDefaults(defineProps<{ compact?: boolean }>(), { compact: false });

const { mode, toggle } = useTheme();
</script>

<template>
    <button
        type="button"
        class="group flex items-center gap-2 border border-line/15 px-2.5 py-1.5 transition cut-sm hover:border-acid-500/60"
        :title="mode === 'light' ? 'Ganti ke mode gelap' : 'Ganti ke mode terang'"
        :aria-label="mode === 'light' ? 'Aktifkan mode gelap' : 'Aktifkan mode terang'"
        @click="toggle"
    >
        <span class="relative flex h-4 w-8 items-center bg-ink-800 cut-tag">
            <span
                class="absolute h-3.5 w-3.5 bg-acid-500 transition-all duration-200"
                :class="mode === 'light' ? 'left-0' : 'left-[calc(100%-0.875rem)]'"
            />
        </span>

        <span
            v-if="!compact"
            class="font-mono text-[9px] font-bold uppercase tracking-[0.18em] text-bone/60"
        >
            {{ mode === 'light' ? 'terang' : 'gelap' }}
        </span>
    </button>
</template>
