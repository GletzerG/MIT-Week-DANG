<script setup lang="ts">
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps<{
    status?: string;
}>();

const form = useForm({
    email: '',
});

const submit = () => {
    form.post(route('password.email'));
};
</script>

<template>
    <GuestLayout>
        <Head title="Lupa Kata Sandi" />

        <div class="mb-6 border-b border-line/10 pb-4">
            <span class="label-tag bg-acid-500/15 text-acid-400">Pemulihan</span>
            <h1 class="mt-2 font-display text-2xl uppercase tracking-tight text-bone">
                Lupa Kata Sandi
            </h1>
            <p class="mt-1 font-cond text-sm text-bone/55">
                Masukkan email akunmu. Kami kirim tautan untuk menyetel sandi baru.
            </p>
        </div>

        <div v-if="status" class="mb-4 border-l-2 border-acid-500 bg-acid-500/10 p-3 font-cond text-xs text-acid-400">
            {{ status }}
        </div>

        <form @submit.prevent="submit" class="space-y-4">
            <div>
                <InputLabel for="email" value="Alamat Email" />

                <TextInput
                    id="email"
                    type="email"
                    class="mt-1"
                    v-model="form.email"
                    required
                    autofocus
                    autocomplete="username"
                    placeholder="nama@email.com"
                />

                <InputError class="mt-1.5" :message="form.errors.email" />
            </div>

            <div class="pt-2">
                <PrimaryButton
                    :class="{ 'opacity-50': form.processing }"
                    :disabled="form.processing"
                >
                    Kirim Tautan Reset
                </PrimaryButton>
            </div>

            <div class="border-t border-line/10 pt-4 text-center">
                <Link
                    :href="route('login')"
                    class="font-cond text-xs font-bold uppercase tracking-wider text-acid-400 hover:text-acid-300"
                >
                    ← Kembali ke halaman masuk
                </Link>
            </div>
        </form>
    </GuestLayout>
</template>
