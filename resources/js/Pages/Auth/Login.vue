<script setup lang="ts">
import Checkbox from '@/Components/Checkbox.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps<{
    canResetPassword?: boolean;
    status?: string;
}>();

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => {
            form.reset('password');
        },
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Masuk" />

        <!-- header kartu ZZZ -->
        <div class="mb-6 border-b border-line/10 pb-4">
            <div class="flex items-center justify-between">
                <span class="label-tag bg-acid-500/15 text-acid-400">Autentikasi</span>
                <span class="font-mono text-[10px] uppercase tracking-widest text-bone/35">Pintu 01</span>
            </div>
            <h1 class="mt-2 font-display text-2xl uppercase tracking-tight text-bone">
                Masuk ke Genesis
            </h1>
            <p class="mt-0.5 font-cond text-sm text-bone/55">
                Akses stasiun analisis dan arena trial harian.
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
                    placeholder="guardian@genesis.test"
                />

                <InputError class="mt-1.5" :message="form.errors.email" />
            </div>

            <div>
                <div class="flex items-center justify-between">
                    <InputLabel for="password" value="Kata Sandi" />
                    <Link
                        v-if="canResetPassword"
                        :href="route('password.request')"
                        class="font-cond text-xs text-bone/45 transition hover:text-acid-400"
                    >
                        Lupa sandi?
                    </Link>
                </div>

                <TextInput
                    id="password"
                    type="password"
                    class="mt-1"
                    v-model="form.password"
                    required
                    autocomplete="current-password"
                    placeholder="••••••••"
                />

                <InputError class="mt-1.5" :message="form.errors.password" />
            </div>

            <div class="flex items-center justify-between pt-1">
                <label class="flex cursor-pointer select-none items-center gap-2">
                    <Checkbox name="remember" v-model:checked="form.remember" />
                    <span class="font-cond text-xs text-bone/60">Ingat sesi ini</span>
                </label>
            </div>

            <div class="pt-2">
                <PrimaryButton
                    :class="{ 'opacity-50': form.processing }"
                    :disabled="form.processing"
                >
                    <span v-if="form.processing">Memverifikasi...</span>
                    <span v-else>Masuk ke Akun</span>
                </PrimaryButton>
            </div>

            <div class="border-t border-line/10 pt-4 text-center">
                <span class="font-cond text-xs text-bone/45">Belum punya akun?</span>
                <Link
                    :href="route('register')"
                    class="ms-1.5 font-cond text-xs font-bold uppercase tracking-wider text-acid-400 hover:text-acid-300"
                >
                    Daftar Guardian Baru →
                </Link>
            </div>
        </form>
    </GuestLayout>
</template>
