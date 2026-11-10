<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        value: number;
        max?: number;
        tone?: 'acid' | 'magenta' | 'signal' | 'amber';
        label?: string;
        readout?: string;
        height?: 'sm' | 'md';
    }>(),
    { max: 100, tone: 'acid', height: 'md' },
);

const pct = computed(() => Math.max(0, Math.min(100, (props.value / Math.max(1, props.max)) * 100)));

const bar = computed(
    () =>
        ({
            acid: 'bg-acid-500',
            magenta: 'bg-magenta-500',
            signal: 'bg-signal-400',
            amber: 'bg-amber-400',
        })[props.tone],
);
</script>

<template>
    <div class="w-full">
        <div v-if="label || readout" class="mb-1 flex items-baseline justify-between">
            <span class="font-mono text-[9px] uppercase tracking-[0.22em] text-bone/40">{{ label }}</span>
            <span class="font-mono text-[10px] font-bold text-bone/70">{{ readout }}</span>
        </div>
        <div
            class="w-full overflow-hidden bg-ink-700"
            :class="height === 'sm' ? 'h-1' : 'h-2'"
        >
            <div
                class="h-full transition-[width] duration-700 ease-out"
                :class="bar"
                :style="{ width: pct + '%' }"
            />
        </div>
    </div>
</template>