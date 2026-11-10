<script setup lang="ts">
import { computed } from 'vue'
import type { AttemptResult } from '@/types/guardian'

const props = defineProps<{ result: AttemptResult }>()
defineEmits<{ next: [] }>()

const isCorrect = computed(() => props.result.correct)
const feedback = computed(() => props.result.feedback)

/** Model AI bisa mati — user cukup ditandai halus, tanpa istilah teknis. */
const degraded = computed(() => feedback.value.from_fallback)
</script>

<template>
  <section
    class="relative overflow-hidden border bg-ink-900 p-5 cut-md sm:p-6"
    :class="isCorrect ? 'border-acid-500/40' : 'border-magenta-500/40'"
  >
    <div
      class="pointer-events-none absolute inset-y-0 left-0 w-[3px]"
      :class="isCorrect ? 'bg-acid-500' : 'bg-magenta-500'"
    />
    <div class="pointer-events-none absolute inset-0 fx-halftone opacity-[0.05]" />

    <header class="relative flex flex-wrap items-center gap-3 pl-2">
      <span
        class="grid h-9 w-9 place-items-center font-display text-base leading-none cut-sm"
        :class="isCorrect ? 'bg-acid-500 text-onacid' : 'bg-magenta-500 text-onacid'"
      >
        {{ isCorrect ? '✓' : '✕' }}
      </span>
      <div class="min-w-0">
        <p class="font-display text-base uppercase leading-none tracking-tight text-bone">{{ feedback.headline }}</p>
        <p class="mt-1 font-mono text-[9px] uppercase tracking-[0.16em] text-bone/35">
          Jawabanmu: {{ result.chosen === 'hoax' ? 'HOAX' : 'FAKTA' }}
          <span class="text-bone/15">◆</span>
          Kebenaran: {{ result.truth_label }}
        </p>
      </div>

      <span
        v-if="degraded"
        class="ml-auto border border-amber-400/30 bg-amber-400/10 px-2 py-1 font-mono text-[8.5px] uppercase tracking-[0.16em] text-amber-300 cut-tag"
        title="Penjelasan dibuat dari basis sinyal, bukan dari LLM"
      >
        tanpa llm
      </span>
    </header>

    <div class="relative mt-4 space-y-2.5 pl-2">
      <p class="whitespace-pre-line font-cond text-[14px] leading-relaxed text-bone/70">
        {{ feedback.analysis }}
      </p>

      <ul v-if="feedback.red_flags.length" class="space-y-1.5 pt-1">
        <li
          v-for="(flag, i) in feedback.red_flags"
          :key="`${flag.type}-${i}`"
          class="flex gap-2.5 border border-line/10 bg-ink-950/60 px-3 py-2 cut-sm"
        >
          <span
            class="mt-1.5 h-1 w-1 shrink-0"
            :class="isCorrect ? 'bg-acid-500' : 'bg-magenta-500'"
          />
          <div>
            <p class="font-cond text-[12.5px] font-bold uppercase tracking-[0.05em] text-bone/85">{{ flag.label }}</p>
            <p class="font-cond text-[12.5px] leading-snug text-bone/45">{{ flag.note }}</p>
          </div>
        </li>
      </ul>

      <div v-if="feedback.tip" class="border-l-2 border-signal-400 bg-signal-500/5 px-3 py-2.5">
        <p class="font-mono text-[8.5px] uppercase tracking-[0.2em] text-signal-300">Pegangan</p>
        <p class="mt-1 font-cond text-[12.5px] leading-snug text-bone/60">{{ feedback.tip }}</p>
      </div>
    </div>

    <button
      type="button"
      class="relative mt-5 w-full border border-line/15 bg-transparent py-3 font-cond text-sm font-bold uppercase tracking-[0.18em] text-bone transition cut-sm hover:border-acid-500 hover:text-acid-400"
      @click="$emit('next')"
    >
      Quest berikutnya
    </button>
  </section>
</template>