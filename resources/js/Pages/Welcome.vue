<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import ThemeToggle from '@/Components/ZZZ/ThemeToggle.vue';

defineProps<{ canLogin?: boolean; canRegister?: boolean }>();

const ticker = [
    'FEED TERVERIFIKASI',
    'DETEKSI KLAIM PAKAI AI',
    'TRIAL QUEST HARIAN',
    'XP · LEVEL · RANK',
    'LITERASI MEDIA 12-18',
];

const steps = [
    {
        n: '01',
        title: 'Baca feed yang sudah dicek',
        body: 'Setiap kartu berita membawa verdict, skor kredibilitas, dan daftar sinyal manipulatifnya. Kamu belajar polanya dulu — bukan menebak buta.',
    },
    {
        n: '02',
        title: 'Tempel klaim apa pun ke detektor',
        body: 'Pesan berantai dari grup keluarga, caption TikTok, judul berita aneh. Mesin menilai sinyalnya, lalu menjelaskan kenapa — atau jujur bilang buktinya kurang.',
    },
    {
        n: '03',
        title: 'Uji instingmu di trial',
        body: 'Di sini semua bantuan dimatikan. Tanpa skor, tanpa sinyal. Hanya kartu berita dan keputusanmu. Benar menambah XP dan streak, salah menguras HP.',
    },
];
</script>

<template>
    <Head title="Guardian Genesis — Arena Verifikasi" />

    <div class="relative min-h-screen overflow-hidden bg-ink-950 text-bone">
        <div class="pointer-events-none fixed inset-0 fx-grid opacity-50" />
        <div class="pointer-events-none fixed -left-24 top-0 h-72 w-72 rounded-full bg-acid-500/10 blur-[110px]" />
        <div class="pointer-events-none fixed -right-20 bottom-0 h-72 w-72 rounded-full bg-magenta-500/10 blur-[110px]" />

        <div class="relative mx-auto max-w-6xl px-4 sm:px-8">
            <!-- nav -->
            <header class="flex items-center justify-between py-5">
                <div class="flex items-center gap-2.5">
                    <ApplicationLogo class="h-8 w-8 text-acid-400" />
                    <div class="leading-none">
                        <div class="font-display text-[13px] uppercase tracking-tight">Guardian</div>
                        <div class="font-mono text-[9px] uppercase tracking-[0.34em] text-acid-400">Genesis</div>
                    </div>
                </div>

                <nav class="flex items-center gap-2">
                    <ThemeToggle />
                    <Link v-if="canLogin" href="/login" class="btn-ghost !py-2 !text-xs">Masuk</Link>
                    <Link v-if="canRegister" href="/register" class="btn-acid !py-2 !text-xs">Daftar</Link>
                </nav>
            </header>

            <!-- ticker -->
            <div class="relative overflow-hidden border-y border-line/10 bg-acid-500 py-1.5">
                <div class="flex w-max animate-marquee gap-8 whitespace-nowrap">
                    <span
                        v-for="(t, i) in [...ticker, ...ticker, ...ticker, ...ticker]"
                        :key="i"
                        class="font-mono text-[10px] font-bold uppercase tracking-[0.28em] text-onacid"
                    >
                        {{ t }} <span class="opacity-40">◆</span>
                    </span>
                </div>
            </div>

            <!-- hero -->
            <main class="grid items-center gap-10 py-12 lg:grid-cols-[1.1fr_0.9fr] lg:py-16">
                <div>
                    <div class="inline-flex items-center gap-2 border border-magenta-500/40 bg-magenta-500/10 px-2.5 py-1 cut-tag">
                        <span class="h-1.5 w-1.5 animate-pulse bg-magenta-500" />
                        <span class="font-mono text-[9px] font-bold uppercase tracking-[0.22em] text-magenta-300">
                            Sistem verifikasi online
                        </span>
                    </div>

                    <h1 class="mt-5 font-display text-[13vw] uppercase leading-[0.82] tracking-tight sm:text-6xl lg:text-7xl">
                        Berita<br />
                        <span class="text-acid-400">bohong</span><br />
                        <span class="txt-outline text-bone/70">bukan</span> takdir.
                    </h1>

                    <p class="mt-6 max-w-lg font-cond text-[15.5px] leading-snug text-bone/60">
                        Guardian Genesis mengubah cara kamu menyaring informasi: baca bukti dulu di
                        feed terverifikasi, uji klaim apa pun dengan detektor AI, lalu pertaruhkan
                        instingmu di trial yang menyembunyikan jawaban.
                    </p>

                    <div class="mt-7 flex flex-wrap gap-2.5">
                        <Link v-if="canRegister" href="/register" class="btn-acid">
                            Mulai sebagai Guardian
                        </Link>
                        <Link v-if="canLogin" href="/login" class="btn-ghost">Sudah punya akun</Link>
                    </div>

                    <div class="mt-7 inline-flex flex-wrap items-center gap-x-2.5 gap-y-1 border border-line/10 bg-ink-900 px-3 py-2 cut-sm">
                        <span class="font-mono text-[8.5px] uppercase tracking-[0.2em] text-bone/30">Akun uji</span>
                        <span class="font-mono text-[11px] text-bone/70">guardian@genesis.test</span>
                        <span class="text-bone/20">/</span>
                        <span class="font-mono text-[11px] text-bone/70">password</span>
                    </div>
                </div>

                <!-- mock kartu -->
                <div class="relative">
                    <div class="pointer-events-none absolute -inset-2 border border-acid-500/15 cut-md" />
                    <div class="relative overflow-hidden border border-line/10 bg-ink-900 p-4 cut-md">
                        <div class="absolute inset-y-0 left-0 w-[3px] bg-magenta-500" />

                        <div class="flex items-start justify-between pl-2">
                            <div class="flex items-center gap-1.5">
                                <span class="bg-magenta-500 px-2 py-1 font-display text-[10px] uppercase text-onacid cut-tag">Hoax</span>
                                <span class="border border-line/10 px-2 py-1 font-mono text-[9px] uppercase tracking-[0.16em] text-bone/40">kesehatan</span>
                            </div>
                            <div class="text-right leading-none">
                                <div class="font-display text-2xl text-magenta-400">8</div>
                                <div class="font-mono text-[8px] uppercase tracking-[0.16em] text-bone/25">kredibilitas</div>
                            </div>
                        </div>

                        <h3 class="mt-3 pl-2 font-display text-[14px] uppercase leading-tight text-bone">
                            Air lemon hangat terbukti membunuh sel kanker dalam 3 hari
                        </h3>

                        <p class="mt-2 pl-2 font-cond text-[13px] leading-snug text-bone/45">
                            Pesan berantai menyebut tidak perlu kemoterapi lagi. Tidak ada nama dokter,
                            tidak ada penelitian, dan diminta segera disebarkan…
                        </p>

                        <div class="mt-3 flex flex-wrap gap-1.5 pl-2">
                            <span class="border border-magenta-500/35 px-1.5 py-0.5 font-mono text-[9px] uppercase tracking-[0.1em] text-magenta-300">− judul kapital</span>
                            <span class="border border-magenta-500/35 px-1.5 py-0.5 font-mono text-[9px] uppercase tracking-[0.1em] text-magenta-300">− otoritas tanpa nama</span>
                            <span class="border border-magenta-500/35 px-1.5 py-0.5 font-mono text-[9px] uppercase tracking-[0.1em] text-magenta-300">− ajakan sebar</span>
                        </div>

                        <div class="mt-3 flex items-center justify-between border-t border-line/10 pt-2.5 pl-2">
                            <span class="font-mono text-[9px] uppercase tracking-[0.12em] text-bone/30">SehatAlami24 · tanpa penulis</span>
                            <span class="flex h-4 w-4 items-center justify-center bg-magenta-500 font-display text-[10px] text-onacid">✕</span>
                        </div>
                    </div>

                    <!-- chip detektor -->
                    <div class="relative mt-3 flex items-center gap-2 border border-acid-500/30 bg-ink-900 px-3 py-2.5 cut-sm">
                        <span class="h-1.5 w-1.5 animate-pulse bg-acid-500" />
                        <span class="font-mono text-[9.5px] uppercase tracking-[0.18em] text-acid-400">
                            Detektor siap · tempel klaim apa pun
                        </span>
                    </div>
                </div>
            </main>

            <!-- langkah -->
            <section class="grid gap-3 border-t border-line/10 py-12 sm:grid-cols-3">
                <article
                    v-for="s in steps"
                    :key="s.n"
                    class="group relative overflow-hidden border border-line/10 bg-ink-900 p-5 cut-md transition hover:border-acid-500/40"
                >
                    <div class="font-display text-4xl leading-none text-bone/15 transition group-hover:text-acid-400/50">{{ s.n }}</div>
                    <h3 class="mt-3 font-display text-base uppercase leading-tight text-bone">{{ s.title }}</h3>
                    <p class="mt-2 font-cond text-[13.5px] leading-snug text-bone/50">{{ s.body }}</p>
                </article>
            </section>

            <!-- rank -->
            <section class="border-t border-line/10 py-8">
                <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                    <span class="font-mono text-[9px] uppercase tracking-[0.24em] text-bone/30">Tangga rank</span>
                    <span class="font-cond text-xs font-bold uppercase tracking-[0.1em] text-bone/50">Novice Guardian</span>
                    <span class="text-bone/20">→</span>
                    <span class="font-cond text-xs font-bold uppercase tracking-[0.1em] text-signal-300">Seeker</span>
                    <span class="text-bone/20">→</span>
                    <span class="font-cond text-xs font-bold uppercase tracking-[0.1em] text-acid-400">Truth Knight</span>
                    <span class="text-bone/20">→</span>
                    <span class="font-cond text-xs font-bold uppercase tracking-[0.1em] text-amber-300">Inquisitor</span>
                    <span class="text-bone/20">→</span>
                    <span class="font-cond text-xs font-bold uppercase tracking-[0.1em] text-magenta-400">Grandmaster</span>
                </div>
            </section>

            <footer class="flex flex-wrap items-center justify-between gap-2 border-t border-line/10 py-6">
                <span class="font-mono text-[10px] uppercase tracking-[0.16em] text-bone/25">
                    Guardian Genesis · literasi media
                </span>
                <span class="font-mono text-[9px] uppercase tracking-[0.16em] text-bone/20">
                    Laravel 11 · Inertia · Vue 3 · Tailwind
                </span>
            </footer>
        </div>
    </div>
</template>