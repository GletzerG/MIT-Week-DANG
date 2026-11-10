<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import GuardianLayout from '@/Layouts/GuardianLayout.vue';
import NewsTile from '@/Components/ZZZ/NewsTile.vue';
import StatMeter from '@/Components/ZZZ/StatMeter.vue';
import type { FeedItem, Guardian, Paginated } from '@/types/feed';

const props = defineProps<{
    guardian: Guardian;
    feed: Paginated<FeedItem>;
    filters: { type: 'all' | 'fact' | 'hoax'; search: string };
    totals: { all: number; fact: number; hoax: number };
    today: { solved: number };
}>();

const search = ref(props.filters.search);

const tabs = computed(() => [
    { key: 'all' as const, label: 'Semua', count: props.totals.all },
    { key: 'fact' as const, label: 'Fakta', count: props.totals.fact },
    { key: 'hoax' as const, label: 'Hoax', count: props.totals.hoax },
]);

const accuracyPct = computed(() => Math.round(props.guardian.accuracy * 100));

const applyFilter = (type: 'all' | 'fact' | 'hoax') =>
    router.get('/dashboard', { type, q: search.value || undefined }, { preserveState: true, preserveScroll: true });

let timer: ReturnType<typeof setTimeout> | undefined;
watch(search, (value) => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get(
            '/dashboard',
            { type: props.filters.type, q: value || undefined },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }, 350);
});
</script>

<template>
    <Head title="Beranda" />

    <GuardianLayout active="dashboard" :guardian="guardian">
        <!-- ══ HERO ══ -->
        <section class="relative overflow-hidden border border-line/10 bg-ink-900 cut-lg">
            <div class="pointer-events-none absolute -right-16 -top-20 h-64 w-64 rounded-full bg-acid-500/10 blur-3xl" />
            <div class="pointer-events-none absolute inset-0 fx-grain" />

            <div class="relative grid gap-6 p-5 sm:p-8 lg:grid-cols-[1fr_auto] lg:items-end">
                <div>
                    <div class="flex items-center gap-2 font-mono text-[10px] uppercase tracking-[0.3em] text-acid-400">
                        <span class="h-1.5 w-1.5 animate-pulse bg-acid-500" />
                        Feed terverifikasi · {{ totals.all }} entri
                    </div>

                    <h1 class="mt-3 font-display text-[28px] uppercase leading-[0.9] tracking-tight text-bone sm:text-5xl">
                        Semua di sini<br />
                        <span class="text-acid-400">sudah dicek.</span>
                    </h1>

                    <p class="mt-3 max-w-xl font-cond text-[13.5px] leading-snug text-bone/55 sm:text-[15px]">
                        Tiap kartu membawa verdict, skor kredibilitas, dan sinyal yang bisa kamu baca
                        sendiri. Pelajari polanya di sini — lalu uji dirimu di
                        <Link
                            href="/quest"
                            class="text-acid-400 underline decoration-acid-500/40 underline-offset-2 hover:text-acid-300"
                        >Trial Quest</Link>
                        yang menyembunyikan jawaban.
                    </p>
                </div>

                <div class="grid grid-cols-2 gap-2 sm:grid-cols-4 lg:grid-cols-2">
                    <div class="border border-line/10 bg-ink-950/60 p-3 cut-sm">
                        <div class="font-display text-2xl leading-none text-bone">{{ totals.all }}</div>
                        <div class="mt-1 font-mono text-[9px] uppercase tracking-[0.18em] text-bone/35">terverifikasi</div>
                    </div>
                    <div class="border border-acid-500/25 bg-acid-500/5 p-3 cut-sm">
                        <div class="font-display text-2xl leading-none text-acid-400">{{ totals.fact }}</div>
                        <div class="mt-1 font-mono text-[9px] uppercase tracking-[0.18em] text-acid-400/60">fakta</div>
                    </div>
                    <div class="border border-magenta-500/25 bg-magenta-500/5 p-3 cut-sm">
                        <div class="font-display text-2xl leading-none text-magenta-400">{{ totals.hoax }}</div>
                        <div class="mt-1 font-mono text-[9px] uppercase tracking-[0.18em] text-magenta-500/60">hoax</div>
                    </div>
                    <div class="border border-line/10 bg-ink-950/60 p-3 cut-sm">
                        <div class="font-display text-2xl leading-none text-bone">
                            {{ accuracyPct }}<span class="text-sm text-bone/40">%</span>
                        </div>
                        <div class="mt-1 font-mono text-[9px] uppercase tracking-[0.18em] text-bone/35">akurasi trial</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ══ CTA MOBILE (di desktop hidup di sidebar) ══ -->
        <Link
            href="/analisis"
            class="mt-4 flex items-center justify-between gap-3 border border-acid-500/40 bg-acid-500/10 px-3.5 py-3 cut-sm transition hover:bg-acid-500/15 lg:hidden"
        >
            <span class="min-w-0">
                <span class="block font-cond text-[13px] font-bold uppercase tracking-[0.1em] text-acid-400">
                    Tempel berita · cek pakai AI
                </span>
                <span class="block font-mono text-[9px] uppercase tracking-[0.14em] text-bone/35">
                    teks · gambar · tautan
                </span>
            </span>
            <span class="shrink-0 font-display text-lg text-acid-400">→</span>
        </Link>

        <!-- ══ FILTER (sticky: tetap terjangkau saat scroll panjang) ══ -->
        <section class="sticky top-[105px] z-30 -mx-3 mt-4 flex flex-col gap-2.5 border-y border-line/10 bg-ink-950/95 px-3 py-2.5 backdrop-blur-md sm:-mx-6 sm:px-6 md:top-[65px] lg:flex-row lg:items-center">
            <div class="flex border border-line/10 bg-ink-900 cut-sm">
                <button
                    v-for="tab in tabs"
                    :key="tab.key"
                    type="button"
                    class="flex flex-1 items-center justify-center gap-2 px-3 py-2.5 font-cond text-sm font-bold uppercase tracking-[0.1em] transition sm:flex-none sm:px-4"
                    :class="
                        filters.type === tab.key
                            ? tab.key === 'hoax'
                                ? 'bg-magenta-500 text-onacid'
                                : 'bg-acid-500 text-onacid'
                            : 'text-bone/50 hover:bg-line/5 hover:text-bone'
                    "
                    @click="applyFilter(tab.key)"
                >
                    {{ tab.label }}
                    <span class="font-mono text-[10px] opacity-60">{{ tab.count }}</span>
                </button>
            </div>

            <div class="relative flex-1">
                <input
                    v-model="search"
                    type="search"
                    placeholder="Cari judul, sumber, atau kategori…"
                    class="w-full border border-line/10 bg-ink-900 px-3.5 py-2.5 pr-16 font-cond text-sm text-bone placeholder:text-bone/30 focus:border-acid-500/60 focus:outline-none focus:ring-1 focus:ring-acid-500/30 cut-sm"
                />
                <button
                    v-if="search"
                    type="button"
                    class="absolute right-2 top-1/2 -translate-y-1/2 border border-line/15 px-2 py-1 font-mono text-[9px] uppercase tracking-[0.14em] text-bone/45 transition cut-tag hover:border-magenta-500/50 hover:text-magenta-400"
                    @click="search = ''"
                >
                    hapus
                </button>
                <span
                    v-else
                    class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 font-mono text-[9px] uppercase tracking-[0.18em] text-bone/25"
                >
                    cari
                </span>
            </div>
        </section>

        <!-- ══ ISI ══ -->
        <div class="mt-4 grid gap-4 lg:grid-cols-[1fr_300px]">
            <section>
                <div class="mb-2.5 flex items-center justify-between">
                    <p class="font-mono text-[9px] uppercase tracking-[0.2em] text-bone/35">
                        <template v-if="feed.data.length">
                            menampilkan {{ feed.from }}–{{ feed.to }} dari {{ feed.total }}
                        </template>
                        <template v-else>tidak ada hasil</template>
                    </p>
                    <p class="font-mono text-[9px] uppercase tracking-[0.16em] text-bone/25">
                        {{ filters.type === 'all' ? 'semua jenis' : filters.type }}
                    </p>
                </div>

                <div v-if="feed.data.length" class="grid gap-3 sm:grid-cols-2">
                    <NewsTile v-for="item in feed.data" :key="item.id" :item="item" />
                </div>

                <div v-else class="border border-dashed border-line/15 bg-ink-900/50 p-8 text-center cut-md">
                    <p class="font-display text-xl uppercase text-bone/70">Tidak ada yang cocok</p>
                    <p class="mt-1.5 font-cond text-sm text-bone/40">
                        Coba kata kunci lain, kembali ke tab Semua, atau cek klaim sendiri.
                    </p>
                    <div class="mt-3 flex flex-wrap justify-center gap-2">
                        <button type="button" class="btn-ghost !px-3 !py-2 !text-xs" @click="applyFilter('all')">
                            Reset filter
                        </button>
                        <Link href="/analisis" class="btn-acid !px-3 !py-2 !text-xs">Buka detektor</Link>
                    </div>
                </div>

                <nav v-if="feed.last_page > 1" class="mt-5 flex flex-wrap items-center gap-1.5">
                    <template v-for="(link, i) in feed.links" :key="i">
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            preserve-scroll
                            class="min-w-8 px-2.5 py-1.5 text-center font-mono text-[11px] uppercase transition cut-sm"
                            :class="
                                link.active
                                    ? 'bg-acid-500 font-bold text-onacid'
                                    : 'border border-line/10 text-bone/50 hover:border-line/30 hover:text-bone'
                            "
                            v-html="link.label"
                        />
                        <span v-else class="px-2.5 py-1.5 font-mono text-[11px] text-bone/20" v-html="link.label" />
                    </template>
                    <span class="ml-auto font-mono text-[10px] uppercase tracking-[0.16em] text-bone/30">
                        {{ feed.from }}–{{ feed.to }} / {{ feed.total }}
                    </span>
                </nav>
            </section>

            <aside class="space-y-3">
                <div class="border border-line/10 bg-ink-900 p-4 cut-md">
                    <div class="flex items-center justify-between">
                        <span class="font-mono text-[9px] uppercase tracking-[0.22em] text-bone/35">Guardian</span>
                        <span class="font-mono text-[9px] uppercase tracking-[0.18em] text-acid-400">{{ guardian.rank_label }}</span>
                    </div>

                    <div class="mt-3 flex items-baseline gap-2">
                        <span class="font-display text-3xl leading-none text-bone">Lv{{ guardian.level }}</span>
                        <span class="font-mono text-[10px] text-bone/35">{{ guardian.xp }} XP</span>
                    </div>

                    <div class="mt-3 space-y-2.5">
                        <StatMeter
                            :value="guardian.xp_into_level"
                            :max="guardian.xp_level_cost"
                            tone="acid"
                            label="Menuju level berikutnya"
                            :readout="`${guardian.xp_into_level} / ${guardian.xp_level_cost}`"
                        />
                        <StatMeter
                            :value="guardian.hp"
                            :max="guardian.max_hp"
                            :tone="guardian.hp > 40 ? 'signal' : 'magenta'"
                            label="Health"
                            :readout="`${guardian.hp} / ${guardian.max_hp}`"
                            height="sm"
                        />
                    </div>

                    <div class="mt-3 grid grid-cols-3 gap-2 border-t border-line/10 pt-3">
                        <div>
                            <div class="font-display text-lg leading-none text-bone">{{ guardian.streak }}</div>
                            <div class="font-mono text-[8px] uppercase tracking-[0.14em] text-bone/30">streak</div>
                        </div>
                        <div>
                            <div class="font-display text-lg leading-none text-bone">{{ guardian.solved_count }}</div>
                            <div class="font-mono text-[8px] uppercase tracking-[0.14em] text-bone/30">selesai</div>
                        </div>
                        <div>
                            <div class="font-display text-lg leading-none text-bone">{{ today.solved }}</div>
                            <div class="font-mono text-[8px] uppercase tracking-[0.14em] text-bone/30">hari ini</div>
                        </div>
                    </div>
                </div>

                <Link
                    href="/analisis"
                    class="group relative block overflow-hidden border border-acid-500/40 bg-acid-500 p-4 text-onacid cut-md transition hover:bg-acid-400"
                >
                    <div class="pointer-events-none absolute inset-0 fx-stripes-ink opacity-10" />
                    <div class="relative">
                        <div class="font-mono text-[9px] font-bold uppercase tracking-[0.2em] opacity-60">Alat · 02</div>
                        <div class="mt-1.5 font-display text-xl uppercase leading-[0.95]">
                            Tempel berita.<br />Cek pakai AI.
                        </div>
                        <p class="mt-2 font-cond text-[12.5px] leading-snug opacity-70">
                            Pesan berantai dari grup keluarga? Masukkan teksnya, dapatkan verdict dan alasannya.
                        </p>
                        <div class="mt-3 inline-flex items-center gap-1 font-cond text-xs font-bold uppercase tracking-[0.14em]">
                            Buka detektor
                            <span class="transition-transform group-hover:translate-x-1">→</span>
                        </div>
                    </div>
                </Link>

                <Link
                    href="/quest"
                    class="group block border border-line/10 bg-ink-900 p-4 cut-md transition hover:border-magenta-500/40"
                >
                    <div class="flex items-center justify-between">
                        <span class="font-mono text-[9px] uppercase tracking-[0.2em] text-magenta-400">Trial · 03</span>
                        <span class="font-mono text-[9px] uppercase tracking-[0.16em] text-bone/30">jawaban disembunyikan</span>
                    </div>
                    <div class="mt-2 font-display text-lg uppercase leading-tight text-bone">Uji instingmu</div>
                    <p class="mt-1.5 font-cond text-[12.5px] leading-snug text-bone/45">
                        Tanpa skor kredibilitas. Tanpa sinyal. Hanya kamu dan kartu berita.
                    </p>
                </Link>
            </aside>
        </div>
    </GuardianLayout>
</template>