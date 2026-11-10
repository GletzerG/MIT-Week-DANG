<script setup lang="ts">
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import ThemeToggle from '@/Components/ZZZ/ThemeToggle.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="relative flex min-h-screen flex-col bg-ink-950 text-bone">
        <!-- kanvas latar ZZZ: grid + glow neon -->
        <div class="pointer-events-none fixed inset-0 fx-grid opacity-50" />
        <div class="pointer-events-none fixed -left-24 top-1/3 h-64 w-64 rounded-full bg-acid-500/10 blur-[100px]" />
        <div class="pointer-events-none fixed -right-24 bottom-1/3 h-64 w-64 rounded-full bg-magenta-500/10 blur-[100px]" />
        <div class="pointer-events-none fixed inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-acid-500/70 to-transparent" />

        <header class="relative z-10 flex items-center justify-between px-4 py-5 sm:px-8">
            <Link href="/" class="inline-flex items-center gap-2.5">
                <ApplicationLogo class="h-8 w-8 text-acid-400" />
                <span class="leading-none">
                    <span class="block font-display text-[13px] uppercase tracking-tight text-bone">Guardian</span>
                    <span class="block font-mono text-[9px] uppercase tracking-[0.34em] text-acid-400">Genesis</span>
                </span>
            </Link>

            <ThemeToggle />
        </header>

        <main class="relative z-10 flex flex-1 items-center justify-center px-4 py-8">
            <div class="w-full max-w-md">
                <div class="panel p-6 sm:p-7">
                    <slot />
                </div>

                <p class="mt-4 text-center font-mono text-[9px] uppercase tracking-[0.2em] text-bone/25">
                    Sistem verifikasi · literasi media
                </p>
            </div>
        </main>
    </div>
</template>
