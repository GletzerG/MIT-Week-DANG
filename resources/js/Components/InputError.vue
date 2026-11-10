<script setup lang="ts">
defineProps<{
    message?: string;
}>();
</script>

<template>
    <div v-show="message">
        <p class="font-mono text-[11px] font-semibold text-magenta-400">
            {{ message }}
        </p>
    </div>
</template>
