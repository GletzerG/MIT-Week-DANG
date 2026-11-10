<script setup lang="ts">
import { computed } from 'vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps<{
    status?: string;
}>();

const form = useForm({});

const submit = () => {
    form.post(route('verification.send'));
};

const verificationLinkSent = computed(
    () => props.status === 'verification-link-sent',
);
</script>

<template>
    <GuestLayout>
        <Head title="Verifikasi Email" />

        <div class="mb-6 border-b border-line/10 pb-4">
            <span class="label-tag bg-acid-500/15 text-acid-400">Verifikasi</span>
            <h1 class="mt-2 font-display text-2xl uppercase tracking-tight text-bone">
                Cek Kotak Masuk
            </h1>
            <p class="mt-1 font-cond text-sm text-bone/55">
                Kami sudah mengirim tautan verifikasi ke emailmu. Klik tautan itu
                sebelum mulai — atau minta kirim ulang.
            </p>
        </div>

        <div
            v-if="verificationLinkSent"
            class="mb-4 border-l-2 border-acid-500 bg-acid-500/10 p-3 font-cond text-xs text-acid-400"
        >
            Tautan verifikasi baru sudah dikirim ke email yang kamu daftarkan.
        </div>

        <form @submit.prevent="submit" class="space-y-4">
            <PrimaryButton
                :class="{ 'opacity-50': form.processing }"
                :disabled="form.processing"
            >
                Kirim Ulang Tautan
            </PrimaryButton>

            <div class="border-t border-line/10 pt-4 text-center">
                <Link
                    :href="route('logout')"
                    method="post"
                    as="button"
                    class="font-cond text-xs font-bold uppercase tracking-wider text-bone/45 hover:text-magenta-400"
                >
                    Keluar dari akun ini
                </Link>
            </div>
        </form>
    </GuestLayout>
</template>
