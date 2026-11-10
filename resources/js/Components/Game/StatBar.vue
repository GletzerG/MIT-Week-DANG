<script setup lang="ts">
/**
 * Bar generik — dipakai untuk XP dan HP.
 * Bentuk persegi dengan garis takik, bukan pil: ini panel instrumen, bukan web.
 */
import { computed } from 'vue'

const props = withDefaults(
  defineProps<{
    label?: string
    value: number
    max: number
    variant?: 'xp' | 'hp'
    accent?: string
    showNumbers?: boolean
    height?: 'sm' | 'md'
  }>(),
  {
    variant: 'xp',
    accent: '#c6f000',
    showNumbers: true,
    height: 'md',
  },
)

const ratio = computed(() => {
  if (props.max <= 0) return 0
  return Math.max(0, Math.min(1, props.value / props.max))
})

const percent = computed(() => `${(ratio.value * 100).toFixed(2)}%`)
const trackClass = computed(() => (props.height === 'sm' ? 'h-1.5' : 'h-2.5'))
const fillStyle = computed(() => ({ width: percent.value, background: props.accent }))
</script>

<template>
  <div class="w-full select-none">
    <div
      v-if="label || showNumbers"
      class="mb-1 flex items-baseline justify-between font-mono text-[9px] uppercase tracking-[0.18em]"
    >
      <span class="text-bone/35">{{ label }}</span>
      <span class="tabular-nums text-bone/55">
        {{ value }}<span class="text-bone/20">/</span>{{ max }}
      </span>
    </div>

    <div class="relative w-full overflow-hidden border border-line/10 bg-ink-950" :class="trackClass">
      <div
        class="pointer-events-none absolute inset-0 z-10 opacity-25"
        style="background-image: repeating-linear-gradient(90deg, transparent 0 10px, rgba(8,8,10,.9) 10px 12px)"
      />
      <div class="h-full transition-all duration-700 ease-out" :style="fillStyle" />
    </div>
  </div>
</template>