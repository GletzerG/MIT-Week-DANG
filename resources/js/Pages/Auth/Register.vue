<script setup lang="ts">
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => {
            form.reset('password', 'password_confirmation');
        },
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Daftar" />

        <div class="mb-6 border-b border-line/10 pb-4">
            <div class="flex items-center justify-between">
                <span class="label-tag bg-acid-500/15 text-acid-400">Pendaftaran</span>
                <span class="font-mono text-[10px] uppercase tracking-widest text-bone/35">Pintu 02</span>
            </div>
            <h1 class="mt-2 font-display text-2xl uppercase tracking-tight text-bone">
                Daftar Guardian
            </h1>
            <p class="mt-0.5 font-cond text-sm text-bone/55">
                Satu akun untuk feed terverifikasi, detektor klaim, dan trial harian.
            </p>
        </div>

        <form @submit.prevent="submit" class="space-y-4">
            <div>
                <InputLabel for="name" value="Nama Panggilan" />

                <TextInput
                    id="name"
                    type="text"
                    class="mt-1"
                    v-model="form.name"
                    required
                    autofocus
                    autocomplete="name"
                    placeholder="Nama yang tampil di leaderboard"
                />

                <InputError class="mt-1.5" :message="form.errors.name" />
            </div>

            <div>
                <InputLabel for="email" value="Alamat Email" />

                <TextInput
                    id="email"
                    type="email"
                    class="mt-1"
                    v-model="form.email"
                    required
                    autocomplete="username"
                    placeholder="nama@email.com"
                />

                <InputError class="mt-1.5" :message="form.errors.email" />
            </div>

            <div>
                <InputLabel for="password" value="Kata Sandi" />

                <TextInput
                    id="password"
                    type="password"
                    class="mt-1"
                    v-model="form.password"
                    required
                    autocomplete="new-password"
                    placeholder="Minimal 8 karakter"
                />

                <InputError class="mt-1.5" :message="form.errors.password" />
            </div>

            <div>
                <InputLabel for="password_confirmation" value="Ulangi Kata Sandi" />

                <TextInput
                    id="password_confirmation"
                    type="password"
                    class="mt-1"
                    v-model="form.password_confirmation"
                    required
                    autocomplete="new-password"
                    placeholder="Ketik ulang sandi"
                />

                <InputError class="mt-1.5" :message="form.errors.password_confirmation" />
            </div>

            <div class="pt-2">
                <PrimaryButton
                    :class="{ 'opacity-50': form.processing }"
                    :disabled="form.processing"
                >
                    <span v-if="form.processing">Mendaftarkan...</span>
                    <span v-else>Buat Akun Guardian</span>
                </PrimaryButton>
            </div>

            <div class="border-t border-line/10 pt-4 text-center">
                <span class="font-cond text-xs text-bone/45">Sudah terdaftar?</span>
                <Link
                    :href="route('login')"
                    class="ms-1.5 font-cond text-xs font-bold uppercase tracking-wider text-acid-400 hover:text-acid-300"
                >
                    Masuk di sini →
                </Link>
            </div>
        </form>
    </GuestLayout>
</template>
