<script setup lang="ts">
withDefaults(
    defineProps<{
        type?: 'button' | 'submit' | 'reset';
    }>(),
    {
        type: 'button',
    },
);
</script>

<template>
    <button :type="type" class="btn-ghost !px-4 !py-2.5 !text-xs">
        <slot />
    </button>
</template>
