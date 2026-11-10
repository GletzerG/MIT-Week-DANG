<script setup lang="ts">
import type { FloatReward } from '@/composables/useGuardian'

defineProps<{ floats: FloatReward[] }>()
</script>

<template>
  <!-- aria-live supaya screen reader ikut mengumumkan reward -->
  <div class="pointer-events-none absolute inset-x-0 top-1/3 z-30 grid place-items-center" aria-live="polite">
    <TransitionGroup name="float" tag="div" class="relative flex flex-col items-center gap-1">
      <span
        v-for="f in floats"
        :key="f.id"
        class="font-display text-2xl uppercase leading-none tracking-tight drop-shadow-[0_0_14px_currentColor] sm:text-3xl"
        :class="f.positive ? 'text-acid-400' : 'text-magenta-500'"
      >
        {{ f.text }}
      </span>
    </TransitionGroup>
  </div>
</template>

<style scoped>
.float-enter-active {
  transition: all 0.25s cubic-bezier(0.2, 1.4, 0.4, 1);
}
.float-enter-from {
  opacity: 0;
  transform: scale(0.5) translateY(20px);
}
.float-leave-active {
  transition: all 0.9s ease-out;
}
.float-leave-to {
  opacity: 0;
  transform: translateY(-90px) scale(1.15);
}
</style>