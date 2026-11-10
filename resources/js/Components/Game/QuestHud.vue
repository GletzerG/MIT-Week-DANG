<script setup lang="ts">
import StatBar from './StatBar.vue'
import type { Guardian } from '@/types/guardian'

defineProps<{ guardian: Guardian }>()
</script>

<template>
  <aside class="relative overflow-hidden border border-line/10 bg-ink-900 p-4 cut-md">
    <div class="pointer-events-none absolute -right-12 -top-12 h-32 w-32 rounded-full bg-acid-500/10 blur-2xl" />

    <div class="relative flex items-center gap-3">
      <div class="grid h-12 w-12 shrink-0 place-items-center border border-acid-500/40 bg-acid-500/10 font-display text-lg leading-none text-acid-400 cut-sm">
        {{ guardian.level }}
      </div>
      <div class="min-w-0">
        <p class="truncate font-cond text-sm font-bold uppercase tracking-[0.06em] text-bone">{{ guardian.name }}</p>
        <p class="truncate font-mono text-[9px] uppercase tracking-[0.18em] text-acid-400">{{ guardian.rank_label }}</p>
      </div>
    </div>

    <div class="relative mt-4 space-y-3">
      <StatBar
        label="Menuju level berikutnya"
        :value="guardian.xp_into_level"
        :max="guardian.xp_level_cost"
        accent="#c6f000"
      />
      <StatBar
        label="Vitality"
        :value="guardian.hp"
        :max="guardian.max_hp"
        variant="hp"
        :accent="guardian.hp > 40 ? '#2fd8f5' : '#ff1470'"
      />
    </div>

    <dl class="relative mt-4 grid grid-cols-3 gap-1.5">
      <div class="border border-line/10 bg-ink-950/50 py-2 text-center cut-sm">
        <dt class="font-mono text-[8px] uppercase tracking-[0.14em] text-bone/30">Streak</dt>
        <dd class="font-display text-sm leading-none text-amber-300">{{ guardian.streak }}</dd>
      </div>
      <div class="border border-line/10 bg-ink-950/50 py-2 text-center cut-sm">
        <dt class="font-mono text-[8px] uppercase tracking-[0.14em] text-bone/30">Solved</dt>
        <dd class="font-display text-sm leading-none text-bone">{{ guardian.solved_count }}</dd>
      </div>
      <div class="border border-line/10 bg-ink-950/50 py-2 text-center cut-sm">
        <dt class="font-mono text-[8px] uppercase tracking-[0.14em] text-bone/30">Acc</dt>
        <dd class="font-display text-sm leading-none text-acid-400">{{ Math.round(guardian.accuracy * 100) }}%</dd>
      </div>
    </dl>

    <p class="relative mt-3 border-t border-line/10 pt-3 font-cond text-[11.5px] leading-snug text-bone/35">
      Butuh <span class="font-mono text-bone/70">{{ guardian.xp_for_next }} XP</span> lagi menuju Level
      {{ guardian.level + 1 }}.
      <template v-if="guardian.next_rank">
        Rank berikutnya: <span class="text-acid-400">{{ guardian.next_rank.label }}</span>
        (Lv {{ guardian.next_rank.min_level }}).
      </template>
    </p>
  </aside>
</template>