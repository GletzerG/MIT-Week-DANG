<script setup lang="ts">
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';

const form = useForm({
    password: '',
});

const submit = () => {
    form.post(route('password.confirm'), {
        onFinish: () => {
            form.reset();
        },
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Konfirmasi Sandi" />

        <div class="mb-6 border-b border-line/10 pb-4">
            <span class="label-tag bg-acid-500/15 text-acid-400">Keamanan</span>
            <h1 class="mt-2 font-display text-2xl uppercase tracking-tight text-bone">
                Konfirmasi Sandi
            </h1>
            <p class="mt-1 font-cond text-sm text-bone/55">
                Ini area sensitif Genesis. Masukkan sandimu sekali lagi sebelum lanjut.
            </p>
        </div>

        <form @submit.prevent="submit" class="space-y-4">
            <div>
                <InputLabel for="password" value="Kata Sandi" />
                <TextInput
                    id="password"
                    type="password"
                    class="mt-1"
                    v-model="form.password"
                    required
                    autocomplete="current-password"
                    autofocus
                />
                <InputError class="mt-1.5" :message="form.errors.password" />
            </div>

            <div class="pt-2">
                <PrimaryButton
                    :class="{ 'opacity-50': form.processing }"
                    :disabled="form.processing"
                >
                    Konfirmasi
                </PrimaryButton>
            </div>
        </form>
    </GuestLayout>
</template>
