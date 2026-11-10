<script setup lang="ts">
import { computed } from 'vue';
import type { VerdictTone } from '@/types/feed';

const props = defineProps<{
    label: string;
    tone: VerdictTone;
    size?: 'sm' | 'lg';
    code?: string;
}>();

const cls = computed(() =>
    props.tone === 'acid'
        ? 'bg-acid-500 text-onacid'
        : props.tone === 'magenta'
          ? 'bg-magenta-500 text-onacid'
          : 'bg-amber-400 text-onacid',
);
</script>

<template>
    <span
        class="inline-flex items-center gap-1.5 font-display uppercase leading-none cut-tag"
        :class="[cls, size === 'lg' ? 'px-3 py-2 text-sm tracking-wide' : 'px-2 py-1 text-[10px] tracking-[0.12em]']"
    >
        <span v-if="code" class="font-mono text-[0.85em] font-bold opacity-60">{{ code }}</span>
        {{ label }}
    </span>
</template>