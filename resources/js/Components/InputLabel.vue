<script setup lang="ts">
defineProps<{
    value?: string;
}>();
</script>

<template>
    <label class="mb-1.5 block font-mono text-[10px] font-bold uppercase tracking-[0.2em] text-bone/45">
        <span v-if="value">{{ value }}</span>
        <span v-else><slot /></span>
    </label>
</template>
