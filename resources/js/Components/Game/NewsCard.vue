<script setup lang="ts">
import { computed } from 'vue'
import type { Quest } from '@/types/guardian'

const props = defineProps<{
  quest: Quest
  state?: 'idle' | 'resolving' | 'correct' | 'wrong'
}>()

/** Kesulitan → warna strip. Pemain menebak bobot misi dari warna, bukan teks. */
const tone = computed(() => {
  const map: Record<string, string> = {
    novice: 'bg-signal-400',
    seeker: 'bg-acid-500',
    knight: 'bg-acid-500',
    grandmaster: 'bg-magenta-500',
  }
  return map[props.quest.difficulty.key] ?? 'bg-signal-400'
})

const stateClass = computed(() => {
  if (props.state === 'correct') return 'border-acid-500/60 animate-[pulse-correct_.7s_ease-out]'
  if (props.state === 'wrong') return 'border-magenta-500/60 animate-shake'
  if (props.state === 'resolving') return 'opacity-60'
  return 'border-line/12'
})
</script>

<template>
  <article class="relative overflow-hidden border bg-ink-900 p-5 transition duration-300 cut-lg sm:p-7" :class="stateClass">
    <div class="pointer-events-none absolute inset-0 fx-grain" />
    <div class="pointer-events-none absolute inset-y-0 left-0 w-[3px]" :class="tone" />

    <!-- nomor urut misi, bukan hiasan: memberi rasa "arsip" -->
    <div class="pointer-events-none absolute right-4 top-2 select-none font-display text-6xl leading-none text-line/[0.03]">
      {{ String(quest.id).slice(-2) }}
    </div>

    <header class="relative flex flex-wrap items-center gap-1.5 pl-2">
      <span class="inline-flex items-center gap-1.5 border border-line/12 px-2 py-1 font-mono text-[9px] uppercase tracking-[0.16em] text-bone/60 cut-tag">
        <span class="h-1 w-1" :class="tone" />
        {{ quest.difficulty.label }}
      </span>
      <span class="border border-line/12 px-2 py-1 font-mono text-[9px] uppercase tracking-[0.16em] text-bone/40 cut-tag">
        {{ quest.category }}
      </span>
      <span class="ml-auto font-mono text-[10px] font-bold uppercase tracking-[0.14em] text-acid-400">
        +{{ quest.difficulty.reward }} XP
      </span>
    </header>

    <h2 class="relative mt-4 pl-2 font-display text-xl uppercase leading-tight tracking-tight text-bone sm:text-2xl">
      {{ quest.title }}
    </h2>

    <div class="relative mt-2.5 flex flex-wrap items-center gap-x-2 gap-y-1 pl-2 font-mono text-[10px] uppercase tracking-[0.12em] text-bone/35">
      <span class="text-bone/55">{{ quest.source }}</span>
      <span class="text-bone/15">◆</span>
      <span>{{ quest.published_at ? new Date(quest.published_at).toLocaleDateString('id-ID') : 'baru saja' }}</span>
    </div>

    <p class="relative mt-4 whitespace-pre-line border-l-2 border-line/15 py-0.5 pl-4 font-cond text-[14.5px] leading-relaxed text-bone/70">
      {{ quest.excerpt }}
    </p>

    <footer class="relative mt-5 flex items-center gap-2 border-t border-line/10 pl-2 pt-3 font-mono text-[9px] uppercase tracking-[0.2em] text-bone/25">
      <span class="h-1 w-1 bg-magenta-500" />
      Baca dulu, jawab belakangan
    </footer>
  </article>
</template>