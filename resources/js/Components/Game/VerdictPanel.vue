<script setup lang="ts">
import { onMounted, onUnmounted } from 'vue'
import type { Verdict } from '@/types/guardian'

const props = defineProps<{ disabled: boolean }>()
const emit = defineEmits<{ choose: [verdict: Verdict] }>()

const options: { key: Verdict; label: string; hint: string; hotkey: string; cls: string }[] = [
  {
    key: 'fact',
    label: 'FAKTA',
    hint: 'Sumber jelas, klaim bisa dicek',
    hotkey: 'F',
    cls: 'border-acid-500/40 hover:border-acid-500 hover:bg-acid-500 hover:text-onacid text-acid-400',
  },
  {
    key: 'hoax',
    label: 'HOAX',
    hint: 'Ada sinyal manipulasi',
    hotkey: 'H',
    cls: 'border-magenta-500/40 hover:border-magenta-500 hover:bg-magenta-500 hover:text-onacid text-magenta-400',
  },
]

function choose(key: Verdict) {
  if (props.disabled) return
  emit('choose', key)
}

function onKey(e: KeyboardEvent) {
  if (props.disabled) return
  const k = e.key.toLowerCase()
  if (k === 'f') choose('fact')
  if (k === 'h') choose('hoax')
}

// Hotkey bikin sesi beruntun terasa cepat — penting untuk gamifikasi.
onMounted(() => window.addEventListener('keydown', onKey))
onUnmounted(() => window.removeEventListener('keydown', onKey))
</script>

<template>
  <div class="grid gap-2.5 sm:grid-cols-2">
    <button
      v-for="option in options"
      :key="option.key"
      type="button"
      :disabled="disabled"
      class="group relative overflow-hidden border bg-ink-900 px-5 py-4 text-left transition duration-150 disabled:cursor-not-allowed disabled:opacity-35 cut-sm"
      :class="option.cls"
      @click="choose(option.key)"
    >
      <div class="flex items-center justify-between">
        <span class="font-display text-lg uppercase leading-none tracking-[0.06em]">{{ option.label }}</span>
        <kbd class="border border-line/25 px-1.5 py-0.5 font-mono text-[10px] opacity-50">{{ option.hotkey }}</kbd>
      </div>
      <span class="mt-1.5 block font-cond text-[11.5px] uppercase tracking-[0.1em] opacity-55">{{ option.hint }}</span>
      <span class="pointer-events-none absolute -bottom-6 -right-3 font-display text-5xl leading-none opacity-[0.06]">
        {{ option.label.charAt(0) }}
      </span>
    </button>
  </div>
</template>