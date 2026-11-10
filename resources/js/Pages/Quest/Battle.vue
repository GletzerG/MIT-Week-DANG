<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import { computed } from 'vue'

import GuardianLayout from '@/Layouts/GuardianLayout.vue'
import QuestHud from '@/Components/Game/QuestHud.vue'
import NewsCard from '@/Components/Game/NewsCard.vue'
import VerdictPanel from '@/Components/Game/VerdictPanel.vue'
import FloatingReward from '@/Components/Game/FloatingReward.vue'
import AiFeedbackPanel from '@/Components/Game/AiFeedbackPanel.vue'
import { useGuardian } from '@/composables/useGuardian'
import type { Guardian, Quest } from '@/types/guardian'

const props = defineProps<{
  guardian: Guardian
  quest: Quest | null
  stats: { solved_today: number }
}>()

const { guardian, quest, phase, result, floats, levelUp, error, isBusy, isRevealed, submit, next } =
  useGuardian(props.guardian, props.quest)

// Kartu ikut "bereaksi": hijau saat benar, getar saat salah.
type CardState = 'idle' | 'resolving' | 'correct' | 'wrong'

const cardState = computed<CardState>(() => {
  if (phase.value === 'resolving') return 'resolving'
  if (phase.value !== 'revealed' || !result.value) return 'idle'
  return result.value.correct ? 'correct' : 'wrong'
})

const emptyPool = computed(() => !quest.value)

// KALAU MAU ANIMASI LEBIH KAYA (spring, stagger):
//   npm i @vueuse/motion
//   app.ts -> import { MotionPlugin } from '@vueuse/motion'; app.use(MotionPlugin)
// Lalu di komponen ini:
//   <NewsCard v-motion
//     :initial="{ opacity: 0, y: 24, scale: .97 }"
//     :enter="{ opacity: 1, y: 0, scale: 1, transition: { type: 'spring', stiffness: 220, damping: 22 } }"
//     :quest="quest!" />
// Versi CSS di bawah sengaja jadi default supaya build tidak butuh dependency ekstra.

</script>

<template>
  <Head title="Battle — Guardian Genesis" />

  <GuardianLayout active="quest" :guardian="guardian">
      <header class="relative mb-6 overflow-hidden border border-line/10 bg-ink-900 p-5 cut-lg sm:p-6">
        <div class="pointer-events-none absolute -right-10 -top-16 h-52 w-52 rounded-full bg-magenta-500/10 blur-3xl" />
        <div class="pointer-events-none absolute inset-0 fx-grain" />
        <div class="relative flex flex-wrap items-end justify-between gap-4">
          <div>
            <div class="flex items-center gap-2 font-mono text-[10px] uppercase tracking-[0.32em] text-magenta-400">
              <span class="h-1.5 w-1.5 animate-pulse bg-magenta-500" />
              Trial · 03
            </div>
            <h1 class="mt-2 font-display text-3xl uppercase leading-[0.85] tracking-tight text-bone sm:text-4xl">
              Uji instingmu
            </h1>
            <p class="mt-2.5 max-w-md font-cond text-[13.5px] leading-snug text-bone/50">
              Semua bantuan dimatikan di sini — tanpa skor kredibilitas, tanpa daftar sinyal.
              Hanya teks berita dan keputusanmu.
            </p>
          </div>
          <div class="flex items-center gap-3 font-mono text-[10px] uppercase tracking-[0.16em] text-bone/35">
            <span>{{ stats.solved_today }} hari ini</span>
            <span class="h-3 w-px bg-line/15" />
            <span class="text-bone/60">{{ guardian.xp }} XP</span>
          </div>
        </div>
      </header>

      <div class="grid gap-4 lg:grid-cols-[300px_minmax(0,1fr)] lg:gap-6">
              <!-- Di ponsel kartu soal datang dulu; HUD menyusul di bawah. -->
              <QuestHud :guardian="guardian" class="order-2 lg:order-1" />

              <main class="relative order-1 lg:order-2">
                <FloatingReward :floats="floats" />

                <!-- Pool habis: jangan tampilkan error, tampilkan jeda naratif. -->
                <div v-if="emptyPool" class="panel grid place-items-center p-8 text-center sm:p-14">
                  <p class="font-display text-lg uppercase tracking-tight text-bone">Arsip Genesis sedang tenang.</p>
                  <p class="mt-2 max-w-sm font-cond text-sm leading-snug text-bone/45">
                    Semua misi hari ini sudah kamu selesaikan. Quest baru datang setiap hari —
                    sementara itu, baca yang sudah terverifikasi atau periksa klaim sendiri.
                  </p>
                  <div class="mt-4 flex flex-wrap justify-center gap-2">
                    <a href="/dashboard" class="btn-ghost !px-3 !py-2 !text-xs">Buka feed</a>
                    <a href="/analisis" class="btn-acid !px-3 !py-2 !text-xs">Cek klaim</a>
                  </div>
                </div>

                <template v-else>
                  <Transition name="swap" mode="out-in">
                    <div :key="quest!.id" class="space-y-4">
                      <NewsCard :quest="quest!" :state="cardState" />

                      <p v-if="error" class="border-l-2 border-magenta-500 bg-magenta-500/10 px-3 py-2 font-cond text-[13px] leading-snug text-magenta-300">
                        {{ error }}
                      </p>

                      <VerdictPanel v-if="!isRevealed" :disabled="isBusy" @choose="submit" />

                      <div v-else-if="isBusy" class="h-16 animate-pulse bg-line/5 cut-md" />

                      <AiFeedbackPanel
                        v-if="isRevealed && result"
                        :result="result"
                        @next="next"
                      />
                    </div>
                  </Transition>
                </template>
              </main>
            </div>

      <!-- Level Up overlay -->
    <Transition name="levelup">
      <div
        v-if="levelUp"
        class="pointer-events-none fixed inset-0 z-50 grid place-items-center bg-black/70 backdrop-blur-sm"
      >
        <div class="relative text-center">
                  <div class="absolute inset-0 -z-10 animate-glow rounded-full blur-3xl"
                       :style="{ background: guardian.accent }" />
                  <p class="font-mono text-[11px] uppercase tracking-[0.6em] text-acid-400">
                    level naik
                  </p>
                  <p class="mt-2 font-display text-6xl font-black text-bone drop-shadow-[0_0_40px_rgba(198,240,0,.45)] sm:text-8xl">
                    {{ guardian.level }}
                  </p>
                  <p class="mt-3 font-cond text-base uppercase tracking-[0.3em] text-acid-400">
                    {{ guardian.rank_label }}
                  </p>
                </div>
      </div>
    </Transition>
  </GuardianLayout>
</template>

<style scoped>
.swap-enter-active,
.swap-leave-active {
  transition: opacity 0.28s ease, transform 0.28s cubic-bezier(0.2, 1, 0.3, 1);
}
.swap-enter-from {
  opacity: 0;
  transform: translateY(16px) scale(0.985);
}
.swap-leave-to {
  opacity: 0;
  transform: translateY(-12px) scale(0.985);
}

.levelup-enter-active,
.levelup-leave-active {
  transition: opacity 0.5s ease;
}
.levelup-enter-from,
.levelup-leave-to {
  opacity: 0;
}
</style>

