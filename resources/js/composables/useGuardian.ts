import { computed, reactive, ref } from 'vue'
import type { AttemptResult, Guardian, Quest, Verdict } from '@/types/guardian'

export interface FloatReward {
  id: number
  text: string
  positive: boolean
}

/**
 * State machine halaman Battle.
 *
 * Fase:
 *   reading   -> kartu tampil, user memilih
 *   resolving -> request berjalan, semua tombol mati
 *   revealed  -> kebenaran + feedback AI tampil
 *
 * Semua mutasi state lewat composable ini, bukan di template. Template
 * tinggal membaca; logika tetap bisa dites tanpa mount komponen.
 */
export function useGuardian(initialGuardian: Guardian, initialQuest: Quest | null) {
  const guardian = reactive<Guardian>({ ...initialGuardian })
  const quest = ref<Quest | null>(initialQuest)
  const phase = ref<'reading' | 'resolving' | 'revealed'>('reading')
  const result = ref<AttemptResult | null>(null)
  const floats = ref<FloatReward[]>([])
  const levelUp = ref(false)
  const error = ref<string | null>(null)

  // Dicatat saat kartu mulai dibaca -> dipakai untuk mengukur kecepatan analisis.
  let questStartedAt = Date.now()
  let floatSeq = 0

  const isBusy = computed(() => phase.value === 'resolving')
  const isRevealed = computed(() => phase.value === 'revealed')

  function resetQuestTimer() {
    questStartedAt = Date.now()
  }

  function pushFloat(text: string, positive: boolean) {
    const id = ++floatSeq
    floats.value.push({ id, text, positive })

    // Auto-bersih: kalau tidak, array tumbuh terus selama sesi panjang.
    window.setTimeout(() => {
      floats.value = floats.value.filter((f) => f.id !== id)
    }, 1400)
  }

  async function submit(verdict: Verdict) {
    if (!quest.value || phase.value !== 'reading') return

    phase.value = 'resolving'
    error.value = null

    try {
      const { data } = await window.axios.post<AttemptResult>('/quest/attempt', {
        quest_id: quest.value.id,
        verdict,
        response_ms: Date.now() - questStartedAt,
      })

      result.value = data
      phase.value = 'revealed'

      // Animasi angka melayang. Tanda ikut dikirim supaya Vue bisa pakai
      // warna berbeda tanpa perlu tahu aturan bisnisnya.
      if (data.xp_delta !== 0) {
        pushFloat(`${data.xp_delta > 0 ? '+' : ''}${data.xp_delta} XP`, data.xp_delta > 0)
      }
      if (data.hp_delta !== 0) {
        pushFloat(`${data.hp_delta} HP`, false)
      }
      if (data.leveled_up) {
        levelUp.value = true
        window.setTimeout(() => (levelUp.value = false), 2600)
      }

      // Sinkronkan stats global: XP bar transisi sendiri lewat CSS.
      Object.assign(guardian, data.guardian)
    } catch (e: any) {
      error.value =
        e?.response?.data?.message ??
        'Koneksi ke Genesis terputus. Coba lagi sebentar.'
      phase.value = 'reading'
    }
  }

  function next() {
    quest.value = result.value?.next_quest ?? null
    result.value = null
    phase.value = 'reading'
    resetQuestTimer()
  }

  return {
    guardian,
    quest,
    phase,
    result,
    floats,
    levelUp,
    error,
    isBusy,
    isRevealed,
    submit,
    next,
    resetQuestTimer,
  }
}
