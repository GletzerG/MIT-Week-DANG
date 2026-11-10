<script setup lang="ts">
import { computed, ref } from 'vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import ThemeToggle from '@/Components/ZZZ/ThemeToggle.vue';

const props = defineProps<{
    active?: 'dashboard' | 'analysis' | 'quest' | 'profile';
    guardian?: { level: number; rank_label: string; hp: number; max_hp: number; xp: number } | null;
}>();

const page = usePage();
const user = computed<{ name: string; email: string } | null>(() => {
    const auth = page.props.auth as { user?: { name: string; email: string } | null } | undefined;

    return auth?.user ?? null;
});

const nav = [
    { key: 'dashboard', label: 'Beranda', href: '/dashboard', code: '01' },
    { key: 'analysis', label: 'Deteksi', href: '/analisis', code: '02' },
    { key: 'quest', label: 'Trial', href: '/quest', code: '03' },
] as const;

const menuOpen = ref(false);
const logout = useForm({});
const signOut = () => logout.post(route('logout'));

const hpPct = computed(() => {
    if (!props.guardian) return 0;
    return Math.round((props.guardian.hp / Math.max(1, props.guardian.max_hp)) * 100);
});
</script>

<template>
    <div class="relative min-h-screen bg-ink-950 text-bone">
        <!-- kanvas: grid + garis sinyal -->
        <div class="pointer-events-none fixed inset-0 fx-grid opacity-60" />
        <div class="pointer-events-none fixed inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-acid-500/60 to-transparent" />

        <!-- ══ HEADER ══ -->
        <header class="sticky top-0 z-40 border-b border-line/10 bg-ink-950/90 backdrop-blur-md">
            <div class="mx-auto flex h-16 max-w-7xl items-stretch gap-2 px-3 sm:px-6">
                <!-- logo -->
                <Link href="/dashboard" class="flex shrink-0 items-center gap-2.5 pr-2">
                    <ApplicationLogo class="h-8 w-8 text-acid-400" />
                    <span class="hidden leading-none sm:block">
                        <span class="block font-display text-[13px] uppercase tracking-tight text-bone">Guardian</span>
                        <span class="block font-mono text-[9px] uppercase tracking-[0.34em] text-acid-400">Genesis</span>
                    </span>
                </Link>

                <!-- nav desktop -->
                <nav class="hidden items-stretch md:flex">
                    <Link
                        v-for="item in nav"
                        :key="item.key"
                        :href="item.href"
                        class="group relative flex items-center gap-2 px-4 font-cond text-sm font-semibold uppercase tracking-[0.12em] transition"
                        :class="active === item.key ? 'bg-acid-500 text-onacid' : 'text-bone/55 hover:bg-line/5 hover:text-bone'"
                    >
                        <span class="font-mono text-[9px] opacity-60">{{ item.code }}</span>
                        {{ item.label }}
                    </Link>
                </nav>

                <div class="ml-auto flex items-center gap-2 sm:gap-3">
                    <!-- Tema: gelap / terang -->
                    <ThemeToggle />

                    <!-- HP -->
                    <div v-if="guardian" class="hidden items-center gap-2 border border-line/10 px-2.5 py-1.5 cut-sm sm:flex">
                        <span class="font-mono text-[9px] uppercase tracking-[0.2em] text-bone/40">HP</span>
                        <div class="h-1.5 w-16 overflow-hidden bg-ink-700">
                            <div
                                class="h-full transition-all duration-500"
                                :class="hpPct > 40 ? 'bg-acid-500' : 'bg-magenta-500'"
                                :style="{ width: hpPct + '%' }"
                            />
                        </div>
                        <span class="font-mono text-[10px] font-bold text-bone/70">{{ guardian.hp }}</span>
                    </div>

                    <!-- level -->
                    <div v-if="guardian" class="flex items-center gap-2 border border-acid-500/40 bg-acid-500/10 px-2.5 py-1.5 cut-sm">
                        <span class="font-mono text-[9px] uppercase tracking-[0.2em] text-acid-400/70">Lv</span>
                        <span class="font-display text-sm leading-none text-acid-400">{{ guardian.level }}</span>
                    </div>

                    <!-- user -->
                    <button
                        v-if="user"
                        type="button"
                        class="group relative flex items-center gap-2"
                        @click="menuOpen = !menuOpen"
                    >
                        <span class="flex h-9 w-9 items-center justify-center bg-acid-500 font-display text-sm text-onacid cut-sm">
                            {{ user.name.charAt(0).toUpperCase() }}
                        </span>
                    </button>

                    <div
                        v-if="menuOpen && user"
                        class="absolute right-3 top-16 z-50 w-60 border border-line/10 bg-ink-900 p-1 shadow-2xl cut-sm"
                        @mouseleave="menuOpen = false"
                    >
                        <div class="border-b border-line/10 px-3 py-2.5">
                            <p class="truncate font-cond text-sm font-semibold text-bone">{{ user.name }}</p>
                            <p class="truncate font-mono text-[10px] text-bone/40">{{ user.email }}</p>
                        </div>
                        <Link href="/profile" class="block px-3 py-2 font-cond text-sm text-bone/70 hover:bg-line/5 hover:text-bone">
                            Profil
                        </Link>
                        <button
                            type="button"
                            class="block w-full px-3 py-2 text-left font-cond text-sm text-magenta-400 hover:bg-magenta-500/10"
                            @click="signOut"
                        >
                            Keluar
                        </button>
                    </div>
                </div>
            </div>

            <!-- nav mobile: tinggi dikunci h-10 supaya offset sticky konten bisa pas -->
            <nav class="flex h-10 border-t border-line/10 md:hidden">
                <Link
                    v-for="item in nav"
                    :key="item.key"
                    :href="item.href"
                    class="flex flex-1 items-center justify-center font-cond text-xs font-bold uppercase tracking-[0.1em] transition"
                    :class="active === item.key ? 'bg-acid-500 text-onacid' : 'text-bone/50'"
                >
                    {{ item.label }}
                </Link>
                <Link
                    href="/profile"
                    class="flex flex-1 items-center justify-center font-cond text-xs font-bold uppercase tracking-[0.1em] transition"
                    :class="active === 'profile' ? 'bg-acid-500 text-onacid' : 'text-bone/50'"
                >
                    Saya
                </Link>
            </nav>
        </header>

        <main class="relative mx-auto max-w-7xl px-3 pb-20 pt-5 sm:px-6 sm:pt-8">
            <slot />
        </main>
    </div>
</template>