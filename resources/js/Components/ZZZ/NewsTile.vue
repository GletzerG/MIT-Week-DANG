<script setup lang="ts">
import { computed, ref } from 'vue';
import VerdictBadge from '@/Components/ZZZ/VerdictBadge.vue';
import type { FeedItem } from '@/types/feed';

const props = defineProps<{ item: FeedItem }>();

const open = ref(false);

const isHoax = computed(() => props.item.verdict.value === 'hoax');
const area = computed(() => (isHoax.value ? 'magenta' : 'acid'));

const credibilityTone = computed(() =>
    props.item.credibility >= 65 ? 'text-acid-400' : props.item.credibility <= 35 ? 'text-magenta-400' : 'text-amber-300',
);

const when = computed(() => {
    if (!props.item.published_at) return 'tanpa tanggal';
    const diff = Date.now() - new Date(props.item.published_at).getTime();
    const h = Math.floor(diff / 3_600_000);
    if (h < 1) return 'baru saja';
    if (h < 24) return `${h} jam lalu`;
    const d = Math.floor(h / 24);
    return `${d} hari lalu`;
});
</script>

<template>
    <article
        class="group relative overflow-hidden bg-ink-900 cut-md edge transition duration-200 hover:-translate-y-0.5 hover:bg-ink-850"
        :class="isHoax ? 'hover:border-magenta-500/50' : 'hover:border-acid-500/50'"
    >
        <!-- bilah verdict di tepi kiri -->
        <div
            class="absolute inset-y-0 left-0 w-[3px]"
            :class="isHoax ? 'bg-magenta-500' : 'bg-acid-500'"
        />

        <div class="p-4 pl-5">
            <!-- baris atas -->
            <div class="flex items-start justify-between gap-3">
                <div class="flex flex-wrap items-center gap-1.5">
                    <VerdictBadge :label="item.verdict.label" :tone="item.verdict.tone" />
                    <span class="border border-line/10 px-2 py-1 font-mono text-[9px] uppercase tracking-[0.18em] text-bone/45">
                        {{ item.category }}
                    </span>
                </div>

                <div class="shrink-0 text-right leading-none">
                    <div class="font-display text-2xl" :class="credibilityTone">{{ item.credibility }}</div>
                    <div class="font-mono text-[8px] uppercase tracking-[0.16em] text-bone/30">kredibilitas</div>
                </div>
            </div>

            <!-- judul -->
            <h3 class="mt-3 font-display text-[15px] uppercase leading-[1.15] tracking-tight text-bone">
                {{ item.title }}
            </h3>

            <!-- cuplikan -->
            <p class="mt-2 font-cond text-[13.5px] leading-snug text-bone/55" :class="open ? '' : 'line-clamp-3'">
                {{ item.excerpt }}
            </p>

            <!-- sinyal -->
            <div v-if="item.red_flags.length" class="mt-3 flex flex-wrap gap-1.5">
                <span
                    v-for="flag in (open ? item.red_flags : item.red_flags.slice(0, 2))"
                    :key="flag.label"
                    class="inline-flex items-center gap-1 border px-1.5 py-0.5 font-mono text-[9px] uppercase tracking-[0.1em]"
                    :class="isHoax ? 'border-magenta-500/30 text-magenta-300/80' : 'border-acid-500/25 text-acid-400/70'"
                    :title="flag.note"
                >
                    <span class="opacity-50">{{ isHoax ? '!' : '+' }}</span>{{ flag.label }}
                </span>
                <button
                    v-if="item.red_flags.length > 2 && !open"
                    type="button"
                    class="px-1.5 py-0.5 font-mono text-[9px] uppercase tracking-[0.1em] text-bone/40 hover:text-bone"
                    @click="open = true"
                >
                    +{{ item.red_flags.length - 2 }} lagi
                </button>
            </div>

            <!-- meta -->
            <div class="mt-3.5 flex items-center justify-between gap-2 border-t border-line/10 pt-2.5">
                <div class="min-w-0 font-mono text-[9.5px] uppercase tracking-[0.12em] text-bone/35">
                    <span class="text-bone/60">{{ item.source }}</span>
                    <span class="mx-1 opacity-40">/</span>
                    <span v-if="item.author">{{ item.author }}</span>
                    <span v-else :class="isHoax ? 'text-magenta-400/80' : ''">tanpa penulis</span>
                    <span class="mx-1 opacity-40">·</span>{{ when }}
                </div>

                <div class="flex shrink-0 items-center gap-1.5">
                    <span class="font-mono text-[9px] uppercase tracking-[0.14em] text-bone/30">
                        {{ item.fact_check.source }}
                    </span>
                    <span
                        class="flex h-4 w-4 items-center justify-center font-display text-[10px] text-onacid"
                        :class="isHoax ? 'bg-magenta-500' : 'bg-acid-500'"
                    >
                        {{ isHoax ? '✕' : '✓' }}
                    </span>
                </div>
            </div>
        </div>

        <!-- garis hover -->
        <div
            class="absolute bottom-0 left-0 h-[2px] w-0 transition-all duration-300 group-hover:w-full"
            :class="isHoax ? 'bg-magenta-500' : 'bg-acid-500'"
        />
    </article>
</template>