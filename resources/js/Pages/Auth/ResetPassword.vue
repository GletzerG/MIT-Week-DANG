<script setup lang="ts">
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps<{
    email: string;
    token: string;
}>();

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('password.store'), {
        onFinish: () => {
            form.reset('password', 'password_confirmation');
        },
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Sandi Baru" />

        <div class="mb-6 border-b border-line/10 pb-4">
            <span class="label-tag bg-acid-500/15 text-acid-400">Pemulihan</span>
            <h1 class="mt-2 font-display text-2xl uppercase tracking-tight text-bone">
                Setel Sandi Baru
            </h1>
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
                />

                <InputError class="mt-1.5" :message="form.errors.email" />
            </div>

            <div>
                <InputLabel for="password" value="Kata Sandi Baru" />

                <TextInput
                    id="password"
                    type="password"
                    class="mt-1"
                    v-model="form.password"
                    required
                    autocomplete="new-password"
                />

                <InputError class="mt-1.5" :message="form.errors.password" />
            </div>

            <div>
                <InputLabel for="password_confirmation" value="Ulangi Kata Sandi Baru" />

                <TextInput
                    id="password_confirmation"
                    type="password"
                    class="mt-1"
                    v-model="form.password_confirmation"
                    required
                    autocomplete="new-password"
                />

                <InputError class="mt-1.5" :message="form.errors.password_confirmation" />
            </div>

            <div class="pt-2">
                <PrimaryButton
                    :class="{ 'opacity-50': form.processing }"
                    :disabled="form.processing"
                >
                    Simpan Sandi Baru
                </PrimaryButton>
            </div>
        </form>
    </GuestLayout>
</template>
