import { computed, ref, watch } from 'vue';

/**
 * Dark / light mode — satu sumber kebenaran untuk seluruh app.
 *
 * KENAPA singleton di level modul: header punya tombolnya, halaman guest
 * punya tombolnya. Kalau tiap komponen menyimpan state sendiri, dua tombol
 * di satu layar bisa menunjuk mode berbeda. Modul dievaluasi sekali, jadi
 * semua pemanggil `useTheme()` melihat ref yang sama.
 *
 * Cara kerja: atribut `data-theme` di <html>. Semua warna membaca CSS
 * variable (resources/css/app.css), jadi tidak ada satu pun kelas Tailwind
 * yang perlu `dark:` / `light:` kembar.
 */
export type ThemeMode = 'dark' | 'light';

const STORAGE_KEY = 'guardian-theme';

function readStored(): ThemeMode | null {
    try {
        const raw = localStorage.getItem(STORAGE_KEY);

        return raw === 'light' || raw === 'dark' ? raw : null;
    } catch {
        return null;
    }
}

function systemMode(): ThemeMode {
    return window.matchMedia?.('(prefers-color-scheme: light)').matches ? 'light' : 'dark';
}

const mode = ref<ThemeMode>(readStored() ?? (typeof window === 'undefined' ? 'dark' : systemMode()));

const isLight = computed(() => mode.value === 'light');

function apply(next: ThemeMode) {
    document.documentElement.dataset.theme = next;
    document.documentElement.style.colorScheme = next;
    document.documentElement.classList.toggle('theme-light', next === 'light');
}

watch(mode, (next) => {
    apply(next);

    try {
        localStorage.setItem(STORAGE_KEY, next);
    } catch {
        /* private mode: tombol tetap jalan, cuma lupa setelah reload */
    }
}, { immediate: true });

// Ikut sistem selama user belum pernah memilih sendiri.
window.matchMedia?.('(prefers-color-scheme: light)').addEventListener?.('change', () => {
    if (readStored() === null) {
        mode.value = systemMode();
    }
});

export function useTheme() {
    return {
        mode,
        isLight,
        toggle: () => {
            mode.value = mode.value === 'light' ? 'dark' : 'light';
        },
        set: (next: ThemeMode) => {
            mode.value = next;
        },
    };
}
