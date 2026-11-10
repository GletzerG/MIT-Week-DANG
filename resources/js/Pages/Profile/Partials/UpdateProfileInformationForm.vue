<script setup lang="ts">
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';

defineProps<{
    mustVerifyEmail?: Boolean;
    status?: String;
}>();

const user = usePage().props.auth.user;

const form = useForm({
    name: user.name,
    email: user.email,
});
</script>

<template>
    <section>
        <header class="mb-5 border-b border-line/10 pb-3">
            <div class="flex items-center justify-between">
                <h2 class="font-display text-lg uppercase tracking-tight text-bone">Identitas</h2>
                <span class="font-mono text-[9px] uppercase tracking-[0.18em] text-bone/30">Data 01</span>
            </div>
            <p class="mt-0.5 font-cond text-sm text-bone/50">
                Nama ini yang tampil di leaderboard dan riwayat trial.
            </p>
        </header>

        <form @submit.prevent="form.patch(route('profile.update'))" class="space-y-4">
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
                />

                <InputError class="mt-1.5" :message="form.errors.email" />
            </div>

            <div
                v-if="mustVerifyEmail && user.email_verified_at === null"
                class="border-l-2 border-amber-400 bg-amber-400/10 p-3"
            >
                <p class="font-cond text-xs text-amber-400">
                    Email belum diverifikasi.
                    <Link
                        :href="route('verification.send')"
                        method="post"
                        as="button"
                        class="font-bold uppercase tracking-wider underline decoration-amber-400/40 underline-offset-2 hover:text-amber-300"
                    >
                        Kirim ulang tautan verifikasi
                    </Link>
                </p>

                <p
                    v-show="status === 'verification-link-sent'"
                    class="mt-2 font-cond text-xs font-bold text-acid-400"
                >
                    Tautan verifikasi baru sudah dikirim.
                </p>
            </div>

            <div class="flex items-center gap-3 pt-1">
                <PrimaryButton class="!w-auto" :disabled="form.processing">
                    Simpan Identitas
                </PrimaryButton>

                <Transition
                    enter-active-class="transition ease-in-out"
                    enter-from-class="opacity-0"
                    leave-active-class="transition ease-in-out"
                    leave-to-class="opacity-0"
                >
                    <span
                        v-if="form.recentlySuccessful"
                        class="font-mono text-[10px] uppercase tracking-[0.18em] text-acid-400"
                    >
                        Tersimpan ✓
                    </span>
                </Transition>
            </div>
        </form>
    </section>
</template>
