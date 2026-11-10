<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import axios from 'axios';
import { Head, Link } from '@inertiajs/vue3';
import GuardianLayout from '@/Layouts/GuardianLayout.vue';
import VerdictBadge from '@/Components/ZZZ/VerdictBadge.vue';
import StatMeter from '@/Components/ZZZ/StatMeter.vue';
import type { ClaimResult, Guardian } from '@/types/feed';

const props = defineProps<{
    guardian: Guardian;
    ai: { enabled: boolean; model: string | null; vision: boolean; visionModel: string | null };
    limits: { min: number; max: number; image_kb: number; image_mimes: string[]; url: number };
    quota: { used: number; cap: number; each: number };
    recent: {
        key: string;
        excerpt: string;
        verdict: string;
        tone: 'acid' | 'magenta' | 'amber';
        confidence: number;
        type: string;
        label: string | null;
    }[];
}>();

type Mode = 'text' | 'image' | 'link';

const mode = ref<Mode>('text');
const text = ref('');
const url = ref('');
const file = ref<File | null>(null);
const previewUrl = ref<string | null>(null);
const dragging = ref(false);
const busy = ref(false);
const stage = ref('');
const error = ref<string | null>(null);
const result = ref<ClaimResult | null>(null);
const showExtracted = ref(false);
const fileInput = ref<HTMLInputElement | null>(null);
const resultEl = ref<HTMLElement | null>(null);
const textareaEl = ref<HTMLTextAreaElement | null>(null);

const guardian = ref<Guardian>(props.guardian);
const quota = ref({ ...props.quota });

const modes: { key: Mode; code: string; label: string; hint: string; disabled: boolean }[] = [
    { key: 'text', code: '01', label: 'Teks', hint: 'Pesan berantai, caption, potongan berita', disabled: false },
    { key: 'image', code: '02', label: 'Gambar', hint: 'Screenshot berita atau postingan', disabled: !props.ai.vision },
    { key: 'link', code: '03', label: 'Tautan', hint: 'URL artikel — dibaca utuh, bukan cuma judul', disabled: false },
];

const activeMode = computed(() => modes.find((m) => m.key === mode.value)!);
const remaining = computed(() => Math.max(0, quota.value.cap - quota.value.used));
const textLength = computed(() => text.value.trim().length);

const canSubmit = computed(() => {
    if (busy.value) return false;
    if (mode.value === 'text') return textLength.value >= props.limits.min && textLength.value <= props.limits.max;
    if (mode.value === 'link') return url.value.trim().length > 4;
    return file.value !== null;
});

const samples = [
    {
        label: 'Pesan grup keluarga',
        text: 'INFO PENTING!!! Jangan pernah minum air lemon hangat setelah makan, karena bisa memicu kanker lambung. Sudah dibuktikan oleh dokter ternama. SEBARKAN ke semua grup sebelum dihapus!!',
    },
    {
        label: 'Pengumuman resmi',
        text: 'Badan Meteorologi Klimatologi dan Geofisika (BMKG) menyampaikan bahwa gempa berkekuatan magnitudo 5,2 berpusat di Sukabumi pada Selasa pukul 09.14 WIB. Tidak ada peringatan tsunami. Informasi resmi bisa dicek di situs bmkg.go.id.',
    },
];

// ── gambar ────────────────────────────────────────────────────────────────
const maxBytes = computed(() => props.limits.image_kb * 1024);

function acceptFile(candidate: File | null | undefined) {
    error.value = null;

    if (!candidate) return;

    const mime = candidate.type.toLowerCase();
    const allowed = props.limits.image_mimes.map((m) => `image/${m === 'jpg' ? 'jpeg' : m}`);

    if (!allowed.includes(mime)) {
        error.value = `Format ${mime || 'itu'} tidak didukung. Pakai PNG, JPG, atau WEBP.`;
        return;
    }

    if (candidate.size > maxBytes.value) {
        error.value = `Gambar ${(candidate.size / 1024 / 1024).toFixed(1)} MB — maksimal ${(props.limits.image_kb / 1024).toFixed(0)} MB.`;
        return;
    }

    if (previewUrl.value) URL.revokeObjectURL(previewUrl.value);

    file.value = candidate;
    previewUrl.value = URL.createObjectURL(candidate);
}

function clearFile() {
    if (previewUrl.value) URL.revokeObjectURL(previewUrl.value);
    previewUrl.value = null;
    file.value = null;
    if (fileInput.value) fileInput.value.value = '';
}

function onDrop(event: DragEvent) {
    dragging.value = false;
    acceptFile(event.dataTransfer?.files?.[0]);
}

function onFileChange(event: Event) {
    acceptFile((event.target as HTMLInputElement).files?.[0]);
}

function onPaste(event: ClipboardEvent) {
    if (mode.value !== 'image') return;

    const item = Array.from(event.clipboardData?.items ?? []).find((i) => i.type.startsWith('image/'));

    if (item) {
        event.preventDefault();
        acceptFile(item.getAsFile());
    }
}

// ── submit ────────────────────────────────────────────────────────────────
async function analyze() {
    if (!canSubmit.value) return;

    error.value = null;
    result.value = null;
    busy.value = true;
    stage.value = mode.value === 'image' ? 'Membaca gambar…' : mode.value === 'link' ? 'Mengambil halaman…' : 'Membaca klaim…';

    // Model visual butuh belasan detik. Tanpa tahap kedua, layar terasa mati.
    const slow = setTimeout(() => {
        stage.value = mode.value === 'image' ? 'Menyalin teks & sinyal visual…' : 'Menilai klaim…';
    }, 6000);

    try {
        let payload: FormData | Record<string, string>;

        if (mode.value === 'image' && file.value) {
            payload = new FormData();
            payload.append('mode', 'image');
            payload.append('image', file.value);
        } else if (mode.value === 'link') {
            payload = { mode: 'link', url: url.value.trim() };
        } else {
            payload = { mode: 'text', text: text.value };
        }

        const { data } = await axios.post<ClaimResult>('/analisis', payload, { timeout: 120000 });

        result.value = data;
        guardian.value = data.guardian;
        quota.value = { ...quota.value, used: data.quota.used };
        showExtracted.value = false;

        await nextTick();
        resultEl.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    } catch (e: unknown) {
        const err = e as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } };
        const validation = err.response?.data?.errors;

        error.value =
            validation && Object.keys(validation).length
                ? Object.values(validation)[0][0]
                : (err.response?.data?.message ?? 'Tidak bisa menghubungi mesin. Coba lagi.');
    } finally {
        clearTimeout(slow);
        busy.value = false;
        stage.value = '';
    }
}

function reset() {
    result.value = null;
    error.value = null;
    showExtracted.value = false;
}

function useSample(sample: { text: string }) {
    mode.value = 'text';
    text.value = sample.text;
    textareaEl.value?.focus();
}

function switchMode(next: Mode) {
    const target = modes.find((m) => m.key === next);
    if (!target || target.disabled) return;

    mode.value = next;
    error.value = null;
}

function onKeydown(event: KeyboardEvent) {
    if ((event.ctrlKey || event.metaKey) && event.key === 'Enter') {
        event.preventDefault();
        analyze();
    }
    if (event.key === 'Escape' && result.value) result.value = null;
}

// ── presentational helpers ────────────────────────────────────────────────
const toneText: Record<string, string> = {
    acid: 'text-acid-400',
    magenta: 'text-magenta-400',
    amber: 'text-amber-400',
};

const toneBg: Record<string, string> = {
    acid: 'bg-acid-500',
    magenta: 'bg-magenta-500',
    amber: 'bg-amber-400',
};

// Kelas dinamis ("border-" + tone) tidak pernah dibuat Tailwind, jadi dipetakan
// utuh di sini. Ini bukan kerapian, ini soal border-nya benar-benar muncul.
const toneBorder: Record<string, string> = {
    acid: 'border-acid-500/40',
    magenta: 'border-magenta-500/40',
    amber: 'border-amber-400/40',
};

const repTone = (tier?: string) =>
    tier === 'trusted' ? 'acid' : tier === 'suspect' ? 'magenta' : 'amber';

const sourceIcon = (type: string) => (type === 'image' ? '▣' : type === 'link' ? '↗' : '⌨');

watch(mode, () => {
    error.value = null;
});

onMounted(() => window.addEventListener('paste', onPaste));
onBeforeUnmount(() => {
    window.removeEventListener('paste', onPaste);
    if (previewUrl.value) URL.revokeObjectURL(previewUrl.value);
});
</script>

<template>
    <Head title="Deteksi Klaim" />

    <GuardianLayout active="analysis" :guardian="guardian">
        <!-- ══ KEPALA ══ -->
        <header class="mb-4 flex flex-wrap items-end justify-between gap-3">
            <div>
                <div class="flex items-center gap-2 font-mono text-[10px] uppercase tracking-[0.3em] text-acid-400">
                    <span class="h-1.5 w-1.5 animate-pulse bg-acid-500" />
                    Alat · 02 — stasiun deteksi
                </div>
                <h1 class="mt-2 font-display text-3xl uppercase leading-[0.85] tracking-tight text-bone sm:text-5xl">
                    Cek dulu.<br /><span class="text-acid-400">Baru percaya.</span>
                </h1>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <span
                    class="label-tag"
                    :class="ai.enabled ? 'bg-acid-500 text-onacid' : 'border border-line/15 text-bone/50'"
                >
                    {{ ai.enabled ? `AI · ${ai.model}` : 'Mode aturan' }}
                </span>
                <span
                    v-if="ai.vision"
                    class="label-tag border border-signal-400/40 bg-signal-400/10 text-signal-300"
                >
                    Vision · {{ ai.visionModel }}
                </span>
                <span class="label-tag border border-line/15 text-bone/50">
                    XP {{ remaining }}/{{ quota.cap }} tersisa
                </span>
            </div>
        </header>

        <div class="grid items-start gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,430px)]">
            <!-- ══ KIRI: INPUT ══ -->
            <section class="panel p-4 sm:p-5">
                <!-- pemilih mode -->
                <div class="grid grid-cols-3 gap-1.5">
                    <button
                        v-for="m in modes"
                        :key="m.key"
                        type="button"
                        :disabled="m.disabled"
                        class="group relative flex flex-col items-start gap-0.5 border px-3 py-2.5 text-left transition cut-sm"
                        :class="[
                            mode === m.key
                                ? 'border-acid-500 bg-acid-500 text-onacid'
                                : 'border-line/10 bg-ink-950/60 text-bone/60 hover:border-line/25 hover:text-bone',
                            m.disabled ? 'cursor-not-allowed opacity-35 hover:border-line/10' : '',
                        ]"
                        @click="switchMode(m.key)"
                    >
                        <span class="flex w-full items-center justify-between">
                            <span class="font-cond text-sm font-bold uppercase tracking-[0.1em]">{{ m.label }}</span>
                            <span class="font-mono text-[9px] opacity-50">{{ m.code }}</span>
                        </span>
                        <span class="font-mono text-[9px] uppercase leading-tight tracking-[0.06em] opacity-55">
                            {{ m.disabled ? 'butuh model visual' : m.hint }}
                        </span>
                    </button>
                </div>

                <!-- ── TEKS ── -->
                <div v-if="mode === 'text'" class="mt-4">
                    <div class="flex items-baseline justify-between">
                        <label for="claim" class="font-mono text-[10px] uppercase tracking-[0.2em] text-bone/40">
                            Teks yang mau dicek
                        </label>
                        <span
                            class="font-mono text-[10px]"
                            :class="textLength > limits.max ? 'text-magenta-400' : textLength >= limits.min ? 'text-acid-400' : 'text-bone/30'"
                        >
                            {{ textLength }} / {{ limits.max }}
                        </span>
                    </div>

                    <textarea
                        id="claim"
                        ref="textareaEl"
                        v-model="text"
                        rows="9"
                        spellcheck="false"
                        :maxlength="limits.max"
                        placeholder="Tempel pesan berantai, caption, atau potongan berita di sini…"
                        class="mt-2 w-full resize-y border border-line/10 bg-ink-950 p-3.5 font-cond text-[15px] leading-relaxed text-bone placeholder:text-bone/25 focus:border-acid-500/60 focus:outline-none focus:ring-1 focus:ring-acid-500/30 cut-sm"
                        @keydown="onKeydown"
                    />

                    <div class="mt-2 flex flex-wrap items-center gap-2">
                        <span class="font-mono text-[9px] uppercase tracking-[0.16em] text-bone/25">contoh cepat</span>
                        <button
                            v-for="s in samples"
                            :key="s.label"
                            type="button"
                            class="border border-line/10 px-2 py-1 font-cond text-[11px] uppercase tracking-[0.08em] text-bone/50 transition cut-tag hover:border-acid-500/50 hover:text-acid-400"
                            @click="useSample(s)"
                        >
                            {{ s.label }}
                        </button>
                    </div>
                </div>

                <!-- ── GAMBAR ── -->
                <div v-else-if="mode === 'image'" class="mt-4">
                    <div class="flex items-baseline justify-between">
                        <span class="font-mono text-[10px] uppercase tracking-[0.2em] text-bone/40">
                            Tangkapan layar berita / postingan
                        </span>
                        <span class="font-mono text-[9px] uppercase tracking-[0.14em] text-bone/25">
                            maks {{ (limits.image_kb / 1024).toFixed(0) }} MB · PNG/JPG/WEBP
                        </span>
                    </div>

                    <!-- sudah ada file -->
                    <div v-if="previewUrl && file" class="mt-2 border border-acid-500/30 bg-ink-950 p-2 cut-sm">
                        <div class="relative overflow-hidden border border-line/10 cut-sm">
                            <img :src="previewUrl" alt="Pratinjau gambar" class="max-h-72 w-full object-contain bg-ink-900" />
                            <div class="pointer-events-none absolute inset-x-0 top-0 h-0.5 animate-pulse bg-acid-500/70" />
                        </div>
                        <div class="mt-2 flex items-center justify-between gap-2">
                            <div class="min-w-0">
                                <p class="truncate font-cond text-[13px] text-bone/80">{{ file.name }}</p>
                                <p class="font-mono text-[9px] uppercase tracking-[0.14em] text-bone/30">
                                    {{ (file.size / 1024).toFixed(0) }} KB
                                </p>
                            </div>
                            <button
                                type="button"
                                class="shrink-0 border border-line/15 px-2.5 py-1 font-mono text-[9px] uppercase tracking-[0.14em] text-bone/50 transition cut-tag hover:border-magenta-500/50 hover:text-magenta-400"
                                @click="clearFile"
                            >
                                ganti
                            </button>
                        </div>
                    </div>

                    <!-- dropzone -->
                    <button
                        v-else
                        type="button"
                        class="mt-2 flex w-full flex-col items-center justify-center gap-2 border border-dashed px-4 py-10 text-center transition cut-md"
                        :class="dragging ? 'border-acid-500 bg-acid-500/10' : 'border-line/20 bg-ink-950/50 hover:border-acid-500/60 hover:bg-line/[0.03]'"
                        @click="fileInput?.click()"
                        @dragover.prevent="dragging = true"
                        @dragleave.prevent="dragging = false"
                        @drop.prevent="onDrop"
                    >
                        <span class="font-display text-2xl text-acid-400">▣</span>
                        <span class="font-cond text-sm font-bold uppercase tracking-[0.12em] text-bone">
                            {{ dragging ? 'Lepas di sini' : 'Tarik gambar / klik untuk pilih' }}
                        </span>
                        <span class="font-mono text-[9px] uppercase tracking-[0.16em] text-bone/35">
                            atau tempel langsung dengan Ctrl+V
                        </span>
                    </button>

                    <input
                        ref="fileInput"
                        type="file"
                        accept="image/png,image/jpeg,image/webp"
                        class="hidden"
                        @change="onFileChange"
                    />

                    <p class="mt-2 font-cond text-[12.5px] leading-snug text-bone/40">
                        Mesin membaca teks di dalam gambar dulu, lalu menilai klaimnya. Kalau tulisannya buram,
                        hasilnya akan bilang begitu — bukan menebak.
                    </p>
                </div>

                <!-- ── TAUTAN ── -->
                <div v-else class="mt-4">
                    <label for="link" class="font-mono text-[10px] uppercase tracking-[0.2em] text-bone/40">
                        Alamat artikel
                    </label>

                    <div class="mt-2 flex items-stretch gap-2">
                        <input
                            id="link"
                            v-model="url"
                            type="url"
                            inputmode="url"
                            spellcheck="false"
                            :maxlength="limits.url"
                            placeholder="https://contoh.com/berita/…"
                            class="min-w-0 flex-1 border border-line/10 bg-ink-950 px-3.5 py-3 font-mono text-[13px] text-bone placeholder:text-bone/25 focus:border-acid-500/60 focus:outline-none focus:ring-1 focus:ring-acid-500/30 cut-sm"
                            @keydown.enter.prevent="analyze"
                        />
                    </div>

                    <div class="mt-3 grid gap-1.5 sm:grid-cols-2">
                        <div class="flex items-center gap-2 border border-line/10 bg-ink-950/50 px-2.5 py-2 cut-sm">
                            <span class="h-1.5 w-1.5 bg-acid-500" />
                            <span class="font-cond text-[12.5px] text-bone/60">Halaman diambil utuh, bukan cuma judul</span>
                        </div>
                        <div class="flex items-center gap-2 border border-line/10 bg-ink-950/50 px-2.5 py-2 cut-sm">
                            <span class="h-1.5 w-1.5 bg-acid-500" />
                            <span class="font-cond text-[12.5px] text-bone/60">Domain, penulis, tanggal ikut dinilai</span>
                        </div>
                    </div>

                    <p class="mt-2 font-cond text-[12.5px] leading-snug text-bone/40">
                        Hanya halaman publik http/https. Alamat jaringan lokal sengaja diblokir.
                    </p>
                </div>

                <!-- aksi -->
                <div class="mt-4 flex flex-wrap items-center gap-2">
                    <button type="button" class="btn-acid" :disabled="!canSubmit" @click="analyze">
                        <span v-if="!busy">{{ mode === 'text' ? 'Analisis teks' : mode === 'image' ? 'Scan gambar' : 'Periksa tautan' }}</span>
                        <span v-else class="flex items-center gap-2">
                            <span class="h-3 w-3 animate-spin border-2 border-ink-950/30 border-t-ink-950" />
                            {{ stage }}
                        </span>
                    </button>

                    <button v-if="result" type="button" class="btn-ghost" @click="reset">Analisis lain</button>

                    <span class="ml-auto hidden font-mono text-[9px] uppercase tracking-[0.16em] text-bone/25 sm:block">
                        ctrl + enter
                    </span>
                </div>

                <p
                    v-if="error"
                    class="mt-3 border-l-2 border-magenta-500 bg-magenta-500/10 px-3 py-2 font-cond text-[13px] leading-snug text-magenta-300"
                >
                    {{ error }}
                </p>

                <p v-if="remaining === 0 && !result" class="mt-3 font-mono text-[10px] uppercase tracking-[0.14em] text-amber-400">
                    kuota XP harian habis — analisis tetap jalan, tanpa XP
                </p>
            </section>

            <!-- ══ KANAN: HASIL ══ -->
            <aside ref="resultEl" class="space-y-3 lg:sticky lg:top-20">
                <!-- kosong -->
                <div v-if="!result && !busy" class="panel p-5">
                    <div class="font-mono text-[10px] uppercase tracking-[0.24em] text-bone/35">Hasil</div>
                    <p class="mt-2 font-display text-xl uppercase leading-tight text-bone/70">
                        Belum ada yang diperiksa
                    </p>
                    <p class="mt-2 font-cond text-[13.5px] leading-snug text-bone/45">
                        Setiap hasil memuat verdict, alasan yang menunjuk bagian teksmu, dan saran langkah
                        verifikasi. Kalau buktinya kurang, mesin akan bilang
                        <span class="text-amber-400">belum pasti</span> — bukan menebak.
                    </p>

                    <div class="mt-4 space-y-2 border-t border-line/10 pt-3">
                        <div v-for="(step, i) in ['Pilih sumber: teks, gambar, atau tautan', 'Tekan analisis — dua lapis mesin jalan', 'Baca alasannya, bukan cuma labelnya']" :key="i" class="flex gap-2.5">
                            <span class="mt-0.5 font-mono text-[10px] text-acid-400">0{{ i + 1 }}</span>
                            <span class="font-cond text-[13px] leading-snug text-bone/55">{{ step }}</span>
                        </div>
                    </div>
                </div>

                <!-- memproses -->
                <div v-else-if="busy" class="panel relative overflow-hidden p-5">
                    <div class="pointer-events-none absolute inset-x-0 top-0 h-0.5 animate-pulse bg-acid-500" />
                    <div class="font-mono text-[10px] uppercase tracking-[0.24em] text-acid-400">memproses</div>
                    <p class="mt-3 font-display text-lg uppercase leading-tight text-bone">{{ stage }}</p>
                    <div class="mt-4 space-y-2">
                        <div v-for="n in 3" :key="n" class="h-2 bg-line/5" :style="{ width: `${100 - n * 12}%` }" />
                    </div>
                    <p class="mt-4 font-mono text-[9px] uppercase tracking-[0.16em] text-bone/30">
                        {{ mode === 'image' ? 'membaca gambar bisa sampai satu menit' : 'biasanya 3–8 detik' }}
                    </p>
                </div>

                <!-- hasil -->
                <template v-else-if="result">
                    <div class="relative overflow-hidden border bg-ink-900 p-5 cut-md" :class="toneBorder[result.verdict.tone]">
                        <div class="pointer-events-none absolute inset-0 fx-grain" />
                        <div class="relative">
                            <div class="flex items-center justify-between gap-2">
                                <span class="font-mono text-[9px] uppercase tracking-[0.24em] text-bone/40">verdict</span>
                                <span class="font-mono text-[9px] uppercase tracking-[0.16em] text-bone/35">
                                    {{ result.engine === 'ai' ? `AI · ${result.model}` : 'lapis aturan' }}
                                </span>
                            </div>

                            <div class="mt-2 flex items-start justify-between gap-3">
                                <div>
                                    <div class="font-display text-3xl uppercase leading-none" :class="toneText[result.verdict.tone]">
                                        {{ result.verdict.label }}
                                    </div>
                                    <div class="mt-1 font-mono text-[9px] uppercase tracking-[0.18em] text-bone/35">
                                        {{ result.verdict.headline }}
                                    </div>
                                </div>
                                <VerdictBadge :label="`${result.confidence}%`" :tone="result.verdict.tone" size="lg" code="KEY" />
                            </div>

                            <div class="mt-3">
                                <StatMeter
                                    :value="result.confidence"
                                    :max="100"
                                    :tone="result.verdict.tone"
                                    :label="`Keyakinan mesin`"
                                    :readout="`${result.confidence}%`"
                                    height="sm"
                                />
                            </div>

                            <p class="mt-3 border-l-2 border-line/15 pl-3 font-cond text-[14px] leading-relaxed text-bone/80">
                                {{ result.summary }}
                            </p>

                            <div v-if="result.xp_awarded" class="mt-3 inline-flex items-center gap-1.5 bg-acid-500 px-2 py-1 font-mono text-[10px] font-bold uppercase tracking-[0.14em] text-onacid cut-tag">
                                +{{ result.xp_awarded }} xp
                            </div>
                        </div>
                    </div>

                    <!-- sumber / provenance -->
                    <div class="panel p-4">
                        <div class="flex items-center justify-between">
                            <span class="font-mono text-[9px] uppercase tracking-[0.22em] text-bone/35">
                                sumber · {{ result.source.type }}
                            </span>
                            <span v-if="result.source.cached" class="font-mono text-[9px] uppercase tracking-[0.14em] text-acid-400">
                                dari cache
                            </span>
                        </div>

                        <p class="mt-1.5 flex items-center gap-1.5 font-cond text-[13.5px] text-bone/75">
                            <span class="text-acid-400">{{ sourceIcon(result.source.type) }}</span>
                            <span class="truncate">{{ result.source.label ?? result.source.title ?? 'teks tempelan' }}</span>
                        </p>

                        <!-- reputasi domain -->
                        <div v-if="result.source.reputation" class="mt-2.5 flex flex-wrap items-center gap-1.5">
                            <VerdictBadge
                                :label="result.source.reputation.label"
                                :tone="repTone(result.source.reputation.tier)"
                            />
                            <span v-if="result.source.attribution" class="flex gap-1.5">
                                <span
                                    class="label-tag"
                                    :class="result.source.attribution.author ? 'border border-acid-500/40 text-acid-400' : 'border border-magenta-500/40 text-magenta-400'"
                                >
                                    {{ result.source.attribution.author ? 'penulis ada' : 'tanpa penulis' }}
                                </span>
                                <span
                                    class="label-tag"
                                    :class="result.source.attribution.date ? 'border border-acid-500/40 text-acid-400' : 'border border-magenta-500/40 text-magenta-400'"
                                >
                                    {{ result.source.attribution.date ? 'tanggal ada' : 'tanpa tanggal' }}
                                </span>
                            </span>
                        </div>

                        <p v-if="result.source.reputation" class="mt-2 font-cond text-[12px] leading-snug text-bone/40">
                            {{ result.source.reputation.note }}
                        </p>

                        <a
                            v-if="result.source.type === 'link' && result.source.ref"
                            :href="result.source.ref"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="mt-2 block truncate font-mono text-[10px] text-signal-400 underline decoration-signal-400/30 underline-offset-2 hover:text-signal-300"
                        >
                            {{ result.source.ref }}
                        </a>

                        <!-- sinyal visual -->
                        <div v-if="result.source.cues.length" class="mt-3 border-t border-line/10 pt-2.5">
                            <div class="font-mono text-[9px] uppercase tracking-[0.2em] text-bone/30">sinyal visual</div>
                            <ul class="mt-1.5 space-y-1">
                                <li v-for="(cue, i) in result.source.cues" :key="i" class="flex gap-2 font-cond text-[12.5px] leading-snug text-bone/55">
                                    <span class="mt-1 h-1 w-1 shrink-0 bg-magenta-500" />
                                    {{ cue }}
                                </li>
                            </ul>
                        </div>

                        <!-- teks yang dibaca -->
                        <div v-if="result.source.type !== 'text'" class="mt-3 border-t border-line/10 pt-2.5">
                            <button
                                type="button"
                                class="flex w-full items-center justify-between font-mono text-[9px] uppercase tracking-[0.2em] text-bone/35 transition hover:text-acid-400"
                                @click="showExtracted = !showExtracted"
                            >
                                <span>teks yang dibaca mesin · {{ result.source.chars }} char</span>
                                <span>{{ showExtracted ? '−' : '+' }}</span>
                            </button>
                            <pre
                                v-if="showExtracted"
                                class="mt-2 max-h-56 overflow-auto whitespace-pre-wrap border border-line/10 bg-ink-950 p-2.5 font-mono text-[11px] leading-relaxed text-bone/60 no-bar"
                            >{{ result.source.text }}</pre>
                        </div>
                    </div>

                    <!-- alasan -->
                    <div v-if="result.reasons.length" class="panel p-4">
                        <div class="font-mono text-[9px] uppercase tracking-[0.22em] text-bone/35">kenapa</div>
                        <ol class="mt-2 space-y-2">
                            <li v-for="(reason, i) in result.reasons" :key="i" class="flex gap-2.5">
                                <span class="mt-0.5 font-mono text-[10px] text-acid-400">{{ String(i + 1).padStart(2, '0') }}</span>
                                <span class="font-cond text-[13.5px] leading-snug text-bone/70">{{ reason }}</span>
                            </li>
                        </ol>
                    </div>

                    <!-- sinyal aturan -->
                    <div v-if="result.signals.length" class="panel p-4">
                        <div class="font-mono text-[9px] uppercase tracking-[0.22em] text-bone/35">
                            sinyal terdeteksi · lapis aturan
                        </div>
                        <div class="mt-2 flex flex-wrap gap-1.5">
                            <span
                                v-for="signal in result.signals"
                                :key="signal.key"
                                class="label-tag border"
                                :class="signal.tone === 'risk' ? 'border-magenta-500/40 text-magenta-400' : 'border-acid-500/40 text-acid-400'"
                                :title="signal.note"
                            >
                                {{ signal.label }}
                            </span>
                        </div>
                    </div>

                    <!-- bendera merah AI -->
                    <div v-if="result.red_flags.length" class="panel p-4">
                        <div class="font-mono text-[9px] uppercase tracking-[0.22em] text-magenta-400">bendera merah</div>
                        <ul class="mt-2 space-y-2">
                            <li v-for="(flag, i) in result.red_flags" :key="i" class="border-l-2 border-magenta-500/40 pl-2.5">
                                <p class="font-cond text-[13px] font-semibold uppercase tracking-[0.06em] text-bone/80">{{ flag.label }}</p>
                                <p class="font-cond text-[12.5px] leading-snug text-bone/45">{{ flag.note }}</p>
                            </li>
                        </ul>
                    </div>

                    <!-- rekomendasi -->
                    <div v-if="result.recommendation" class="border border-signal-400/30 bg-signal-400/5 p-4 cut-md">
                        <div class="font-mono text-[9px] uppercase tracking-[0.22em] text-signal-400">langkah berikutnya</div>
                        <p class="mt-1.5 font-cond text-[13.5px] leading-relaxed text-bone/75">{{ result.recommendation }}</p>
                    </div>
                </template>

                <!-- riwayat -->
                <div v-if="recent.length" class="panel p-4">
                    <div class="font-mono text-[9px] uppercase tracking-[0.22em] text-bone/30">terakhir diperiksa</div>
                    <ul class="mt-2 space-y-2">
                        <li v-for="item in recent" :key="item.key" class="flex items-start gap-2">
                            <span class="mt-1 h-1.5 w-1.5 shrink-0" :class="toneBg[item.tone]" />
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-cond text-[12.5px] leading-snug text-bone/55">{{ item.excerpt }}</p>
                                <p class="font-mono text-[9px] uppercase tracking-[0.14em] text-bone/25">
                                    {{ item.verdict }} · {{ item.confidence }}% · {{ item.type }}
                                </p>
                            </div>
                        </li>
                    </ul>
                </div>
            </aside>
        </div>

        <p class="mt-6 text-center font-mono text-[9px] uppercase tracking-[0.2em] text-bone/20">
            mesin bisa salah · konfirmasi ke sumber resmi ·
            <Link href="/quest" class="text-bone/40 underline underline-offset-2 hover:text-acid-400">uji instingmu di trial</Link>
        </p>
    </GuardianLayout>
</template>