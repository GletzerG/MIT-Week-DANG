import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/**
 * Token desain: Zenless Zone Zero.
 *
 * Tiga aturan yang membuat "rasa ZZZ" muncul:
 *  1. Hitam hampir murni sebagai kanvas — bukan abu-abu, bukan gelap kebiruan.
 *  2. SATU warna sinyal neon yang dominan (acid), sisanya aksen langka.
 *     Kalau magenta dipakai di semua tempat, tidak ada yang terasa mendesak.
 *  3. Sudut dipotong (clip-path), bukan rounded. Lengkung = lembut;
 *     potongan miring = poster jalanan.
 *
 * @type {import('tailwindcss').Config}
 */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.{js,ts,vue}',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Barlow Condensed', 'Inter', ...defaultTheme.fontFamily.sans],
                display: ['Archivo Black', 'Impact', 'sans-serif'],
                cond: ['Barlow Condensed', 'sans-serif'],
                mono: ['JetBrains Mono', 'ui-monospace', 'monospace'],
                // dipertahankan: komponen quest lama memakai font-cinzel
                cinzel: ['Archivo Black', 'Impact', 'sans-serif'],
            },

            colors: {
                /*
                 * Semua langkah di bawah dibaca dari CSS variable (lihat
                 * resources/css/app.css). Angka RGB dipisah spasi supaya
                 * opacity Tailwind (`bg-ink-900/80`) tetap jalan.
                 *
                 * Konsekuensinya: dark/light mode cukup menukar satu blok
                 * variable di :root / [data-theme='light'].
                 */
                ink: {
                    950: 'rgb(var(--c-ink-950) / <alpha-value>)',
                    900: 'rgb(var(--c-ink-900) / <alpha-value>)',
                    850: 'rgb(var(--c-ink-850) / <alpha-value>)',
                    800: 'rgb(var(--c-ink-800) / <alpha-value>)',
                    700: 'rgb(var(--c-ink-700) / <alpha-value>)',
                    600: 'rgb(var(--c-ink-600) / <alpha-value>)',
                    500: 'rgb(var(--c-ink-500) / <alpha-value>)',
                },
                acid: {
                    300: 'rgb(var(--c-acid-300) / <alpha-value>)',
                    400: 'rgb(var(--c-acid-400) / <alpha-value>)',
                    500: 'rgb(var(--c-acid-500) / <alpha-value>)',
                    600: 'rgb(var(--c-acid-600) / <alpha-value>)',
                    700: 'rgb(var(--c-acid-700) / <alpha-value>)',
                },
                magenta: {
                    300: 'rgb(var(--c-magenta-300) / <alpha-value>)',
                    400: 'rgb(var(--c-magenta-400) / <alpha-value>)',
                    500: 'rgb(var(--c-magenta-500) / <alpha-value>)',
                    600: 'rgb(var(--c-magenta-600) / <alpha-value>)',
                },
                signal: {
                    300: 'rgb(var(--c-signal-300) / <alpha-value>)',
                    400: 'rgb(var(--c-signal-400) / <alpha-value>)',
                    500: 'rgb(var(--c-signal-500) / <alpha-value>)',
                },
                amber: {
                    300: 'rgb(var(--c-amber-300) / <alpha-value>)',
                    400: 'rgb(var(--c-amber-400) / <alpha-value>)',
                    500: 'rgb(var(--c-amber-500) / <alpha-value>)',
                },
                bone: 'rgb(var(--c-bone) / <alpha-value>)',

                /* garis & overlay: putih di gelap, hitam di terang */
                line: 'rgb(var(--c-line) / <alpha-value>)',

                /* tulisan di atas isian neon — gelap di dua mode */
                onacid: 'rgb(var(--c-on-acid) / <alpha-value>)',

                // alias lama supaya komponen yang belum dimigrasi tetap hidup
                genesis: {
                    gold: '#dcff4a',
                    cyan: '#2fd8f5',
                    violet: '#ff1470',
                    ember: '#ff1470',
                    abyss: '#08080a',
                },
            },

            boxShadow: {
                hard: '4px 4px 0 0 rgb(var(--c-line) / 0.9)',
                'hard-acid': '4px 4px 0 0 #c6f000',
                'hard-magenta': '4px 4px 0 0 #ff1470',
                'inner-line': 'inset 0 0 0 1px rgb(var(--c-line) / 0.07)',
            },

            keyframes: {
                shake: {
                    '0%, 100%': { transform: 'translateX(0)' },
                    '20%': { transform: 'translateX(-8px)' },
                    '40%': { transform: 'translateX(8px)' },
                    '60%': { transform: 'translateX(-5px)' },
                    '80%': { transform: 'translateX(5px)' },
                },
                glow: {
                    '0%, 100%': { opacity: '0.35', transform: 'scale(1)' },
                    '50%': { opacity: '0.7', transform: 'scale(1.15)' },
                },
                'pulse-correct': {
                    '0%': { boxShadow: '0 0 0 0 rgba(198,240,0,.55)' },
                    '100%': { boxShadow: '0 0 0 22px rgba(198,240,0,0)' },
                },
                scan: {
                    '0%': { transform: 'translateY(-100%)' },
                    '100%': { transform: 'translateY(1200%)' },
                },
                marquee: {
                    '0%': { transform: 'translateX(0)' },
                    '100%': { transform: 'translateX(-50%)' },
                },
                flicker: {
                    '0%, 100%': { opacity: '1' },
                    '52%': { opacity: '1' },
                    '54%': { opacity: '.35' },
                    '56%': { opacity: '1' },
                    '92%': { opacity: '.6' },
                },
                slam: {
                    '0%': { transform: 'scale(1.25) rotate(-3deg)', opacity: '0' },
                    '60%': { transform: 'scale(.97) rotate(1deg)', opacity: '1' },
                    '100%': { transform: 'scale(1) rotate(0)', opacity: '1' },
                },
            },

            animation: {
                shake: 'shake 0.45s cubic-bezier(.36,.07,.19,.97)',
                glow: 'glow 2.2s ease-in-out infinite',
                'pulse-correct': 'pulse-correct 0.7s ease-out',
                scan: 'scan 2.4s linear infinite',
                marquee: 'marquee 32s linear infinite',
                flicker: 'flicker 5s steps(1) infinite',
                slam: 'slam 0.4s cubic-bezier(.2,.9,.2,1)',
            },
        },
    },

    plugins: [forms],
};